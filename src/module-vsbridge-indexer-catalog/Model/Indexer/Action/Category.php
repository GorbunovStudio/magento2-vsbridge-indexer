<?php

namespace Divante\VsbridgeIndexerCatalog\Model\Indexer\Action;

use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Category as ResourceModel;
use Divante\VsbridgeIndexerCore\Indexer\RebuildActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;

/**
 * Class Category
 */
class Category implements RebuildActionInterface
{
    /**
     * @var ResourceModel
     */
    private $resourceModel;

    /**
     * Category constructor.
     *
     * @param ResourceModel $resourceModel
     */
    public function __construct(ResourceModel $resourceModel)
    {
        $this->resourceModel = $resourceModel;
    }

    /**
     * @param int $storeId
     * @param array $categoryIds
     *
     * @return \Generator
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function rebuild(int $storeId, array $categoryIds): \Traversable
    {
        $lastCategoryId = 0;

        if (!empty($categoryIds)) {
            $resolvedCategoryIds = $this->resourceModel->getParentIds($categoryIds);

            if (empty($resolvedCategoryIds)) {
                throw new NoSuchEntityException(new Phrase(
                    'No categories found for the requested IDs: %1.',
                    [implode(', ', $categoryIds)]
                ));
            }

            $categoryIds = $resolvedCategoryIds;
        }

        do {
            $categories = $this->resourceModel->getCategories($storeId, $categoryIds, $lastCategoryId);

            foreach ($categories as $category) {
                $lastCategoryId = $category['entity_id'];
                $category['id'] = (int)$category['entity_id'];

                yield $lastCategoryId => $category;
            }
        } while (!empty($categories));
    }
}
