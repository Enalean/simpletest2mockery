<?php

namespace ST2Mockery;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class DirenameToDIRContVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node)
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
}
