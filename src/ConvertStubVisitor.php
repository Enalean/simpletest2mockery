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

namespace ST2Mockery;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ConvertStubVisitor extends NodeVisitorAbstract
{

    public function leaveNode(Node $node)
    {
        if ($this->isCallTo($node, 'returns')) {
            return CodeGenerator::getReturn($node->var, $node->args);
        }
        if ($this->isCallTo($node, 'returnsEmptyDar')) {
            return CodeGenerator::getReturnsEmptyDar($node->var);
        }
        if ($this->isCallTo($node, 'returnsDar')) {
            return CodeGenerator::getReturnsDar($node->var, $node->args);
        }
        if ($this->isCallTo($node, 'returnsDarFromArray')) {
            return CodeGenerator::getReturnsDarFromArray($node->var, $node->args);
        }
        if ($this->isCallToExpectOrStubFunctions($node)) {
            return $this->getFromExpectOrStub($node->var->args[0]->value, (string) $node->name, $node->args);
        }
    }

    private function isCallTo(Node $node, string $method_name): bool
    {
        return $node instanceof Node\Expr\MethodCall && (string) $node->name === $method_name;
    }

    private function isCallToExpectOrStubFunctions(Node $node): bool
    {
        return $node instanceof Node\Expr\MethodCall
            && $node->var instanceof Node\Expr\FuncCall
            && in_array((string) $node->var->name, ['expect', 'stub']);
    }

    private function getFromExpectOrStub(Node $mock_target, string $method_name, array $args)
    {
        if ($mock_target instanceof Node\Expr\Variable || $mock_target instanceof Node\Expr\PropertyFetch) {
            if (count($args) === 0) {
                return CodeGenerator::getShouldReceive($mock_target, $method_name);
            }
            return CodeGenerator::getWith(
                CodeGenerator::getShouldReceive($mock_target, $method_name),
                $args
            );
        }
        if ($mock_target instanceof Node\Scalar\String_) {
            $class_name = (string) $mock_target->value;
            return CodeGenerator::getWith(
                CodeGenerator::getShouldReceive(
                    CodeGenerator::getNewMockerySpy($class_name),
                    $method_name
                ),
                $args,
            );
        }
    }
}