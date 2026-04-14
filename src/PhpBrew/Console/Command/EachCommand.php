<?php

namespace PhpBrew\Console\Command;

class EachCommand extends VirtualCommand
{
    protected function configure(): void
    {
        $this
            ->setName('each')
            ->setDescription('Iterate and run a given shell command over all php versions managed by PHPBrew.');
    }
}
