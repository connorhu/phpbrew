<?php

namespace PhpBrew\Console\Command;

use PhpBrew\VariantBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VariantsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('variants')
            ->setDescription('List php variants')
            ->setHelp('phpbrew variants [php-version]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $variants = new VariantBuilder();
        $list = $variants->getVariantNames();
        sort($list);

        $output->writeln('Variants: ');
        $output->writeln($this->wrapLine(implode(', ', $list)));
        $output->writeln('');
        $output->writeln('');

        $output->writeln('Virtual variants: ');
        foreach ($variants->virtualVariants as $name => $subvars) {
            $output->writeln($this->wrapLine("$name: " . implode(', ', $subvars)));
        }

        $output->writeln('');
        $output->writeln('');
        $output->writeln('Using variants to build PHP:');
        $output->writeln('');
        $output->writeln('  phpbrew install php-5.3.10 +default');
        $output->writeln('  phpbrew install php-5.3.10 +mysql +pdo');
        $output->writeln('  phpbrew install php-5.3.10 +mysql +pdo +apxs2');
        $output->writeln('  phpbrew install php-5.3.10 +mysql +pdo +apxs2=/usr/bin/apxs2');
        $output->writeln('');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function wrapLine(string $line, string $prefix = '  ', string $indent = '  '): string
    {
        $lineX = 0;
        $newLine = $prefix;
        for ($i = 0; $i < strlen($line); $i++, $lineX++) {
            $c = $line[$i];
            $newLine .= $c;
            if ($lineX > 68 && $c === ' ') {
                $newLine .= PHP_EOL . $indent;
                $lineX = 0;
            }
        }
        return $newLine;
    }
}
