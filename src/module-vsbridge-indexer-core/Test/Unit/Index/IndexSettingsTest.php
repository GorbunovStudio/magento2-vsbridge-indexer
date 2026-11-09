<?php

declare(strict_types=1);

namespace Divante\VsbridgeIndexerCore\Test\Unit\Index;

use Divante\VsbridgeIndexerCore\Config\IndicesSettings;
use Divante\VsbridgeIndexerCore\Index\IndexSettings;
use Divante\VsbridgeIndexerCore\Index\Indices\ConfigResolver;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Intl\DateTimeFactory;

/**
 * Class IndexSettingsTest responsible for testing \Divante\VsbridgeIndexerCore\Index\IndexSettings
 */
class IndexSettingsTest extends TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $indicesSettingsMock;

    /**
     * @var IndicesSettings|PHPUnit_Framework_MockObject_MockObject
     */
    private $configResolver;

    /**
     * @var Store|PHPUnit_Framework_MockObject_MockObject
     */
    private $storeMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $storeManagerMock;

    /**
     * @var IndexSettings
     */
    private $esIndexSettings;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);

        $this->storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->indicesSettingsMock = $this->createMock(IndicesSettings::class);
        $this->configResolver = $this->createMock(ConfigResolver::class);

        $this->esIndexSettings = new IndexSettings(
            $this->storeManagerMock,
            $this->configResolver,
            $this->indicesSettingsMock,
            new DateTimeFactory()
        );
    }

    /**
     * @param string $storeCode
     *
     * @dataProvider provideStores
     */
    public function testGetIndexAlias(string $identifier, string $storeCode)
    {
        $indexPrefix = 'vuestorefront';
        $this->indicesSettingsMock->method('addIdentifierToDefaultStoreView')->willReturn(true);
        $this->indicesSettingsMock->method('getIndexNamePrefix')->willReturn($indexPrefix);
        $this->indicesSettingsMock->method('getIndexIdentifier')->willReturn('code');
        $this->storeMock->method('getCode')->willReturn($storeCode);

        $expectedAlias = strtolower($indexPrefix . '_' . $storeCode
            . ($identifier === IndexSettings::DUMMY_INDEX_IDENTIFIER ? '' : '_' . $identifier));

        $this->assertEquals(
            $expectedAlias,
            $this->esIndexSettings->getIndexAlias($identifier, $this->storeMock)
        );
    }

    /**
     * @return array
     */
    public static function provideStores()
    {
        return [
            ['vue_storefront_catalog', 'de_code'],
            ['vue_storefront_catalog', 'De_code'],
            ['vue_storefront_catalog', 'DE_CODE'],
            ['product', 'de_code'],
            ['product', 'de_code'],
            ['product', 'DE_CODE'],
        ];
    }

    public function testDefaultStoreIdentifierCanBeOmitted(): void
    {
        $this->indicesSettingsMock->method('getIndexNamePrefix')->willReturn('catalog');
        $this->indicesSettingsMock->method('addIdentifierToDefaultStoreView')->willReturn(false);
        $this->indicesSettingsMock->method('getIndexIdentifier')->willReturn('id');
        $this->storeManagerMock->method('getDefaultStoreView')->willReturn($this->storeMock);
        $this->storeMock->method('getId')->willReturn(1);
        self::assertSame('catalog_product', $this->esIndexSettings->getIndexAlias('product', $this->storeMock));
        $otherStore = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $otherStore->method('getId')->willReturn(2);
        self::assertSame('catalog_2_product', $this->esIndexSettings->getIndexAlias('product', $otherStore));
    }
}
