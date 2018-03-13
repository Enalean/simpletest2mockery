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
 */

require_once 'bootstrap.php';

class MockTest
{
    public function setUp()
    {
        parent::setUp();
        $this->tracker = \Mockery::spy(Foo::class);
    }

    public function testMethodsAreConverted()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo->allows()->getTrackerById('param1', 'param2')->andReturns('result');
    }

    public function itConvertsAlsoItMethods()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo->expects()->searchAncestorIds('param1');
        $foo->allows(['searchAncestorIds' => 'result']);
    }

    public function testWhithOnceLaterInTheStack()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo->allows(['searchAncestorIds' => 'result']);

        buildOtherStuff();

        $foo->expects()->searchAncestorIds('param1');
    }

    public function testWhithMockHelper()
    {
        $bar = \Mockery::spy(Barbell\Bar::class);
    }

    public function testPartialMock()
    {
        $baz = \Mockery::mock(Baz::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $baz->expects()->meth1();
    }

    public function testWithPartialMock()
    {
        $baz = \Mockery::mock(Food\Truck::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $baz->expects()->burger();
    }

    public function testWhenInitialisationIsDoneInSetUp()
    {
        $this->tracker->allows(['getId' => 123]);
    }
}