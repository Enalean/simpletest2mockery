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
Mock::generate('Bar');
Mock::generatePartial('Baz', 'BazTestVersion', array('meth1',
    'meth2'));

class FactoryMock
{
    protected function setLicenseAgreementAtPackageCreation(SomeClass $package, ?int $foo)
    {
    }
}

class MockTest
{
    protected $another_foo;

    public function setUp()
    {
        parent::setUp();

        // A Tracker
        $this->tracker = new MockFoo();

        $this->another_foo = mock('Foo');
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

    public function testTestHelperGetPartialMock()
    {
        $mock = TestHelper::getPartialMock('Tracker_Artifact', array('createNewChangeset','getUserManager'));
    }

    public function testMockWithoutAssignement()
    {
        new Bla(
            mock('UserManager')
        );
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

    public function testReturnSeveralMethodCalls()
    {
        $foo = new MockFoo();
        $foo->setReturnValue('add', 'result');
        $foo->setReturnValue('mul', 'faaaaa');
    }

    public function testExpectNeverWithoutArguments()
    {
        $foo = new MockFoo();
        $foo->expectNever('searchAncestorIds');
    }

    public function testExpectCallCount()
    {
        $foo = new MockFoo();
        $foo->expectCallCount('searchAncestorIds', 2);
    }

    public function testMultipleReturns()
    {
        $foo = new MockFoo($this);

        $bar01 = new MockBar($this);
        $foo->setReturnReference('searchByTitle', $bar01, array(array('Project documentation'), 569, 0));

        $bar02 = new MockBar($this);
        $foo->setReturnReference('searchByTitle', $bar02, array(array('Folder 1', 'Folder 2'), 569, 35));
    }

    public function testReturnWithParamsFollowedByReturnWithoutParam()
    {
        $foo = new MockFoo($this);

        $foo->setReturnValue('searchByTitle', false, array(1, 2));
        $foo->setReturnValue('searchByTitle', true);
    }

    public function testConvertStubOfClassInMockeryStubs()
    {
        stub('Foo')->searchByTitle(1, 2)->returns(true);
    }

    public function testConvertReturnsVariousDar()
    {
        stub($this->another_foo)->getSomeDBResults()->returnsEmptyDar();
        stub($this->another_foo)->getSomeDBResults()->returnsDar(['some_id' => 12], ['some_id' => 13]);
        stub($this->another_foo)->getSomeDBResults()->returnsDarFromArray([['some_id' => 12], ['some_id' => 14]]);
    }

    public function testConvertExpectOfClassInMockeryStubs()
    {
        $foo = mock('Foo');
        expect($foo)->searchByTitle()->once();
    }

    public function testHalfBackedConvertOfExpectIsBetterThanNothing()
    {
        expect($this->another_foo)->savePermissions()->count(2);
        expect($this->another_foo)->savePermissions('', array(2), 'v1')->at(0);
        expect($this->another_foo)->savePermissions('', array(3), 'v2')->at(1);
    }

    public function testExpectAtLeastOnce()
    {
        expect($dao)->updateWidgetRankByWidgetId()->atLeastOnce();
    }

    public function testExpectWithWildcard()
    {
        expect($this->another_foo)->savePermissionsWildcard(1, '*')->once();
    }

    public function testConvertAMockTracker()
    {
        aMockTracker()->withId(12)->build();
    }

    public function testConvertAMockProject()
    {
        $project = aMockProject()->withId(111)->withUnixName('foo')->build();
    }

    public function testConvertThrowOn()
    {
        $foo = new MockFoo($this);
        $foo->throwOn('purgeFiles', new RuntimeException("Error while doing things"));
    }
}
