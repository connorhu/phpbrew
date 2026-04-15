<?php

namespace PhpBrew\Console\Command\Fpm;

use PhpBrew\Console\Command\VirtualCommand;

class StartCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this->setName('fpm:start')
            ->setDescription('Start FPM server');
    }
}
