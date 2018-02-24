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

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\StaticCall) {
            $this->recordGenerate($node);
        }

        if ($node instanceof Node\Expr\New_) {
            $new_mock = $this->convertNewMock($node);
            if ($new_mock !== null) {
                return $new_mock;
            }
        }

        if ($node instanceof Node\Expr\MethodCall) {
            switch ((string)$node->name) {
                case 'setReturnValue':
                case 'setReturnReference':
                    return $this->convertReturn($node);
                case 'setReturnValueAt':
                    return $this->convertReturnAt($node);
                case 'expectOnce':
                    return $this->convertExpect($node, 'once');
                case 'expectNever':
                    return $this->convertExpect($node, 'never');

            }
        }
    }

    private function getTargetClassName(string $instantiated_class)
    {
        return new Node\Name($this->mocks[$instantiated_class]['class_name'].'::class');
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
            $this->mocks['Mock'.$class_name] = [
                'class_name' => $class_name,
            ];
        }
    }

    private function recordMockGeneratePartial(Node\Expr\StaticCall $node)
    {
        if (count($node->args) === 3) {
            $class_name = (string) $node->args[0]->value->value;
            $mock_name  = (string) $node->args[1]->value->value;
            $mocked_methods = $node->args[2];
            $this->mocks[$mock_name] = [
                'class_name' => $class_name,
                'args'       => new Node\Arg(
                    new Node\Expr\Array_($mocked_methods->value->items)
                ),
            ];
        }
    }

    private function convertNewMock(Node\Expr\New_ $node)
    {
        $instantiated_class = (string) $node->class;
        if (isset($this->mocks[$instantiated_class])) {
            if (isset($this->mocks[$instantiated_class]['args'])) {
                return new Node\Expr\FuncCall(
                    new Node\Name('partial_mock'),
                    [
                        $this->getTargetClassName($instantiated_class),
                        $this->mocks[$instantiated_class]['args'],
                    ]
                );
            } else {
                return new Node\Expr\FuncCall(new Node\Name('mock'), [$this->getTargetClassName($instantiated_class)]);
            }
        }
    }

    private function convertReturn(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 3) {
            $method_name = (string) $node->args[0]->value->value;
            $returned_value = $node->args[1];
            $arguments = [];
            if (isset($node->args[2])) {
                $arguments = $node->args[2]->value->items;
            }
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('stub'), [$node->var]),
                    $method_name,
                    $arguments
                ),
                'returns',
                [$returned_value->value]
            );
        }
    }

    private function convertReturnAt(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 3) {
            $count       = (int) $node->args[0]->value->value;
            $method_name = (string) $node->args[1]->value->value;
            $returned_value = $node->args[2];
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('stub'), [$node->var]),
                    $method_name
                ),
                'returnsAt',
                [
                    new Node\Arg(new Node\Scalar\LNumber($count)),
                    $returned_value->value
                ]
            );
        }
    }

    private function convertExpect(Node\Expr\MethodCall $node, $occurence)
    {
        if (count($node->args) <= 2) {
            $method_name = (string) $node->args[0]->value->value;
            $arguments = [];
            if (isset($node->args[1])) {
                $arguments = $node->args[1]->value->items;
            }
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('expect'), [$node->var]),
                    $method_name,
                    $arguments
                ),
                $occurence
            );
        }
    }
}