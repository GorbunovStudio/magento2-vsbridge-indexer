<?php

namespace Divante\VsbridgeIndexerCore\Test\Unit\Indexer;

use Divante\VsbridgeIndexerCore\Api\BulkLoggerInterface;
use Divante\VsbridgeIndexerCore\Api\Index\DataProviderResolverInterface;
use Divante\VsbridgeIndexerCore\Api\Index\TransactionKeyInterface;
use Divante\VsbridgeIndexerCore\Api\IndexOperationInterface;
use Divante\VsbridgeIndexerCore\Exception\ConnectionDisabledException;
use Divante\VsbridgeIndexerCore\Index\BulkRequest;
use Divante\VsbridgeIndexerCore\Index\BulkResponse;
use Divante\VsbridgeIndexerCore\Index\Index;
use Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandler;
use Divante\VsbridgeIndexerCore\Logger\IndexerLogger;
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;

class GenericIndexerHandlerTest extends TestCase
{
    private function handler($operations, $logger = null): GenericIndexerHandler
    {
        $providers = $this->createMock(DataProviderResolverInterface::class);
        $providers->method('getDataProviders')->with('product')->willReturn([]);
        $transaction = $this->createMock(TransactionKeyInterface::class);
        $transaction->method('load')->willReturn('current');
        return new GenericIndexerHandler($providers, $this->createMock(BulkLoggerInterface::class),
            $logger ?? new IndexerLogger('test'), $operations, new Batch(), $transaction, 'product');
    }

    private function store(): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        return $store;
    }

    public function testRefreshPrecedesPublication(): void
    {
        $calls = [];
        $operations = $this->createMock(IndexOperationInterface::class);
        $operations->method('getIndexByName')->willReturn(new Index('products_v2', 'products'));
        $operations->method('getBatchIndexingSize')->willReturn(100);
        $operations->method('createBulk')->willReturn(new BulkRequest());
        $operations->method('executeBulk')->willReturn(new BulkResponse(['errors' => false, 'items' => []]));
        $operations->expects(self::once())->method('cleanAfterOptimizeEsIndexing');
        $operations->method('refreshIndex')->willReturnCallback(static function () use (&$calls) { $calls[] = 'refresh'; });
        $operations->method('switchIndexer')->willReturnCallback(static function () use (&$calls) { $calls[] = 'publish'; });
        $this->handler($operations)->saveIndex(new \ArrayIterator([1 => ['sku' => 'a']]), $this->store());
        self::assertSame(['refresh', 'publish'], $calls);
    }

    public function testFailedBulkRestoresSettingsAndDoesNotPublish(): void
    {
        $operations = $this->createMock(IndexOperationInterface::class);
        $operations->method('getIndexByName')->willReturn(new Index('products_v2', 'products'));
        $operations->method('getBatchIndexingSize')->willReturn(100);
        $operations->method('createBulk')->willReturn(new BulkRequest());
        $operations->method('executeBulk')->willReturn(new BulkResponse(['errors' => true, 'items' => []]));
        $operations->expects(self::once())->method('cleanAfterOptimizeEsIndexing');
        $operations->expects(self::never())->method('switchIndexer');
        $operations->expects(self::never())->method('refreshIndex');
        $this->expectException(\RuntimeException::class);
        $this->handler($operations)->saveIndex(new \ArrayIterator([1 => ['sku' => 'a']]), $this->store());
    }

    public function testTransportFailureIsNotMaskedByCleanupFailure(): void
    {
        $operations = $this->createMock(IndexOperationInterface::class);
        $operations->method('getIndexByName')->willReturn(new Index('products_v2', 'products'));
        $operations->method('getBatchIndexingSize')->willReturn(100);
        $operations->method('createBulk')->willReturn(new BulkRequest());
        $failure = new \RuntimeException('connection lost');
        $operations->method('executeBulk')->willThrowException($failure);
        $operations->method('cleanAfterOptimizeEsIndexing')->willThrowException(new \RuntimeException('cleanup failed'));
        $operations->expects(self::never())->method('switchIndexer');
        try {
            $this->handler($operations)->saveIndex(new \ArrayIterator([1 => ['sku' => 'a']]), $this->store());
            self::fail('Bulk failure must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @dataProvider cleanupSelections */
    public function testCleanupIsTypelessAndScoped(?array $ids): void
    {
        $operations = $this->createMock(IndexOperationInterface::class);
        $operations->method('getIndexAlias')->willReturn('products');
        $operations->method('indexExists')->willReturn(true);
        $operations->method('getIndexByName')->willReturn(new Index('products', 'products'));
        $selection = ['must_not' => ['term' => ['tsk' => 'current']]];
        if ($ids) {
            $selection['must'] = ['terms' => ['_id' => $ids]];
        }
        $operations->expects(self::once())->method('deleteByQuery')->with(1, [
            'index' => 'products', 'body' => ['query' => ['bool' => $selection]],
        ]);
        $this->handler($operations)->cleanUpByTransactionKey($this->store(), $ids);
    }

    public static function cleanupSelections(): array
    {
        return [[[4, 5]], [null]];
    }

    public function testDisabledIndexerDoesNotWrite(): void
    {
        $operations = $this->createMock(IndexOperationInterface::class);
        $operations->method('getIndexByName')->willThrowException(new ConnectionDisabledException('disabled'));
        $operations->expects(self::never())->method('createIndex');
        $operations->expects(self::never())->method('executeBulk');
        $this->handler($operations)->saveIndex(new \ArrayIterator(), $this->store());
    }

    public function testFailedRefreshDoesNotPublish(): void
    {
        $operations = $this->createMock(IndexOperationInterface::class);
        $operations->method('getIndexByName')->willReturn(new Index('products_v2', 'products'));
        $operations->method('getBatchIndexingSize')->willReturn(100);
        $operations->method('refreshIndex')->willThrowException(new \RuntimeException('refresh failed'));
        $operations->expects(self::never())->method('switchIndexer');
        $this->expectExceptionMessage('refresh failed');
        $this->handler($operations)->saveIndex(new \ArrayIterator(), $this->store());
    }

    public function testFullActionPreservesEntityEvent(): void
    {
        $indexer = $this->createMock(GenericIndexerHandler::class);
        $indexer->expects(self::once())->method('createIndex')->willReturn(new Index('new', 'products'));
        $indexer->expects(self::once())->method('saveIndex');
        $factory = $this->createMock(\Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandlerFactory::class);
        $factory->expects(self::exactly(2))->method('create')->with(['entityName' => 'product'])->willReturn($indexer);
        $stores = $this->createMock(\Divante\VsbridgeIndexerCore\Indexer\StoreManager::class);
        $stores->method('getStores')->willReturn([$this->store()]);
        $rebuild = $this->createMock(\Divante\VsbridgeIndexerCore\Indexer\RebuildActionInterface::class);
        $rebuild->method('rebuild')->willReturn(new \ArrayIterator());
        $pool = $this->createMock(\Divante\VsbridgeIndexerCore\Indexer\RebuildActionPool::class);
        $pool->method('getAction')->with('product')->willReturn($rebuild);
        $events = $this->createMock(\Magento\Framework\Event\ManagerInterface::class);
        $events->expects(self::once())->method('dispatch')->with(
            \Divante\VsbridgeIndexerCore\Api\EventInterface::VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER,
            ['store_id' => 1, 'data_type' => 'product', 'entity_ids' => []]
        );
        (new \Divante\VsbridgeIndexerCore\Indexer\Action\Full($pool, $factory, $stores, $events, 'product'))->execute([]);
    }
}
