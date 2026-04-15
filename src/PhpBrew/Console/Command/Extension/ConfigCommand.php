<?php

namespace PhpBrew\Console\Command\Extension;

use PhpBrew\Config;
use PhpBrew\Extension\ExtensionFactory;
use PhpBrew\Logger;
use PhpBrew\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('extension:config')
            ->setDescription('Edit extension-specific configuration file')
            ->setHelp('phpbrew ext config [--sapi] [extension name]')
            ->addArgument('extension', InputArgument::REQUIRED, 'Extension name', null,
                fn(CompletionInput $i): array => array_map(
                    fn($path) => basename(basename($path, '.disabled'), '.ini'),
                    glob(Config::getCurrentPhpDir() . '/var/db/*.{ini,disabled}', GLOB_BRACE) ?: []
                ))
            ->addOption('sapi', 's', InputOption::VALUE_REQUIRED, 'Edit extension for SAPI name.');
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
        $sapi = $input->getOption('sapi') ?: null;

        $ext = ExtensionFactory::lookup($extensionName);
        if (!$ext) {
            $io->error("Extension $extensionName not found.");
            return Command::FAILURE;
        }

        $file = $ext->getConfigFilePath($sapi);
        $logger->info("Looking for {$file} file...");
        if (!file_exists($file)) {
            $file .= '.disabled';
            $logger->info("Looking for {$file} file...");
            if (!file_exists($file)) {
                $io->warning("Sorry, I can't find the ini file for the requested extension: \"{$extensionName}\".");
                return Command::FAILURE;
            }
        }

        return Utils::editor($file) === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
