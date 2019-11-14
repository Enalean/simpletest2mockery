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

namespace ST2Mockery;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ConvertMockHelpers extends NodeVisitorAbstract
{

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\FuncCall && (string)$node->name === 'aMockTracker') {
            $node->name = new Node\Name('aMockeryTracker');
        }

        if ($this->isCallTo($node, 'build') && $this->isRootNode($node, 'aMockProject')) {
            $with = $this->collectWith($node);
            $constructor_args = CodeGenerator::getAsArgsForMethodCall(
                CodeGenerator::getMap(
                    [
                        'getID'       => $with['id'] ?? CodeGenerator::getFalseExpr(),
                        'getUnixName' => $with['unixname'] ?? CodeGenerator::getFalseExpr(),
                        'isPublic'    => $with['public'] ?? CodeGenerator::getFalseExpr(),
                    ]
                )
            );

            return CodeGenerator::getNewMockerySpy('Project', $constructor_args);
        }
    }

    private function isCallTo(Node $node, string $method_name): bool
    {
        return $node instanceof Node\Expr\MethodCall && (string) $node->name === $method_name;
    }

    private function isRootNode(Node $node, string $function_name)
    {
        if ($node instanceof Node\Expr\FuncCall && (string)$node->name === $function_name) {
            return true;
        }
        if ($node instanceof Node\Expr\MethodCall) {
            return $this->isRootNode($node->var, $function_name);
        }
        return false;
    }

    private function collectWith(Node $node): array
    {
        $return = [];
        if ($this->isCallTo($node, 'withId')) {
            $return['id'] = $node->args[0]->value;
        }
        if ($this->isCallTo($node, 'withUnixName')) {
            $return['unixname'] = $node->args[0]->value;
        }
        if ($this->isCallTo($node, 'isPublic')) {
            $return['public'] = true;
        }
        if ($node instanceof Node\Expr\MethodCall) {
            return array_merge($return, $this->collectWith($node->var));
        }
        return $return;
    }
}