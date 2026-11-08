<?php

namespace Divante\VsbridgeIndexerCatalog\Test\Unit\Console;

use Divante\VsbridgeIndexerCatalog\Model\Indexer\Action\Category;
use Divante\VsbridgeIndexerCatalog\Model\Indexer\CategoryEntityValidator;
use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Category as ResourceCategory;
use Divante\VsbridgeIndexerCore\Api\BulkLoggerInterface;
use Divante\VsbridgeIndexerCore\Api\BulkRequestInterface;
use Divante\VsbridgeIndexerCore\Api\EventInterface;
use Divante\VsbridgeIndexerCore\Api\Index\DataProviderResolverInterface;
use Divante\VsbridgeIndexerCore\Api\Index\TransactionKeyInterface;
use Divante\VsbridgeIndexerCore\Api\IndexOperationInterface;
use Divante\VsbridgeIndexerCore\Api\SingleEntityValidatorInterface;
use Divante\VsbridgeIndexerCore\Console\Command\SingleEntityIndexCommand;
use Divante\VsbridgeIndexerCore\Index\BulkResponse;
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
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\Framework\ObjectManagerInterface;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\Indexer\State;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * vsbridge:index for categories through the real command, Magento reindexRow(), Base, Rows and handler.
 */
class MissingCategoryCommandTest extends TestCase
{
    public function testCategoryValidatorIsRegisteredForTheCategoryIndexer(): void
    {
        $validators = self::configuredValidators();

        self::assertSame(['vsbridge_category_indexer' => CategoryEntityValidator::class], $validators);
        self::assertTrue(is_subclass_of(CategoryEntityValidator::class, SingleEntityValidatorInterface::class));
    }

    public function testMissingCategoryFailsBeforeIndexingWithoutCleanupOrSuccessInvalidation(): void
    {
        $result = $this->runCommand('2147483647', []);

        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('No categories found for the requested IDs: 2147483647.', $result['display']);
        self::assertSame([], $result['parentIdCalls'], 'The generator must not run.');
        self::assertSame([], $result['written']);
        self::assertSame([], $result['deleteQueries']);
        self::assertSame([], $result['successEvents']);
    }

    public function testExistingCategoryIsIndexed(): void
    {
        $result = $this->runCommand('3', [2, 3]);

        self::assertSame(0, $result['exitCode'], $result['display']);
        self::assertSame([['3']], $result['parentIdCalls']);
        self::assertSame([2, 3], $result['written']);
        self::assertCount(1, $result['deleteQueries']);
        self::assertSame([['store_id' => 3, 'data_type' => 'category', 'entity_ids' => ['3']]], $result['successEvents']);
    }

    /**
     * @return string[] Validator class names keyed by indexer code, as configured in the catalog di.xml
     */
    private static function configuredValidators(): array
    {
        $validators = [];
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/di.xml');
        foreach ($xml->type as $type) {
            if ((string)$type['name'] !== SingleEntityIndexCommand::class) {
                continue;
            }
            foreach ($type->arguments->argument as $argument) {
                if ((string)$argument['name'] === 'entityValidators') {
                    foreach ($argument->item as $item) {
                        $validators[(string)$item['name']] = (string)$item;
                    }
                }
            }
        }

        return $validators;
    }

