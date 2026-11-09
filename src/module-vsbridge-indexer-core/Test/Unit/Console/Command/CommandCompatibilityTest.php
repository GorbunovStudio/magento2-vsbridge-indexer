<?php

namespace Divante\VsbridgeIndexerCore\Test\Unit\Console\Command;

use Divante\VsbridgeIndexerCore\Console\Command\RebuildEsIndexCommand;
use Divante\VsbridgeIndexerCore\Console\Command\Rebuild\RebuildFactory;
use Divante\VsbridgeIndexerCore\Console\Command\ResetEsIndexCommand;
use Divante\VsbridgeIndexerCore\Console\Command\SingleEntityIndexCommand;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

class CommandCompatibilityTest extends TestCase
{
    public function testAllCommandsLoadAndRegisterWithInstalledConsole(): void
    {
        $factory = $this->createMock(ObjectManagerFactory::class);
        $application = new Application();
        foreach ([
            'vsbridge:index' => new SingleEntityIndexCommand($factory),
            'vsbridge:reset' => new ResetEsIndexCommand($factory),
            'vsbridge:reindex' => new RebuildEsIndexCommand(
                $this->createMock(RebuildFactory::class),
                $this->createMock(ManagerInterface::class),
                $this->createMock(State::class)
            ),
        ] as $name => $command) {
            $application->add($command);
            self::assertSame($command, $application->find($name));
        }
    }
}
