<?php

namespace PhpBrew\Console;

use BadMethodCallException;
use Jean85\PrettyVersions;
use OutOfBoundsException;
use PhpBrew\Exception\SystemCommandException;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class Application extends BaseApplication
{
    const NAME = 'phpbrew';

    public function __construct()
    {
        try {
            $version = PrettyVersions::getVersion('phpbrew/phpbrew')->getPrettyVersion();
        } catch (OutOfBoundsException $e) {
            $version = 'dev';
        }
        parent::__construct(self::NAME, $version);
    }

    public function getHelp(): string
    {
        return <<<'BANNER'
  ______ _   _ ____________
  | ___ \ | | || ___ \ ___ \
  | |_/ / |_| || |_/ / |_/ /_ __ _____      __
  |  __/|  _  ||  __/| ___ \ '__/ _ \ \ /\ / /
  | |   | | | || |   | |_/ / | |  __/\ V  V /
  \_|   \_| |_/\_|   \____/|_|  \___| \_/\_/

BANNER;
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption(new InputOption(
            'no-progress',
            null,
            InputOption::VALUE_NONE,
            'Do not display progress bar.'
        ));
        return $definition;
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::doRun($input, $output);
        } catch (SystemCommandException $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error('Error: ' . trim($e->getMessage()));

            $buildLog = $e->getLogFile();
            if ($buildLog !== null && file_exists($buildLog)) {
                $io->error('The last 5 lines in the log file:');
                $lines = array_slice(file($buildLog), -5);
                foreach ($lines as $line) {
                    $output->writeln(trim($line));
                }
                $io->error('Please checkout the build log file for more details:');
                $io->error("\t tail $buildLog");
            }
            return 1;
        } catch (BadMethodCallException $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error($e->getMessage());
            $io->error('Seems like an application logic error, please contact the developer.');
            return 1;
        } catch (Throwable $e) {
            if ($output->isDebug()) {
                throw $e;
            }
            $io = new SymfonyStyle($input, $output);
            $io->error($e->getMessage());
            return 1;
        }
    }
}
