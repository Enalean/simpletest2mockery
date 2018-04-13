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
    private $nodes_to_delete;

    public function __construct(array &$nodes_to_delete)
    {
        $this->nodes_to_delete =& $nodes_to_delete;
    }


    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Expression && in_array($node->expr->getAttribute('X-Id'), $this->nodes_to_delete)) {
            return NodeTraverser::REMOVE_NODE;
        }
        return $node;
    }
}