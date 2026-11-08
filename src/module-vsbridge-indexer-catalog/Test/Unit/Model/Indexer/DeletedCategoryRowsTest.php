<?php

namespace Divante\VsbridgeIndexerCatalog\Test\Unit\Model\Indexer;

use Divante\VsbridgeIndexerCatalog\Model\Indexer\Action\Category;
use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Category as ResourceCategory;
use Divante\VsbridgeIndexerCore\Api\BulkLoggerInterface;
use Divante\VsbridgeIndexerCore\Api\BulkRequestInterface;
use Divante\VsbridgeIndexerCore\Api\EventInterface;
use Divante\VsbridgeIndexerCore\Api\Index\DataProviderResolverInterface;
use Divante\VsbridgeIndexerCore\Api\Index\TransactionKeyInterface;
use Divante\VsbridgeIndexerCore\Api\IndexOperationInterface;
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
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;

/**
 * Scheduled (MView) category updates through the real Base, Rows, Category action and indexer handler.
 */
class DeletedCategoryRowsTest extends TestCase
{
    /** Surviving catalog_category_entity rows after a deletion: ID => path. */
    private const CATALOG = [2 => '1/2', 3 => '1/2/3', 4 => '1/2/4'];

    /** @dataProvider changelogBatches */
    public function testChangelogBatchExportsSurvivorsAndRemovesDeletedDocuments(array $ids, array $exported): void
    {
        $pageFilters = [];
        $resource = $this->createMock(ResourceCategory::class);
        $resource->expects(self::once())->method('getParentIds')->with($ids)
            ->willReturnCallback(static function (array $ids) {
                $resolved = [];
                foreach ($ids as $id) {
                    foreach (explode('/', self::CATALOG[(int)$id] ?? '') as $pathId) {
                        if ((int)$pathId > 1) {
                            $resolved[] = (int)$pathId;
                        }
                    }
                }
                return array_values(array_unique($resolved));
            });
        $resource->method('getCategories')
            ->willReturnCallback(static function ($storeId, array $ids, $fromId) use (&$pageFilters) {
                $pageFilters[] = $ids;
                $page = [];
                foreach (array_keys(self::CATALOG) as $id) {
                    if ($id > $fromId && in_array($id, $ids, true)) {
                        $page[] = ['entity_id' => $id];
                    }
                }
                return $page;
            });
        $pool = $this->createMock(RebuildActionPool::class);
        $pool->method('getAction')->with('category')->willReturn(new Category($resource));

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(3);
        $stores = $this->createMock(StoreManager::class);
        $stores->method('getStores')->willReturn([$store]);

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
        $operations->method('getBatchIndexingSize')->willReturn(100);
        $operations->method('createBulk')->willReturn($bulk);
        $operations->method('executeBulk')->willReturn(new BulkResponse(['errors' => false, 'items' => []]));
        $operations->method('deleteByQuery')->willReturnCallback(static function ($storeId, array $query) use (&$deleteQueries) {
            $deleteQueries[] = $query;
        });
        $providers = $this->createMock(DataProviderResolverInterface::class);
        $providers->method('getDataProviders')->willReturn([]);
        $transactionKey = $this->createMock(TransactionKeyInterface::class);
        $transactionKey->method('load')->willReturn('current');
        $handler = new GenericIndexerHandler(
            $providers, $this->createMock(BulkLoggerInterface::class), new IndexerLogger('test'),
            $operations, new Batch(), $transactionKey, 'category'
        );
        $handlerFactory = $this->createMock(GenericIndexerHandlerFactory::class);
        $handlerFactory->method('create')->with(['entityName' => 'category'])->willReturn($handler);

        $successEvents = [];
        $events = $this->createMock(ManagerInterface::class);
        $events->method('dispatch')->willReturnCallback(static function ($name, array $data) use (&$successEvents) {
            if ($name === EventInterface::VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER) {
                $successEvents[] = $data;
            }
        });
        $actionFactory = $this->createMock(ActionFactory::class);
        $actionFactory->method('create')->with('rows', 'category')
            ->willReturn(new Rows($pool, $handlerFactory, $stores, $events, 'category'));

        // Magento\Framework\Mview\View::update() calls execute() with the changelog IDs and advances
        // the view's version only when it returns.
        (new Base($actionFactory, 'category'))->execute($ids);

        self::assertSame($exported, $written);
        foreach ($pageFilters as $filter) {
            self::assertNotEmpty($filter, 'A partial update must never page through all categories.');
        }
        // One page of survivors followed by the empty page that ends pagination; no query without survivors.
        self::assertSame($exported ? [$exported, $exported] : [], $pageFilters);
        self::assertSame([[
            'index' => 'categories',
            'body' => ['query' => ['bool' => [
                'must_not' => ['term' => ['tsk' => 'current']],
                'must' => ['terms' => ['_id' => $ids]],
            ]]],
        ]], $deleteQueries);
        self::assertSame([['store_id' => 3, 'data_type' => 'category', 'entity_ids' => $ids]], $successEvents);
    }

    public static function changelogBatches(): array
    {
        return [
            'deleted-only chunk' => [[50], []],
            'several deleted categories' => [[50, 51], []],
            'unassigned root deleted with its child' => [[1, 48, 49], []],
            'admin delete under the store root' => [[1, 2, 50], [2]],
            'existing category with parents' => [[3], [2, 3]],
        ];
    }
}
