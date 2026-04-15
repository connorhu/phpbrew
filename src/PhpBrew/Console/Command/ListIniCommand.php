<?php

namespace PhpBrew\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListIniCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('list-ini')
            ->setDescription('List loaded ini config files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->warning(
            'The list-ini command is deprecated and will be removed in the future.' . PHP_EOL
            . 'Please use `php --ini` instead.'
        );

        if ($filelist = php_ini_scanned_files()) {
            $output->writeln('Loaded ini files:');
            if (strlen($filelist) > 0) {
                $files = explode(',', $filelist);
                foreach ($files as $file) {
                    $output->writeln(' - ' . trim($file));
                }
            }
        }

        return Command::SUCCESS;
    }
}
