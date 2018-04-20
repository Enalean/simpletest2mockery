<?php

namespace ST2Mockery;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class NormalizeSetUpAndTearDownVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node)
    {
        $this->normalizeSetUp($node);
        $this->normalizeTearDown($node);
    }

    private function normalizeSetUp(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->name === 'setUp') {
            $setup_found                 = false;
            $setup_globals_mockery_found = false;
            if ($node instanceof Node\Stmt\ClassMethod && $node->name->name === 'setUp') {
                foreach ($node->stmts as $index => $stmt) {
                    if (isset($stmt->expr) &&
                        $stmt->expr instanceof Node\Expr\StaticCall &&
                        $stmt->expr->class->parts[0] === 'parent' &&
                        $stmt->expr->name->name === 'setUp'
                    ) {
                        $setup_found = true;
                    }

                    if (isset($stmt->expr) &&
                        $stmt->expr instanceof Node\Expr\MethodCall &&
                        $stmt->expr->var instanceof Node\Expr\Variable &&
                        (string) $stmt->expr->var->name === 'this' &&
                        (string) $stmt->expr->name === 'setUpGlobalsMockery'
                    ) {
                        $setup_globals_mockery_found = true;
                    }
                }
            }

            $this->addMissingSetUpStatments($node, $setup_found, $setup_globals_mockery_found);
        }
    }

    private function addMissingSetUpStatments(Node $node, bool $setup_found, bool $setup_globals_mockery_found)
    {
        $new_stmts = [];
        if (! $setup_found && ! $setup_globals_mockery_found) {
            $new_stmts [] = new Node\Stmt\Expression(
                new Node\Expr\StaticCall(
                    new Node\Name('parent'),
                    new Node\Name('setUp')
                )
            );
            $new_stmts [] = new Node\Stmt\Expression(
                new Node\Expr\MethodCall(
                    new Node\Expr\Variable('this'),
                    'setUpGlobalsMockery'
                )
            );
        }

        foreach ($node->stmts as $stmt) {
            if (! $setup_globals_mockery_found &&
                isset($stmt->expr) &&
                $stmt->expr instanceof Node\Expr\StaticCall &&
                (string)$stmt->expr->class === 'parent' &&
                (string)$stmt->expr->name === 'setUp') {
                $new_stmts []= $stmt;
                $new_stmts [] = new Node\Stmt\Expression(
                    new Node\Expr\MethodCall(
                        new Node\Expr\Variable('this'),
                        'setUpGlobalsMockery'
                    )
                );
            } else {
                $new_stmts []= $stmt;
            }
        }
        $node->stmts = $new_stmts;
    }

    private function normalizeTearDown(Node $node)
    {
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
