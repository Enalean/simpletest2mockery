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

use PhpParser\BuilderFactory;
use PhpParser\Node;
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
            if (!method_exists($node->name, '__toString')) {
                $this->logger->warning("Method call on something we don't manage in $this->filepath at L" . $node->getLine());
                return $node;
            }
            switch ((string)$node->name) {
                case 'setReturnValue':
                case 'setReturnReference':
                    //return $this->convertReturn($node);
                    break;

                case 'expectOnce':
                    return $this->convertExpectOnce($node);
            }
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


    private function convertNewMock(Node\Expr\New_ $node)
    {
        if ($node->class instanceof Node\Expr\Variable || $node->class instanceof Node\Expr\PropertyFetch) {
            $this->logger->warning("Instantiation based on variables not managed in $this->filepath at L".$node->getLine());
            return $node;
        }
        $instantiated_class = (string) $node->class;
        if (isset($this->mocks[$instantiated_class])) {
            return new Node\Expr\StaticCall(new Node\Name('\Mockery'), new Node\Name('spy'), [$this->getTargetClassName($instantiated_class)]);
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
            /*$message = [];
            if (isset($node->args[2])) {
                $message[] = $node->args[2];
            }*/

            //new BuilderFactory();


            $var_name = (string) $node->var->name;
            //var_dump($node->getAttribute('parent')->setAttribute('foo', 'bar'));
            foreach ($node->getAttribute('parent')->getAttribute('parent')->stmts as $stmt) {
                var_dump($stmt->getAttribute('foo'));
                /*if ($stmt === $node) {
                    var_dump('found');
                }*/
            }
            die('end');
            //var_dump($node->getAttribute('parent')->getAttribute('parent'));

            return [
                new Node\Expr\Assign(
                    new Node\Expr\Variable($var_name.'_mock'),
                    new Node\Expr\MethodCall(
                        $node->var,
                        'shouldReceive',
                        [
                            new Node\Arg(new Node\Scalar\String_($method_name))
                        ]
                    )
                ),
                new Node\Expr\MethodCall(
                    new Node\Expr\Variable($var_name.'_mock'),
                    'with',
                    $method_args
                )
            ];

            return
                new Node\Expr\Assign(
                    new Node\Expr\Variable($var_name.'_mock'),
                    new Node\Expr\MethodCall(
                        $node->var,
                        'shouldReceive',
                        [
                            new Node\Arg(new Node\Scalar\String_($method_name))
                        ]
                    )
                );
/*
            return new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\FuncCall(new Node\Name('expect'), [$node->var]),
                    $method_name,
                    $method_args
                ),
                'once',
                $message
            );*/
        } else {
            throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
        }
    }
}
