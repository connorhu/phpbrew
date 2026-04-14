<?php

namespace PhpBrew\Console\Command;

use Exception;
use PhpBrew\BuildFinder;
use PhpBrew\Config;
use PhpBrew\Logger;
use PhpBrew\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RemoveCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('remove')
            ->setDescription('Remove installed php build.')
            ->addArgument('build', InputArgument::REQUIRED, 'Installed PHP build name', null,
                fn(CompletionInput $i): array => BuildFinder::findInstalledBuilds());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logger = new Logger($output);
        $buildName = $input->getArgument('build');

        $prefix = Config::getVersionInstallPrefix($buildName);
        if (!file_exists($prefix)) {
            throw new Exception("$prefix does not exist.");
        }

        if ($io->confirm("Are you sure you want to delete $buildName?", true)) {
            Utils::recursive_unlink($prefix, $logger);
            $io->writeln("$buildName is removed.  I hope you're not surprised. :)");
        } else {
            $io->writeln('Let me guess, you drunk tonight.');
        }

        return Command::SUCCESS;
    }
}
