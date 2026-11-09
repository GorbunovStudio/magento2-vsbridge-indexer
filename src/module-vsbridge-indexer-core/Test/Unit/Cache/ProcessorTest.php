<?php

namespace Divante\VsbridgeIndexerCore\Test\Unit\Cache;

use Divante\VsbridgeIndexerCore\Cache\Config;
use Divante\VsbridgeIndexerCore\Cache\Processor;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ProcessorTest extends TestCase
{
    /** @dataProvider batches */
    public function testRequestsContainOnlyCurrentBatch($size, array $ids, array $expectedTags, string $type): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $values = [
            'clear_cache' => '1', 'entity_invalidate_batch_size' => $size,
            'vsf_base_url' => 'https://cache.invalid/', 'invalidate_cache_key' => 'test-key',
            'connection_timeout' => '7',
        ];
        $scope->method('getValue')->willReturnCallback(static function ($path, $scopeType, $storeId) use ($values) {
            self::assertSame('stores', $scopeType);
            self::assertSame(3, $storeId);
            return $values[basename($path)];
        });
        $events = $this->createMock(ManagerInterface::class);
        $events->expects(self::once())->method('dispatch')->with('vsf_prepare_cache_tags', self::anything())
            ->willReturnCallback(static function ($name, $data) {
                // Verify observer-provided tag prefixes still take effect.
                $tags = $data['cache_tags']->getData('items');
                $tags['custom'] = 'X';
                $data['cache_tags']->setData('items', $tags);
            });
        $urls = [];
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::exactly(count($expectedTags)))->method('setConfig')->with(['timeout' => 7]);
        $curl->expects(self::exactly(count($expectedTags)))->method('write')
            ->willReturnCallback(static function ($method, $url, $version) use (&$urls) {
                self::assertSame('GET', $method);
                self::assertSame('1.0', $version);
                $urls[] = $url;
            });
        $curl->method('read')->willReturn("HTTP/1.0 200 OK\r\n\r\n");
        $factory = $this->createMock(CurlFactory::class);
        $factory->expects(self::exactly(count($expectedTags)))->method('create')->willReturn($curl);

        (new Processor($factory, new Config($scope), $events, new NullLogger()))
            ->cleanCacheByDocIds(3, $type, $ids);

        self::assertSame(array_map(static fn ($tags) =>
            'https://cache.invalid/invalidate?key=test-key&tag=' . $tags, $expectedTags), $urls);
    }

    public static function batches(): array
    {
        return [
            'one ID per batch' => [1, [101, 202], ['C101', 'C202'], 'category'],
            'incomplete final batch' => [2, [101, 202, 303, 404, 505], ['P101,P202', 'P303,P404', 'P505'], 'product'],
            'batch larger than selection' => [5, [101, 202], ['C101,C202'], 'category'],
            'disabled splitting' => [0, [101, 202], ['C101,C202'], 'category'],
            'empty configuration disables splitting' => ['', [101, 202], ['C101,C202'], 'category'],
            'extended tag prefix' => [1, [202, 101], ['X202', 'X101'], 'custom'],
        ];
    }
}
