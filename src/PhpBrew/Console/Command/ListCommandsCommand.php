<?php

namespace PhpBrew\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommandsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('list-commands')
            ->setAliases(['commands'])
            ->setDescription('List available phpbrew commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->getApplication()->getHelp());
        $output->writeln('');

        $commands = $this->getApplication()->all();
        ksort($commands);

        $table = new Table($output);
        $table->setStyle('compact');

        foreach ($commands as $name => $command) {
            if ($command->isHidden() || $name !== $command->getName()) {
                continue;
            }
            $table->addRow([
                sprintf('  <info>%s</info>', $name),
                $command->getDescription(),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
