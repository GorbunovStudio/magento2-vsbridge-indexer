<?php

namespace Divante\VsbridgeIndexerCore\Test\Unit\Indexer;

use Divante\VsbridgeIndexerCore\Indexer\Action\AbstractAction;
use Divante\VsbridgeIndexerCore\Indexer\Action\ActionFactory;
use Divante\VsbridgeIndexerCore\Indexer\Base;
use Magento\Framework\ObjectManager\Config\Config;
use Magento\Framework\ObjectManager\Definition\Runtime;
use Magento\Framework\ObjectManager\Factory\Dynamic\Developer;
use Magento\Framework\ObjectManager\Factory\Dynamic\Production;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

class BaseDiTest extends TestCase
{
    public function testConfiguredIndexersResolveEntityNamesThroughMagentoFactories(): void
    {
        $configuration = [];
        $src = dirname(__DIR__, 4);
        foreach (glob($src . '/*/etc/di.xml') as $file) {
            $xml = simplexml_load_file($file);
            foreach ($xml->virtualType as $virtualType) {
                if ((string)$virtualType['type'] !== Base::class) {
                    continue;
                }
                $arguments = [];
                foreach ($virtualType->arguments->argument as $argument) {
                    $arguments[(string)$argument['name']] = (string)$argument;
                }
                $configuration[(string)$virtualType['name']] = ['type' => Base::class, 'arguments' => $arguments];
            }
        }
        self::assertCount(8, $configuration);
        foreach ([Developer::class, Production::class] as $factoryClass) {
            foreach ($configuration as $name => $definition) {
                self::assertNotEmpty($definition['arguments']['entityName'], $name);
                $action = $this->createMock(AbstractAction::class);
                $action->expects(self::once())->method('execute')->with([]);
                $actions = $this->createMock(ActionFactory::class);
                $actions->expects(self::once())->method('create')
                    ->with('full', $definition['arguments']['entityName'])->willReturn($action);
                $manager = $this->createMock(ObjectManagerInterface::class);
                $manager->method('get')->with(ActionFactory::class)->willReturn($actions);
                $config = new Config();
                $config->extend($configuration);
                $factory = new $factoryClass($config, $manager, new Runtime());
                $indexer = $factory->create($name);
                self::assertInstanceOf(Base::class, $indexer);
                $indexer->executeFull();
            }
        }
    }
}
