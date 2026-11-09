<?php

namespace Divante\VsbridgeIndexerCore\Test\Unit\Index;

use Divante\VsbridgeIndexerCore\Api\Client\ClientInterface;
use Divante\VsbridgeIndexerCore\Config\OptimizationSettings;
use Divante\VsbridgeIndexerCore\Index\IndexOperations;
use Divante\VsbridgeIndexerCore\Index\IndexSettings;
use Divante\VsbridgeIndexerCore\Api\BulkResponseInterfaceFactory as BulkResponseFactory;
use Divante\VsbridgeIndexerCore\Api\BulkRequestInterfaceFactory as BulkRequestFactory;
use Divante\VsbridgeIndexerCore\Api\IndexInterfaceFactory as IndexFactory;
use Divante\VsbridgeIndexerCore\Index\Index;
use Divante\VsbridgeIndexerCore\OpenSearch\ClientResolver;
use PHPUnit\Framework\TestCase;
use Magento\Store\Model\Store;

class IndexOperationsTest extends TestCase
{
    /**
     * @var Store|PHPUnit_Framework_MockObject_MockObject
     */
    private $storeMock;

    /**
     * @var IndexSettings|PHPUnit_Framework_MockObject_MockObject
     */
    private $esIndexSettingsMock;

    /**
     * @var ClientResolver
     */
    private $clientResolverMock;

    /**
     * @var IndexFactory
     */
    private $indexFactoryMock;

    /**
     * @var BulkResponseFactory
     */
    private $bulkResponseFactoryMock;

    /**
     * @var BulkRequestFactory
     */
    private $bulkRequestFactoryMock;

    /**
     * @var IndexOperations
     */
    private $indexOperations;

    /** @var PHPUnit_Framework_MockObject_MockObject  */
    private $clientMock;

    /** @var OptimizationSettings|PHPUnit_Framework_MockObject_MockObject */
    private $optimizationSettingsMock;

    /** @var array[][]  */
    private $indicesXmlConfiguration  = [
        'vue' => [
            'mapping' => null
        ]
    ];

