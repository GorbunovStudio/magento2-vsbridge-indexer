<?php

namespace Divante\VsbridgeIndexerCatalog\Test\Model\Attributes;

use Divante\VsbridgeIndexerCatalog\Model\Attributes\ProductAttributes;
use Divante\VsbridgeIndexerCatalog\Api\CatalogConfigurationInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * Class ProductAttributesTest
 */
class ProductAttributesTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var
     */
    private $objectManager;

    /**
     * @var CatalogConfigurationInterface
     */
    private $catalogConfigMock;

    /**
     * @var ProductAttributes
     */
    private $productAttributes;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        $this->catalogConfigMock = $this->createMock(CatalogConfigurationInterface::class);
        $this->productAttributes = $this->objectManager->getObject(
            ProductAttributes::class,
            ['catalogConfiguration' => $this->catalogConfigMock]
        );
    }

    /**
     * @param int $storeId
     * @param array $selectedAttributes
     *
     * @dataProvider provideAllowedAttributes
     */
    public function testGetAttributes(int $storeId, array $selectedAttributes): void
    {
        $attributes = ProductAttributes::REQUIRED_ATTRIBUTES;
        $this->catalogConfigMock->expects($this->once())
            ->method('getAllowedAttributesToIndex')
            ->with($storeId)
            ->willReturn($selectedAttributes);

        $productAttributes = $this->productAttributes->getAttributes($storeId);

        foreach ($attributes as $attributeCode) {
            $this->assertContains($attributeCode, $productAttributes);
        }
    }

    /**
     *
     */
    public function testGetAllAttributes(): void
    {
        $storeId = 2;

        $this->catalogConfigMock->expects($this->once())
            ->method('getAllowedAttributesToIndex')
            ->with($storeId)
            ->willReturn([]);

        $productAttributes = $this->productAttributes->getAttributes($storeId);
        $this->assertEmpty($productAttributes);
    }

    /**
     * @return array
     */
    public static function provideAllowedAttributes(): array
    {
        return [
            [
                'storeId' => 1,
                'selectedAttributes' => [
                    'sku',
                    'url_path',
                    'url_key',
                    'name',
                    'price',
                    'visibility',
                    'status',
                    'price_type',
                ]
            ]
        ];
    }
}
