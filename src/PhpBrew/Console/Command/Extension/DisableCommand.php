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

class DisableCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('extension:disable')
            ->setDescription('Disable PHP extension')
            ->setHelp('phpbrew ext disable [extension name]')
            ->addArgument('extension', InputArgument::REQUIRED, 'Extension name', null,
                fn(CompletionInput $i): array => array_map(
                    fn($path) => basename($path, '.ini'),
                    glob(Config::getCurrentPhpDir() . '/var/db/*.ini') ?: []
                ))
            ->addOption('sapi', 's', InputOption::VALUE_REQUIRED, 'Disable extension for SAPI name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!getenv('PHPBREW_PHP')) {
            $io = new SymfonyStyle($input, $output);
            $io->error('PHPBREW_PHP environment variable is not defined. Please switch to a PHP version first.');
            return Command::FAILURE;
        }

        $logger = new Logger($output);
        $extensionName = $input->getArgument('extension');
        $sapi = $input->getOption('sapi') ?: null;

        $manager = new ExtensionManager($logger);
        $manager->disable($extensionName, $sapi);

        return Command::SUCCESS;
    }
}
