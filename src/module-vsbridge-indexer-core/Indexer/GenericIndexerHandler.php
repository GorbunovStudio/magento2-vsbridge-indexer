<?php

namespace Divante\VsbridgeIndexerCore\Indexer;

use Divante\VsbridgeIndexerCore\Api\BulkLoggerInterface;
use Divante\VsbridgeIndexerCore\Api\DataProviderInterface;
use Divante\VsbridgeIndexerCore\Api\IndexInterface;
use Divante\VsbridgeIndexerCore\Api\Index\TransactionKeyInterface;
use Divante\VsbridgeIndexerCore\Api\IndexOperationInterface;
use Divante\VsbridgeIndexerCore\Exception\ConnectionDisabledException;
use Divante\VsbridgeIndexerCore\Exception\ConnectionUnhealthyException;
use Divante\VsbridgeIndexerCore\Logger\IndexerLogger;
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\Store\Api\Data\StoreInterface;
use Traversable;
use Divante\VsbridgeIndexerCore\Api\Index\DataProviderResolverInterface;
use Divante\VsbridgeIndexerCore\Exception\IndexNotExistException;

/**
 * Class IndexerHandler
 *
 * TODO refactor - coupling between objects
 */
class GenericIndexerHandler
{
    /**
     * @var Batch
     */
    private $batch;

    /**
     * @var IndexOperationInterface
     */
    private $indexOperations;

    /**
     * @var string
     */
    private $entityName;

    /**
     * @var IndexerLogger
     */
    private $indexerLogger;

    /**
     * @var int|string
     */
    private $transactionKey;

    /**
     * @var BulkLoggerInterface
     */
    private $bulkLogger;

    /**
     * @var DataProviderResolverInterface
     */
    private $dataProviderResolver;

    /**
     * GenericIndexerHandler constructor.
     * @param DataProviderResolverInterface $dataProviderResolver
     * @param BulkLoggerInterface $bulkLogger
     * @param IndexerLogger $indexerLogger
     * @param IndexOperationInterface $indexOperations
     * @param Batch $batch
     * @param TransactionKeyInterface $transactionKey
     * @param string $entityName
     */
    public function __construct(
        DataProviderResolverInterface $dataProviderResolver,
        BulkLoggerInterface $bulkLogger,
        IndexerLogger $indexerLogger,
        IndexOperationInterface $indexOperations,
        Batch $batch,
        TransactionKeyInterface $transactionKey,
        string $entityName
    ) {
        $this->dataProviderResolver = $dataProviderResolver;
        $this->bulkLogger = $bulkLogger;
        $this->batch = $batch;
        $this->indexOperations = $indexOperations;
        $this->entityName = $entityName;
        $this->indexerLogger = $indexerLogger;
        $this->transactionKey = $transactionKey;
    }

    /**
     * Save documents in OpenSearch
     *
     * @param Traversable $documents
     * @param StoreInterface $store
     *
     * @return void
     * @throws ConnectionUnhealthyException
     */
    public function saveIndex(Traversable $documents, StoreInterface $store)
    {
        try {
            $index = $this->getIndex($store);
            $storeId = (int)$store->getId();
            $batchSize = $this->indexOperations->getBatchIndexingSize();

            foreach ($this->batch->getItems($documents, $batchSize) as $docs) {
                foreach ($this->getDataProviders() as $dataProvider) {
                    if (!empty($docs)) {
                        $docs = $dataProvider->addData($docs, $storeId);
                    }
                }

                if (!empty($docs)) {
                    $bulkRequest = $this->indexOperations->createBulk()->addDocuments(
                        $index->getName(),
                        $docs
                    );

                    $failure = null;
                    try {
                        $this->indexOperations->optimizeEsIndexing($storeId, $index->getName());
                        $response = $this->indexOperations->executeBulk($storeId, $bulkRequest);
                        $this->bulkLogger->log($response);
                        if ($response->hasErrors()) {
                            throw new \RuntimeException('OpenSearch bulk indexing failed; see the bulk error log.');
                        }
                    } catch (\Throwable $exception) {
                        $failure = $exception;
                        throw $exception;
                    } finally {
                        try {
                            $this->indexOperations->cleanAfterOptimizeEsIndexing($storeId, $index->getName());
                        } catch (\Throwable $cleanupException) {
                            if ($failure === null) {
                                throw $cleanupException;
                            }
                            $this->indexerLogger->error($cleanupException->getMessage());
                        }
                    }
                }

                $docs = null;
            }

            $this->indexOperations->refreshIndex($storeId, $index);

            if ($index->isNew()) {
                $this->indexOperations->switchIndexer($store->getId(), $index->getName(), $index->getAlias());
            }
        } catch (ConnectionDisabledException $exception) {
            // Indexer disabled in configuration.
        } catch (ConnectionUnhealthyException $exception) {
            $this->indexerLogger->error($exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Remove stale documents in OpenSearch by transaction key
     *
     * @param StoreInterface $store
     * @param array $docIds
     *
     * @return void
     */
    public function cleanUpByTransactionKey(StoreInterface $store, ?array $docIds = null)
    {
        try {
            $indexAlias = $this->indexOperations->getIndexAlias($this->entityName, $store);

            if ($this->indexOperations->indexExists($store->getId(), $indexAlias)) {
                $index = $this->indexOperations->getIndexByName($this->entityName, $store);
                $transactionKeyQuery = ['must_not' => ['term' => ['tsk' => $this->transactionKey->load()]]];
                $query = ['query' => ['bool' => $transactionKeyQuery]];

                if ($docIds) {
                    $query['query']['bool']['must']['terms'] = ['_id' => array_values($docIds)];
                }

                $query = [
                    'index' => $index->getName(),
                    'body' => $query,
                ];

                $this->indexOperations->deleteByQuery($store->getId(), $query);
            }
        } catch (ConnectionDisabledException $exception) {
            // Indexer disabled in configuration.
        }
    }

    /**
     * @return DataProviderInterface[]
     */
    private function getDataProviders()
    {
        return $this->dataProviderResolver->getDataProviders($this->entityName);
    }

    /**
     * Get Index
     *
     * @param StoreInterface $store
     *
     * @return IndexInterface
     */
    private function getIndex(StoreInterface $store)
    {
        try {
            $index = $this->indexOperations->getIndexByName($this->entityName, $store);
        } catch (IndexNotExistException $e) {
            $index = $this->indexOperations->createIndex($this->entityName, $store);
        }

        return $index;
    }

    /**
     * @param StoreInterface $store
     */
    public function createIndex(StoreInterface $store)
    {
        try {
            return $this->indexOperations->createIndex($this->entityName, $store);
        } catch (ConnectionDisabledException $exception) {
            return null;
        }
    }
}
