<?php

namespace Divante\VsbridgeIndexerCore\Console\Command\Rebuild;

use Divante\VsbridgeIndexerCore\Indexer\StoreManager;
use Magento\Store\Api\Data\StoreInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class responsible for running vsbridge indexers - exporting data to ES
 */
class Rebuild
{
    /**
     * @var StoreManager
     */
    private $indexerStoreManager;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var IndicesProvider
     */
    private $indicesProvider;

    /**
     * Rebuild constructor.
     * @param StoreManager $indexerStoreManager
     * @param OutputInterface $output
     * @param IndicesProvider $indicesProvider
     */
    public function __construct(
        StoreManager $indexerStoreManager,
        OutputInterface $output,
        IndicesProvider $indicesProvider
    ) {
        $this->indexerStoreManager = $indexerStoreManager;
        $this->output = $output;
        $this->indicesProvider = $indicesProvider;
    }

    /**
     * @param string|null $storeId
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(?string $storeId = null)
    {
        if (!$this->validate()) {
            return;
        }

        if ($storeId && !$this->indexerStoreManager->isStoreAllowedToReindex($storeId)) {
            $this->output->writeln("Store " . $storeId . " is not allowed.");
            return;
        }

        $storeList = $this->indexerStoreManager->getStores($storeId);

        foreach ($storeList as $store) {
            $this->output->writeln(
                "Reindexing all VS indexes for store " . $store->getName() . "..."
            );
            $this->reindexStore($store);
            $this->output->writeln("Reindexing has completed!");
        }
    }

    /**
     * Validate indices
     *
     * @return bool
     */
    private function validate(): bool
    {
        $invalidIndices = $this->indicesProvider->getInvalidIndicesNames();

        if (!empty($invalidIndices)) {
            $message = 'Some indices has invalid status: '. implode(', ', $invalidIndices) . '. ';
            $message .= 'Please change indices status to VALID manually or use bin/magento vsbridge:reset command.';
            $this->output->writeln("<info>WARNING: Indexation can't be executed. $message</info>");

            return false;
        }

        return true;
    }

    /**
     * Reindex each vsbridge index for the specified store
     *
     * @param StoreInterface $store
     */
    private function reindexStore(StoreInterface $store)
    {
        $this->indexerStoreManager->override([$store]);
        foreach ($this->indicesProvider->getValidIndices() as $indexer) {
            $startTime = microtime(true);
            $indexer->reindexAll();
            $this->output->writeln(
                $indexer->getTitle() . ' index has been rebuilt successfully in '
                . gmdate('H:i:s', (int)(microtime(true) - $startTime))
            );
        }
    }
}
