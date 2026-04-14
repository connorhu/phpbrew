<?php

namespace PhpBrew\PatchKit;

use PhpBrew\Logger;
use PhpBrew\Buildable;

interface PatchRule
{
    public function apply(Buildable $build, Logger $logger);

    public function backup(Buildable $build, Logger $logger);
}
