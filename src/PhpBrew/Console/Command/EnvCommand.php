<?php

namespace PhpBrew\Console\Command;

use PhpBrew\BuildFinder;
use PhpBrew\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('env')
            ->setDescription('Export environment variables')
            ->addArgument('build', InputArgument::OPTIONAL, 'PHP build name', null,
                fn(CompletionInput $i): array => BuildFinder::findInstalledBuilds());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $buildName = $input->getArgument('build');
        if (!$buildName) {
            $buildName = getenv('PHPBREW_PHP');
        }

        $this->export($output, 'PHPBREW_ROOT', Config::getRoot());
        $this->export($output, 'PHPBREW_HOME', Config::getHome());

        $this->replicate($output, 'PHPBREW_LOOKUP_PREFIX');

        if ($buildName !== false) {
            $targetPhpBinPath = Config::getVersionBinPath($buildName);
            if (is_dir($targetPhpBinPath)) {
                $this->export($output, 'PHPBREW_PHP', $buildName);
                $this->export($output, 'PHPBREW_PATH', $targetPhpBinPath);
            }
        }

        $this->replicate($output, 'PHPBREW_SYSTEM_PHP');

        $output->writeln('# Run this command to configure your shell:');
        $output->writeln('# eval "$(phpbrew env)"');

        return Command::SUCCESS;
    }

    private function export(OutputInterface $output, string $varName, string $value): void
    {
        $output->writeln(sprintf('export %s=%s', $varName, $value));
    }

    private function replicate(OutputInterface $output, string $varName): void
    {
        $value = getenv($varName);
        if ($value !== false && $value !== '') {
            $this->export($output, $varName, $value);
        }
    }
}
