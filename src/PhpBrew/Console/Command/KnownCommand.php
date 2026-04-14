<?php

namespace PhpBrew\Console\Command;

use PhpBrew\Config;
use PhpBrew\Logger;
use PhpBrew\ReleaseList;
use PhpBrew\Tasks\FetchReleaseListTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class KnownCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('known')
            ->setDescription('List known PHP versions')
            ->addOption('more', 'm', InputOption::VALUE_NONE, 'Show more older versions')
            ->addOption('old', 'o', InputOption::VALUE_NONE, 'List old phps (less than 5.3)')
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Update release list')
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

        $releaseList = new ReleaseList();

        if (!$releaseList->foundLocalReleaseList() || $input->getOption('update') || $input->getOption('old')) {
            $options = $this->buildOptions($input);
            $fetchTask = new FetchReleaseListTask($logger, $options);
            $releases = $fetchTask->fetch();
        } else {
            $io->writeln(sprintf(
                'Read local release list (last update: %s UTC).',
                gmdate('Y-m-d H:i:s', filectime(Config::getPHPReleaseListPath()))
            ));
            $releases = $releaseList->loadLocalReleaseList();
            $io->writeln('You can run `phpbrew update` or `phpbrew known --update` to get a newer release list.');
        }

        foreach ($releases as $majorVersion => $versions) {
            if (version_compare($majorVersion, '5.2', 'le') && !$input->getOption('old')) {
                continue;
            }
            $versionList = array_keys($versions);
            if (!$input->getOption('more')) {
                array_splice($versionList, 8);
            }
            $io->writeln(
                "<comment>{$majorVersion}:</comment> "
                . wordwrap(implode(', ', $versionList), 80, PHP_EOL . str_repeat(' ', 5))
                . (!$input->getOption('more') ? ' ...' : '')
            );
        }

        if ($input->getOption('old')) {
            $io->warning('PHPBrew needs PHP 5.3 or above to run. build/switch to versions below 5.3 at your own risk.');
        }

        return Command::SUCCESS;
    }

    /**
     * Build a simple options object compatible with Tasks that expect GetOptionKit\OptionResult-like access.
     * Returns an anonymous object with magic __get for option access.
     */
    private function buildOptions(InputInterface $input): object
    {
        return new class($input) {
            public function __construct(private InputInterface $input) {}
            public function __get(string $name): mixed
            {
                if ($this->input->hasOption($name)) {
                    return $this->input->getOption($name);
                }
                return null;
            }
            public function __isset(string $name): bool
            {
                return $this->input->hasOption($name) && $this->input->getOption($name) !== null && $this->input->getOption($name) !== false;
            }
        };
    }
}
