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
use ST2Mockery\FilterTestCase;
use ST2Mockery\NodeRemovalVisitor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

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
            ->addOption('run-tests', '', InputOption::VALUE_NONE, 'Run tests on generated file, if tests are green, prepare for commit otherwise trash the code');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source_filepath  = $input->getArgument('source');
        $target_directory = $input->getArgument('target');
        if (is_dir($source_filepath)) {
            $rii = new FilterTestCase(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($source_filepath),
                    \RecursiveIteratorIterator::SELF_FIRST
                )
            );
            foreach ($rii as $file) {
                $convert_to_phpunit_visitor = $this->load($file->getPathname());
                $saved = $this->save($this->getTargetDirectoy($file->getPathname(), $target_directory, $source_filepath), $convert_to_phpunit_visitor);
                if ($saved !== '' && $input->getOption('run-tests')) {
                    $phpunit_command = ['./src/vendor/bin/phpunit', '-c', 'tests/phpunit/phpunit.xml', $saved];
                    if (getenv('PHP') !== false) {
                        $phpunit_command = array_merge([getenv('PHP')], $phpunit_command);
                    }
                    $process = new Process($phpunit_command);
                    $process->run();
                    if ($process->isSuccessful()) {
                        (new Process(['git', 'rm', $file->getPathname()]))->mustRun();
                        (new Process(['git', 'add', $saved]))->mustRun();
                    } else {
                        unlink($saved);
                    }
                }
            }
        } elseif (is_file($source_filepath)) {
            $convert_to_phpunit_visitor = $this->load($source_filepath);
            $this->save($target_directory, $convert_to_phpunit_visitor);
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
        $convert_to_PHPUnit_visitor = new ConvertToPHPUnitVisitor($this->logger, $path);
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

    public function save(string $directory_path, ConvertToPHPUnitVisitor $convert_to_PHP_unit_visitor): string
    {
        $target_filename = $directory_path . '/' . $convert_to_PHP_unit_visitor->getClassName().'.php';
        if (is_file($target_filename)) {
            $this->logger->error($target_filename.' already exists, file not saved');
            return '';
        }
        if (! is_dir($directory_path)) {
            mkdir($directory_path, 0755, true);
        }
        file_put_contents($target_filename, $this->getNewCodeAsString());
        return $target_filename;
    }

    private function getTargetDirectoy(string $source_filepath, string $target_directory, ?string $source_basedir)
    {
        if ($source_basedir !== null && strpos($source_filepath, $source_basedir) === 0) {
            $source_directory = dirname($source_filepath);
            $relative = substr($source_directory, strlen($source_basedir) + 1);
            return $target_directory . '/' . $relative;
        }
        return $target_directory;
    }
}