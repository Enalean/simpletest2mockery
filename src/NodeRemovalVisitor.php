<?php

namespace ST2Mockery;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class NodeRemovalVisitor extends NodeVisitorAbstract
{


    /**
     * @var array
     */
    private $common;

    public function __construct(array &$common)
    {
        $this->common =& $common;
    }


    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Expression && in_array($node->expr->getAttribute('X-Id'), $this->common)) {
            return NodeTraverser::REMOVE_NODE;
        }
        return $node;
    }
}