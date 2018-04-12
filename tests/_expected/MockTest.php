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
        $foo_getTrackerById = $foo->shouldReceive('getTrackerById');
        $foo_getTrackerById->with('param1', 'param2');
        $foo_getTrackerById->andReturns('result');
    }

    public function itConvertsAlsoItMethods()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo_searchAncestorIds = $foo->shouldReceive('searchAncestorIds');
        $foo_searchAncestorIds->with('param1');
        $foo_searchAncestorIds->once();
        $foo_searchAncestorIds->andReturns('result');
    }

    public function testWhithOnceLaterInTheStack()
    {
        $foo = \Mockery::spy(Foo::class);
        $foo_searchAncestorIds = $foo->shouldReceive('searchAncestorIds');
        $foo_searchAncestorIds->andReturns('result');

        buildOtherStuff();
        $foo_searchAncestorIds->with('param1');

        $foo_searchAncestorIds->once();
    }

    public function testWhithMockHelper()
    {
        $bar = \Mockery::spy(Barbell\Bar::class);
    }

    public function testPartialMock()
    {
        $baz = \Mockery::mock(Baz::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $baz_meth1 = $baz->shouldReceive('meth1');
        $baz_meth1->once();
    }

    public function testWithPartialMock()
    {
        $baz = \Mockery::mock(Food\Truck::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $baz_burger = $baz->shouldReceive('burger');
        $baz_burger->once();
    }

    public function testWhenInitialisationIsDoneInSetUp()
    {
        $this_tracker_getId = $this->tracker->shouldReceive('getId');
        $this_tracker_getId->andReturns(123);
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
}
