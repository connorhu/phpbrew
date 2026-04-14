<?php

namespace PhpBrew\Console\Command;

use PhpBrew\BuildFinder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('system')
            ->setDescription('Get or set the internally used PHP binary')
            ->addArgument('version', InputArgument::OPTIONAL, 'PHP version', null,
                fn(CompletionInput $i): array => BuildFinder::findInstalledBuilds());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = getenv('PHPBREW_SYSTEM_PHP');
        if ($path !== false && $path !== '') {
            $output->writeln($path);
        }
        return Command::SUCCESS;
    }
}
