<?php

namespace ST2Mockery;

use Mockery\MethodCall;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerInterface;

class ConvertMockGenerationVisitor extends NodeVisitorAbstract
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
     * @return int|null|Node|Node[]
     * @throws \Exception
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Expression) {
            if ($node->expr instanceof Node\Expr\StaticCall) {
                if ($this->recordGenerate($node->expr) === null) {
                    return NodeTraverser::REMOVE_NODE;
                }
            }
        } else {
            return $this->convertMockGenerate($node);
        }
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
        $this->checkInstantiatedClass($instantiated_class);
        if (isset($this->mocks[$instantiated_class])) {
            if (isset($this->mocks[$instantiated_class]['args'])) {
                return $this->getNewMockeryPartialMock($this->mocks[$instantiated_class]['class_name']);
            }
            return $this->getNewMockerySpy($this->mocks[$instantiated_class]['class_name']);
        }
    }

    private function getNewMockeryPartialMock(string $class_name)
    {
        $this->checkInstantiatedClass($class_name);
        return
            new Node\Expr\MethodCall(
                new Node\Expr\MethodCall(
                    new Node\Expr\StaticCall(
                        new Node\Name('\Mockery'),
                        new Node\Name('mock'),
                        [
                            new Node\Expr\ClassConstFetch(
                                new Node\Name('\\'.$class_name),
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
        $this->checkInstantiatedClass($class_name);
        return
            new Node\Expr\StaticCall(
                new Node\Name('\Mockery'),
                new Node\Name('spy'),
                [
                    new Node\Expr\ClassConstFetch(
                        new Node\Name('\\'.$class_name),
                        new Node\Identifier('class')
                    )
                ]
            );
    }

    /**
     * @param Node $node
     * @throws \Exception
     */
    private function convertMockGenerate(Node $node)
    {
        $new_mock = null;
        if ($node instanceof Node\Expr\New_) {
            $new_mock = $this->convertNewMock($node);
        }
        if ($node instanceof Node\Expr\StaticCall && (string)$node->class === 'TestHelper' && (string) $node->name === 'getPartialMock') {
            $new_mock = $this->getNewMockeryPartialMock((string)$node->args[0]->value->value);
        }
        if ($node instanceof Node\Expr\FuncCall) {
            switch ($node->name->parts[0]) {
                case 'mock':
                    $new_mock = $this->convertCallMockToMockerySpy($node);
                    break;

                case 'partial_mock':
                    $new_mock = $this->convertCallMockToMockeryPartial($node);
            }

        }
        if ($new_mock !== null) {
            return $new_mock;
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

    private function checkInstantiatedClass(string $class_name)
    {
        if ($class_name === 'DataAccessResult') {
            throw new \RuntimeException('Direct mocking of DataAccessResult should be converted to stub(x)->method()->returnDar() first');
        }
    }
}
