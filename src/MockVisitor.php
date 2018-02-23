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

namespace Reflector;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class MockVisitor extends NodeVisitorAbstract
{
    /**
     * @var array
     */
    private $mocks = [];

    private $partial_mock = [];

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\StaticCall) {
            $this->recordGenerate($node);
        }

        if ($node instanceof Node\Expr\New_) {
            $instantiated_class = (string) $node->class;
            if (isset($this->mocks[$instantiated_class])) {
                return new Node\Expr\FuncCall(new Node\Name('mock'), [new Node\Arg(new Node\Scalar\String_($this->mocks[$instantiated_class]))]);
            }
            if (isset($this->partial_mock[$instantiated_class])) {
                return new Node\Expr\FuncCall(
                    new Node\Name('partial_mock'),
                    [
                        new Node\Arg(new Node\Scalar\String_($this->partial_mock[$instantiated_class]['class_name'])),
                        $this->partial_mock[$instantiated_class]['args'],
                    ]
                );
            }
        }

        if ($node instanceof Node\Expr\MethodCall) {
            if ((string)$node->name === 'setReturnValue') {
                return $this->convertReturn($node);
            }
            if ((string)$node->name === 'expectOnce') {
                return $this->convertExpectOnce($node);
            }
        }
    }

    private function recordGenerate(Node\Expr\StaticCall $node)
    {
        if ($this->isStaticCall($node, 'Mock', 'generate')) {
            $this->recordMockGenerate($node);
        }
        if ($this->isStaticCall($node, 'Mock', 'generatePartial')) {
            $this->recordMockGeneratePartial($node);
        }
    }

    private function isStaticCall(Node\Expr\StaticCall $node, string $class, string $method)
    {
        return (string)$node->class === $class && (string)$node->name === $method;
    }

    private function recordMockGenerate(Node\Expr\StaticCall $node)
    {
        if (count($node->args) === 1) {
            $class_name = (string) $node->args[0]->value->value;
            $this->mocks['Mock'.$class_name] = $class_name;
        }
    }

    private function recordMockGeneratePartial(Node\Expr\StaticCall $node)
    {
        if (count($node->args) === 3) {
            $class_name = (string) $node->args[0]->value->value;
            $mock_name  = (string) $node->args[1]->value->value;
            $mocked_methods = $node->args[2];
            $this->partial_mock[$mock_name] = [
                'class_name' => $class_name,
                'args'       => $mocked_methods,
            ];
        }
    }

    private function convertReturn(Node\Expr\MethodCall $node)
    {
        if (count($node->args) === 2) {
            $method_name = (string) $node->args[0]->value->value;
            $arguments = $node->args[1];
            return new Node\Expr\MethodCall(
                    new Node\Expr\MethodCall(
                        new Node\Expr\FuncCall(new Node\Name('stub'), [$node->var]),
                        $method_name
                    ),
                    'returns',
                    [$arguments->value]
            );
        }
    }

    private function convertExpectOnce(Node\Expr\MethodCall $node)
    {
        if (count($node->args) === 2) {
            $method_name = (string) $node->args[0]->value->value;
            $arguments = $node->args[1];
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('expect'), [$node->var]),
                    $method_name,
                    $arguments->value->items
                ),
                'once'
            );
        }
    }

    private function isMock(string $class_name)
    {
        foreach($this->mocks as $mock) {
            if ('Mock'.$mock === $class_name) {
                return $mock;
            }
        }
        return false;
    }
}