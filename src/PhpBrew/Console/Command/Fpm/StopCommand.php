<?php

namespace PhpBrew\Console\Command\Fpm;

use PhpBrew\Console\Command\VirtualCommand;

class StopCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this->setName('fpm:stop')
            ->setDescription('Stop FPM server');
    }
}
