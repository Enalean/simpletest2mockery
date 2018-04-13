<?php declare(strict_types=1);
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

namespace ST2Mockery;

use PhpParser\{Lexer, NodeTraverser, NodeVisitor, Parser, PrettyPrinter, NodeDumper};
use Psr\Log\LoggerInterface;

class ST2Mockery
{
    private $oldTokens;
    private $oldStmts;
    private $newStmts;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function run(array $argv)
    {
        if (! isset($argv[1])) {
            throw new \RuntimeException("Please provide a file or directory as first parameter");
        }
        $filepath = $argv[1];

        if (is_dir($filepath)) {
            $rii = new FilterTestCase(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($filepath),
                    \RecursiveIteratorIterator::SELF_FIRST
                )
            );
            foreach ($rii as $file) {
                try {
                    $this->parseAndSave($file->getPathname());
                } catch (\Exception $exception) {
                    $this->logger->error("Unable to convert {$file->getPathname()}: ".$exception->getMessage());
                }
            }
            return;
        } elseif (file_exists($filepath)) {
            $this->parseAndSave($filepath);
            return;
        }
        throw new \RuntimeException("$filepath is neither a file nor a directory");
    }

    private function parseAndSave(string $path)
    {
        $this->load($path);
        //$this->printStatments();
        $this->save($path);
    }

    public function load(string $path)
    {
        $this->logger->info("Processing $path");
        $lexer = new Lexer\Emulative([
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
            ],
        ]);
        $parser = new Parser\Php5($lexer);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\CloningVisitor());

        $common = [];
        $traverser->addVisitor(new SimpleTestToMockeryVisitor($this->logger, $path, $common));
        $traverser->addVisitor(new DirenameToDIRContVisitor());
        $traverser->addVisitor(new NormalizeSetUpAndTearDownVisitor());
        $traverser->addVisitor(new ConvertMockGenerationVisitor($this->logger, $path));

        $this->oldStmts = $parser->parse(file_get_contents($path));
        $this->oldTokens = $lexer->getTokens();

        $this->newStmts = $traverser->traverse($this->oldStmts);

        $tr2 = new NodeTraverser();
        $tr2->addVisitor(new NodeRemovalVisitor($common));

        $this->newStmts = $tr2->traverse($this->newStmts);
    }

    public function printStatments()
    {
        $dumper = new NodeDumper;
        echo $dumper->dump($this->newStmts) . "\n";
    }

    public function save(string $path)
    {
        $printer = new PrettyPrinter\Standard(['shortArraySyntax' => true]);
        $newCode = $printer->printFormatPreserving($this->newStmts, $this->oldStmts, $this->oldTokens);

        file_put_contents($path, $newCode);
    }
}
