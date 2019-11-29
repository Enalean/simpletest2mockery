<?php
/**
 * Copyright (c) Enalean, 2019-Present. All Rights Reserved.
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

declare(strict_types=1);

namespace ST2Mockery;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ConvertExpectationsVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\MethodCall &&
            (string) $node->name === 'expectException' &&
            $node->var instanceof Node\Expr\Variable &&
            $node->var->name === 'this' &&
            isset($node->args[0]) &&
            $node->args[0]->value instanceof Node\Scalar\String_
        ) {
            $node->args[0]->value = new Node\Expr\ClassConstFetch(
                new Node\Name\FullyQualified($node->args[0]->value->value),
                'class'
            );
            return $node;
        }
    }
}
