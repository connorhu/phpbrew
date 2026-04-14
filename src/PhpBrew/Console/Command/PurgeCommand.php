<?php

namespace PhpBrew\Console\Command;

use PhpBrew\BuildFinder;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;

class PurgeCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this
            ->setName('purge')
            ->setDescription('Remove installed php version and config files.')
            ->addArgument('builds', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'PHP build(s)', null,
                fn(CompletionInput $i): array => BuildFinder::findInstalledBuilds());
    }
}
