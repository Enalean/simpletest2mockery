<?php
/**
 * Copyright (c) Enalean, 2019-Present. All Rights Reserved.
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

declare(strict_types=1);

namespace ST2Mockery\PHPUnit;

use PhpParser\{Lexer, NodeTraverser, NodeVisitor, Parser, PrettyPrinter, NodeDumper};
use Psr\Log\LoggerInterface;
use ST2Mockery\NodeRemovalVisitor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ToPHPUnitCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName('to-phpunit')
            ->setDescription('Convert file to phpunit (from simpletest case)')
            ->addArgument('file', InputArgument::REQUIRED, 'File or directory to convert');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filepath = $input->getArgument('file');
        if (is_file($filepath)) {
            $this->parseAndSave($filepath);
        }
        return 0;
    }

    public function parseAndSave(string $path): void
    {
        $this->load($path);
        //$this->printStatments();
        $this->save($path);
    }

    public function load(string $path): void
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
        $traverser->addVisitor(new ConvertToPHPUnitVisitor());

        $this->oldStmts = $parser->parse(file_get_contents($path));
        $this->oldTokens = $lexer->getTokens();

        $this->newStmts = $traverser->traverse($this->oldStmts);

        $traverser2 = new NodeTraverser();
        $traverser2->addVisitor(new NodeRemovalVisitor($nodes_to_delete));

        $this->newStmts = $traverser2->traverse($this->newStmts);
    }

    public function printStatments(): void
    {
        $dumper = new NodeDumper;
        echo $dumper->dump($this->newStmts) . "\n";
    }

    public function getNewCodeAsString(): string
    {
        $printer = new PrettyPrinter\Standard(['shortArraySyntax' => true]);
        return $printer->printFormatPreserving($this->newStmts, $this->oldStmts, $this->oldTokens);
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->getNewCodeAsString());
    }
}