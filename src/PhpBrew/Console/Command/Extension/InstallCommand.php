<?php

namespace PhpBrew\Console\Command\Extension;

use Exception;
use PhpBrew\Config;
use PhpBrew\Downloader\DownloadFactory;
use PhpBrew\Extension\ExtensionDownloader;
use PhpBrew\Extension\ExtensionFactory;
use PhpBrew\Extension\ExtensionManager;
use PhpBrew\ExtensionList;
use PhpBrew\Logger;
use PhpBrew\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('extension:install')
            ->setAliases(['ext-install'])
            ->setDescription('Install PHP extension')
            ->setHelp('phpbrew [-dv, -r] ext install [extension name] [-- [options....]]')
            ->addArgument('ext-name', InputArgument::REQUIRED, 'Extension name', null,
                fn(CompletionInput $i): array => array_filter(
                    scandir(Config::getBuildDir() . '/' . Config::getCurrentPhpName() . '/ext') ?: [],
                    fn($d) => $d !== '.' && $d !== '..'
                ))
            ->addArgument('version', InputArgument::OPTIONAL, 'Extension version', 'stable')
            ->addArgument('options', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Extra configure options')
            ->addOption('pecl', null, InputOption::VALUE_NONE, 'Try to download from PECL even when ext source is bundled with php-src.')
            ->addOption('redownload', null, InputOption::VALUE_NONE, 'Force to redownload extension source even if it is already available.')
            // DownloadFactory options:
            ->addOption('downloader', null, InputOption::VALUE_REQUIRED, 'Use alternative downloader.')
            ->addOption('continue', null, InputOption::VALUE_NONE, 'Continue getting a partially downloaded file.')
            ->addOption('http-proxy', null, InputOption::VALUE_REQUIRED, 'HTTP proxy address')
            ->addOption('http-proxy-auth', null, InputOption::VALUE_REQUIRED, 'HTTP proxy authentication')
            ->addOption('connect-timeout', null, InputOption::VALUE_REQUIRED, 'Connection timeout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!getenv('PHPBREW_PHP')) {
            $io = new SymfonyStyle($input, $output);
            $io->error('PHPBREW_PHP environment variable is not defined. Please switch to a PHP version first.');
            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $logger = new Logger($output);
        $options = $this->buildOptions($input);

        $buildDir = Config::getCurrentBuildDir();
        $extDir = $buildDir . DIRECTORY_SEPARATOR . 'ext';
        if (!is_dir($extDir)) {
            $io->error("Error: The ext directory '$extDir' does not exist.");
            $io->error("It looks like you don't have the PHP source in $buildDir or you didn't extract the tarball.");
            $io->error('Suggestion: Please install at least one PHP with your preferred version and switch to it.');
            return Command::FAILURE;
        }

        $extName = $input->getArgument('ext-name');
        $version = $input->getArgument('version') ?? 'stable';
        $extraOptions = $input->getArgument('options') ?? [];

        if (strtolower($extName) === 'apc' && version_compare(PHP_VERSION, '5.6.0') > 0) {
            $io->warning('apc is not compatible with php 5.6+ versions, install apcu instead.');
        }

        // Detect protocol
        if ((preg_match('#^git://#', $extName) || preg_match('#\.git$#', $extName))
            && !preg_match('#github|bitbucket#', $extName)
        ) {
            $pathinfo = pathinfo($extName);
            $repoUrl = $extName;
            $extName = $pathinfo['filename'];
            $extDir2 = Config::getBuildDir()
                . DIRECTORY_SEPARATOR . Config::getCurrentPhpName()
                . DIRECTORY_SEPARATOR . 'ext'
                . DIRECTORY_SEPARATOR . $extName;

            if (!file_exists($extDir2)) {
                passthru("git clone $repoUrl $extDir2", $ret);
                if ($ret != 0) {
                    $io->error('Clone failed.');
                    return Command::FAILURE;
                }
            }
        }

        // Expand extensionset from config
        $extensions = [];
        if (substr($extName, 0, 1) === '+') {
            $config = Config::getConfigParam('extensions');
            $extName = ltrim($extName, '+');
            if (isset($config[$extName])) {
                foreach ($config[$extName] as $extensionName => $extOptions) {
                    $args = explode(' ', $extOptions);
                    $extensions[$extensionName] = $this->getExtConfig($args);
                }
            } else {
                $io->writeln('Extension set name not found. Have you configured it at the config.yaml file?');
            }
        } else {
            $args = array_merge([$version], $extraOptions);
            $extensions[$extName] = $this->getExtConfig($args);
        }

        $extensionList = new ExtensionList($logger, $options);
        $manager = new ExtensionManager($logger);

        foreach ($extensions as $extensionName => $extConfig) {
            $provider = $extensionList->exists($extensionName);

            if (!$provider) {
                throw new Exception("Could not find provider for $extensionName.");
            }

            $extensionName = $provider->getPackageName();
            $ext = ExtensionFactory::lookupRecursive($extensionName);

            $always_redownload = $options->pecl || $options->redownload || (!$provider->isBundled($extensionName));

            if (!$ext || $always_redownload) {
                if (empty($extConfig->version)) {
                    $extConfig->version = $provider->getDefaultVersion();
                }

                $extensionDownloader = new ExtensionDownloader($logger, $options);
                $extensionDownloader->download($provider, $extConfig->version);

                if ($provider->shouldLookupRecursive()) {
                    $ext = ExtensionFactory::lookupRecursive($extensionName);
                } else {
                    $ext = ExtensionFactory::lookup($extensionName);
                }

                if ($ext) {
                    $extensionDownloader->renameSourceDirectory($ext);
                }
            }

            if (!$ext) {
                throw new Exception("$extensionName not found.");
            }
            $manager->installExtension($ext, $extConfig->options);
        }

        return Command::SUCCESS;
    }

    private function getExtConfig(array $args): object
    {
        $version = null;
        $options = [];

        if (count($args) > 0) {
            $pos = array_search('--', $args);
            if ($pos !== false) {
                $options = array_slice($args, $pos + 1);
            }
            if ($pos === false || $pos == 1) {
                $version = $args[0];
            }
        }

        return (object) ['version' => $version, 'options' => $options];
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
