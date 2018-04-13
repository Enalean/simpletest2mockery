<?php

namespace ST2Mockery;

use PhpParser\Node;

class AbstractMockRepresentation
{
    private $op_stack     = [];
    private $nodeUniqueId = 1;
    /**
     * @var array
     */
    private $nodes_to_delete;

    public function __construct(array &$nodes_to_delete)
    {
        $this->nodes_to_delete =& $nodes_to_delete;
    }

    public function resetStack()
    {
        $this->op_stack = [];
    }

    public function addReturns(Node\Expr $variable, $method_name, $value, array $arguments = [])
    {
        $this->addArguments($variable, $method_name, $arguments);
        $this->op_stack[$this->getVarName($variable)][$method_name]['return'] = $value;
    }


    public function addExpectCallCount(Node\Expr $variable, $method_name, int $count, array $arguments = [])
    {
        $this->addArguments($variable, $method_name, $arguments);
        $this->op_stack[$this->getVarName($variable)][$method_name]['count'] = $count;
    }

    private function addArguments(Node\Expr $variable, $method_name, array $arguments)
    {
        if (\count($arguments) > 0) {
            if (isset($this->op_stack[$this->getVarName($variable)][$method_name]['args'])) {
                unset($this->op_stack[$this->getVarName($variable)][$method_name]);
            }
            $this->op_stack[$this->getVarName($variable)][$method_name]['args'] = $arguments;
        }
    }

    public function generateCode(Node\Expr $node, string $method_name)
    {
        $var_name = $this->getVarName($node);
        if (isset($this->op_stack[$var_name][$method_name]['node'])) {
            $this->nodes_to_delete[] = $this->op_stack[$var_name][$method_name]['node']->getAttribute('X-Id');
        }
        $new_node = $this->generateShouldReceive($node, $method_name);
        $new_node->setAttribute('X-Id', $this->nodeUniqueId++);
        $this->op_stack[$var_name][$method_name]['node'] = $new_node;
        return $new_node;
    }

    private function generateShouldReceive(Node\Expr $original_node, string $method_name)
    {
        $variable = new Node\Expr\MethodCall(
            $original_node,
            'shouldReceive',
            [
                new Node\Arg(new Node\Scalar\String_($method_name))
            ]
        );

        if (isset($this->op_stack[$this->getVarName($original_node)][$method_name]['args'])) {
            $variable = $this->generateWith($variable, $this->op_stack[$this->getVarName($original_node)][$method_name]['args']);
        }
        if (isset($this->op_stack[$this->getVarName($original_node)][$method_name]['count'])) {
            $variable = $this->generateCount($variable, $this->op_stack[$this->getVarName($original_node)][$method_name]['count']);
        }
        if (isset($this->op_stack[$this->getVarName($original_node)][$method_name]['return'])) {
            $variable = $this->generateAndReturns($variable, $this->op_stack[$this->getVarName($original_node)][$method_name]['return']);
        }
        return $variable;
    }

    private function generateAndReturns(Node\Expr\MethodCall $node, $returned_value)
    {
        return new Node\Expr\MethodCall($node, 'andReturns', [$returned_value]);
    }

    private function generateWith(Node\Expr\MethodCall $node, array $method_args)
    {
        return new Node\Expr\MethodCall($node, 'with', $method_args);
    }

    private function generateCount(Node\Expr\MethodCall $node, int $count)
    {
        switch ($count){
            case 0:
                return new Node\Expr\MethodCall($node, 'never');
            case 1:
                return new Node\Expr\MethodCall($node, 'once');
            default:
                return new Node\Expr\MethodCall($node, 'times', [new Node\Arg(new Node\Scalar\LNumber($count))]);
        }
    }

    private function getVarName(Node\Expr $node)
    {
        if ($node instanceof Node\Expr\Variable) {
            return (string)$node->name;
        }
        if ($node instanceof Node\Expr\PropertyFetch && (string) $node->var->name === 'this') {
            return 'this.'.(string) $node->name->name;
        }
    }

    private function getArgumentSignature(array $arguments = [])
    {
        if (\count($arguments) === 0) {
            return '-';
        }
        return $this->serializeArguments($arguments);
    }

    private function serializeArguments(array $arguments)
    {
        $params = [];
        foreach ($arguments as $argument) {
            if ($argument->value instanceof Node\Expr\Array_) {
                $params [] = 'a('.$this->serializeArguments($argument->value->items).')';
            } elseif ($argument->value instanceof Node\Scalar) {
                $params []= (string) $argument->value->value;
            } else {
                throw new \RuntimeException("Unsupported type of method parameter: ".get_class($argument));
            }
        }
        return implode(', ', $params);
    }
}
