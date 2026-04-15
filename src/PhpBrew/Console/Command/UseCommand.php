<?php

namespace PhpBrew\Console\Command;

use PhpBrew\BuildFinder;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;

class UseCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this
            ->setName('use')
            ->setDescription('Use php, switch version temporarily')
            ->addArgument('version', InputArgument::REQUIRED, 'PHP version', null,
                fn(CompletionInput $i): array => BuildFinder::findInstalledVersions());
    }
}
