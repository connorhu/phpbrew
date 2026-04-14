<?php

namespace PhpBrew;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Logger compatibility shim replacing CLIFramework\Logger.
 * Wraps Symfony Console OutputInterface for logging.
 */
class Logger
{
    private OutputInterface $output;

    private bool $quiet = false;

    private static ?self $instance = null;

    public function __construct(?OutputInterface $output = null)
    {
        $this->output = $output ?? new NullOutput();
    }

    /**
     * Returns a singleton instance (used by tests).
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function setQuiet(): void
    {
        $this->quiet = true;
    }

    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    public function isDebug(): bool
    {
        return $this->output->isDebug();
    }

    /**
     * Returns a numeric log level for compatibility with CLIFramework\Logger.
     * Maps to Symfony Console verbosity levels (higher = more verbose).
     */
    public function getLevel(): int
    {
        if ($this->quiet) {
            return 0;
        }
        if ($this->output->isDebug()) {
            return 4;
        }
        if ($this->output->isVeryVerbose()) {
            return 3;
        }
        if ($this->output->isVerbose()) {
            return 2;
        }
        return 1;
    }

    public function writeln(string $msg): void
    {
        if (!$this->quiet) {
            $this->output->writeln($msg);
        }
    }

    public function write(string $msg): void
    {
        if (!$this->quiet) {
            $this->output->write($msg);
        }
    }

    public function newline(): void
    {
        if (!$this->quiet) {
            $this->output->writeln('');
        }
    }

    public function info(string $msg): void
    {
        if (!$this->quiet) {
            $this->output->writeln($msg);
        }
    }

    public function warn(string $msg): void
    {
        if (!$this->quiet) {
            $this->output->writeln($msg);
        }
    }

    public function warning(string $msg): void
    {
        $this->warn($msg);
    }

    public function error(string $msg): void
    {
        $this->output->writeln($msg);
    }

    public function debug(string $msg): void
    {
        if (!$this->quiet && $this->output->isDebug()) {
            $this->output->writeln($msg);
        }
    }

    public function notice(string $msg): void
    {
        if (!$this->quiet) {
            $this->output->writeln($msg);
        }
    }

    public function log(string $level, string $msg): void
    {
        $this->{$level}($msg);
    }
}
