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
            ->addArgument('source', InputArgument::REQUIRED, 'File or directory to convert')
            ->addArgument('target', InputArgument::REQUIRED, 'Directory to place new file')
            ->addArgument('source_basedir', InputArgument::OPTIONAL, 'Part of source path to truncate');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source_filepath  = $input->getArgument('source');
        $target_directory = $input->getArgument('target');
        $source_basedir   = $input->getArgument('source_basedir');
        if (is_file($source_filepath)) {
            $convert_to_phpunit_visitor = $this->load($source_filepath);
            $this->save($this->getTargetDirectoy($source_filepath, $target_directory, $source_basedir), $convert_to_phpunit_visitor);
        }
        return 0;
    }

    public function load(string $path): ConvertToPHPUnitVisitor
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
        $convert_to_PHPUnit_visitor = new ConvertToPHPUnitVisitor($this->logger);
        $traverser->addVisitor($convert_to_PHPUnit_visitor);

        $this->oldStmts = $parser->parse(file_get_contents($path));
        $this->oldTokens = $lexer->getTokens();

        $this->newStmts = $traverser->traverse($this->oldStmts);

        return $convert_to_PHPUnit_visitor;
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

    public function save(string $directory_path, ConvertToPHPUnitVisitor $convert_to_PHP_unit_visitor): void
    {
        if (! is_dir($directory_path)) {
            mkdir($directory_path, 0755, true);
        }
        file_put_contents($directory_path . '/' . $convert_to_PHP_unit_visitor->getClassName().'.php', $this->getNewCodeAsString());
    }

    private function getTargetDirectoy(string $source_filepath, string $target_directory, ?string $source_basedir)
    {
        if ($source_basedir !== null && strpos($source_filepath, $source_basedir) === 0) {
            $source_directory = dirname($source_filepath);
            $common_part = strlen($source_basedir);
            $relative = substr($source_directory, strlen($source_basedir) + 1);
            return $target_directory . '/' . $relative;
        }
        return $target_directory;
    }
}