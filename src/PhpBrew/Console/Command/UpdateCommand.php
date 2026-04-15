<?php

namespace PhpBrew\Console\Command;

use PhpBrew\Logger;
use PhpBrew\Tasks\FetchReleaseListTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Update PHP release source file')
            ->addOption('old', 'o', InputOption::VALUE_NONE, 'List versions older than PHP 7.0')
            // DownloadFactory options:
            ->addOption('downloader', null, InputOption::VALUE_REQUIRED, 'Use alternative downloader.')
            ->addOption('continue', null, InputOption::VALUE_NONE, 'Continue getting a partially downloaded file.')
            ->addOption('http-proxy', null, InputOption::VALUE_REQUIRED, 'HTTP proxy address')
            ->addOption('http-proxy-auth', null, InputOption::VALUE_REQUIRED, 'HTTP proxy authentication')
            ->addOption('connect-timeout', null, InputOption::VALUE_REQUIRED, 'Connection timeout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logger = new Logger($output);
        $options = $this->buildOptions($input);

        $fetchTask = new FetchReleaseListTask($logger, $options);
        $releases = $fetchTask->fetch();

        foreach ($releases as $majorVersion => $versions) {
            if (version_compare($majorVersion, '5.2', '<=')) {
                continue;
            }
            $io->writeln("<comment>{$majorVersion}:</comment> " . count(array_keys($versions)) . ' releases');
        }
        $io->writeln('===> Done');

        return Command::SUCCESS;
    }

    private function buildOptions(InputInterface $input): object
    {
        return new class($input) {
            public function __construct(private InputInterface $input) {}
            public function __get(string $name): mixed
            {
                if ($this->input->hasOption($name)) return $this->input->getOption($name);
                return null;
            }
            public function __isset(string $name): bool
            {
                if (!$this->input->hasOption($name)) return false;
                $val = $this->input->getOption($name);
                return $val !== null && $val !== false;
            }
        };
    }
}
