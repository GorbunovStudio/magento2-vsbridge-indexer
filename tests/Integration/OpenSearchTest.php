<?php

use Divante\VsbridgeIndexerCore\Api\BulkRequestInterfaceFactory;
use Divante\VsbridgeIndexerCore\Api\BulkResponseInterfaceFactory;
use Divante\VsbridgeIndexerCore\Api\IndexInterfaceFactory;
use Divante\VsbridgeIndexerCore\Api\Index\DataProviderResolverInterface;
use Divante\VsbridgeIndexerCore\Api\Index\TransactionKeyInterface;
use Divante\VsbridgeIndexerCore\Config\IndicesSettings;
use Divante\VsbridgeIndexerCore\Config\OptimizationSettings;
use Divante\VsbridgeIndexerCore\OpenSearch\Client;
use Divante\VsbridgeIndexerCore\OpenSearch\ClientBuilder;
use Divante\VsbridgeIndexerCore\OpenSearch\ClientResolver;
use Divante\VsbridgeIndexerCore\Index\BulkLogger;
use Divante\VsbridgeIndexerCore\Index\BulkRequest;
use Divante\VsbridgeIndexerCore\Index\BulkResponse;
use Divante\VsbridgeIndexerCore\Index\Index;
use Divante\VsbridgeIndexerCore\Index\IndexOperations;
use Divante\VsbridgeIndexerCore\Index\IndexSettings;
use Divante\VsbridgeIndexerCore\Index\Indices\ConfigResolver;
use Divante\VsbridgeIndexerCore\Index\Mapping\GeneralMapping;
use Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandler;
use Divante\VsbridgeIndexerCore\Logger\IndexerLogger;
use Divante\VsbridgeIndexerCatalog\Index\Mapping as Catalog;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** Exercises real OpenSearch with fixture Magento data sources, without a Magento database. */
class OpenSearchTest extends TestCase
{
    public function testIndexingLifecycle(): void
    {
        $endpoint = getenv('OPENSEARCH_TEST_URL');
        if (!$endpoint) {
            self::markTestSkipped('Set OPENSEARCH_TEST_URL to the isolated fixture.');
        }
        $url = parse_url($endpoint);
        $raw = (new ClientBuilder())->build(['host' => $url['host'], 'port' => $url['port'] ?? 9200, 'scheme' => $url['scheme']]);
        self::assertSame('2.15.0', $raw->info()['version']['number']);
        $client = new Client($raw);
        $prefix = 'vsbridge_test_' . bin2hex(random_bytes(4));
        $maps = $this->mappings();
        $config = [];
        foreach ($maps as $entity => $mapping) {
            $config[$entity] = ['mapping' => $mapping];
        }
        $parser = $this->createMock(ConfigResolver::class);
        $parser->method('resolve')->willReturn($config);
        $settings = $this->createMock(IndicesSettings::class);
        $settings->method('getIndexNamePrefix')->willReturn($prefix);
        $settings->method('getIndexIdentifier')->willReturn('id');
        $settings->method('addIdentifierToDefaultStoreView')->willReturn(true);
        $settings->method('getFieldsLimit')->willReturn(1000);
        $settings->method('getBatchIndexingSize')->willReturn(100);
        $indexSettings = new IndexSettings($this->createMock(StoreManagerInterface::class), $parser, $settings, new DateTimeFactory());
        $resolver = $this->createMock(ClientResolver::class);
        $resolver->method('getClient')->willReturn($client);
        $indexFactory = $this->createMock(IndexInterfaceFactory::class);
        $indexFactory->method('create')->willReturnCallback(static fn($data) => new Index(...$data));
        $requestFactory = $this->createMock(BulkRequestInterfaceFactory::class);
        $requestFactory->method('create')->willReturnCallback(static fn() => new BulkRequest());
        $responseFactory = $this->createMock(BulkResponseInterfaceFactory::class);
        $responseFactory->method('create')->willReturnCallback(static fn($data) => new BulkResponse($data['rawResponse']));
        $optimization = $this->createMock(OptimizationSettings::class);
        $optimization->method('changeNumberOfReplicas')->willReturn(true);
        $optimization->method('changeRefreshInterval')->willReturn(true);
        $optimization->method('getDefaultNumberOfReplicas')->willReturn(0);
        $optimization->method('getDefaultRefreshInterval')->willReturn('1s');
        $operations = new IndexOperations($resolver, $responseFactory, $requestFactory, $indexSettings, $indexFactory, $optimization);
        $providers = $this->createMock(DataProviderResolverInterface::class);
        $providers->method('getDataProviders')->willReturn([]);
        $transaction = $this->createMock(TransactionKeyInterface::class);
        $transaction->method('load')->willReturn('new');
        $stores = [];
        foreach ([1, 2] as $id) {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getId')->willReturn($id);
            $stores[] = $store;
        }
        $handler = static fn($entity) => new GenericIndexerHandler(
            $providers,
            new BulkLogger(new NullLogger()),
            new IndexerLogger('integration'),
            $operations,
            new Batch(),
            $transaction,
            $entity
        );
        try {
            self::assertFalse($client->indexExists($prefix . '_missing'));
            self::assertSame([], $client->getIndicesNameByAlias($prefix . '_missing'));
            self::assertNotEmpty($client->getClustersHealth()[0]['status']);
            $oldIndexes = [];
            for ($pass = 1; $pass <= 2; $pass++) {
                foreach ($stores as $store) {
                    foreach ($maps as $entity => $mapping) {
                        $indexer = $handler($entity);
                        $indexer->createIndex($store);
                        $document = ['id' => 1, 'tsk' => 'old'];
                        if ($entity === 'product') {
                            $document += ['created_at' => '2026-10-19 12:00:00', 'configurable_children' => [['slug' => 'child', 'final_price' => 10.5]]];
                        } elseif ($entity === 'category') {
                            $document += ['children_data' => [['slug' => 'child', 'children_count' => 0]]];
                        }
                        $indexer->saveIndex(new ArrayIterator([1 => $document]), $store);
                        $alias = $operations->getIndexAlias($entity, $store);
                        $names = $client->getIndicesNameByAlias($alias);
                        self::assertCount(1, $names);
                        if (isset($oldIndexes[$alias])) {
                            self::assertNotSame($oldIndexes[$alias], $names[0]);
                            self::assertFalse($client->indexExists($oldIndexes[$alias]));
                        }
                        $oldIndexes[$alias] = $names[0];
                        self::assertSame(1, $raw->count(['index' => $alias])['count']);
                        $state = $client->getIndexSettings($alias)[$names[0]]['settings']['index'];
                        self::assertSame('1s', $state['refresh_interval']);
                        self::assertSame('0', $state['number_of_replicas']);
                    }
                }
            }
            $store = $stores[0];
            $indexer = $handler('product');
            $alias = $operations->getIndexAlias('product', $store);
            $indexer->saveIndex(new ArrayIterator([1 => ['id' => 1, 'tsk' => 'old', 'slug' => 'changed'], 2 => ['id' => 2, 'tsk' => 'new']]), $store);
            self::assertSame('changed', $raw->get(['index' => $alias, 'id' => 1])['_source']['slug']);
            $indexer->cleanUpByTransactionKey($store, [1]);
            $client->refreshIndex($alias);
            self::assertFalse($raw->exists(['index' => $alias, 'id' => 1]));
            self::assertTrue($raw->exists(['index' => $alias, 'id' => 2]));
            $client->bulk(['body' => (new BulkRequest())->deleteDocuments($alias, [2])->getOperations()]);
            $client->refreshIndex($alias);
            self::assertSame(0, $raw->count(['index' => $alias])['count']);
            self::assertSame(1, $raw->count(['index' => $operations->getIndexAlias('product', $stores[1])])['count']);
            self::assertSame(1, $raw->count(['index' => $operations->getIndexAlias('category', $store)])['count']);
            // Bad data in a replacement index must leave the old alias and its contents usable.
            $active = $client->getIndicesNameByAlias($alias);
            $indexer->createIndex($store);
            try {
                $indexer->saveIndex(new ArrayIterator([3 => ['attribute_set_id' => 'not-a-number']]), $store);
                self::fail('Expected bulk mapping failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('bulk indexing failed', $exception->getMessage());
            }
            self::assertSame($active, $client->getIndicesNameByAlias($alias));
            self::assertTrue($client->indexExists($active[0]));
        } finally {
            // Delete only randomly prefixed indexes created by this test.
            $client->deleteIndex($prefix . '*');
        }
    }

    private function mappings(): array
    {
        $events = $this->createMock(ManagerInterface::class);
        $events->method('dispatch')->willReturnCallback(static function ($name, $data) {
            $properties = $data['mapping']->getData('properties');
            $properties['extension_flag'] = ['type' => 'boolean'];
            $data['mapping']->setData('properties', $properties);
        });
        $productAttributes = $this->createMock(\Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Product\LoadAttributes::class);
        $productAttributes->method('execute')->willReturn([]);
        $categoryAttributes = $this->createMock(\Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Category\LoadAttributes::class);
        $categoryAttributes->method('execute')->willReturn([]);
        $swatch = new Catalog\Attribute\SwatchMapping();
        $options = new Catalog\Attribute\OptionMapping($swatch);
        $additional = [];
        foreach (['bundle_options' => 'BundleOptionsMapping', 'product_links' => 'ProductLinksMapping', 'category' => 'CategoryMapping', 'custom_options' => 'CustomOptionsMapping', 'tier_prices' => 'TierPricesMapping'] as $field => $name) {
            $class = Catalog\Product::class . '\\' . $name;
            $additional[$field] = new $class();
        }
        $additional['configurable_options'] = new Catalog\Product\ConfigurableOptionsMapping($swatch);
        $additional['attributes_meta'] = new Catalog\Product\AttributesMetaMapping($options);
        $additional['downloadable_options'] = new \Divante\VsbridgeDownloadable\Index\Mapping\Product\DownloadableOptionsMapping();
        return [
            'product' => new Catalog\Product(new GeneralMapping(), new Catalog\StockMapping(), $productAttributes, [], $additional),
            'category' => new Catalog\Category($events, new GeneralMapping(), $this->createMock(\Divante\VsbridgeIndexerCatalog\Model\Attributes\CategoryChildAttributes::class), $categoryAttributes, []),
            'attribute' => new Catalog\Attribute($options),
            'cms_page' => new \Divante\VsbridgeIndexerCms\Index\Mapping\CmsPage($events),
            'cms_block' => new \Divante\VsbridgeIndexerCms\Index\Mapping\CmsBlock($events),
            'review' => new \Divante\VsbridgeIndexerReview\Index\Mapping\Review($events),
            'taxrule' => new \Divante\VsbridgeIndexerTax\Index\Mapping\Tax($events),
            'agreement' => new \Divante\VsbridgeIndexerAgreement\Index\Mapping\Agreement(),
        ];
    }
}
