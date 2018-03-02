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
use PhpParser\NodeTraverser;
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
        $this->convertDirnameFileToDirConst($node);

        $this->normalizeSetUpTearDown($node);

        $this->convertMockGenerate($node);

        if ($this->isATestMethod($node)) {
            $this->mocked_var_stack = [];
        }

        if ($node instanceof Node\Stmt\Expression) {
            return $this->inspectExpression($node);
        }
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
     * @param Node\Expr\StaticCall $node
     * @return null|Node\Expr\StaticCall
     * @throws \Exception
     */
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

    /**
     * @param Node\Expr\StaticCall $node
     * @return null
     * @throws \Exception
     */
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
            }
            return $this->getNewMockerySpy($this->mocks[$instantiated_class]['class_name']);
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

    /**
     * @param Node $node
     */
    private function convertDirnameFileToDirConst(Node $node)
    {
        if ($node instanceof Node\Expr\Include_ &&
            $node->expr instanceof Node\Expr\BinaryOp\Concat &&
            $node->expr->left instanceof Node\Expr\FuncCall &&
            (string)$node->expr->left->name->parts[0] === 'dirname' &&
            $node->expr->left->args[0]->value instanceof Node\Scalar\MagicConst\File
        ) {
            $node->expr->left = new Node\Scalar\MagicConst\Dir();
        }
    }

    /**
     * @param Node $node
     */
    private function normalizeSetUpTearDown(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->name === 'setUp') {
            $setup_found = false;
            foreach ($node->stmts as $stmt) {
                if ($stmt->expr instanceof Node\Expr\StaticCall &&
                    $stmt->expr->class->parts[0] === 'parent' &&
                    $stmt->expr->name->name === 'setUp'
                ) {
                    $setup_found = true;
                }
            }
            $new_stmts = [];
            if (!$setup_found) {
                $new_stmts [] = new Node\Stmt\Expression(
                    new Node\Expr\StaticCall(
                        new Node\Name(
                            'parent'
                        ),
                        new Node\Identifier(
                            'setUp'
                        )
                    )
                );
                $node->stmts = array_merge($new_stmts, $node->stmts);
            }
        }

        if ($node instanceof Node\Stmt\ClassMethod && $node->name->name === 'tearDown') {
            $teardown_found = false;
            foreach ($node->stmts as $stmt) {
                if ($stmt->expr instanceof Node\Expr\StaticCall &&
                    $stmt->expr->class->parts[0] === 'parent' &&
                    $stmt->expr->name->name === 'tearDown'
                ) {
                    $teardown_found = true;
                }
            }
            if (!$teardown_found) {
                $node->stmts [] = new Node\Stmt\Expression(
                    new Node\Expr\StaticCall(
                        new Node\Name(
                            'parent'
                        ),
                        new Node\Identifier(
                            'tearDown'
                        )
                    )
                );
            }
        }
    }

    /**
     * @param Node $node
     * @throws \Exception
     */
    private function convertMockGenerate(Node $node)
    {
        if ($node instanceof Node\Expr\Assign) {
            $new_mock = null;
            if ($node->expr instanceof Node\Expr\New_) {
                $new_mock = $this->convertNewMock($node->expr);
            }
            if ($node->expr instanceof Node\Expr\FuncCall) {
                switch ($node->expr->name->parts[0]) {
                    case 'mock':
                        $new_mock = $this->convertCallMockToMockerySpy($node->expr);
                        break;

                    case 'partial_mock':
                        $new_mock = $this->convertCallMockToMockeryPartial($node->expr);
                }

            }
            if ($new_mock !== null) {
                $node->expr = $new_mock;
                $this->resetMethodStack($node->var);
            }
        }
    }


    private function convertCallMockToMockerySpy(Node\Expr\FuncCall $node)
    {
        if ($node->args[0]->value instanceof Node\Scalar\String_) {
            return $this->getNewMockerySpy((string) $node->args[0]->value->value);
        }
        return null;
    }

    private function convertCallMockToMockeryPartial(Node\Expr\FuncCall $node)
    {
        if ($node->args[0]->value instanceof Node\Scalar\String_) {
            return $this->getNewMockeryPartialMock((string) $node->args[0]->value->value);
        }
        return null;
    }

    /**
     * @param Node $node
     * @return array|int|Node\Stmt\Expression
     * @throws \Exception
     */
    private function inspectExpression(Node $node)
    {
        if ($node->expr instanceof Node\Expr\StaticCall) {
            if ($this->recordGenerate($node->expr) === null) {
                return NodeTraverser::REMOVE_NODE;
            }
        }

        $new_node = $this->convertNode($node->expr);
        if (is_array($new_node)) {
            return $this->convertNodesToExpressions($node, $new_node);
        } elseif ($new_node instanceof Node\Expr) {
            return new Node\Stmt\Expression($new_node, $node->getAttributes());
        } elseif ($new_node !== null) {
            throw new \Exception('Return of convertNode not handled');
        }
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
                case 'expectAt':
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
     * @return array
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

            $returns = $this->generateMockeryMock($node->var, $method_name, $arguments);
            $returns []= new Node\Expr\MethodCall(
                new Node\Expr\Variable($this->getMockedVarName($node->var, $method_name)),
                'andReturns',
                [$returned_value->value]
            );
            return $returns;
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return array
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

            $returns = $this->generateMockeryMock($node->var, $method_name, $method_args);
            $returns []= new Node\Expr\MethodCall(
                new Node\Expr\Variable($this->getMockedVarName($node->var, $method_name)),
                'once'
            );
            return $returns;
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

            $returns = $this->generateMockeryMock($node->var, $method_name, $method_args);
            $returns []= new Node\Expr\MethodCall(
                new Node\Expr\Variable($this->getMockedVarName($node->var, $method_name)),
                'never'
            );
            return $returns;
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @return array
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

            $returns = $this->generateMockeryMock($node->var, $method_name, []);
            $returns []= new Node\Expr\MethodCall(
                new Node\Expr\Variable($this->getMockedVarName($node->var, $method_name)),
                'times',
                $count
            );
            return $returns;
        }
        throw new \Exception("Un-managed number of arguments for expectCallCount at L".$node->getLine());
    }

    /**
     * @param Node\Expr $node
     * @param string $method_name
     * @param array $method_args
     * @return array
     * @throws \Exception
     */
    private function generateMockeryMock(Node\Expr $node, string $method_name, array $method_args)
    {
        $returns = [];

        $mocked_method_var_name = $this->getMockedVarName($node, $method_name);
        if ($mocked_method_var_name === null) {
            $this->generateMockedVarName($node, $method_name);
            $returns []=
                new Node\Expr\Assign(
                    new Node\Expr\Variable($this->getMockedVarName($node, $method_name)),
                    new Node\Expr\MethodCall(
                        $node,
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
                    new Node\Expr\Variable($this->getMockedVarName($node, $method_name)),
                    'with',
                    $method_args
                );
        }

        return $returns;
    }

    private function getMockedVarName(Node\Expr $node, string $method_name)
    {
        if ($node instanceof Node\Expr\Variable) {
            $original_var_name = (string) $node->name;
            if (isset($this->mocked_var_stack[$original_var_name][$method_name])) {
                return $this->mocked_var_stack[$original_var_name][$method_name];
            }
        } elseif ($node instanceof Node\Expr\PropertyFetch && (string) $node->var->name === 'this') {
            $original_var_name = (string) $node->name->name;
            if (isset($this->mocked_var_stack['this'][$original_var_name][$method_name])) {
                return $this->mocked_var_stack['this'][$original_var_name][$method_name];
            }
        } else {
            throw new \Exception('getMockedVarName on unhandled expression at L'.$node->getLine());
        }
        return null;
    }

    /**
     * @param Node\Expr $node
     * @param string $method_name
     * @return string
     * @throws \Exception
     */
    private function generateMockedVarName(Node\Expr $node, string $method_name)
    {
        if ($node instanceof Node\Expr\Variable) {
            $original_var_name = (string) $node->name;
            $this->mocked_var_stack[$original_var_name][$method_name] = $original_var_name.'_'.$method_name;
            return $this->mocked_var_stack[$original_var_name][$method_name];
        } elseif ($node instanceof Node\Expr\PropertyFetch && (string) $node->var->name === 'this') {
            $original_var_name = (string) $node->name->name;
            $this->mocked_var_stack['this'][$original_var_name][$method_name] = 'this_'.$original_var_name.'_'.$method_name;
            return $this->mocked_var_stack['this'][$original_var_name][$method_name];
        } else {
            throw new \Exception('getMockedVarName on unhandled expression at L'.$node->getLine());
        }
    }

    /**
     * @param Node\Expr $node
     * @throws \Exception
     */
    private function resetMethodStack(Node\Expr $node)
    {
        if ($node instanceof Node\Expr\Variable) {
            $var_name = (string) $node->name;
            unset($this->mocked_var_stack[$var_name]);
        } elseif ($node instanceof Node\Expr\PropertyFetch && (string) $node->var->name === 'this') {
            $var_name = (string) $node->name->name;
            unset($this->mocked_var_stack['this'][$var_name]);
        } else {
            throw new \Exception('resetMethodStack on unhandled expression at L'.$node->getLine());
        }
    }

    /**
     * @param Node $original_node
     * @param array $new_node
     * @return array
     */
    private function convertNodesToExpressions(Node $original_node, array $new_node)
    {
        $new_stmts = [];
        $nb_nodes = count($new_node);
        for ($i = 0; $i < $nb_nodes; $i++) {
            $new_stmts [] = new Node\Stmt\Expression($new_node[$i]);
        }
        $new_stmts[($nb_nodes - 1)]->setAttributes($original_node->getAttributes());
        return $new_stmts;
    }
}
