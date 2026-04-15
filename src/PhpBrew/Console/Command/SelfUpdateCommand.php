<?php

namespace PhpBrew\Console\Command;

use Exception;
use PhpBrew\Downloader\DownloadFactory;
use PhpBrew\Logger;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SelfUpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('self-update')
            ->setDescription('Self-update, default to master version')
            ->setHelp('phpbrew self-update [branch-name]')
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

        global $argv;
        $script = realpath($argv[0]);

        if (!is_writable($script)) {
            throw new Exception("$script is not writable.");
        }

        $logger->info("Updating phpbrew $script...");
        $url = 'https://github.com/phpbrew/phpbrew/releases/latest/download/phpbrew.phar';

        $downloader = DownloadFactory::getInstance(
            $logger,
            $options,
            [DownloadFactory::METHOD_CURL, DownloadFactory::METHOD_WGET]
        );
        $tempFile = $downloader->download($url);

        if ($tempFile === false) {
            throw new RuntimeException('Update Failed', 1);
        }
        chmod($tempFile, 0755);

        if (!$this->checkRequirements($tempFile)) {
            unlink($tempFile);
            throw new RuntimeException('Update failed');
        }

        if (!rename($tempFile, $script)) {
            throw new RuntimeException('Update Failed', 3);
        }

        $logger->info('Version updated.');
        system($script . ' init');

        return Command::SUCCESS;
    }

    private function checkRequirements(string $binary): bool
    {
        system(escapeshellcmd($binary) . ' --version', $code);
        return $code === 0;
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
