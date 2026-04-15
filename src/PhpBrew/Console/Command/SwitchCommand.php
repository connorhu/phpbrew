<?php

namespace PhpBrew\Console\Command;

use PhpBrew\BuildFinder;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;

class SwitchCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this
            ->setName('switch')
            ->setDescription('Switch default php version.')
            ->addArgument('version', InputArgument::REQUIRED, 'PHP version', null,
                fn(CompletionInput $i): array => BuildFinder::findInstalledVersions());
    }
}
