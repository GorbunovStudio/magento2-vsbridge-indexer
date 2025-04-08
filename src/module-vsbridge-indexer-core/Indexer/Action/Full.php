<?php

namespace Divante\VsbridgeIndexerCore\Indexer\Action;

use Divante\VsbridgeIndexerCore\Indexer\RebuildActionPool;
use Divante\VsbridgeIndexerCore\Indexer\StoreManager;
use Divante\VsbridgeIndexerCore\Model\ElasticsearchResolverInterface;
use Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandlerFactory;
use Divante\VsbridgeIndexerCore\Api\EventInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Full reindex action
 */
class Full extends AbstractAction
{
    /**
     * @var ElasticsearchResolverInterface
     */
    private $esVersionResolver;

    /**
     * Full constructor.
     * @param ElasticsearchResolverInterface $esVersionResolver
     * @param RebuildActionPool $actionPool
     * @param GenericIndexerHandlerFactory $indexerHandlerFactory
     * @param StoreManager $storeManager
     * @param string $typeName
     */
    public function __construct(
        ElasticsearchResolverInterface $esVersionResolver,
        RebuildActionPool $actionPool,
        GenericIndexerHandlerFactory $indexerHandlerFactory,
        StoreManager $storeManager,
        private EventManager $eventManager,
        string $typeName
    ) {
        parent::__construct($actionPool, $indexerHandlerFactory, $storeManager, $typeName);

        $this->esVersionResolver = $esVersionResolver;
    }

    /**
     * Execute full reindex
     *
     * @param array $ids
     *
     * @return void
     */
    public function execute(array $ids)
    {
        $esVersion = $this->esVersionResolver->getVersion();
        $stores = $this->getStores();

        if ($esVersion === ElasticsearchResolverInterface::DEFAULT_ES_VERSION) {
            foreach ($stores as $store) {
                $this->saveIndex($store);
                $this->getIndexerHandler()->cleanUpByTransactionKey($store);
            }
        } else {
            foreach ($stores as $store) {
                $this->getIndexerHandler()->createIndex($store);
                $this->saveIndex($store);
            }
        }
    }

    private function saveIndex(StoreInterface $store)
    {
        $storeId = (int)$store->getId();

        $this->getIndexerHandler()->saveIndex(
            $this->rebuild($storeId, []), 
            $store
        );

        $this->eventManager->dispatch(
            EventInterface::VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER,
            [
                'store_id' => $storeId,
                'data_type' => $this->getTypeName(),
                'entity_ids' => [],
            ]
        );
    }
}
