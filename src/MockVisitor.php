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

class MockVisitor extends NodeVisitorAbstract
{
    /**
     * @var array
     */
    private $mocks = [];

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\StaticCall) {
            if ((string)$node->class === 'Mock' && (string)$node->name == 'generate') {
                if (count($node->args) === 1) {
                    $this->mocks[] = (string) $node->args[0]->value->value;
                    //var_dump($node->args[0]->value->value);
                }
            }
        }

        if ($node instanceof Node\Expr\New_) {
            if (($class_name = $this->isMock((string) $node->class)) !== false) {
                return new Node\Expr\FuncCall(new Node\Name('mock'), [new Node\Arg(new Node\Scalar\String_($class_name))]);
            }
        }

        if ($node instanceof Node\Expr\MethodCall) {
            if ((string)$node->name === 'setReturnValue') {
                if (count($node->args) === 2) {
                    $method_name = (string) $node->args[0]->value->value;
                    //var_dump($method_name);
                    $arguments = $node->args[1];
                    return new Node\Expr\MethodCall(
                        new Node\Expr\FuncCall(new Node\Name('stub'), [$node->var]),
                        $method_name
                    );
                }
            }
        }
    }

    private function isMock(string $class_name)
    {
        foreach($this->mocks as $mock) {
            if ('Mock'.$mock === $class_name) {
                return $mock;
            }
        }
        return false;
    }
}