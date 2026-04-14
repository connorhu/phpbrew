<?php

namespace PhpBrew\Console\Command;

use PhpBrew\BuildFinder;
use PhpBrew\Config;
use PhpBrew\VariantParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('list')
            ->setDescription('List installed PHPs')
            ->addOption('dir', 'd', InputOption::VALUE_NONE, 'Show php directories.')
            ->addOption('variants', 'v', InputOption::VALUE_NONE, 'Show used variants.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $builds = BuildFinder::findInstalledBuilds();
        $currentBuild = Config::getCurrentPhpName();

        if (empty($builds)) {
            $io->note('Please install at least one PHP with your preferred version.');
            return Command::SUCCESS;
        }

        if ($currentBuild === false || !in_array($currentBuild, $builds)) {
            $io->writeln('* (system)');
        }

        foreach ($builds as $build) {
            $versionPrefix = Config::getVersionInstallPrefix($build);

            if ($currentBuild === $build) {
                $io->writeln(sprintf('<options=bold>* %-15s</>', $build));
            } else {
                $io->writeln(sprintf('<options=bold>  %-15s</>', $build));
            }

            if ($input->getOption('dir')) {
                $io->writeln(sprintf('    Prefix:   %s', $versionPrefix));
            }

            if ($input->getOption('variants') && file_exists($versionPrefix . DIRECTORY_SEPARATOR . 'phpbrew.variants')) {
                $info = unserialize(file_get_contents($versionPrefix . DIRECTORY_SEPARATOR . 'phpbrew.variants'));
                $output->write('    Variants: ');
                $output->writeln(wordwrap(VariantParser::revealCommandArguments($info), 75, " \\\n              "));
            }
        }

        return Command::SUCCESS;
    }
}
