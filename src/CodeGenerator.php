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

class CodeGenerator
{
    public static function getNewMockerySpy(string $class_name): Node\Expr\StaticCall
    {
        return
            new Node\Expr\StaticCall(
                new Node\Name('\Mockery'),
                new Node\Name('spy'),
                [
                    new Node\Expr\ClassConstFetch(
                        new Node\Name('\\'.$class_name),
                        new Node\Identifier('class')
                    )
                ]
            );
    }

    public static function getShouldReceive(Node $node, string $method_name): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            $node,
            'shouldReceive',
            [new Node\Arg(new Node\Scalar\String_($method_name))]
        );
    }

    public static function getWith(Node $node, array $args): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            $node,
            'with',
            $args
        );
    }

    public static function getReturn(Node $node, array $args): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            $node,
            'andReturns',
            $args
        );
    }

    public static function getReturnsEmptyDar(Node $node): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            $node,
            'andReturns',
            [
                new Node\Arg(
                    new Node\Expr\StaticCall(
                        new Node\Name('\TestHelper'),
                        new Node\Name('emptyDar')
                    )
                )
            ]
        );
    }

    public static function getReturnsDar(Node $node, array $args): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            $node,
            'andReturns',
            [
                new Node\Arg(
                    new Node\Expr\StaticCall(
                        new Node\Name('\TestHelper'),
                        new Node\Name('arrayToDar'),
                        $args
                    )
                )
            ]
        );
    }

    public static function getReturnsDarFromArray(Node $node, array $args): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            $node,
            'andReturns',
            [
                new Node\Arg(
                    new Node\Expr\StaticCall(
                        new Node\Name('\TestHelper'),
                        new Node\Name('argListToDar'),
                        $args
                    )
                )
            ]
        );
    }
}