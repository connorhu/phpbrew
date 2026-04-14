<?php

namespace PhpBrew\Console\Command;

use PhpBrew\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PathCommand extends Command
{
    private const VALID_PATHS = [
        'root', 'home', 'build', 'bin', 'include', 'etc', 'ext',
        'ext-src', 'extension-src', 'extension-dir', 'config-scan', 'dist',
    ];

    protected function configure(): void
    {
        $this
            ->setName('path')
            ->setDescription('Show paths of the current PHP.')
            ->setHelp('phpbrew path [' . implode(', ', self::VALID_PATHS) . ']')
            ->addArgument('type', InputArgument::REQUIRED, 'Path type', null,
                fn(CompletionInput $input): array => self::VALID_PATHS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('type');
        switch ($name) {
            case 'root':
                $output->write(Config::getRoot());
                break;
            case 'home':
                $output->write(Config::getHome());
                break;
            case 'config-scan':
                $output->write(Config::getCurrentPhpConfigScanPath());
                break;
            case 'dist':
                $output->write(Config::getDistFileDir());
                break;
            case 'build':
                $output->write(Config::getCurrentBuildDir());
                break;
            case 'bin':
                $output->write(Config::getCurrentPhpBin());
                break;
            case 'include':
                $output->write(Config::getVersionInstallPrefix(Config::getCurrentPhpName())
                    . DIRECTORY_SEPARATOR . 'include');
                break;
            case 'extension-src':
            case 'ext-src':
                $output->write(Config::getCurrentBuildDir() . DIRECTORY_SEPARATOR . 'ext');
                break;
            case 'extension-dir':
            case 'ext-dir':
            case 'ext':
                $output->write(ini_get('extension_dir'));
                break;
            case 'etc':
                $output->write(Config::getVersionInstallPrefix(Config::getCurrentPhpName())
                    . DIRECTORY_SEPARATOR . 'etc');
                break;
            default:
                return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
