<?php

namespace Divante\VsbridgeIndexerCatalog\Test\Model;

use Divante\VsbridgeIndexerCatalog\Api\CatalogConfigurationInterface;
use Divante\VsbridgeIndexerCatalog\Model\Product\LoadTierPrices;
use Divante\VsbridgeIndexerCatalog\Model\ProductMetaData;
use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Product\AttributeDataProvider;
use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Product\TierPrices;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class LoadTierPricesTest extends TestCase
{

    private $attributeDataProviderMock;

    /**
     * @var TierPrices
     */
    private $tierPriceResourceMock;

    /**
     * @var StoreManagerInterface
     */
    private $storeManagerMock;

    /**
     * @var ProductMetaData
     */
    private $productMetaDataMock;

    /**
     * @var CatalogConfigurationInterface
     */
    private $configSettingsMock;

    private $loadTierPrices;

    private $storeMock;

    private $attributeMock;


    protected function setUp(): void
    {
        $this->attributeDataProviderMock = $this->createStub(AttributeDataProvider::class);
        $this->tierPriceResourceMock = $this->createStub(TierPrices::class);
        $this->storeManagerMock = $this->createStub(StoreManagerInterface::class);
        $this->productMetaDataMock = $this->createStub(ProductMetaData::class);
        $this->configSettingsMock = $this->createStub(CatalogConfigurationInterface::class);
        $this->storeMock = $this->createStub(Store::class);
        $this->attributeMock = $this->createStub(Attribute::class);

        $this->loadTierPrices = new LoadTierPrices(
            $this->configSettingsMock,
            $this->tierPriceResourceMock,
            $this->storeManagerMock,
            $this->productMetaDataMock,
            $this->attributeDataProviderMock
        );
    }

    public function testGetWebsiteIdWhenScopeIsNotGlobal(): void
    {
        $storeId = 1;
        $reflector = new ReflectionClass( LoadTierPrices::class );
        $method = $reflector->getMethod( 'getWebsiteId' );

        $this->attributeMock->method('isScopeGlobal')->willReturn(false);

        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->storeMock->method('getWebsiteId')->willReturn(1);

        $this->attributeDataProviderMock->method('getAttributeByCode')
            ->willReturn($this->attributeMock);

        $result = $method->invokeArgs( $this->loadTierPrices, array( $storeId ) );
        $this->assertEquals( 1, $result );
    }

    public function testGetWebsiteIdWhenScopeIsGlobal(): void
    {
        $storeId = 1;
        $reflector = new ReflectionClass( LoadTierPrices::class );
        $method = $reflector->getMethod( 'getWebsiteId' );

        $this->attributeDataProviderMock->method('getAttributeByCode')
            ->willReturn($this->attributeMock);

        $this->attributeMock->method('isScopeGlobal')->willReturn(true);
        $result = $method->invokeArgs( $this->loadTierPrices, array( $storeId ) );
        $this->assertEquals( 0, $result );
    }
}
