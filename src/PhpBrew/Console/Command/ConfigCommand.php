<?php

namespace PhpBrew\Console\Command;

use PhpBrew\Config;
use PhpBrew\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('config')
            ->setDescription('Edit your current php.ini in your favorite $EDITOR')
            ->setHelp('phpbrew config [--sapi]')
            ->addOption('sapi', 's', InputOption::VALUE_REQUIRED, 'Edit php.ini for SAPI name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sapi = $input->getOption('sapi') ?: 'cli';
        $file = Config::getVersionEtcPath(Config::getCurrentPhpName()) . '/' . $sapi . '/php.ini';

        return Utils::editor($file) === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
