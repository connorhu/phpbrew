<?php

namespace PhpBrew\Tests\Command;

use PhpBrew\Testing\CommandTestCase;

/**
 * @large
 * @group command
 */
class PathCommandTest extends CommandTestCase
{

    public function argumentsProvider()
    {
        return array(
            array("build",   "#\.phpbrew/build/.+#"),
            array("ext-src", "#\.phpbrew/build/.+/ext$#"),
            array("include", "#\.phpbrew/php/.+/include$#"),
            array("etc",     "#\.phpbrew/php/.+/etc$#"),
            array("dist",    "#\.phpbrew/distfiles$#"),
            array("root",    "#\.phpbrew$#"),
            array("home",    "#\.phpbrew$#"),
        );
    }

    /**
     * @dataProvider argumentsProvider
     */
    public function testPathCommand($arg, $pattern)
    {
        putenv('PHPBREW_PHP=7.4.0');

        $path = $this->runCommandWithStdout("phpbrew path $arg");
        $this->assertRegExp($pattern, $path);
    }
}
