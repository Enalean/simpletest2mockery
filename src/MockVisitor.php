<?php declare(strict_types=1);
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
use Psr\Log\LoggerInterface;

class MockVisitor extends NodeVisitorAbstract
{
    /**
     * @var array
     */
    private $mocks = [];
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var string
     */
    private $filepath;

    public function __construct(LoggerInterface $logger, string $filepath)
    {
        $this->logger   = $logger;
        $this->filepath = $filepath;
    }

    /**
     * @param Node $node
     * @return int|null|Node|Node[]|Node\Expr\FuncCall|Node\Expr\MethodCall|Node\Expr\New_|Node\Expr\StaticCall
     * @throws \Exception
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\AssignRef) {
            return $this->convertAssignRef($node);
        }

        if ($node instanceof Node\Expr\StaticCall) {
            return $this->recordGenerate($node);
        }

        if ($node instanceof Node\Expr\New_) {
            $new_mock = $this->convertNewMock($node);
            if ($new_mock !== null) {
                return $new_mock;
            }
        }

        if ($node instanceof Node\Expr\FuncCall && (
            $node->name->parts[0] === 'mock' ||
            $node->name->parts[0] === 'partial_mock' ||
            $node->name->parts[0] === 'stub')) {
            return $this->convertCallStringToClassConst($node);
        }

        if ($node instanceof Node\Expr\MethodCall) {
            if (! method_exists($node->name, '__toString')) {
                $this->logger->warning("Method call on something we don't manage in $this->filepath at L".$node->getLine());
                return $node;
            }
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
                case 'expectAt':
                    return $this->convertExpectAt($node);
                case 'expect':
                    return $this->convertExpect($node);
                case 'expectAtLeastOnce':
                    return $this->convertExpectAtLeastOnce($node);
                case 'throwOn':
                    return $this->convertThrowOn($node);
                case 'throwAt':
                    return $this->convertThrowsAt($node);
                    break;
                case 'expectMaximumCallCount':
                case 'expectMinimumCallCount':
                case 'errorOn':
                case 'errorAt':
                    throw new \Exception("Those methods should not be used ".(string)$node->name);
            }
        }
    }

    private function convertCallStringToClassConst(Node\Expr\FuncCall $node)
    {
        if ($node->args[0]->value instanceof Node\Scalar\String_) {
            $node->args[0]->value = new Node\Expr\ClassConstFetch(
                new Node\Name((string) $node->args[0]->value->value),
                new Node\Identifier('class')
            );
        }
        return $node;
    }

    private function convertAssignRef(Node $node)
    {
        if ($node->expr instanceof Node\Expr\New_) {
            return new Node\Expr\Assign(
                $node->var,
                $node->expr
            );
        }
        if ($node->expr instanceof Node\Expr\FuncCall && $node->expr->name->parts[0] === 'mock') {
            return new Node\Expr\Assign(
                $node->var,
                $node->expr
            );
        }
        return $node;
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
        if (count($node->args) <= 2) {
            $class_name = (string) $node->args[0]->value->value;
            if (isset($node->args[1])) {
                $mock_name = (string) $node->args[1]->value->value;
            } else {
                $mock_name = 'Mock'.$class_name;
            }
            $this->mocks[$mock_name] = [
                'class_name' => $class_name,
            ];
            return null;
        }
        $this->logger->error("Mock::generate form not supported in $this->filepath at L".$node->getLine());
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
        } else {
            throw new \Exception("Mock::generate form not supported at L".$node->getLine());
        }
        return $node;
    }

    private function convertNewMock(Node\Expr\New_ $node)
    {
        if ($node->class instanceof Node\Expr\Variable || $node->class instanceof Node\Expr\PropertyFetch) {
            $this->logger->warning("Instantiation based on variables not managed in $this->filepath at L".$node->getLine());
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
            throw new \Exception("Un-managed number of arguments for returnAt at L".$node->getLine());
        }
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node\Expr\MethodCall
     * @throws \Exception
     */
    private function convertExpect(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 2) {
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
            return
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('expect'), [$node->var]),
                    $method_name,
                    $method_args
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
        if (count($node->args) >= 2) {
            if (count($node->args) === 3) {
                $this->logger->warning("Comment discarded on expectCallCount in $this->filepath at L".$node->getLine());
            }
            $method_name = (string) $node->args[0]->value->value;
            $count = [];
            if ($node->args[1]->value instanceof Node\Scalar) {
                if ($node->args[1]->value instanceof Node\Scalar\LNumber) {
                    $count[] = $node->args[1];
                } else {
                    $count[] = new Node\Arg(new Node\Scalar\LNumber((int) $node->args[1]->value->value));
                }
            } else {
                throw new \Exception("Un-managed call count at L".$node->getLine());
            }
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('expect'), [$node->var]),
                    $method_name
                ),
                'count',
                $count
            );
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
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

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node\Expr\MethodCall
     * @throws \Exception
     */
    private function convertExpectAt(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 3) {
            $timing         = (int) $node->args[0]->value->value;
            $method_name    = (string) $node->args[1]->value->value;
            if ($node->args[2]->value instanceof Node\Expr\ArrayDimFetch) {
                $this->logger->error("Unsupported construction in $this->filepath at L".$node->getLine());
                return $node;
            } else {
                $returned_value = $node->args[2]->value->items;
            }
            return
                new Node\Expr\MethodCall(
                    new Node\Expr\MethodCall(
                        new Node\Expr\FuncCall(new Node\Name('expect'), [$node->var]),
                        $method_name,
                        $returned_value
                    ),
                    'at',
                    [
                        new Node\Arg(new Node\Scalar\LNumber($timing))
                    ]
                );
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node\Expr\MethodCall
     * @throws \Exception
     */
    private function convertExpectAtLeastOnce(Node\Expr\MethodCall $node)
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
                'atLeastOnce',
                $message
            );
        }
        throw new \Exception("Un-managed number of arguments for convertExpectAtLeastOnce at L".$node->getLine());
    }

    private function convertThrowOn(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 3) {
            $method_name = (string) $node->args[0]->value->value;
            $exception = [];
            if (isset($node->args[1])) {
                $exception[] = $node->args[1];
            }
            $method_args = [];
            if (isset($node->args[2])) {
                if ($node->args[2]->value instanceof Node\Expr\ConstFetch && (string) $node->args[2]->value->name->parts[0] === 'false') {
                    $method_args = [];
                } elseif (isset($node->args[2]->value->items)) {
                    $method_args = $node->args[2]->value->items;
                } else {
                    throw new \Exception("Unhandled construction at  L".$node->getLine());
                }
            }
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('stub'), [$node->var]),
                    $method_name,
                    $method_args
                ),
                'throws',
                $exception
            );
        }
        throw new \Exception("Un-managed number of arguments for convertThrowOn at L".$node->getLine());
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node\Expr\MethodCall
     * @throws \Exception
     */
    private function convertThrowsAt(Node\Expr\MethodCall $node)
    {
        if (count($node->args) === 3) {
            $timing         = (int) $node->args[0]->value->value;
            $method_name    = (string) $node->args[1]->value->value;
            return
                new Node\Expr\MethodCall(
                    new Node\Expr\MethodCall(
                        new Node\Expr\FuncCall(new Node\Name('stub'), [$node->var]),
                        $method_name
                    ),
                    'throwsAt',
                    [
                        new Node\Arg(new Node\Scalar\LNumber($timing)),
                        $node->args[2]
                    ]
                );
        }
        throw new \Exception("Un-managed number of arguments for convertThrowsAt at L".$node->getLine());
    }
}