<?php

declare(strict_types=1);

namespace Divante\VsbridgeIndexerCore\Indexer;

use Divante\VsbridgeIndexerCore\Indexer\Action\ActionFactory;

/**
 * Base Indexer class
 */
class Base implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    /** @var ActionFactory */
    private $actionFactory;

    /**
     * @var string
     */
    private $entityName;

    /**
     * Base constructor.
     *
     * @param ActionFactory $actionFactory
     * @param string $entityName
     */
    public function __construct(
        ActionFactory $actionFactory,
        string $entityName
    ) {
        $this->entityName = $entityName;
        $this->actionFactory = $actionFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute($ids)
    {
        return $this->actionFactory->create('rows', $this->entityName)->execute($ids);
    }

    /**
     * @inheritdoc
     */
    public function executeFull()
    {
        return $this->actionFactory->create('full', $this->entityName)->execute([]);
    }

    /**
     * @inheritdoc
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }
}
