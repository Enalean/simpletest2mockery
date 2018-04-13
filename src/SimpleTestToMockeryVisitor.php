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

/**
 * Class SimpleTestToMockeryVisitor
 *
 * @package Reflector
 */
class SimpleTestToMockeryVisitor extends NodeVisitorAbstract
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var string
     */
    private $filepath;

    /**
     * @var AbstractMockRepresentation
     */
    private $mock_representation;

    public function __construct(LoggerInterface $logger, string $filepath, array &$nodes_to_delete)
    {
        $this->logger          = $logger;
        $this->filepath        = $filepath;
        $this->mock_representation = new AbstractMockRepresentation($nodes_to_delete);
    }

    /**
     * @param Node $node
     * @return int|null|Node|Node[]|Node\Expr\FuncCall|Node\Expr\MethodCall|Node\Expr\New_|Node\Expr\StaticCall
     * @throws \Exception
     */
    public function leaveNode(Node $node)
    {
        if ($this->isATestMethod($node)) {
            $this->mock_representation->resetStack();
        }

        return $this->convertNode($node);
    }

    private function isATestMethod(Node $node)
    {
        return
            $node instanceof Node\Stmt\ClassMethod
            &&
                (
                    strpos((string)$node->name->name, 'test') !== false
                    ||
                    strpos((string)$node->name->name, 'it') !== false
                );
    }


    /**
     * @param Node $node
     * @return array|Node\Expr\MethodCall|void
     * @throws \Exception
     */
    private function convertNode(Node $node)
    {
        if ($node instanceof Node\Expr\MethodCall) {
            if (!method_exists($node->name, '__toString')) {
                $this->logger->warning("Method call on something we don't manage in $this->filepath at L" . $node->getLine());
                return;
            }

            $method_name = (string)$node->name;

            switch ($method_name) {
                case 'setReturnValue':
                case 'setReturnReference':
                    return $this->convertReturn($node);
                    break;

                case 'expectOnce':
                    return $this->convertExpectOnce($node);

                case 'expectNever':
                    return $this->convertExpectNever($node);

                case 'expectCallCount':
                    return $this->convertCallCount($node);

                case 'setReturnValueAt':
                case 'setReturnReferenceAt':
                    return $this->convertReturnAt($node);

                case 'expectAt':
                    return $this->convertExpectAt($node);
                case 'expect':
                case 'expectAtLeastOnce':
                case 'throwOn':
                case 'throwAt':
                    throw new \Exception("$method_name implementation missing in $this->filepath L".$node->getLine());
                    break;
            }
        }
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node
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

            $this->mock_representation->addReturns($node->var, $method_name, $returned_value->value, $arguments);

            return $this->mock_representation->generateCode($node->var, $method_name);
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return Node
     * @throws \Exception
     */
    private function convertExpectOnce(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 3) {
            $method_name = (string) $node->args[0]->value->value;
            $arguments = [];
            if (isset($node->args[1])) {
                if ($node->args[1]->value instanceof Node\Expr\ConstFetch && (string) $node->args[1]->value->name->parts[0] === 'false') {
                    $arguments = [];
                } elseif (isset($node->args[1]->value->items)) {
                    $arguments = $node->args[1]->value->items;
                } else {
                    throw new \Exception("Unhandled construction at  L".$node->getLine());
                }
            }

            $this->mock_representation->addExpectCallCount($node->var, $method_name, 1, $arguments);

            return $this->mock_representation->generateCode($node->var, $method_name);
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
            $method_args = [];
            if (isset($node->args[1])) {
                $method_args[] = $node->args[1];
            }

            $this->mock_representation->addExpectCallCount($node->var, $method_name, 0);

            return $this->mock_representation->generateCode($node->var, $method_name);
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
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
            $count = 0;
            if ($node->args[1]->value instanceof Node\Scalar) {
                $count = (int) $node->args[1]->value->value;
            } else {
                throw new \Exception("Un-managed call count at L".$node->getLine());
            }

            $this->mock_representation->addExpectCallCount($node->var, $method_name, $count);

            return $this->mock_representation->generateCode($node->var, $method_name);
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
    }

    private function convertReturnAt(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 3) {
            $count       = (int) $node->args[0]->value->value;
            $method_name = (string) $node->args[1]->value->value;
            $returned_value = $node->args[2];

            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\MethodCall(
                        $node->var,
                        'shouldReceive',
                        [new Node\Arg(new Node\Scalar\String_($method_name))]
                    ),
                    'once',
                    []
                ),
                'andReturns',
                [$returned_value]
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
    private function convertExpectAt(Node\Expr\MethodCall $node)
    {
        if (count($node->args) <= 3) {
            $timing         = (int) $node->args[0]->value->value;
            $method_name    = (string) $node->args[1]->value->value;
            if ($node->args[2]->value instanceof Node\Expr\ArrayDimFetch) {
                $this->logger->error("Unsupported construction in $this->filepath at L".$node->getLine());
                return $node;
            } else {
                $arguments = $node->args[2]->value->items;
            }

            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\MethodCall(
                        $node->var,
                        'shouldReceive',
                        [new Node\Arg(new Node\Scalar\String_($method_name))]
                    ),
                    'with',
                    $arguments
                ),
                'ordered'
            );
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
    }
}
