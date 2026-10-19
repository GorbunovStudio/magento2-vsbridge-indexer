<?php

namespace Divante\VsbridgeIndexerCore\Indexer\Action;

use Divante\VsbridgeIndexerCore\Indexer\RebuildActionPool;
use Divante\VsbridgeIndexerCore\Indexer\StoreManager;
use Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandlerFactory;
use Divante\VsbridgeIndexerCore\Api\EventInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * Full reindex action
 */
class Full extends AbstractAction
{
    /**
     * Full constructor.
     * @param RebuildActionPool $actionPool
     * @param GenericIndexerHandlerFactory $indexerHandlerFactory
     * @param StoreManager $storeManager
     * @param string $typeName
     */
    public function __construct(
        RebuildActionPool $actionPool,
        GenericIndexerHandlerFactory $indexerHandlerFactory,
        StoreManager $storeManager,
        private EventManager $eventManager,
        string $typeName
    ) {
        parent::__construct($actionPool, $indexerHandlerFactory, $storeManager, $typeName);
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
        $stores = $this->getStores();

        foreach ($stores as $store) {
            $storeId = (int)$store->getId();

            if (!$this->getIndexerHandler()->createIndex($store)) {
                continue;
            }
            
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
}
