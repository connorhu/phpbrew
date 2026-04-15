<?php

namespace PhpBrew\Console\Command\Extension;

use PhpBrew\Config;
use PhpBrew\Extension\ExtensionFactory;
use PhpBrew\Extension\ExtensionManager;
use PhpBrew\Logger;
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
            ->setName('extension:clean')
            ->setDescription('Clean up the compiled objects in the extension source directory.')
            ->addArgument('extension', InputArgument::REQUIRED, 'Extension name', null,
                fn(CompletionInput $i): array => array_filter(
                    scandir(Config::getBuildDir() . '/' . Config::getCurrentPhpName() . '/ext') ?: [],
                    fn($d) => $d !== '.' && $d !== '..'
                ))
            ->addOption('purge', 'p', InputOption::VALUE_NONE, 'Remove all the source files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!getenv('PHPBREW_PHP')) {
            $io = new SymfonyStyle($input, $output);
            $io->error('PHPBREW_PHP environment variable is not defined. Please switch to a PHP version first.');
            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $logger = new Logger($output);
        $extensionName = $input->getArgument('extension');

        if ($ext = ExtensionFactory::lookup($extensionName)) {
            $io->writeln("Cleaning $extensionName...");
            $manager = new ExtensionManager($logger);

            if ($input->getOption('purge')) {
                $manager->purgeExtension($ext);
            } else {
                $manager->cleanExtension($ext);
            }
            $io->writeln('Done');
        }

        return Command::SUCCESS;
    }
}
