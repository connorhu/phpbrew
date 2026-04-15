<?php

namespace PhpBrew\Tests;

use PhpBrew\Testing\CommandTestCase;

class CompletionTest extends CommandTestCase
{
    /**
     * @dataProvider completionProvider
     */
    public function testCompletion($shell)
    {
        $this->markTestSkipped('Shell completion tests need rewrite for Symfony Console built-in completion.');
    }

    public static function completionProvider()
    {
        return array(
            'bash' => array('bash'),
            'zsh' => array('zsh'),
        );
    }
}
