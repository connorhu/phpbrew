<?php

namespace PhpBrew\Console\Command\Extension;

use PhpBrew\Config;
use PhpBrew\Extension\ExtensionManager;
use PhpBrew\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EnableCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('extension:enable')
            ->setDescription('Enable PHP extension')
            ->setHelp('phpbrew ext enable [extension name]')
            ->addArgument('extension', InputArgument::REQUIRED, 'Extension name', null,
                fn(CompletionInput $i): array => array_map(
                    fn($path) => basename($path, '.ini.disabled'),
                    glob(Config::getCurrentPhpDir() . '/var/db/*.ini.disabled') ?: []
                ))
            ->addOption('sapi', 's', InputOption::VALUE_REQUIRED, 'Enable extension for SAPI name.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (!getenv('PHPBREW_PHP')) {
            $io = new SymfonyStyle($input, $output);
            $io->error(
                "Error: PHPBREW_PHP environment variable is not defined.\n"
                . "  This extension command requires you specify a PHP version from your build list.\n"
                . "  And it looks like you haven't switched to a version from the builds that were built with PHPBrew.\n"
                . 'Suggestion: Please install at least one PHP with your preferred version and switch to it.'
            );
            // We can't throw here without stopping execution; set a flag and check in execute
            // Actually we CAN exit: throw new \RuntimeException to abort
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!getenv('PHPBREW_PHP')) {
            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $logger = new Logger($output);
        $extensionName = $input->getArgument('extension');
        $sapi = $input->getOption('sapi') ?: null;

        $manager = new ExtensionManager($logger);
        $manager->enable($extensionName, $sapi);

        return Command::SUCCESS;
    }
}
