<?php

namespace PhpBrew\Console\Command\Extension;

use Exception;
use PhpBrew\Config;
use PhpBrew\Extension\Extension;
use PhpBrew\Extension\ExtensionFactory;
use PhpBrew\Extension\M4Extension;
use PhpBrew\Logger;
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
            ->setName('extension:list')
            ->setAliases(['extension', 'ext'])
            ->setDescription('List extensions or show extension information')
            ->addOption('show-options', null, InputOption::VALUE_NONE, 'Show extension configure options')
            ->addOption('show-path', null, InputOption::VALUE_NONE, 'Show extension config.m4 path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logger = new Logger($output);

        $buildDir = Config::getCurrentBuildDir();
        $extDir = $buildDir . DIRECTORY_SEPARATOR . 'ext';

        $extensions = [];
        $lookupDirectories = ['', 'ext', 'extension'];

        if (file_exists($extDir) && is_dir($extDir)) {
            $logger->debug("Scanning $extDir...");
            foreach (scandir($extDir) as $extName) {
                if ($extName == '.' || $extName == '..') continue;
                $dir = $extDir . DIRECTORY_SEPARATOR . $extName;
                foreach ($lookupDirectories as $lookupDirectory) {
                    $extensionDir = $dir . (empty($lookupDirectory) ? '' : DIRECTORY_SEPARATOR . $lookupDirectory);
                    if ($m4files = ExtensionFactory::configM4Exists($extensionDir)) {
                        foreach ($m4files as $m4file) {
                            try {
                                $ext = ExtensionFactory::createM4Extension($extName, $m4file);
                                $extensions[$ext->getExtensionName()] = $ext;
                                break;
                            } catch (Exception $e) {}
                        }
                        break;
                    }
                }
            }
        }

        $io->writeln('Loaded extensions:');
        foreach ($extensions as $extName => $ext) {
            if (extension_loaded($extName)) {
                $this->describeExtension($ext, $input, $output);
            }
        }

        $io->writeln('Available local extensions:');
        foreach ($extensions as $extName => $ext) {
            if (!extension_loaded($extName)) {
                $this->describeExtension($ext, $input, $output);
            }
        }

        return Command::SUCCESS;
    }

    private function describeExtension(Extension $ext, InputInterface $input, OutputInterface $output): void
    {
        $output->write(sprintf(
            ' [%s] %-12s %-12s',
            extension_loaded($ext->getExtensionName()) ? '*' : ' ',
            $ext->getExtensionName(),
            phpversion($ext->getExtensionName())
        ));

        if ($input->getOption('show-path')) {
            $output->write(sprintf(' from %s', $ext->getConfigM4Path()));
        }
        $output->writeln('');

        if ($input->getOption('show-options')) {
            $padding = '     ';
            if ($ext instanceof M4Extension) {
                $options = $ext->getConfigureOptions();
                if (!empty($options)) {
                    $output->writeln($padding . 'Configure options:');
                    foreach ($options as $option) {
                        $output->writeln($padding . '  ' . sprintf(
                            '%-32s %s',
                            $option->option . ($option->valueHint ? '[=' . $option->valueHint . ']' : ''),
                            $option->desc
                        ));
                    }
                }
            }
        }
    }
}
