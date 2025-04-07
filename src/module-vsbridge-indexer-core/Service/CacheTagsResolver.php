<?php

declare(strict_types=1);

namespace Divante\VsbridgeIndexerCore\Service;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;

use Divante\VsbridgeIndexerCore\Api\Cache\DataTypeInterface;

class CacheTagsResolver
{
    /**
     * Mapping ES data type to cache tag used by VSF
     * @var string[]
     */
    private $cacheTagsMapping = [
        DataTypeInterface::TYPE_PRODUCT => 'P',
        DataTypeInterface::TYPE_CATEGORY => 'C',
        DataTypeInterface::TYPE_URL_REWRITE => 'url_rewrite',
        DataTypeInterface::TYPE_RUSH_ADDON => 'rush_addons',
        DataTypeInterface::TYPE_STATISTIC_VALUE => 'statistic_value',
        DataTypeInterface::TYPE_SETTING => 'settings',
        DataTypeInterface::TYPE_GIFT_CARD_TEMPLATE => 'gift_card_templates',
        DataTypeInterface::TYPE_STORE_RATING => 'store_rating',
    ];

    public function __construct(
        private SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
    ) {
    }

    public function getTagsList(string $dataType, array $entityIds): string
    {
        $dataTypeTag = $this->cacheTagsMapping[$dataType] ?? null;
        if ($dataTypeTag === null) {
            throw new \RuntimeException(sprintf('Invalid data type: %s', $dataType));
        }

        if (empty($entityIds) 
            || $dataType === DataTypeInterface::TYPE_GIFT_CARD_TEMPLATE
            || $dataType === DataTypeInterface::TYPE_STORE_RATING
        ) {
            return $dataTypeTag;
        }

        $tags = [];

        switch ($dataType) {
            case DataTypeInterface::TYPE_PRODUCT:
                $searchCriteria = $this->searchCriteriaBuilderFactory->create()
                    ->addFilter('entity_id', $entityIds, 'in')
                    ->create();

                $products = $this->productRepository->getList($searchCriteria)->getItems();

                foreach ($products as $product) {
                    $tags[] = $dataTypeTag . $product->getId();
                    $tags[] = $dataTypeTag . $product->getId();
                }

                break;
            case DataTypeInterface::TYPE_CATEGORY:
                $searchCriteria = $this->searchCriteriaBuilderFactory->create()
                    ->addFilter('entity_id', $entityIds, 'in')
                    ->create();

                $categories = $this->categoryRepository->getList($searchCriteria)->getItems();

                foreach ($categories as $category) {
                    $tags[] = $dataTypeTag . $category->getId();
                    $tags[] = $dataTypeTag . preg_replace('/^c\//', '', $category->getUrlKey());
                }

                break;
            case DataTypeInterface::TYPE_URL_REWRITE:
                $urlRewriteCollection = $this->urlRewriteCollectionFactory->create();

                $rewrites = $urlRewriteCollection->getB
                    ->addFieldToFilter('url_rewrite_id', ['in' => $entityIds])
                    ->getItems();
    
                foreach ($rewrites as $rewrite) {
                    $tags[] = sprintf('%s_%s', $dataTypeTag, $rewrite->getRequestPath());
                }

                break;
            case DataTypeInterface::TYPE_RUSH_ADDON:
            case DataTypeInterface::TYPE_STATISTIC_VALUE:
            case DataTypeInterface::TYPE_SETTING:
                foreach ($entityIds as $entityId) {
                    $tags[] = sprintf('%s_%s', $dataTypeTag, $entityId);
                }

                break;
        }

        return implode(', ', $tags);
    }

    public function getCacheTags(): array
    {
        return $this->cacheTagsMapping;
    }
}