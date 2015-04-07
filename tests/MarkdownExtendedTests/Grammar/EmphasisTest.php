<?php
/*
 * This file is part of the PHP-MarkdownExtended package.
 *
 * (c) Pierre Cassat <me@e-piwi.fr> and contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MarkdownExtendedTests\Grammar;

use \MarkdownExtendedTests\ParserTest;
use \MarkdownExtended\MarkdownExtended;

class EmphasisTest extends ParserTest
{

    public function testCreate()
    {
        $md = '**Hello** _World_';
        $this->assertEquals(
            (string) MarkdownExtended::parse($md, array('template'=>false)),
            '<strong>Hello</strong> <em>World</em>',
            'Emphasis fails!'
        );
    }
    
}
