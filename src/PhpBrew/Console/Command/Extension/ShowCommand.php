<?php

namespace PhpBrew\Console\Command\Extension;

use Exception;
use PhpBrew\Config;
use PhpBrew\Extension\Extension;
use PhpBrew\Extension\ExtensionFactory;
use PhpBrew\Extension\PeclExtension;
use PhpBrew\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ShowCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('extension:show')
            ->setDescription('Show information of a PHP extension')
            ->setHelp('phpbrew [-dv, -r] ext show [extension name]')
            ->addArgument('extension', InputArgument::REQUIRED, 'Extension name', null,
                fn(CompletionInput $i): array => array_filter(
                    scandir(Config::getBuildDir() . '/' . Config::getCurrentPhpName() . '/ext') ?: [],
                    fn($d) => $d !== '.' && $d !== '..'
                ))
            ->addOption('download', null, InputOption::VALUE_NONE, 'Download the extension source if extension not found.');
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
        $extensionName = $input->getArgument('extension');

        $ext = ExtensionFactory::lookup($extensionName);
        if (!$ext) {
            $ext = ExtensionFactory::lookupRecursive($extensionName);
        }

        if (!$ext) {
            throw new Exception("$extensionName extension not found.");
        }

        $this->describeExtension($ext, $output);

        return Command::SUCCESS;
    }

    private function describeExtension(Extension $ext, OutputInterface $output): void
    {
        $info = [
            'Name' => $ext->getExtensionName(),
            'Source Directory' => $ext->getSourceDirectory(),
            'Config' => $ext->getConfigM4Path(),
            'INI File' => $ext->getConfigFilePath(),
            'Extension' => ($ext instanceof PeclExtension) ? 'Pecl' : 'Core',
            'Zend' => $ext->isZend() ? 'yes' : 'no',
            'Loaded' => extension_loaded($ext->getExtensionName()) ? '<info>yes</info>' : '<error>no</error>',
        ];

        foreach ($info as $label => $val) {
            $output->writeln(sprintf('%20s: %s', $label, $val));
        }

        $options = $ext->getConfigureOptions();
        if (!empty($options)) {
            $output->writeln('');
            $output->writeln(sprintf('%20s: ', 'Configure Options'));
            $output->writeln('');
            foreach ($options as $option) {
                $output->writeln(sprintf(
                    '        %-32s %s',
                    $option->option . ($option->valueHint ? '[=' . $option->valueHint . ']' : ''),
                    $option->desc
                ));
                $output->writeln('');
            }
        }
    }
}
