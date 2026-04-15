<?php

namespace PhpBrew\Console\Command\Fpm;

use PhpBrew\Console\Command\VirtualCommand;

class RestartCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this->setName('fpm:restart')
            ->setDescription('Restart FPM server');
    }
}
