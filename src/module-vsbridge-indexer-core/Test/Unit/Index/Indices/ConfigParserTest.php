<?php

namespace Divante\VsbridgeIndexerCore\Test\Unit\Index\Indices;

use Divante\VsbridgeIndexerCore\Api\MappingInterface;
use Divante\VsbridgeIndexerCore\Index\Indices\ConfigParser;
use Divante\VsbridgeIndexerCore\Index\MappingFactory;
use PHPUnit\Framework\TestCase;

class ConfigParserTest extends TestCase
{
    public function testEachEntityResolvesItsOwnMapping(): void
    {
        $productMapping = $this->createMock(MappingInterface::class);
        $categoryMapping = $this->createMock(MappingInterface::class);
        $factory = $this->createMock(MappingFactory::class);
        $factory->method('get')->willReturnMap([
            ['ProductMapping', $productMapping],
            ['CategoryMapping', $categoryMapping],
        ]);

        $result = (new ConfigParser($factory))->parse([
            'product' => ['mapping' => 'ProductMapping'],
            'category' => ['mapping' => 'CategoryMapping'],
        ]);

        self::assertSame([
            'product' => ['mapping' => $productMapping],
            'category' => ['mapping' => $categoryMapping],
        ], $result);
    }
}
