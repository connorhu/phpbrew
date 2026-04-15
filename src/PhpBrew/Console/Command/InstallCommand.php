<?php

namespace PhpBrew\Console\Command;

use Exception;
use PhpBrew\Build;
use PhpBrew\Config;
use PhpBrew\ConfigureParameters;
use PhpBrew\Distribution\DistributionUrlPolicy;
use PhpBrew\Logger;
use PhpBrew\ReleaseList;
use PhpBrew\Tasks\AfterConfigureTask;
use PhpBrew\Tasks\BeforeConfigureTask;
use PhpBrew\Tasks\BuildTask;
use PhpBrew\Tasks\ConfigureTask;
use PhpBrew\Tasks\DownloadTask;
use PhpBrew\Tasks\DSymTask;
use PhpBrew\Tasks\ExtractTask;
use PhpBrew\Tasks\InstallTask;
use PhpBrew\Tasks\MakeTask;
use PhpBrew\Tasks\PrepareDirectoryTask;
use PhpBrew\Tasks\TestTask;
use PhpBrew\VariantBuilder;
use PhpBrew\VariantParser;
use PhpBrew\VersionDslParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('install')
            ->setDescription('Install php')
            ->setAliases(['i', 'ins'])
            ->setHelp('phpbrew install [php-version] ([+variant...])')
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'PHP version to install',
                null,
                function (CompletionInput $input): array {
                    $releases = ReleaseList::getReadyInstance()->getReleases();
                    $versions = [];
                    foreach ($releases as $majorVersions) {
                        foreach (array_keys($majorVersions) as $v) {
                            $versions[] = $v;
                        }
                    }
                    $versions[] = 'latest';
                    $versions[] = 'next';
                    return $versions;
                }
            )
            ->addArgument(
                'variants',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Variants to enable/disable (e.g. +mysql -apxs2)',
                [],
                function (CompletionInput $input): array {
                    $builder = new VariantBuilder();
                    $list = $builder->getVariantNames();
                    sort($list);
                    return array_map(fn($n) => '+' . $n, $list);
                }
            )
            ->addOption('test', null, InputOption::VALUE_NONE, 'Run tests after the installation.')
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'The name of the installation. '
                . 'By default the installed path is equal to the release version name (php-5.x.x), '
                . 'however you can specify a custom name instead of the default `php-5.x.x`. For example, `myphp-5.3.2-dbg`'
            )
            ->addOption('post-clean', null, InputOption::VALUE_NONE, 'Run make clean after the installation.')
            ->addOption(
                'production',
                null,
                InputOption::VALUE_NONE,
                'Use production configuration file. this installer will copy the php-production.ini into the etc directory.'
            )
            ->addOption(
                'build-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the build directory. '
                . 'the distribution tarball will be extracted to the directory you specified '
                . 'instead of $PHPBREW_ROOT/build/{name}.'
            )
            ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Specify PHPBrew root instead of PHPBREW_ROOT')
            ->addOption('home', null, InputOption::VALUE_REQUIRED, 'Specify PHPBrew home instead of PHPBREW_HOME')
            ->addOption('no-config-cache', null, InputOption::VALUE_NONE, 'Do not use config.cache for configure script.')
            ->addOption(
                'no-clean',
                null,
                InputOption::VALUE_NONE,
                'Do not clean previously compiled objects before building PHP. '
                . 'By default phpbrew will run `make clean` before running the configure script '
                . 'to ensure everything is cleaned up.'
            )
            ->addOption('no-patch', null, InputOption::VALUE_NONE, 'Do not apply any patch')
            ->addOption('no-configure', null, InputOption::VALUE_NONE, 'Do not run configure script')
            ->addOption('no-install', null, InputOption::VALUE_NONE, 'Do not install, just run build the target')
            ->addOption(
                'nice',
                null,
                InputOption::VALUE_REQUIRED,
                'Runs build processes at an altered scheduling priority. '
                . 'The priority can be adjusted over a range of -20 (the highest) to 20 (the lowest).'
            )
            ->addOption(
                'patch',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Apply patch before build.'
            )
            ->addOption('old', null, InputOption::VALUE_NONE, 'Install phpbrew incompatible phps (< 5.3)')
            ->addOption(
                'user-config',
                null,
                InputOption::VALUE_NONE,
                'Allow users create their own config file (php.ini or extension config init files)'
            )
            // DownloadFactory options:
            ->addOption('downloader', null, InputOption::VALUE_REQUIRED, 'Use alternative downloader.')
            ->addOption('continue', null, InputOption::VALUE_NONE, 'Continue getting a partially downloaded file.')
            ->addOption('http-proxy', null, InputOption::VALUE_REQUIRED, 'HTTP proxy address')
            ->addOption('http-proxy-auth', null, InputOption::VALUE_REQUIRED, 'HTTP proxy authentication')
            ->addOption('connect-timeout', null, InputOption::VALUE_REQUIRED, 'Connection timeout')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force the installation (redownloads source).'
            )
            ->addOption('dryrun', 'd', InputOption::VALUE_NONE, 'Do not build, but run through all the tasks.')
            ->addOption(
                'like',
                null,
                InputOption::VALUE_REQUIRED,
                'Inherit variants from an existing build. '
                . 'This option would require an existing build directory from the {version}.'
            )
            ->addOption(
                'jobs',
                'j',
                InputOption::VALUE_REQUIRED,
                'Specifies the number of jobs to run build simultaneously (make -jN).'
            )
            ->addOption('stdout', null, InputOption::VALUE_NONE, 'Outputs install logs to stdout.')
            ->addOption('sudo', null, InputOption::VALUE_NONE, 'sudo to run install command.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new Logger($output);

        if (extension_loaded('posix') && posix_getuid() === 0) {
            $logger->warn(
                "*WARNING* You're running phpbrew as root/sudo. Unless you're going to install "
                . "system-wide phpbrew, this might cause problems."
            );
            sleep(3);
        }

        $distUrl = null;
        $versionInfo = array();
        $options = $this->buildOptions($input);
        $releaseList = ReleaseList::getReadyInstance($options);
        $versionDslParser = new VersionDslParser();
        $clean = new MakeTask($logger, $options);
        $clean->setQuiet();

        $version = $input->getArgument('version');

        if ($root = $input->getOption('root')) {
            Config::setPhpbrewRoot($root);
        }
        if ($home = $input->getOption('home')) {
            Config::setPhpbrewHome($home);
        }

        if ('latest' === strtolower($version)) {
            $version = $releaseList->getLatestVersion();
        }

        // this should point to master or the latest version branch yet to be released
        if ('next' === strtolower($version)) {
            $version = 'github.com/php/php-src:master';
        }

        if ($info = $versionDslParser->parse($version)) {
            $version = $info['version'];
            $distUrl = $info['url'];

            // re-download when installing not from a tag
            $options->force = empty($info['is_tag']);
        } else {
            // TODO ↓ clean later ↓ d.d.d versions should be part of the DSL too
            $version = preg_replace('/^php-/', '', $version);
            $versionInfo = $releaseList->getVersion($version);
            if (!$versionInfo) {
                throw new Exception("Version $version not found.");
            }
            $version = $versionInfo['version'];

            $distUrlPolicy = new DistributionUrlPolicy();
            $distUrl = $distUrlPolicy->buildUrl($version, $versionInfo['filename'], $versionInfo['museum']);
        }

        // get options and variants for building php
        // the variants argument array may include semantic options like 'as', 'like', 'using'
        $args = $input->getArgument('variants') ?: [];

        // Symfony Console strips the -- separator before passing arguments.
        // Re-insert it before the first configure option (starting with --)
        // so VariantParser can correctly separate variants from extra configure flags.
        foreach ($args as $i => $arg) {
            if (str_starts_with($arg, '--')) {
                array_splice($args, $i, 0, ['--']);
                break;
            }
        }

        $semanticOptions = $this->parseSemanticOptions($args);
        $buildAs = isset($semanticOptions['as']) ? $semanticOptions['as'] : $input->getOption('name');
        $buildLike = isset($semanticOptions['like']) ? $semanticOptions['like'] : $input->getOption('like');

        // convert patch to realpath
        if ($input->getOption('patch')) {
            $patchPaths = array();
            foreach ($input->getOption('patch') as $patch) {
                $patchPath = realpath($patch);
                if ($patchPath !== false) {
                    $patchPaths[(string) $patch] = $patchPath;
                }
            }
            // rewrite patch paths in the mutable options object
            $options->patch = $patchPaths;
        }

        // Initialize the build object, contains the information to build php.
        $build = new Build($version, $buildAs);

        $installPrefix = Config::getInstallPrefix() . DIRECTORY_SEPARATOR . $build->getName();
        if (!file_exists($installPrefix)) {
            if (!mkdir($installPrefix, 0755, true) && !is_dir($installPrefix)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $installPrefix));
            }
        }
        $build->setInstallPrefix($installPrefix);

        // find inherited variants
        if ($buildLike) {
            if ($parentBuild = Build::findByName($buildLike)) {
                $logger->info("===> Loading build settings from $buildLike");
                $build->loadVariantInfo($parentBuild->settings->toArray());
            }
        }

        $msg = "===> phpbrew will now build {$build->getVersion()}";
        if ($buildLike) {
            $msg .= ' using variants from ' . $buildLike;
        }
        if (isset($semanticOptions['using'])) {
            $msg .= ' plus custom variants: ' . implode(', ', $semanticOptions['using']);
            $args = array_merge($args, $semanticOptions['using']);
        }
        if ($buildAs) {
            $msg .= ' as ' . $buildAs;
        }
        $logger->info($msg);

        if (!empty($args)) {
            $logger->debug("---> Parsing variants from command arguments '" . implode(' ', $args) . "'");
        }

        // ['extra_options'] => the extra options to be passed to ./configure command
        // ['enabled_variants'] => enabled variants
        // ['disabled_variants'] => disabled variants
        $variantInfo = VariantParser::parseCommandArguments($args);
        $build->loadVariantInfo($variantInfo); // load again

        // assume +default variant if no build config is given
        if (!$variantInfo['enabled_variants']) {
            $build->settings->enableVariant('default');
            $logger->notice(
                "You haven't enabled any variants. The default variant will be enabled: "
            );
            $builder = new VariantBuilder();
            $logger->notice('[' . implode(', ', $builder->virtualVariants['default']) . ']');
            $logger->notice("Please run 'phpbrew variants' for more information." . PHP_EOL);
        }

        if (preg_match('/5\.3\./', $version)) {
            $logger->notice('PHP 5.3 requires +intl, enabled by default.');
            $build->enableVariant('intl');
        }

        // always add +xml by default unless --without-pear is present
        // TODO: This can be done by "-pear"
        if (!in_array('--without-pear', $variantInfo['extra_options'])) {
            $build->enableVariant('xml');
        }

        $logger->info('===> Loading and resolving variants...');
        $build->loadVariantInfo($variantInfo);

        $prepareTask = new PrepareDirectoryTask($logger, $options);
        $prepareTask->run($build);

        // Move to build directory, because we are going to download distribution.
        $buildDir = $input->getOption('build-dir') ?: Config::getBuildDir();
        if (!file_exists($buildDir)) {
            if (!mkdir($buildDir, 0755, true) && !is_dir($buildDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $buildDir));
            }
        }

        $parameters = new ConfigureParameters();

        if (!$input->getOption('no-config-cache')) {
            $parameters = $parameters->withOption('--cache-file', Config::getCacheDir() . '/config.cache');
        }

        $prefix = $build->getInstallPrefix();

        $parameters = $parameters->withOption('--prefix', $prefix);

        // Options for specific versions
        // todo: extract to BuildPlan class: PHP53 BuildPlan, PHP54 BuildPlan, PHP55 BuildPlan ?
        if ($build->compareVersion('5.4') == -1) {
            // copied from https://github.com/Homebrew/homebrew-php/blob/master/Formula/php53.rb
            $parameters = $parameters
                ->withOption('--enable-sqlite-utf8')
                ->withOption('--enable-zend-multibyte');
        } elseif ($build->compareVersion('5.6') == -1) {
            $parameters = $parameters->withOption('--enable-zend-signals');
        }

        $variantBuilder = new VariantBuilder();
        $parameters = $variantBuilder->build($build, $parameters);

        $pkgConfigPaths = getenv('PKG_CONFIG_PATH');
        if ($pkgConfigPaths !== '' && $pkgConfigPaths !== false) {
            foreach (explode(PATH_SEPARATOR, $pkgConfigPaths) as $pkgConfigPath) {
                $parameters = $parameters->withPkgConfigPath($pkgConfigPath);
            }
        }

        $distFileDir = Config::getDistFileDir();

        $downloadTask = new DownloadTask($logger, $options);
        $algo = 'md5';
        $hash = null;
        if (isset($versionInfo['sha256'])) {
            $algo = 'sha256';
            $hash = $versionInfo['sha256'];
        } elseif (isset($versionInfo['md5'])) {
            $algo = 'md5';
            $hash = $versionInfo['md5'];
        }
        $targetFilePath = $downloadTask->download($distUrl, $distFileDir, $algo, $hash);
        if (!file_exists($targetFilePath)) {
            throw new \PhpBrew\Exception\SystemCommandException(
                "Download failed, $targetFilePath does not exist.",
                $build
            );
        }
        unset($downloadTask);

        $extractTask = new ExtractTask($logger, $options);
        $targetDir = $extractTask->extract($build, $targetFilePath, $buildDir);
        if (!file_exists($targetDir)) {
            throw new \PhpBrew\Exception\SystemCommandException(
                "Extract failed, $targetDir does not exist.",
                $build
            );
        }
        unset($extractTask);

        // Update build source directory
        $logger->debug('Source Directory: ' . realpath($targetDir));
        $build->setSourceDirectory($targetDir);

        if (!$input->getOption('no-clean') && file_exists($targetDir . DIRECTORY_SEPARATOR . 'Makefile')) {
            if ($build->isEnabledVariant('apxs2') || $build->isEnabledVariant('fpm')) {
                $logger->info('You want to build several variants. No clean is not available.');
            } else {
                $logger->info(
                    'Found existing Makefile, running make clean to ensure everything will be rebuilt.'
                );
                $logger->info(
                    "You can append --no-clean option after the install command if you don't want to rebuild."
                );
                $clean->clean($build);
            }
        }

        // Change directory to the downloaded source directory.
        chdir($targetDir);
        // Write variants info.
        $variantInfoFile = $build->getInstallPrefix() . DIRECTORY_SEPARATOR . 'phpbrew.variants';
        $logger->debug("Writing variant info to $variantInfoFile");
        if (false === $build->writeVariantInfoFile($variantInfoFile)) {
            $logger->warn("Can't store variant info.");
        }

        $targetPaths = array();
        if (!$input->getOption('no-configure')) {
            $configureOptions = $parameters->getOptions();
            // https://gist.github.com/tvlooy/953a7c0658e70b573ab4
            $sapis = array('cli' => array(
                'enable' => array('--enable-cli'),
                'disable' => array('--disable-cli')
            ));

            if ($build->isEnabledVariant('apxs2')) {
                $sapis['apache2'] = array(
                    'enable' => array('--with-apxs2'),
                    'disable' => array(),
                );
            }
            if ($build->isEnabledVariant('fpm')) {
                $addedOptions = array('--enable-fpm');
                if (PHP_OS === 'Linux') {
                    $addedOptions[] = '--with-fpm-systemd';
                }
                $sapis['fpm'] = array(
                    'enable' => $addedOptions,
                    'disable' => array(),
                );
            }

            foreach ($sapis as $sapi => $enableDisable) {
                $logger->info('Running make clean to ensure everything will be rebuilt.');
                $clean->clean($build);

                $addedOptions = $removedOptions = array();

                foreach ($sapis as $sapiName => $sapiEnableDisable) {
                    list($addedOptions, $removedOptions) = $this->enableDisable(
                        $parameters,
                        $sapiEnableDisable,
                        $sapiName === $sapi,
                        $configureOptions
                    );
                }

                $parameters = $parameters
                    ->withOption('--with-config-file-path', $prefix . '/etc/' . $sapi)
                    ->withOption('--with-config-file-scan-dir', $prefix . '/var/db/' . $sapi);

                $this->build($build, $parameters, $logger, $options);

                foreach ($addedOptions as $addedOption) {
                    $parameters = $parameters->withoutOption($addedOption);
                }
                foreach ($removedOptions as $removedOption) {
                    $parameters = $parameters->withOption(
                        $removedOption,
                        array_key_exists($removedOption, $configureOptions) ? $configureOptions[$removedOption] : null
                    );
                }

                {
                    $buildTask = new BuildTask($logger, $options);
                    $buildTask->run($build);
                    unset($buildTask); // trigger __destruct
                }

                if ($input->getOption('test')) {
                    $testTask = new TestTask($logger, $options);
                    $testTask->run($build);
                    unset($testTask); // trigger __destruct
                }

                if (!$input->getOption('no-install')) {
                    $installTask = new InstallTask($logger, $options);
                    $installTask->install($build);
                    unset($installTask); // trigger __destruct
                }

                if ($input->getOption('post-clean')) {
                    $clean->clean($build);
                }

                /* POST INSTALLATION **/
                {
                    $dsym = new DSymTask($logger, $options);
                    $dsym->patch($build, $options);
                }

                $etcDirectory = $build->getEtcDirectory();

                if ('fpm' === $sapi) {
                    // copy php-fpm config
                    $logger->info('---> Creating php-fpm.conf');
                    $fpmUnixSocket = $build->getInstallPrefix() . "/var/run/php-fpm.sock";
                    $this->installAs("$etcDirectory/php-fpm.conf.default", "$etcDirectory/php-fpm.conf", false, $logger);
                    $this->installAs(
                        "$etcDirectory/php-fpm.d/www.conf.default",
                        "$etcDirectory/php-fpm.d/www.conf",
                        false,
                        $logger
                    );

                    $patchingFiles = array("$etcDirectory/php-fpm.d/www.conf", "$etcDirectory/php-fpm.conf");
                    foreach ($patchingFiles as $patchingFile) {
                        if (file_exists($patchingFile)) {
                            $logger->info("---> Found $patchingFile");
                            // Patch pool listen unix
                            // The original config was below:
                            //
                            // listen = 127.0.0.1:9000
                            //
                            // See http://php.net/manual/en/install.fpm.configuration.php for more details
                            $ini = file_get_contents($patchingFile);
                            $logger->info("---> Patching default fpm pool listen path to $fpmUnixSocket");
                            $ini = preg_replace('/^listen = .*$/m', "listen = $fpmUnixSocket" . PHP_EOL, $ini);
                            file_put_contents($patchingFile, $ini);
                            break;
                        }
                    }
                }

                $logger->info('---> Creating php.ini');
                $phpConfigPath = $build->getSourceDirectory()
                    . DIRECTORY_SEPARATOR . ($input->getOption('production') ? 'php.ini-production' : 'php.ini-development');
                $logger->info("---> Copying $phpConfigPath ");

                if (file_exists($phpConfigPath) && !$input->getOption('dryrun')) {
                    $targetConfigPath = $this->makeIniFile($build, $etcDirectory, $phpConfigPath, $sapi, $logger, $options);
                    $targetPaths[] = '    ' . $targetConfigPath;
                }
            }
        }

        if ($build->isEnabledVariant('pear')) {
            $logger->info('Initializing pear config...');
            $pearHome = Config::getHome();

            @mkdir("$pearHome/tmp/pear/temp", 0755, true);
            @mkdir("$pearHome/tmp/pear/cache_dir", 0755, true);
            @mkdir("$pearHome/tmp/pear/download_dir", 0755, true);

            system("pear config-set temp_dir $pearHome/tmp/pear/temp");
            system("pear config-set cache_dir $pearHome/tmp/pear/cache_dir");
            system("pear config-set download_dir $pearHome/tmp/pear/download_dir");

            $logger->info('Enabling pear auto-discover...');
            system('pear config-set auto_discover 1');
        }

        $logger->debug('Source directory: ' . $targetDir);

        $buildName = $build->getName();

        $logger->info("Congratulations! Now you have PHP with $version as $buildName");

        if ($build->isEnabledVariant('pdo') && $build->isEnabledVariant('mysql')) {
            echo <<<EOT

* We found that you enabled 'mysql' variant, you might need to setup your
  'pdo_mysql.default_socket' or 'mysqli.default_socket' in your php.ini file.

EOT;
        }

        if (count($targetPaths)) {
            $targetConfigPaths = implode(PHP_EOL, $targetPaths);

            echo <<<EOT

* To configure your installed PHP further, you can edit the config file(s) at
$targetConfigPaths

EOT;
        }

        // If the bashrc file is not found, it means 'init' command didn't get
        // a chance to be executed.
        if (!file_exists(Config::getHome() . DIRECTORY_SEPARATOR . 'bashrc')) {
            echo <<<EOT

* WARNING:
  You haven't run 'phpbrew init' yet! Be sure to setup your phpbrew to use your own php(s)
  Please run 'phpbrew init' to setup your phpbrew in place.

EOT;
        }

        // If the environment variable is not defined, it means users didn't
        // setup their .bashrc or .zshrc
        if (!getenv('PHPBREW_HOME')) {
            echo <<<EOT

* WARNING:
  You haven't setup your .bashrc file to load phpbrew shell script yet!
  Please run 'phpbrew init' to see the steps!

EOT;
        }

        echo <<<EOT

