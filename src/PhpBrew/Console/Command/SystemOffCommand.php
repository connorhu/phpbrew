<?php

namespace PhpBrew\Console\Command;

class SystemOffCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this
            ->setName('system-off')
            ->setDescription('Use the currently effective PHP binary internally');
    }
}
