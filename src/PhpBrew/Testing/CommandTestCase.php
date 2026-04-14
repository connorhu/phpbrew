<?php

namespace PhpBrew\Testing;

use PhpBrew\Console\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Tester\ApplicationTester;

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
     * Tries to load the DI container from etc/container.php.
     * Falls back to a bare Application (commands not registered yet during migration).
     */
    protected function setupApplication(): Application
    {
        $containerFile = dirname(__DIR__, 3) . '/etc/container.php';
        if (file_exists($containerFile)) {
            $container = require $containerFile;
            return $container->get(Application::class);
        }
        // During migration: return bare application (commands registered later via DI container)
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
     * Create an ApplicationTester for the given command line.
     */
    private function makeTester(string $cmdLine): ApplicationTester
    {
        $app = $this->setupApplication();
        $app->setAutoExit(false);
        $app->setCatchExceptions(false);
        return new ApplicationTester($app);
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
        $tester = $this->makeTester($cmdLine);
        return $tester->run($this->makeInput($cmdLine), ['decorated' => false]) === 0;
    }

    /**
     * Run a command and return its output, or false on failure.
     */
    public function runCommandWithStdout(string $cmdLine): string|false
    {
        $tester = $this->makeTester($cmdLine);
        $status = $tester->run($this->makeInput($cmdLine), ['decorated' => false]);
        return $status === 0 ? $tester->getDisplay() : false;
    }

    /**
     * Assert that a command succeeds (exit code 0).
     */
    public function assertCommandSuccess(string $cmdLine): void
    {
        $tester = $this->makeTester($cmdLine);
        try {
            $status = $tester->run($this->makeInput($cmdLine), ['decorated' => false]);
        } catch (\CurlKit\CurlException $e) {
            $this->markTestIncomplete($e->getMessage());
            return;
        }
        $this->assertSame(0, $status, $tester->getDisplay());
    }
}
