<?php

namespace PhpBrew\Console\Command;

class SwitchOffCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this
            ->setName('switch-off')
            ->setDescription('Definitely go back to the system php');
    }
}
