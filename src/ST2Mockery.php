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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ST2Mockery extends Command
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
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('to-mockery')
            ->setDescription('Convert simpletest mocks to mockery')
            ->addArgument('file', InputArgument::REQUIRED, 'File or directory to convert');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filepath = $input->getArgument('file');

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
            return 0;
        } elseif (file_exists($filepath)) {
            $this->parseAndSave($filepath);
            return 0;
        }
        throw new \RuntimeException("$filepath is neither a file nor a directory");
    }

    public function parseAndSave(string $path)
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
        $parser = new Parser\Php7($lexer);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\CloningVisitor());

        $nodes_to_delete = [];
        //$traverser->addVisitor(new ParentConnector());
        $traverser->addVisitor(new SimpleTestToMockeryVisitor($this->logger, $path, $nodes_to_delete));
        $traverser->addVisitor(new DirnameToDIRContVisitor());
        $traverser->addVisitor(new NormalizeSetUpAndTearDownVisitor());
        $traverser->addVisitor(new ConvertMockGenerationVisitor($this->logger, $path));
        $traverser->addVisitor(new ConvertStubVisitor());
        $traverser->addVisitor(new ConvertMockHelpers());
        $traverser->addVisitor(new ConvertExpectationsVisitor());

        $this->oldStmts = $parser->parse(file_get_contents($path));
        $this->oldTokens = $lexer->getTokens();

        $this->newStmts = $traverser->traverse($this->oldStmts);

        $traverser2 = new NodeTraverser();
        $traverser2->addVisitor(new NodeRemovalVisitor($nodes_to_delete));

        $this->newStmts = $traverser2->traverse($this->newStmts);
    }

    public function printStatments()
    {
        $dumper = new NodeDumper;
        echo $dumper->dump($this->newStmts) . "\n";
    }

    public function getNewCodeAsString()
    {
        $printer = new PrettyPrinter\Standard(['shortArraySyntax' => true]);
        return $printer->printFormatPreserving($this->newStmts, $this->oldStmts, $this->oldTokens);
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->getNewCodeAsString($path));
    }
}