    protected function setUp(): void
    {
        $this->storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->esIndexSettingsMock = $this->getMockBuilder(IndexSettings::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->bulkRequestFactoryMock = $this->getMockBuilder(BulkRequestFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->bulkResponseFactoryMock = $this->getMockBuilder(BulkResponseFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->indexFactoryMock = $this->getMockBuilder(IndexFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $this->clientMock = $this->getMockBuilder(
            ClientInterface::class
        )->disableOriginalConstructor()->getMock();

        $this->clientResolverMock = $this->getMockBuilder(ClientResolver::class)
                ->disableOriginalConstructor()
                ->getMock();

        $this->optimizationSettingsMock = $this->getMockBuilder(OptimizationSettings::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->indexOperations = new IndexOperations(
            $this->clientResolverMock,
            $this->bulkResponseFactoryMock,
            $this->bulkRequestFactoryMock,
            $this->esIndexSettingsMock,
            $this->indexFactoryMock,
            $this->optimizationSettingsMock
        );
    }

    public function testGetExistingIndex()
    {
        $alias =  'vuestorefront_1';
        $name = 'vuestorefront_1';

        $indexMock = new Index(
            $name,
            $alias,
            null
        );

        $this->indexFactoryMock
            ->method('create')
            ->with([
                'name' => $name,
                'alias' => $alias,
                'mapping' => null,
            ])
            ->willReturn($indexMock);

        $this->esIndexSettingsMock->method('getConfig')->willReturn($this->indicesXmlConfiguration);
        $this->esIndexSettingsMock->method('getIndexAlias')->willReturn($alias);
        $this->esIndexSettingsMock->method('createIndexName')->willReturn($name);
        $this->esIndexSettingsMock->method('getEsConfig')->willReturn([]);
        $this->clientResolverMock->method('getClient')->with(1)->willReturn($this->clientMock);
        $this->storeMock->method('getId')->willReturn(1);

        $this->clientMock->method('indexExists')->with($name)->willReturn(true);

        $index = $this->indexOperations->getIndexByName('vue', $this->storeMock);
        $this->assertEquals($indexMock, $index);
        $this->assertEquals($index->isNew(), false);
    }

    public function testCreateNewIndex()
    {
        $this->storeMock->method('getId')->willReturn(1);

        $alias =  'vuestorefront_de';
        $name = 'vuestorefront_1';

        $indexMock = new Index(
            $name,
            $alias,
            null
        );

        $this->indexFactoryMock
            ->method('create')
            ->with([
                'name' => $name,
                'alias' => $alias,
                'mapping' => null,
            ])
            ->willReturn($indexMock);

        $this->esIndexSettingsMock->method('getConfig')->willReturn($this->indicesXmlConfiguration);
        $this->esIndexSettingsMock->method('getIndexAlias')->willReturn($alias);
        $this->esIndexSettingsMock->method('createIndexName')->willReturn($name);
        $this->esIndexSettingsMock->method('getEsConfig')->willReturn([]);
        $this->clientResolverMock->method('getClient')->with(1)->willReturn($this->clientMock);

        $index = $this->indexOperations->createIndex('vue', $this->storeMock);
        $this->assertEquals($indexMock, $index);
        $this->assertEquals($index->isNew(), true);
    }

    public function testAliasUpdateFailurePreservesOldIndex(): void
    {
        $this->clientResolverMock->method('getClient')->willReturn($this->clientMock);
        $this->clientMock->method('getIndicesNameByAlias')->willReturn(['old']);
        $this->clientMock->method('updateAliases')->willThrowException(new \RuntimeException('alias failed'));
        $this->clientMock->expects(self::never())->method('deleteIndex');
        $this->expectException(\RuntimeException::class);
        $this->indexOperations->switchIndexer(1, 'new', 'products');
    }

    public function testCreateIndexUsesItsSingleMapping(): void
    {
        $mapping = $this->createMock(\Divante\VsbridgeIndexerCore\Api\MappingInterface::class);
        $properties = ['properties' => ['sku' => ['type' => 'keyword']]];
        $mapping->method('getMappingProperties')->willReturn($properties);
        $this->storeMock->method('getId')->willReturn(1);
        $this->esIndexSettingsMock->method('getConfig')->willReturn(['product' => ['mapping' => $mapping]]);
        $this->esIndexSettingsMock->method('getIndexAlias')->willReturn('products');
        $this->esIndexSettingsMock->method('createIndexName')->willReturn('products_new');
        $this->esIndexSettingsMock->method('getEsConfig')->willReturn([]);
        $this->clientResolverMock->method('getClient')->willReturn($this->clientMock);
        $this->indexFactoryMock->method('create')->with([
            'name' => 'products_new', 'alias' => 'products', 'mapping' => $mapping,
        ])->willReturn(new Index('products_new', 'products', $mapping));
        $this->clientMock->expects(self::once())->method('putMapping')->with('products_new', $properties);

        self::assertSame($mapping, $this->indexOperations->createIndex('product', $this->storeMock)->getMapping());
    }

    public function testOldIndexIsDeletedOnlyAfterAliasUpdate(): void
    {
        $calls = [];
        $this->clientResolverMock->method('getClient')->willReturn($this->clientMock);
        $this->clientMock->method('getIndicesNameByAlias')->willReturn(['old']);
        $this->clientMock->method('updateAliases')->willReturnCallback(static function ($actions) use (&$calls) {
            self::assertSame([
                ['add' => ['index' => 'new', 'alias' => 'products']],
                ['remove' => ['index' => 'old', 'alias' => 'products']],
            ], $actions);
            $calls[] = 'alias';
        });
        $this->clientMock->method('deleteIndex')->with('old')->willReturnCallback(static function () use (&$calls) {
            $calls[] = 'delete';
        });
        $this->indexOperations->switchIndexer(1, 'new', 'products');
        self::assertSame(['alias', 'delete'], $calls);
    }

    public function testDisabledChecksDoNotRequestHealth(): void
    {
        $this->clientResolverMock->method('getClient')->willReturn($this->clientMock);
        $this->clientMock->expects(self::never())->method('getClustersHealth');
        $this->clientMock->method('bulk')->willReturn(['errors' => false, 'items' => []]);
        $bulk = (new \Divante\VsbridgeIndexerCore\Index\BulkRequest())->addDocuments('products', [1 => []]);
        $this->bulkResponseFactoryMock->method('create')->willReturn(new \Divante\VsbridgeIndexerCore\Index\BulkResponse(['errors' => false, 'items' => []]));
        self::assertFalse($this->indexOperations->executeBulk(1, $bulk)->hasErrors());
    }

    public function testUnsupportedQueueCheckIsActionable(): void
    {
        $this->optimizationSettingsMock->method('checkMaxBulkQueueRequirement')->willReturn(true);
        $this->clientMock->expects(self::never())->method('bulk');
        $this->expectException(\Divante\VsbridgeIndexerCore\Exception\ConnectionUnhealthyException::class);
        $this->expectExceptionMessage('max_bulk_queue_requirement');
        $bulk = (new \Divante\VsbridgeIndexerCore\Index\BulkRequest())->addDocuments('products', [1 => []]);
        $this->indexOperations->executeBulk(1, $bulk);
    }
    /** @dataProvider healthStatuses */
    public function testHealthPolicy(string $status): void
    {
        $this->optimizationSettingsMock->method('checkClusterHealth')->willReturn(true);
        $this->clientResolverMock->method('getClient')->willReturn($this->clientMock);
        $this->clientMock->method('getClustersHealth')->willReturn([['status' => $status]]);
        $this->clientMock->method('bulk')->willReturn(['errors' => false, 'items' => []]);
        $this->bulkResponseFactoryMock->method('create')->willReturn(new \Divante\VsbridgeIndexerCore\Index\BulkResponse(['errors' => false, 'items' => []]));
        if ($status !== 'green') {
            $this->clientMock->expects(self::never())->method('bulk');
            $this->expectException(\Divante\VsbridgeIndexerCore\Exception\ConnectionUnhealthyException::class);
        }
        $bulk = (new \Divante\VsbridgeIndexerCore\Index\BulkRequest())->addDocuments('products', [1 => []]);
        $response = $this->indexOperations->executeBulk(1, $bulk);
        self::assertFalse($response->hasErrors());
    }

    public static function healthStatuses(): array
    {
        return [['green'], ['yellow'], ['red']];
    }
}
