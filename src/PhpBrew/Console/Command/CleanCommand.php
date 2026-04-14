<?php

namespace PhpBrew\Console\Command;

use PhpBrew\Build;
use PhpBrew\BuildFinder;
use PhpBrew\Config;
use PhpBrew\Logger;
use PhpBrew\Tasks\MakeTask;
use PhpBrew\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CleanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('clean')
            ->setDescription('Clean up the source directory of a PHP distribution')
            ->setHelp('phpbrew clean [-a|--all] [php-version]')
            ->addArgument('version', InputArgument::REQUIRED, 'PHP build version', null,
                fn(CompletionInput $i): array => BuildFinder::findInstalledBuilds())
            ->addOption('all', 'a', InputOption::VALUE_NONE,
                'Remove all the files in the source directory of the PHP distribution.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logger = new Logger($output);
        $version = $input->getArgument('version');
        $buildDir = Config::getBuildDir() . DIRECTORY_SEPARATOR . $version;

        if ($input->getOption('all')) {
            if (!file_exists($buildDir)) {
                $io->writeln('Source directory ' . $buildDir . ' does not exist.');
            } else {
                $io->writeln('Source directory ' . $buildDir . ' found, deleting...');
                Utils::recursive_unlink($buildDir, $logger);
            }
        } else {
            $make = new MakeTask($logger);
            $make->setQuiet();
            $build = new Build($version);
            $build->setSourceDirectory($buildDir);
            if ($make->clean($build)) {
                $io->writeln('Distribution is cleaned up. Woof! ');
            }
        }

        return Command::SUCCESS;
    }
}
