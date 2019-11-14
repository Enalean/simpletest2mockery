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
        if ($node instanceof Node\Expr\FuncCall && (string)$node->name === 'stub') {
            if ($node->args[0]->value instanceof Node\Scalar\String_) {
                $class_name = (string) $node->args[0]->value->value;
                $node->args[0] = new Node\Arg(
                    new Node\Expr\ClassConstFetch(
                        new Node\Name('\\'.$class_name),
                        new Node\Identifier('class')
                    )
                );

                $node->name = new Node\Name('mockery_stub');

                return $node;
            }
        }

        if ($node instanceof Node\Expr\MethodCall) {
            if ($node->var instanceof Node\Expr\FuncCall && (string) $node->var->name === 'expect') {
                if ($node->var->args[0]->value instanceof Node\Expr\Variable || $node->var->args[0]->value instanceof Node\Expr\PropertyFetch) {
                    $method_name = (string) $node->name;
                    if (count($node->args) === 0) {
                        return $this->getShouldReceiveFromExpect($node, $method_name);
                    }
                    return new Node\Expr\MethodCall(
                        $this->getShouldReceiveFromExpect($node, $method_name),
                        'with',
                        $node->args
                    );
                }
            }
        }
    }

    private function getShouldReceiveFromExpect(Node $node, string $method_name): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            $node->var->args[0]->value,
            'shouldReceive',
            [new Node\Arg(new Node\Scalar\String_($method_name))]
        );
    }
}