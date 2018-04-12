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
Mock::generate('Foo');
Mock::generatePartial('Baz', 'BazTestVersion', array('meth1',
    'meth2'));

class MockTest
{
    public function setUp()
    {
        $this->tracker = new MockFoo();
    }

    public function testMethodsAreConverted()
    {
        $foo = new MockFoo();
        $foo->setReturnValue('getTrackerById', 'result', array('param1', 'param2'));
    }

    public function itConvertsAlsoItMethods()
    {
        $foo = new MockFoo();
        $foo->expectOnce('searchAncestorIds', array('param1'));
        $foo->setReturnValue('searchAncestorIds', 'result');
    }

    public function testWhithOnceLaterInTheStack()
    {
        $foo = new MockFoo();
        $foo->setReturnValue('searchAncestorIds', 'result');

        buildOtherStuff();

        $foo->expectOnce('searchAncestorIds', array('param1'));
    }

    public function testWhithMockHelper()
    {
        $bar = mock('Barbell\Bar');
    }

    public function testPartialMock()
    {
        $baz = new BazTestVersion();
        $baz->expectOnce('meth1');
    }

    public function testWithPartialMock()
    {
        $baz = partial_mock('Food\Truck', ['burger'], ['constructor_param1', 'constructor_param2']);
        $baz->expectOnce('burger');
    }

    public function testWhenInitialisationIsDoneInSetUp()
    {
        $this->tracker->setReturnValue('getId', 123);
    }

    public function testReturnValueAtWithoutArguments()
    {
        $foo = new MockFoo();
        $foo->setReturnValueAt(0, 'searchAncestorIds', 'result');
        $foo->setReturnValueAt(1, 'searchAncestorIds', 'faaaaa');
    }

    public function testExpectAt()
    {
        $foo = new MockFoo();
        $foo->expectAt(0, 'searchAncestorIds', array(1, 2));
        $foo->expectAt(1, 'searchAncestorIds', array(3, 4));
    }
}