To use the newly built PHP, try the line(s) below:

    $ phpbrew use $buildName

Or you can use switch command to switch your default php to $buildName:

    $ phpbrew switch $buildName

Enjoy!

EOT;

        return Command::SUCCESS;
    }

    /**
     * Parse semantic options (as, like, using) from the variants argument array.
     * Mutates $args in-place, removing the parsed entries.
     */
    public function parseSemanticOptions(array &$args): array
    {
        $settings = array();

        $definitions = array(
            'as' => '*',
            'like' => '*',
            'using' => '*+',
        );

        // XXX: support 'using'
        foreach ($definitions as $k => $requirement) {
            $idx = array_search($k, $args);

            if ($idx !== false) {
                if ($requirement == '*') {
                    // Find the value next to the position
                    list($key, $val) = array_splice($args, $idx, 2);
                    $settings[$key] = $val;
                } elseif ($requirement == '*+') {
                    $values = array_splice($args, $idx, 2);
                    $key = array_shift($values);
                    $settings[$key] = $values;
                }
            }
        }

        return $settings;
    }

    protected function installAs(string $source, string $target, bool $override = false, ?Logger $logger = null): bool|null
    {
        if (file_exists($source)) {
            if ($override || !file_exists($target)) {
                return copy($source, $target);
            } else {
                if ($logger) {
                    $logger->notice("Found existing $target.");
                }
                return false;
            }
        }
        return null;
    }

    /**
     * @param Build $build
     * @param string $etcDirectory
     * @param string $phpConfigPath
     * @param string $sapi
     * @param Logger $logger
     * @param object $options
     * @return string
     */
    private function makeIniFile(
        Build $build,
        string $etcDirectory,
        string $phpConfigPath,
        string $sapi,
        Logger $logger,
        object $options
    ): string {
        $sapiEtcDirectory = $etcDirectory . DIRECTORY_SEPARATOR . $sapi;
        if (!is_dir($sapiEtcDirectory) && mkdir($sapiEtcDirectory) && !is_dir($sapiEtcDirectory)) {
            $logger->error("Can't create config directory " . $sapiEtcDirectory);
        }
        $extensionsConfig = $etcDirectory . '/../var/db/' . $sapi;
        if (!is_dir($extensionsConfig) && mkdir($extensionsConfig, 0777, true) && !is_dir($extensionsConfig)) {
            $logger->error("Can't create config directory " . $sapiEtcDirectory);
        }

        $targetConfigPath = $sapiEtcDirectory . DIRECTORY_SEPARATOR . 'php.ini';

        if (file_exists($targetConfigPath)) {
            $logger->notice("Found existing $targetConfigPath.");
        } else {
            // TODO: Move this to PhpConfigPatchTask
            // move config file to target location
            copy($phpConfigPath, $targetConfigPath);
        }

        if (!$options->{'no-patch'}) {
            $config = parse_ini_file($targetConfigPath, true);
            $configContent = file_get_contents($targetConfigPath);

            if (!isset($config['date']['timezone'])) {
                $logger->info('---> Found date.timezone is not set, patching...');

                // Replace current timezone
                if ($timezone = ini_get('date.timezone')) {
                    $logger->info("---> Found date.timezone, patching config timezone with $timezone");
                    $configContent = preg_replace(
                        '/^;?date.timezone\s*=\s*.*/im',
                        "date.timezone = $timezone",
                        $configContent
                    );
                }
            }

            if (!isset($config['phar']['readonly'])) {
                $pharReadonly = ini_get('phar.readonly');
                // 0 or "" means readonly is disabled manually
                if (!$pharReadonly) {
                    $logger->info('---> Disabling phar.readonly option.');
                    $configContent = preg_replace(
                        '/^;?phar.readonly\s*=\s*.*/im',
                        'phar.readonly = 0',
                        $configContent
                    );
                }
            }

            // turn off detect_encoding for 5.3
            if ($build->compareVersion('5.4') < 0) {
                $logger->info("---> Turn off detect_encoding for php 5.3.*");
                $configContent = $configContent . PHP_EOL . "detect_unicode = Off" . PHP_EOL;
            }

            file_put_contents($targetConfigPath, $configContent);
        }

        return $targetConfigPath;
    }

    private function build(Build $build, ConfigureParameters $parameters, Logger $logger, object $options): void
    {
        $configureTask = new BeforeConfigureTask($logger, $options);
        $configureTask->run($build, $parameters);
        unset($configureTask); // trigger __destruct

        $configureTask = new ConfigureTask($logger, $options);
        $configureTask->run($build, $parameters);
        unset($configureTask); // trigger __destruct

        $configureTask = new AfterConfigureTask($logger, $options);
        $configureTask->run($build);
        unset($configureTask); // trigger __destruct
    }

    /**
     * @param ConfigureParameters $parameters
     * @param array $sapiSettings
     * @param bool $currentSapi
     * @param array $defaults
     *
     * @return array
     */
    private function enableDisable(ConfigureParameters &$parameters, array $sapiSettings, bool $currentSapi, array $defaults): array
    {
        $addedOptions = $removedOptions = array();

        $enableOptions = $currentSapi ? $sapiSettings['enable'] : $sapiSettings['disable'];
        $disableOptions = $currentSapi ? $sapiSettings['disable'] : $sapiSettings['enable'];

        foreach ($enableOptions as $configOption) {
            $addedOptions[] = $configOption;
            $parameters = $parameters->withOption(
                $configOption,
                array_key_exists($configOption, $defaults) ? $defaults[$configOption] : null
            );
        }

        foreach ($disableOptions as $configOption) {
            $removedOptions[] = $configOption;
            $parameters = $parameters->withoutOption($configOption);
        }

        return array($addedOptions, $removedOptions);
    }

    /**
     * Build a mutable options object wrapping InputInterface.
     * Supports overrides via array, and allows mutation via __set.
     * Compatible with Tasks that expect GetOptionKit\OptionResult-like property access.
     */
    private function buildOptions(InputInterface $input, array $overrides = []): object
    {
        return new class($input, $overrides) {
            private array $overrides;

            public function __construct(private InputInterface $input, array $overrides)
            {
                $this->overrides = $overrides;
            }

            public function __get(string $name): mixed
            {
                if (array_key_exists($name, $this->overrides)) {
                    return $this->overrides[$name];
                }
                if ($this->input->hasOption($name)) {
                    return $this->input->getOption($name);
                }
                return null;
            }

            public function __set(string $name, mixed $value): void
            {
                $this->overrides[$name] = $value;
            }

            public function __isset(string $name): bool
            {
                if (array_key_exists($name, $this->overrides)) {
                    $val = $this->overrides[$name];
                } elseif ($this->input->hasOption($name)) {
                    $val = $this->input->getOption($name);
                } else {
                    return false;
                }
                return $val !== null && $val !== false && $val !== [];
            }
        };
    }
}
