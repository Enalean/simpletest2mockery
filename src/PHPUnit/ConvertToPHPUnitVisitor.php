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

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerInterface;

class ConvertToPHPUnitVisitor extends NodeVisitorAbstract
{
    private $class_name;
    /**
     * @var LoggerInterface
     */
    private $logger;
    private $filepath;

    public function __construct(LoggerInterface $logger, string $filepath)
    {
        $this->logger = $logger;
        $this->filepath = $filepath;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_ && isset($node->extends) && (string) $node->extends->parts[0] === 'TuleapTestCase') {
            $this->setClassName($node);
            $stmts = [
                new Node\Stmt\TraitUse([new Node\Name\FullyQualified('Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration')]),
            ];
            $stmts = array_merge($stmts, $node->stmts);

            return new Node\Stmt\Class_($node->name, ['extends' => new Node\Name\FullyQualified('PHPUnit\Framework\TestCase'), 'stmts' => $stmts], $node->getAttributes());
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($node->name->name === 'setUp' || $node->name->name === 'tearDown') {
                return new Node\Stmt\ClassMethod($node->name, ['flags' => Node\Stmt\Class_::MODIFIER_PROTECTED, 'returnType' => 'void', 'stmts' => $node->stmts], $node->getAttributes());
            }
            if (strpos($node->name->name, 'it') === 0) {
                $node->name->name = 'test'.ucfirst($node->name->name);
                $node->returnType = new Node\Name('void');
            }
            if (strpos($node->name->name, 'test') === 0) {
                $node->returnType = new Node\Name('void');
            }
        }

        if ($node instanceof Node\Expr\MethodCall && (string) $node->name === 'assertEqual') {
            return new Node\Expr\MethodCall(
                $node->var,
                'assertEquals',
                $node->args,
                $node->getAttributes()
            );
        }

        if ($node instanceof Node\Expr\MethodCall && (string) $node->name === 'assertIsA') {
            return new Node\Expr\MethodCall(
                $node->var,
                'assertInstanceOf',
                [
                    new Node\Arg(
                        new Node\Expr\ClassConstFetch(new Node\Name\FullyQualified((string) $node->args[1]->value->value), 'class')
                    ),
                    $node->args[0],
                ],
                $node->getAttributes()
            );
        }

        if ($node instanceof Node\Expr\MethodCall && (string) $node->name === 'assertCount') {
            return new Node\Expr\MethodCall(
                $node->var,
                'assertCount',
                [
                    new Node\Arg(
                        new Node\Scalar\LNumber($node->args[1]->value->value)
                    ),
                    $node->args[0],
                ],
                $node->getAttributes()
            );
        }

        if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\MethodCall && (string) $node->expr->name === 'setUpGlobalsMockery') {
            return NodeTraverser::REMOVE_NODE;
        }
    }

    private function setClassName(Node\Stmt\Class_ $node)
    {
        if ($this->class_name === null) {
            $this->class_name = (string) $node->name;
        } else {
            $this->logger->error(sprintf('Class name already set, you will need to manually split %s', $this->filepath));
        }
    }

    public function getClassName()
    {
        return $this->class_name;
    }
}
