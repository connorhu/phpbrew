<?php

namespace PhpBrew\Testing;

use PhpBrew\Console\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class CommandTestCase extends TestCase
{
    protected $debug = false;

    public string $primaryVersion = '7.0.33';

    /**
     * You need to set this to true in each subclass you want to use VCR in.
     */
    public $usesVCR = false;

    public function getPrimaryVersion(): string
    {
        return $this->primaryVersion;
    }

    /**
     * Returns an Application instance with all commands registered.
     * Loads the DI container from etc/container.php.
     * Falls back to a bare Application if the container file does not exist yet.
     */
    protected function setupApplication(): Application
    {
        $containerFile = dirname(__DIR__, 3) . '/etc/container.php';
        if (file_exists($containerFile)) {
            $container = require $containerFile;
            return $container->get(Application::class);
        }
        $app = new Application();
        $app->setAutoExit(false);
        $app->setCatchExceptions(false);
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->usesVCR) {
            VCRAdapter::enableVCR($this);
        }
    }

    protected function tearDown(): void
    {
        if ($this->usesVCR) {
            VCRAdapter::disableVCR();
        }
    }

    /**
     * Build a configured Application with autoExit and catchExceptions disabled.
     */
    private function makeApp(): Application
    {
        // Reset SHELL_VERBOSITY so a previous --quiet run does not silence this app.
        // Symfony Console's configureIO() reads SHELL_VERBOSITY from the environment.
        putenv('SHELL_VERBOSITY=0');

        $app = $this->setupApplication();
        $app->setAutoExit(false);
        $app->setCatchExceptions(false);
        return $app;
    }

    /**
     * Strip the "phpbrew " prefix and return a StringInput.
     */
    private function makeInput(string $cmdLine): StringInput
    {
        return new StringInput(trim(preg_replace('/^phpbrew\s+/', '', $cmdLine)));
    }

    /**
     * Run a command and return true if it succeeded (exit code 0).
     *
     * @param string $cmdLine e.g. "phpbrew env" or "phpbrew --quiet known"
     */
    public function runCommand(string $cmdLine): bool
    {
        $app = $this->makeApp();
        $output = new BufferedOutput();
        return $app->run($this->makeInput($cmdLine), $output) === 0;
    }

    /**
     * Run a command and return its output, or false on failure.
     */
    public function runCommandWithStdout(string $cmdLine): string|false
    {
        $app = $this->makeApp();
        $output = new BufferedOutput();
        $status = $app->run($this->makeInput($cmdLine), $output);
        return $status === 0 ? $output->fetch() : false;
    }

    /**
     * Assert that a command succeeds (exit code 0).
     */
    public function assertCommandSuccess(string $cmdLine): void
    {
        $app = $this->makeApp();
        $output = new BufferedOutput();
        try {
            $status = $app->run($this->makeInput($cmdLine), $output);
        } catch (\CurlKit\CurlException $e) {
            $this->markTestIncomplete($e->getMessage());
            return;
        }
        $this->assertSame(0, $status, $output->fetch());
    }
}
