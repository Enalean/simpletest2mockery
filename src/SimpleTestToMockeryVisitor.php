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
use Psr\Log\LoggerInterface;

//$usDao  = \Mockery::spy(AdminDelegation_UserServiceDao::class);
//$stuff = $usDao->shouldReceive('addUserService');
//$stuff->with(112, AdminDelegation_Service::SHOW_PROJECT_ADMINS);
//$stuff->once();
//$stuff->andReturn(true);

class SimpleTestToMockeryVisitor extends NodeVisitorAbstract
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

    private $mocked_var_stack = [];

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
        // Replace include dirname(__FILE__) by __DIR__
        if ($node instanceof Node\Expr\Include_ &&
            $node->expr instanceof Node\Expr\BinaryOp\Concat &&
            $node->expr->left instanceof Node\Expr\FuncCall &&
            (string) $node->expr->left->name->parts[0] === 'dirname' &&
            $node->expr->left->args[0]->value instanceof Node\Scalar\MagicConst\File
        ) {
            $node->expr->left = new Node\Scalar\MagicConst\Dir();
        }
        // TODO: reset the method stack
        if ($node instanceof Node\Expr\New_) {
            $new_mock = $this->convertNewMock($node);
            if ($new_mock !== null) {
                return $new_mock;
            }
        }

        if ($node instanceof Node\Expr\FuncCall && (
                $node->name->parts[0] === 'mock')) {
            return $this->convertCallMockToMockerySpy($node);
        }

        if ($node instanceof Node\Stmt\Expression) {
            // TODO: remove static call via expression
            if ($node->expr instanceof Node\Expr\StaticCall) {
                if ($this->recordGenerate($node->expr) === null) {
                    return NodeTraverser::REMOVE_NODE;
                }
            }

            $new_nodes = $this->stuffNodes($node->expr);
            if (is_array($new_nodes)) {
                $new_stmts = [];
                $nb_nodes = count($new_nodes);
                for ($i = 0; $i < $nb_nodes; $i++) {
                    $new_stmts []= new Node\Stmt\Expression($new_nodes[$i]);
                }
                $new_stmts[($nb_nodes - 1)]->setAttributes($node->getAttributes());
                return $new_stmts;
            } elseif ($new_nodes instanceof Node\Expr) {
                return new Node\Stmt\Expression($new_nodes, $node->getAttributes());
            }
        }
    }

    private function convertCallMockToMockerySpy(Node\Expr\FuncCall $node)
    {
        if ($node->args[0]->value instanceof Node\Scalar\String_) {
            return $this->getNewMockerySpy((string) $node->args[0]->value->value);
        }
        return $node;
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

    private function recordMockGeneratePartial(Node\Expr\StaticCall $node)
    {
        if (count($node->args) === 3) {
            if (! isset($node->args[0]->value->value)) {
                throw new \Exception('Mock::generatePartial form not supported at L'.$node->getLine());
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
        throw new \Exception("Mock::generate form not supported at L".$node->getLine());
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

    private function convertNewMock(Node\Expr\New_ $node)
    {
        if ($node->class instanceof Node\Expr\Variable || $node->class instanceof Node\Expr\PropertyFetch) {
            $this->logger->warning("Instantiation based on variables not managed in $this->filepath at L".$node->getLine());
            return $node;
        }
        $instantiated_class = (string) $node->class;
        if (isset($this->mocks[$instantiated_class])) {
            if (isset($this->mocks[$instantiated_class]['args'])) {
                return $this->getNewMockeryPartialMock($this->mocks[$instantiated_class]['class_name']);
            } else {
                return $this->getNewMockerySpy($this->mocks[$instantiated_class]['class_name']);
            }
        }
    }

    private function getNewMockeryPartialMock(string $class_name)
    {
        return
            new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\StaticCall(
                        new Node\Name('\Mockery'),
                        new Node\Name('mock'),
                        [
                            new Node\Expr\ClassConstFetch(
                                new Node\Name($class_name),
                                new Node\Identifier('class')
                            )
                        ]
                    ),
                    'makePartial'
                ),
                'shouldAllowMockingProtectedMethods'
            );
    }

    private function getNewMockerySpy(string $class_name)
    {
        return
            new Node\Expr\StaticCall(
                new Node\Name('\Mockery'),
                new Node\Name('spy'),
                [
                    new Node\Expr\ClassConstFetch(
                        new Node\Name($class_name),
                        new Node\Identifier('class')
                    )
                ]
            );
    }

    private function stuffNodes(Node $node)
    {
        if ($node instanceof Node\Expr\MethodCall) {
            if (!method_exists($node->name, '__toString')) {
                $this->logger->warning("Method call on something we don't manage in $this->filepath at L" . $node->getLine());
                return $node;
            }
            switch ((string)$node->name) {
                case 'setReturnValue':
                case 'setReturnReference':
                    return $this->convertReturn($node);
                    break;

                case 'expectOnce':
                    return $this->convertExpectOnce($node);
            }
        }
        return $node;
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

            $var_name = (string) $node->var->name;

            $returns = $this->generateMockeryMock($node->var, $var_name, $method_name, $arguments);
            $returns []= new Node\Expr\MethodCall(
                new Node\Expr\Variable($this->mocked_var_stack[$var_name][$method_name]),
                'andReturns',
                [$returned_value->value]
            );
            return $returns;

        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
    }

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

            $var_name = (string) $node->var->name;

            $returns = $this->generateMockeryMock($node->var, $var_name, $method_name, $method_args);
            $returns []= new Node\Expr\MethodCall(
                new Node\Expr\Variable($this->mocked_var_stack[$var_name][$method_name]),
                'once'
            );
            return $returns;
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
    }

    private function generateMockeryMock(Node\Expr\Variable $var, string $original_var_name, string $method_name, array $method_args)
    {
        $returns = [];

        $mocked_method_var_name = $original_var_name.'_mock_'.$method_name;
        if (! isset($this->mocked_var_stack[$original_var_name][$method_name])) {
            $this->mocked_var_stack[$original_var_name][$method_name] = $mocked_method_var_name;
            $returns []=
                new Node\Expr\Assign(
                    new Node\Expr\Variable($mocked_method_var_name),
                    new Node\Expr\MethodCall(
                        $var,
                        'shouldReceive',
                        [
                            new Node\Arg(new Node\Scalar\String_($method_name))
                        ]
                    )
                );
        }
        if (count($method_args) > 0) {
            $returns []=
                new Node\Expr\MethodCall(
                    new Node\Expr\Variable($mocked_method_var_name),
                    'with',
                    $method_args
                );
        }

        return $returns;
    }
}
