<?php

namespace Divante\VsbridgeIndexerCore\Indexer\Action;

use Divante\VsbridgeIndexerCore\Indexer\RebuildActionPool;
use Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandler;
use Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandlerFactory;
use Divante\VsbridgeIndexerCore\Indexer\StoreManager;

/**
 * Abstract class for indexer action
 */
abstract class AbstractAction
{
    /**
     * @var RebuildActionPool
     */
    private $action;

    /**
     * @var GenericIndexerHandlerFactory
     */
    private $indexerFactory;

    /**
     * @var StoreManager
     */
    private $storeManager;

    /**
     * @var string
     */
    private $entityName;

    /**
     * Base constructor.
     *
     * @param RebuildActionPool $actionPool
     * @param GenericIndexerHandlerFactory $indexerHandlerFactory
     * @param StoreManager $storeManager
     * @param string $entityName
     */
    public function __construct(
        RebuildActionPool $actionPool,
        GenericIndexerHandlerFactory $indexerHandlerFactory,
        StoreManager $storeManager,
        string $entityName
    ) {
        $this->storeManager = $storeManager;
        $this->indexerFactory = $indexerHandlerFactory;
        $this->action = $actionPool;
        $this->entityName = $entityName;
    }

    /**
     * Execute action for given ids
     *
     * @param array $ids
     *
     * @return void
     */
    abstract public function execute(array $ids);

    /**
     * @return \Magento\Store\Api\Data\StoreInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStores()
    {
        return $this->storeManager->getStores();
    }

    /**
     * @param int $storeId
     * @param array $ids
     *
     * @return \Traversable
     */
    public function rebuild(int $storeId, array $ids)
    {
        $action = $this->action->getAction($this->entityName);

        return $action->rebuild($storeId, $ids);
    }

    /**
     * @return GenericIndexerHandler
     */
    public function getIndexerHandler(): GenericIndexerHandler
    {
        return $this->indexerFactory->create(['entityName' => $this->entityName]);
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }
}
