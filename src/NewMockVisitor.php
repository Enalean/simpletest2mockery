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
use PhpParser\NodeVisitorAbstract;

class NewMockVisitor  extends NodeVisitorAbstract
{
    /**
     * @var array
     */
    private $mocks;

    public function __construct(array &$mocks)
    {
        $this->mocks = $mocks;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\New_) {
            if ((string) $node->class === 'MockAdminDelegation_UserServiceDao') {
                return new Node\Expr\FuncCall(new Node\Name('mock'), [new Node\Arg(new Node\Scalar\String_('AdminDelegation_UserServiceDao'))]);
            }
        }
    }

    private function isMock(string $class_name)
    {
        foreach($this->mocks as $mock) {
            if ('Mock'.$mock === $class_name) {
                return true;
            }
        }
        return false;
    }
}