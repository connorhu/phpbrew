<?php

namespace PhpBrew\Console\Command;

use Phar;
use PhpBrew\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize phpbrew config file.')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED,
                'The YAML config file which should be copied into phpbrew home. '
                . 'The config file is used for creating custom virtual variants. '
                . 'For more details, please see https://github.com/phpbrew/phpbrew/wiki/Setting-up-Configuration')
            ->addOption('root', null, InputOption::VALUE_REQUIRED,
                'Override the default PHPBREW_ROOT path setting. '
                . 'This option is usually used to load system-wide build pool. '
                . 'e.g. phpbrew init --root=/opt/phpbrew');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $root = $input->getOption('root') ?: Config::getRoot();
        $home = Config::getHome();
        $buildDir = Config::getBuildDir();
        $buildPrefix = Config::getInstallPrefix();

        $io->writeln("Using root: $root");
        if (!file_exists($root)) {
            mkdir($root, 0755, true);
        }

        $paths = [$home, $root, $buildDir, $buildPrefix];
        foreach ($paths as $p) {
            $output->writeln("Checking directory $p", OutputInterface::VERBOSITY_DEBUG);
            if (!file_exists($p)) {
                $output->writeln("Creating directory $p", OutputInterface::VERBOSITY_DEBUG);
                mkdir($p, 0755, true);
            }
        }

        $output->writeln('Creating .metadata_never_index to prevent SpotLight indexing', OutputInterface::VERBOSITY_DEBUG);
        $indexFiles = [
            $root . DIRECTORY_SEPARATOR . '.metadata_never_index',
            $home . DIRECTORY_SEPARATOR . '.metadata_never_index',
        ];
        foreach ($indexFiles as $indexFile) {
            if (!file_exists($indexFile)) {
                touch($indexFile);
            }
        }

        if ($configFile = $input->getOption('config')) {
            if (!file_exists($configFile)) {
                $io->error("config file '$configFile' does not exist.");
                return Command::FAILURE;
            }
            $output->writeln("Using yaml config from '$configFile'", OutputInterface::VERBOSITY_DEBUG);
            copy($configFile, $root . DIRECTORY_SEPARATOR . 'config.yaml');
        }

        $io->writeln('<info>Initialization successfully finished!</info>');
        $io->writeln('<info><=====================================================></info>');

        // write bashrc script to phpbrew home
        file_put_contents($home . '/bashrc', $this->getBashScriptPath());
        // write phpbrew.fish script to phpbrew home
        file_put_contents($home . '/phpbrew.fish', $this->getFishScriptPath());

        if (strpos(getenv('SHELL') ?: '', 'fish') !== false) {
            $initConfig = <<<EOS
Paste the following line(s) to the end of your ~/.config/fish/config.fish and start a
new shell, phpbrew should be up and fully functional from there:

    source $home/phpbrew.fish
EOS;
        } else {
            $initConfig = <<<EOS
Paste the following line(s) to the end of your ~/.bashrc and start a
new shell, phpbrew should be up and fully functional from there:

    source $home/bashrc

To enable PHP version info in your shell prompt, please set PHPBREW_SET_PROMPT=1
in your `~/.bashrc` before you source `~/.phpbrew/bashrc`

    export PHPBREW_SET_PROMPT=1

To enable .phpbrewrc file searching, please export the following variable:

    export PHPBREW_RC_ENABLE=1

EOS;
        }

        $output->writeln(<<<EOS
Phpbrew environment is initialized, required directories are created under

    $home

$initConfig

For further instructions, simply run `phpbrew` to see the help message.

Enjoy phpbrew at \$HOME!!


EOS
        );

        $io->writeln('<info><=====================================================></info>');

        return Command::SUCCESS;
    }

    protected function getCurrentShellDirectory(): string
    {
        $path = Phar::running();
        if ($path) {
            $path = $path . DIRECTORY_SEPARATOR . 'shell';
        } else {
            // New file is at src/PhpBrew/Console/Command/ (4 levels deep from project root)
            $path = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'shell';
        }
        return $path;
    }

    protected function getBashScriptPath(): string
    {
        $path = $this->getCurrentShellDirectory();
        return file_get_contents($path . DIRECTORY_SEPARATOR . 'bashrc');
    }

    protected function getFishScriptPath(): string
    {
        $path = $this->getCurrentShellDirectory();
        return file_get_contents($path . DIRECTORY_SEPARATOR . 'phpbrew.fish');
    }
}
