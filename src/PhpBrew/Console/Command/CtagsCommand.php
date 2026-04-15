<?php

namespace PhpBrew\Console\Command;

use PhpBrew\BuildFinder;
use PhpBrew\CommandBuilder;
use PhpBrew\Config;
use PhpBrew\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CtagsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ctags')
            ->setDescription('Run ctags at current php source dir for extension development.')
            ->addArgument('version', InputArgument::OPTIONAL, 'PHP build version', null,
                fn(CompletionInput $i): array => BuildFinder::findInstalledBuilds());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logger = new Logger($output);
        $versionName = $input->getArgument('version');

        if ($versionName) {
            $sourceDir = Config::getBuildDir() . DIRECTORY_SEPARATOR . $versionName;
        } else {
            if (!getenv('PHPBREW_PHP')) {
                $io->error(<<<EOF
Error: PHPBREW_PHP environment variable is not defined.
  This command requires you specify a PHP version from your build list.
  And it looks like you haven't switched to a version from the builds that were built with PHPBrew.
Suggestion: Please install at least one PHP with your preferred version and switch to it.
EOF
                );
                return Command::FAILURE;
            }
            $sourceDir = Config::getCurrentBuildDir();
        }

        if (!file_exists($sourceDir)) {
            $io->error("$sourceDir does not exist.");
            return Command::FAILURE;
        }

        $logger->info('Scanning ' . $sourceDir);

        $cmd = new CommandBuilder('ctags');
        $cmd->arg('-R');
        $cmd->arg('-a');
        $cmd->arg('-h');
        $cmd->arg('.c.h.cpp');
        $cmd->arg($sourceDir . DIRECTORY_SEPARATOR . 'main');
        $cmd->arg($sourceDir . DIRECTORY_SEPARATOR . 'ext');
        $cmd->arg($sourceDir . DIRECTORY_SEPARATOR . 'Zend');

        $output->writeln($cmd->__toString(), OutputInterface::VERBOSITY_DEBUG);
        $cmd->execute();

        $logger->info('Done');

        return Command::SUCCESS;
    }
}
