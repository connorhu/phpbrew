<?php

namespace PhpBrew\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommandsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('list-commands')
            ->setDescription('List available phpbrew commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Show banner from Application::getHelp()
        $output->writeln($this->getApplication()->getHelp());
        // Delegate to built-in 'list' command
        return $this->getApplication()->find('list')->run(new ArrayInput([]), $output);
    }
}
