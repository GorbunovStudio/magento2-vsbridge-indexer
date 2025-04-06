<?php

namespace Divante\VsbridgeIndexerCore\Indexer\Action;

use Divante\VsbridgeIndexerCore\Indexer\RebuildActionPool;
use Divante\VsbridgeIndexerCore\Indexer\StoreManager;
use Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandlerFactory;
use Divante\VsbridgeIndexerCore\Api\EventInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * Rows reindex action
 */
class Rows extends AbstractAction
{
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
     * Execute rows reindex
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

            $this->getIndexerHandler()->saveIndex(
                $this->rebuild($storeId, $ids), 
                $store
            );
    
            $this->eventManager->dispatch(
                EventInterface::VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER,
                [
                    'storeId' => $storeId,
                    'typeName' => $this->getTypeName(),
                    'ids' => $ids,
                ]
            );

            $this->getIndexerHandler()->cleanUpByTransactionKey($store, $ids);
        }
    }
}
