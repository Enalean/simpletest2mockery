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
        $foo->shouldReceive('getTrackerById')->with('param1', 'param2')->andReturns('result');
    }

    public function itConvertsAlsoItMethods()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo->shouldReceive('searchAncestorIds')->with('param1')->once()->andReturns('result');
    }

    public function testWhithOnceLaterInTheStack()
    {
        $foo = \Mockery::spy(Foo::class);

        buildOtherStuff();

        $foo->shouldReceive('searchAncestorIds')->andReturns('result')->with('param1')->once();
    }

    public function testWhithMockHelper()
    {
        $bar = \Mockery::spy(Barbell\Bar::class);
    }

    public function testPartialMock()
    {
        $baz = \Mockery::mock(Baz::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $baz->shouldReceive('meth1')->once();
    }

    public function testWithPartialMock()
    {
        $baz = \Mockery::mock(Food\Truck::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $baz->shouldReceive('burger')->once();
    }

    public function testWhenInitialisationIsDoneInSetUp()
    {
        $this->tracker->shouldReceive('getId')->andReturns(123);
    }

    public function testReturnValueAtWithoutArguments()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo->shouldReceive('searchAncestorIds')->once()->andReturns('result');
        $foo->shouldReceive('searchAncestorIds')->once()->andReturns('faaaaa');
    }

    public function testExpectAt()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo->shouldReceive('searchAncestorIds')->with(1, 2)->ordered();
        $foo->shouldReceive('searchAncestorIds')->with(3, 4)->ordered();
    }

    public function testReturnSeveralMethodCalls()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo->shouldReceive('add')->andReturns('result');
        $foo->shouldReceive('mul')->andReturns('faaaaa');
    }

    public function testExpectNeverWithoutArguments()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo->shouldReceive('searchAncestorIds')->never();
    }

    public function testExpectCallCount()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo->shouldReceive('searchAncestorIds')->times(2);
    }
}
