<?php

namespace PhpBrew\Console\Command;

use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;

class CdCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this
            ->setName('cd')
            ->setDescription('Change to directories')
            ->setHelp('phpbrew cd [var|etc|build|dist]')
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory type', null,
                fn(CompletionInput $i): array => ['var', 'etc', 'build', 'dist']);
    }
}
