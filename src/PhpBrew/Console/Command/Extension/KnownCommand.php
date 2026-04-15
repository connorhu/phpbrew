<?php

namespace PhpBrew\Console\Command\Extension;

use PhpBrew\Config;
use PhpBrew\Downloader\DownloadFactory;
use PhpBrew\Extension\ExtensionDownloader;
use PhpBrew\ExtensionList;
use PhpBrew\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class KnownCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('extension:known')
            ->setDescription('List known versions of a PHP extension')
            ->setHelp('phpbrew [-dv, -r] ext known extension_name')
            ->addArgument('extension', InputArgument::REQUIRED, 'Extension name', null,
                fn(CompletionInput $i): array => array_filter(
                    scandir(Config::getBuildDir() . '/' . Config::getCurrentPhpName() . '/ext') ?: [],
                    fn($d) => $d !== '.' && $d !== '..'
                ))
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
        $extensionName = $input->getArgument('extension');

        $extensionList = new ExtensionList($logger, $options);
        $provider = $extensionList->exists($extensionName);

        if ($provider) {
            $extensionDownloader = new ExtensionDownloader($logger, $options);
            $versionList = $extensionDownloader->knownReleases($provider);
            $io->writeln('');
            $io->writeln(wordwrap(implode(', ', $versionList), 80, PHP_EOL));
        } else {
            $io->writeln("Can not determine host or unsupported of $extensionName");
        }

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
