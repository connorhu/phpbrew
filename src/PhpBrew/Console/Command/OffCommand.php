<?php

namespace PhpBrew\Console\Command;

class OffCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this
            ->setName('off')
            ->setDescription('Temporarily go back to the system php');
    }
}
