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

class ConvertToPHPUnitVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_ && (string) $node->extends->parts[0] === 'TuleapTestCase') {
            $stmts = [
                new Node\Stmt\TraitUse([new Node\Name\FullyQualified('Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration')]),
            ];
            $stmts = array_merge($stmts, $node->stmts);

            return new Node\Stmt\Class_($node->name, ['extends' => new Node\Name\FullyQualified('PHPUnit\Framework\TestCase'), 'stmts' => $stmts], $node->getAttributes());
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($node->name->name === 'setUp') {
                return new Node\Stmt\ClassMethod($node->name, ['flags' => Node\Stmt\Class_::MODIFIER_PROTECTED, 'returnType' => 'void', 'stmts' => $node->stmts], $node->getAttributes());
            }
            if (strpos($node->name->name, 'test') !== 0) {
                $node->name->name = 'test'.ucfirst($node->name->name);
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

        if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\MethodCall && (string) $node->expr->name === 'setUpGlobalsMockery') {
            return NodeTraverser::REMOVE_NODE;
        }
    }
}