    private function runCommand(string $id, array $resolvedIds): array
    {
        $parentIdCalls = [];
        // Validation happens in the command; the indexing generator resolves the same IDs again.
        $validatorResource = $this->createMock(ResourceCategory::class);
        $validatorResource->expects(self::once())->method('getParentIds')->with([$id])->willReturn($resolvedIds);
        $resource = $this->createMock(ResourceCategory::class);
        $resource->method('getParentIds')->willReturnCallback(static function (array $ids) use (&$parentIdCalls, $resolvedIds) {
            $parentIdCalls[] = $ids;
            return $resolvedIds;
        });
        $resource->method('getCategories')->willReturnCallback(static function ($storeId, array $ids, $fromId) {
            self::assertNotEmpty($ids, 'A single-entity update must never page through all categories.');
            return $fromId ? [] : array_map(static fn ($id) => ['entity_id' => $id], $ids);
        });
        $pool = $this->createMock(RebuildActionPool::class);
        $pool->method('getAction')->with('category')->willReturn(new Category($resource));

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(3);
        $store->method('getName')->willReturn('Test store');
        $stores = $this->createMock(StoreManager::class);
        $stores->method('getStores')->willReturn([$store]);
        $stores->expects(self::once())->method('override')->with([$store]);

        $written = [];
        $bulk = $this->createMock(BulkRequestInterface::class);
        $bulk->method('addDocuments')->willReturnCallback(function ($index, array $docs) use (&$written, $bulk) {
            array_push($written, ...array_keys($docs));
            return $bulk;
        });
        $deleteQueries = [];
        $operations = $this->createMock(IndexOperationInterface::class);
        $operations->method('getIndexByName')->willReturn(new Index('categories', 'categories'));
        $operations->method('getIndexAlias')->willReturn('categories');
        $operations->method('indexExists')->willReturn(true);
        $operations->method('getBatchIndexingSize')->willReturn(1);
        $operations->method('createBulk')->willReturn($bulk);
        $operations->method('executeBulk')->willReturn(new BulkResponse(['errors' => false, 'items' => []]));
        $operations->method('deleteByQuery')->willReturnCallback(static function ($storeId, array $query) use (&$deleteQueries) {
            $deleteQueries[] = $query;
        });
        $providers = $this->createMock(DataProviderResolverInterface::class);
        $providers->method('getDataProviders')->willReturn([]);
        $handler = new GenericIndexerHandler(
            $providers, $this->createMock(BulkLoggerInterface::class), new IndexerLogger('test'),
            $operations, new Batch(), $this->createMock(TransactionKeyInterface::class), 'category'
        );
        $handlerFactory = $this->createMock(GenericIndexerHandlerFactory::class);
        $handlerFactory->method('create')->with(['entityName' => 'category'])->willReturn($handler);

        // The success event is what triggers cache invalidation subscribers.
        $successEvents = [];
        $events = $this->createMock(ManagerInterface::class);
        $events->method('dispatch')->willReturnCallback(static function ($name, $data) use (&$successEvents) {
            if ($name === EventInterface::VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER) {
                $successEvents[] = $data;
            }
        });
        $actionFactory = $this->createMock(ActionFactory::class);
        $actionFactory->method('create')->with('rows', 'category')
            ->willReturn(new Rows($pool, $handlerFactory, $stores, $events, 'category'));
        $base = new Base($actionFactory, 'category');

        // Keep Magento's real reindexRow() so swallowed exceptions cannot hide a defect.
        $indexer = $this->getMockBuilder(Indexer::class)->disableOriginalConstructor()
            ->onlyMethods(['getActionInstance', 'getState', 'getId', 'getTitle'])->getMock();
        $indexer->method('getActionInstance')->willReturn($base);
        $indexer->method('getState')->willReturn($this->createMock(State::class));
        $indexer->method('getId')->willReturn('vsbridge_category_indexer');
        $indexer->method('getTitle')->willReturn('Categories');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with('3')->willReturn($store);
        $objects = $this->createMock(ObjectManagerInterface::class);
        $objects->method('get')->willReturnMap([
            [StoreManagerInterface::class, $storeManager],
            [StoreManager::class, $stores],
            [CategoryEntityValidator::class, new CategoryEntityValidator($validatorResource)],
        ]);
        $command = $this->getMockBuilder(SingleEntityIndexCommand::class)
            ->setConstructorArgs([$this->createMock(ObjectManagerFactory::class), self::configuredValidators()])
            ->onlyMethods(['getObjectManager', 'getAllIndexers'])->getMock();
        $command->method('getObjectManager')->willReturn($objects);
        $command->method('getAllIndexers')->willReturn([$indexer]);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $tester = new ApplicationTester($application);
        $exitCode = $tester->run([
            'command' => 'vsbridge:index', 'index' => 'vsbridge_category_indexer',
            'store' => '3', 'id' => $id,
        ]);

        return [
            'exitCode' => $exitCode,
            'display' => $tester->getDisplay(),
            'parentIdCalls' => $parentIdCalls,
            'written' => $written,
            'deleteQueries' => $deleteQueries,
            'successEvents' => $successEvents,
        ];
    }
}
