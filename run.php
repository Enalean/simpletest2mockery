<?php
/**
 * Copyright (c) Enalean, 2018. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 *
 */

require_once 'vendor/autoload.php';

use PhpParser\{Lexer, NodeTraverser, NodeVisitor, Parser, PrettyPrinter, NodeDumper};

$lexer = new Lexer\Emulative([
    'usedAttributes' => [
        'comments',
        'startLine', 'endLine',
        'startTokenPos', 'endTokenPos',
    ],
]);
$parser = new Parser\Php7($lexer);

$traverser = new NodeTraverser();
$traverser->addVisitor(new NodeVisitor\CloningVisitor());

$printer = new PrettyPrinter\Standard();

$oldStmts = $parser->parse(file_get_contents($argv[1]));
$oldTokens = $lexer->getTokens();

$newStmts = $traverser->traverse($oldStmts);

$dumper = new NodeDumper;
echo $dumper->dump($newStmts) . "\n";

$newCode = $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);

file_put_contents($argv[1], $newCode);

//echo $newCode;