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
    public static function getNewMockerySpy(string $class_name, array $contructor_args = []): Node\Expr\StaticCall
    {
        $spy_args = [
            new Node\Expr\ClassConstFetch(
                new Node\Name('\\'.$class_name),
                new Node\Identifier('class')
            )
        ];
        $spy_args = array_merge($spy_args, $contructor_args);
        return self::getMockeryStaticCallTo('spy', $spy_args);
    }

    private static function getMockeryStaticCallTo(string $method_name, array $args = []): Node\Expr\StaticCall
    {
        return new Node\Expr\StaticCall(
            new Node\Name('\Mockery'),
            new Node\Name($method_name),
            $args
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

    public static function ordered(Node $node)
    {
        return new Node\Expr\MethodCall(
            $node,
            'ordered'
        );
    }

    public static function getWith(Node $node, array $args): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            $node,
            'with',
            array_map(
                function ($arg) {
                    if ($arg instanceof Node\Arg && $arg->value instanceof Node\Scalar\String_ && $arg->value->value === '*') {
                        return self::getMockeryStaticCallTo('any');
                    }
                    return $arg;
                },
                $args
            )
        );
    }

    public static function getAtLeastOnce(Node $node): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            new Node\Expr\MethodCall(
                $node,
                'atLeast'
            ),
            'once'
        );
    }

    public static function times(Node $node, Node\Scalar\LNumber $count): Node\Expr\MethodCall
    {
        return new Node\Expr\MethodCall(
            $node,
            'times',
            self::getAsArgsForMethodCall($count)
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
        return self::getReturn(
            $node,
            self::getAsArgsForMethodCall(
                self::getTestHelperNoArgs('emptyDar')
            )
        );
    }

    public static function getReturnsDar(Node $node, array $args): Node\Expr\MethodCall
    {
        return self::getReturn(
            $node,
            self::getAsArgsForMethodCall(
                self::getTestHelperWithArgs(
                    'arrayToDar',
                    $args
                )
            )
        );
    }

    public static function getReturnsDarFromArray(Node $node, array $args): Node\Expr\MethodCall
    {
        return self::getReturn(
            $node,
            self::getAsArgsForMethodCall(
                self::getTestHelperWithArgs(
                    'argListToDar',
                    $args
                )
            )
        );
    }

    public static function getAsArgsForMethodCall(Node $node): array
    {
        return [
            new Node\Arg($node)
        ];
    }

    private static function getTestHelperNoArgs(string $method): Node\Expr\StaticCall
    {
        return new Node\Expr\StaticCall(
            new Node\Name('\TestHelper'),
            new Node\Name($method)
        );
    }

    private static function getTestHelperWithArgs(string $method, array $args): Node\Expr\StaticCall
    {
        return new Node\Expr\StaticCall(
            new Node\Name('\TestHelper'),
            new Node\Name($method),
            $args
        );
    }

    public static function getMap(array $map)
    {
        $members = [];
        foreach ($map as $key => $value) {
            $members []= new Node\Expr\ArrayItem(
                $value,
                new Node\Scalar\String_($key)
            );
        }
        return new Node\Expr\Array_($members);
    }

    public static function getFalseExpr(): Node\Expr
    {
        return new Node\Expr\ConstFetch(new Node\Name('false'));
    }
}