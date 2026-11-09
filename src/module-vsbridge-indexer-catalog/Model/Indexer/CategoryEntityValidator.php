<?php

declare(strict_types=1);

namespace Divante\VsbridgeIndexerCatalog\Model\Indexer;

use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Category as ResourceModel;
use Divante\VsbridgeIndexerCore\Api\SingleEntityValidatorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;

/**
 * Rejects a vsbridge:index category request for a category that does not exist
 */
class CategoryEntityValidator implements SingleEntityValidatorInterface
{
    /**
     * @var ResourceModel
     */
    private $resourceModel;

    /**
     * @param ResourceModel $resourceModel
     */
    public function __construct(ResourceModel $resourceModel)
    {
        $this->resourceModel = $resourceModel;
    }

    /**
     * @inheritdoc
     */
    public function validate(string $entityId): void
    {
        if (empty($this->resourceModel->getParentIds([$entityId]))) {
            throw new NoSuchEntityException(new Phrase(
                'No categories found for the requested IDs: %1.',
                [$entityId]
            ));
        }
    }
}
