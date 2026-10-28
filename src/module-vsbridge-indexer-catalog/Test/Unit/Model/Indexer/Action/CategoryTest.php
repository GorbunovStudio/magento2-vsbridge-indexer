<?php

namespace Divante\VsbridgeIndexerCatalog\Test\Unit\Model\Indexer\Action;

use Divante\VsbridgeIndexerCatalog\Model\Indexer\Action\Category;
use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Category as ResourceCategory;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    /** @dataProvider successfulSelections */
    public function testSelectionAndPagination(array $input, array $resolved, array $exported): void
    {
        $resource = $this->createMock(ResourceCategory::class);
        $resource->expects($input ? self::once() : self::never())->method('getParentIds')
            ->with($input)->willReturn($resolved);
        $pages = array_map(static fn ($id) => [['entity_id' => (string)$id]], $exported);
        $pages[] = [];
        $page = 0;
        $resource->expects(self::exactly(count($pages)))->method('getCategories')
            ->willReturnCallback(function ($storeId, $ids, $fromId) use (&$page, $pages, $resolved, $exported) {
                self::assertSame(3, $storeId);
                self::assertSame($resolved, $ids);
                self::assertSame($page === 0 ? 0 : (string)$exported[$page - 1], $fromId);
                return $pages[$page++];
            });

        $documents = iterator_to_array((new Category($resource))->rebuild(3, $input));
        self::assertSame($exported, array_keys($documents));
        self::assertSame($exported, array_column($documents, 'id'));
    }

    public static function successfulSelections(): array
    {
        return [
            'existing category and parents' => [[42], [2, 10, 42], [2, 10, 42]],
            'intentional full export' => [[], [], [2, 10, 42, 73]],
            'mixed selection' => [[42, 2147483647], [2, 10, 42], [2, 10, 42]],
        ];
    }

    /** @dataProvider missingSelections */
    public function testUnresolvedPartialSelectionFailsBeforePageQuery(array $ids): void
    {
        $resource = $this->createMock(ResourceCategory::class);
        $resource->expects(self::once())->method('getParentIds')->with($ids)->willReturn([]);
        $resource->expects(self::never())->method('getCategories');
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(implode(', ', $ids));
        iterator_to_array((new Category($resource))->rebuild(3, $ids));
    }

    public static function missingSelections(): array
    {
        return [[[2147483647]], [[2147483646, 2147483647]]];
    }
}
