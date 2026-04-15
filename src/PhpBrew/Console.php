<?php

namespace PhpBrew;

use PhpBrew\Console\Application;

/**
 * @deprecated Will be removed. Use PhpBrew\Console\Application instead.
 * Kept as stub to avoid fatal errors during migration. bin/phpbrew will be updated in the final task.
 */
class Console extends Application
{
    /** @deprecated */
    public static function getInstance(): self
    {
        $app = new self();
        $app->setAutoExit(false);
        return $app;
    }
}
