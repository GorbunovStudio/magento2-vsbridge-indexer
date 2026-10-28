<?php

namespace Divante\VsbridgeIndexerCatalog\Test\Unit\Console;

use Divante\VsbridgeIndexerCatalog\Model\Indexer\Action\Category;
use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Category as ResourceCategory;
use Divante\VsbridgeIndexerCore\Api\BulkLoggerInterface;
use Divante\VsbridgeIndexerCore\Api\EventInterface;
use Divante\VsbridgeIndexerCore\Api\Index\DataProviderResolverInterface;
use Divante\VsbridgeIndexerCore\Api\Index\TransactionKeyInterface;
use Divante\VsbridgeIndexerCore\Api\IndexOperationInterface;
use Divante\VsbridgeIndexerCore\Cache\ConfigInterface;
use Divante\VsbridgeIndexerCore\Cache\Processor;
use Divante\VsbridgeIndexerCore\Console\Command\SingleEntityIndexCommand;
use Divante\VsbridgeIndexerCore\Index\Index;
use Divante\VsbridgeIndexerCore\Indexer\Action\ActionFactory;
use Divante\VsbridgeIndexerCore\Indexer\Action\Rows;
use Divante\VsbridgeIndexerCore\Indexer\Base;
use Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandler;
use Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandlerFactory;
use Divante\VsbridgeIndexerCore\Indexer\RebuildActionPool;
use Divante\VsbridgeIndexerCore\Indexer\StoreManager;
use Divante\VsbridgeIndexerCore\Logger\IndexerLogger;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\Framework\ObjectManagerInterface;
use Magento\Indexer\Model\Indexer;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

class MissingCategoryCommandTest extends TestCase
{
    public static function executionPaths(): array
    {
        return [['rows'], ['console']];
    }

    /** @dataProvider executionPaths */
    public function testMissingCategoryFailsWithoutWritesOrSuccessInvalidation(string $path): void
    {
        $resource = $this->createMock(ResourceCategory::class);
        $resource->expects(self::once())->method('getParentIds')->with(['2147483647'])->willReturn([]);
        $resource->expects(self::never())->method('getCategories');
        $pool = $this->createMock(RebuildActionPool::class);
        $pool->method('getAction')->with('category')->willReturn(new Category($resource));

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(3);
        $store->method('getName')->willReturn('Test store');
        $stores = $this->createMock(StoreManager::class);
        $stores->method('getStores')->willReturn([$store]);
        $stores->expects($path === 'console' ? self::once() : self::never())->method('override')->with([$store]);

        $operations = $this->createMock(IndexOperationInterface::class);
        $operations->method('getIndexByName')->willReturn(new Index('categories', 'categories'));
        $operations->method('getBatchIndexingSize')->willReturn(1);
        foreach (['createBulk', 'executeBulk', 'deleteByQuery', 'getIndexAlias', 'refreshIndex', 'switchIndexer'] as $method) {
            $operations->expects(self::never())->method($method);
        }
        $handler = new GenericIndexerHandler(
            $this->createMock(DataProviderResolverInterface::class),
            $this->createMock(BulkLoggerInterface::class),
            new IndexerLogger('test'), $operations, new Batch(),
            $this->createMock(TransactionKeyInterface::class), 'category'
        );
        $handlerFactory = $this->createMock(GenericIndexerHandlerFactory::class);
        $handlerFactory->method('create')->with(['entityName' => 'category'])->willReturn($handler);

        // Model an event subscriber that would invalidate cache after successful indexing.
        // No external Budsies observer or real HTTP connection is loaded.
        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->expects(self::never())->method('create');
        $cacheConfig = $this->createMock(ConfigInterface::class);
        $cacheConfig->method('clearCache')->willReturn(true);
        $events = $this->createMock(ManagerInterface::class);
        $cache = new Processor($curlFactory, $cacheConfig, $events, new NullLogger());
        $successEvents = [];
        $events->method('dispatch')->willReturnCallback(static function ($name, $data) use ($cache, &$successEvents) {
            if ($name === EventInterface::VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER) {
                $successEvents[] = $data;
                $cache->cleanCacheByDocIds($data['store_id'], $data['data_type'], $data['entity_ids']);
            }
        });
        $rows = new Rows($pool, $handlerFactory, $stores, $events, 'category');
        $actionFactory = $this->createMock(ActionFactory::class);
        $actionFactory->method('create')->with('rows', 'category')->willReturn($rows);
        $base = new Base($actionFactory, 'category');

        if ($path === 'rows') {
            try {
                $base->executeRow('2147483647');
                self::fail('Missing category must fail row execution.');
            } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
                self::assertStringContainsString('2147483647', $exception->getMessage());
            }
            self::assertSame([], $successEvents);
            return;
        }

        // Keep Magento's real reindexRow() so swallowed exceptions cannot hide the defect.
        $indexer = $this->getMockBuilder(Indexer::class)->disableOriginalConstructor()
            ->onlyMethods(['getActionInstance', 'getState', 'getId', 'getTitle'])->getMock();
        $indexer->method('getActionInstance')->willReturn($base);
        $indexer->expects(self::never())->method('getState');
        $indexer->method('getId')->willReturn('vsbridge_category_indexer');
        $indexer->method('getTitle')->willReturn('Categories');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with('3')->willReturn($store);
        $objects = $this->createMock(ObjectManagerInterface::class);
        $objects->method('get')->willReturnMap([
            [StoreManagerInterface::class, $storeManager], [StoreManager::class, $stores],
        ]);
        $command = $this->getMockBuilder(SingleEntityIndexCommand::class)
            ->setConstructorArgs([$this->createMock(ObjectManagerFactory::class)])
            ->onlyMethods(['getObjectManager', 'getAllIndexers'])->getMock();
        $command->method('getObjectManager')->willReturn($objects);
        $command->method('getAllIndexers')->willReturn([$indexer]);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $tester = new ApplicationTester($application);
        $exitCode = $tester->run([
            'command' => 'vsbridge:index', 'index' => 'vsbridge_category_indexer',
            'store' => '3', 'id' => '2147483647',
        ]);

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('2147483647', $tester->getDisplay());
        self::assertStringContainsString('No categories found', $tester->getDisplay());
        self::assertSame([], $successEvents);
    }
}
