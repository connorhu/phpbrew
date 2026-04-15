<?php

namespace PhpBrew\Console\Command;

use Exception;
use PhpBrew\Config;
use PhpBrew\Distribution\DistributionUrlPolicy;
use PhpBrew\Downloader\DownloadFactory;
use PhpBrew\Logger;
use PhpBrew\ReleaseList;
use PhpBrew\Tasks\DownloadTask;
use PhpBrew\Tasks\PrepareDirectoryTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DownloadCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('download')
            ->setDescription('Download php')
            ->setHelp('phpbrew download [php-version]')
            ->addArgument('version', InputArgument::REQUIRED, 'PHP version to download', null,
                fn(CompletionInput $i): array => array_keys(ReleaseList::getReadyInstance()->getReleases()))
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force extraction')
            ->addOption('old', null, InputOption::VALUE_NONE, 'enable old phps (less than 5.3)')
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

        $version = preg_replace('/^php-/', '', $input->getArgument('version'));
        $releaseList = ReleaseList::getReadyInstance($options);
        $versionInfo = $releaseList->getVersion($version);
        if (!$versionInfo) {
            $io->error("Version $version not found.");
            return Command::FAILURE;
        }
        $version = $versionInfo['version'];
        $distUrlPolicy = new DistributionUrlPolicy();
        $distUrl = $distUrlPolicy->buildUrl($version, $versionInfo['filename'], $versionInfo['museum']);

        $prepare = new PrepareDirectoryTask($logger, $options);
        $prepare->run();

        $distFileDir = Config::getDistFileDir();

        $download = new DownloadTask($logger, $options);
        $algo = 'md5';
        $hash = null;
        if (isset($versionInfo['sha256'])) {
            $algo = 'sha256';
            $hash = $versionInfo['sha256'];
        } elseif (isset($versionInfo['md5'])) {
            $hash = $versionInfo['md5'];
        }
        $targetDir = $download->download($distUrl, $distFileDir, $algo, $hash);

        if (!file_exists($targetDir)) {
            $io->error('Download failed.');
            return Command::FAILURE;
        }
        $io->writeln("Done, please look at: $targetDir");

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
