<?php

namespace ST2Mockery;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class NormalizeSetUpAndTearDownVisitor extends NodeVisitorAbstract
{

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->name === 'setUp') {
            $setup_found = false;
            foreach ($node->stmts as $stmt) {
                if (isset($stmt->expr) &&
                    $stmt->expr instanceof Node\Expr\StaticCall &&
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
                if (isset($stmt->expr) &&
                    $stmt->expr instanceof Node\Expr\StaticCall &&
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
}
