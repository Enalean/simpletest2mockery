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
use PhpParser\NodeVisitorAbstract;

class MockVisitor extends NodeVisitorAbstract
{
    /**
     * @var array
     */
    private $mocks = [];

    /**
     * @param Node $node
     * @return int|null|Node|Node[]|Node\Expr\FuncCall|Node\Expr\MethodCall|Node\Expr\New_|Node\Expr\StaticCall
     * @throws \Exception
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\StaticCall) {
            return $this->recordGenerate($node);
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
                case 'setReturnReferenceAt':
                    return $this->convertReturnAt($node);
                case 'expectOnce':
                    return $this->convertExpectOnce($node);
                case 'expectNever':
                    return $this->convertExpectNever($node);
                case 'expectCallCount':
                    return $this->convertCallCount($node);
                case 'expect':
                case 'expectAt':
                case 'expectAtLeastOnce':
                case 'throwOn':
                case 'throwAt':
                    throw new \Exception("Implementation is missing for ".(string)$node->name." at L".$node->getLine());
                    break;
                case 'expectMaximumCallCount':
                case 'expectMinimumCallCount':
                case 'errorOn':
                case 'errorAt':
                    throw new \Exception("Those methods should not be used ".(string)$node->name);
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
            return $this->recordMockGenerate($node);
        }
        if ($this->isStaticCall($node, 'Mock', 'generatePartial')) {
            return $this->recordMockGeneratePartial($node);
        }
        return $node;
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
            return null;
        }
        return $node;
    }

    private function recordMockGeneratePartial(Node\Expr\StaticCall $node)
    {
        if (count($node->args) === 3) {
            if (! isset($node->args[0]->value->value)) {
                // Maybe a partial mock abstraction => do not manage that
                return $node;
            }
            $class_name = (string) $node->args[0]->value->value;
            $mock_name  = (string) $node->args[1]->value->value;
            $mocked_methods = $node->args[2];
            $this->mocks[$mock_name] = [
                'class_name' => $class_name,
                'args'       => new Node\Arg(
                    new Node\Expr\Array_($mocked_methods->value->items)
                ),
            ];
            return null;
        }
        return $node;
    }

    private function convertNewMock(Node\Expr\New_ $node)
    {
        if ($node->class instanceof Node\Expr\Variable) {
            // Instantiation based on variables not managed
            return $node;
        }
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

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node\Expr\MethodCall
     * @throws \Exception
     */
    private function convertReturn(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 3) {
            $method_name = (string) $node->args[0]->value->value;
            $returned_value = $node->args[1];
            $arguments = [];
            if (isset($node->args[2])) {
                if (isset($node->args[2]->value->items)) {
                    $arguments = $node->args[2]->value->items;
                } elseif ($node->args[2]->value instanceof Node\Expr\Variable) {
                    $arguments[] = new Node\Arg($node->args[2]->value);
                }
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
        } else {
            throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
        }
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node\Expr\MethodCall
     * @throws \Exception
     */
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
        } else {
            throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
        }
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node\Expr\MethodCall
     * @throws \Exception
     */
    private function convertExpectOnce(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 3) {
            $method_name = (string) $node->args[0]->value->value;
            $method_args = [];
            if (isset($node->args[1])) {
                if ($node->args[1]->value instanceof Node\Expr\ConstFetch && (string) $node->args[1]->value->name->parts[0] === 'false') {
                    $method_args = [];
                } elseif (isset($node->args[1]->value->items)) {
                    $method_args = $node->args[1]->value->items;
                } else {
                    throw new \Exception("Unhandled construction at  L".$node->getLine());
                }
            }
            $message = [];
            if (isset($node->args[2])) {
                $message[] = $node->args[2];
            }
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('expect'), [$node->var]),
                    $method_name,
                    $method_args
                ),
                'once',
                $message
            );
        } else {
            throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
        }
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node\Expr\MethodCall
     * @throws \Exception
     */
    private function convertCallCount(Node\Expr\MethodCall $node)
    {
        if (count($node->args) === 2) {
            $method_name = (string) $node->args[0]->value->value;
            $count = [];
            if ($node->args[1]->value instanceof Node\Scalar\LNumber) {
                $count[] = $node->args[1];
            }
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('expect'), [$node->var]),
                    $method_name
                ),
                'count',
                $count
            );
        } else {
            throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
        }
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node\Expr\MethodCall
     * @throws \Exception
     */
    private function convertExpectNever(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 2) {
            $method_name = (string) $node->args[0]->value->value;
            $arguments = [];
            if (isset($node->args[1])) {
                $arguments[] = $node->args[1];
            }
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('expect'), [$node->var]),
                    $method_name
                ),
                'never',
                $arguments
            );
        } else {
            throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
        }
    }
}