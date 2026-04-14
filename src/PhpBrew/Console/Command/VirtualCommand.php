<?php

namespace PhpBrew\Console\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for commands that are implemented as shell functions in ~/.phpbrew/bashrc.
 * If called directly from PHP (not via the shell function), throws a RuntimeException.
 *
 * @codeCoverageIgnore
 */
abstract class VirtualCommand extends Command
{
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        throw new RuntimeException(
            "If you see this, the ~/.phpbrew/bashrc script is not loaded in your shell.\n"
            . "Please run: source ~/.phpbrew/bashrc"
        );
    }
}
