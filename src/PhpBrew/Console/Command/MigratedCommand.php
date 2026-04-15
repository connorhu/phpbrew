<?php

namespace PhpBrew\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigratedCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrated')
            ->setDescription('This command is migrated');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(<<<HELP
- `phpbrew install-ext` command is now moved to `phpbrew extension:install`
- `phpbrew enable` command is now moved to `phpbrew extension:enable`
HELP
        );
        return Command::SUCCESS;
    }
}
