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
        $this->setUpGlobalsMockery();

        // A Tracker
        $this->tracker = \Mockery::spy(\Foo::class);

        $this->another_foo = \Mockery::spy(\Foo::class);
    }

    public function testMethodsAreConverted()
    {
        $foo = \Mockery::spy(\Foo::class);
        $foo->shouldReceive('getTrackerById')->with('param1', 'param2')->andReturns('result');
    }

    public function itConvertsAlsoItMethods()
    {
        $foo = \Mockery::spy(\Foo::class);
        $foo->shouldReceive('searchAncestorIds')->with('param1')->once()->andReturns('result');
    }

    public function testWhithOnceLaterInTheStack()
    {
        $foo = \Mockery::spy(\Foo::class);

        buildOtherStuff();

        $foo->shouldReceive('searchAncestorIds')->with('param1')->once()->andReturns('result');
    }

    public function testWhithMockHelper()
    {
        $bar = \Mockery::spy(\Barbell\Bar::class);
        $password_verifier = \Mockery::spy(\Tuleap\User\PasswordVerifier::class);
    }

    public function testPartialMock()
    {
        $baz = \Mockery::mock(\Baz::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $baz->shouldReceive('meth1')->once();
    }

    public function testWithPartialMock()
    {
        $baz = \Mockery::mock(\Food\Truck::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $baz->shouldReceive('burger')->once();
    }

    public function testTestHelperGetPartialMock()
    {
        $mock = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
    }

    public function testMockWithoutAssignement()
    {
        new Bla(
            \Mockery::spy(\UserManager::class)
        );
    }

    public function testWhenInitialisationIsDoneInSetUp()
    {
        $this->tracker->shouldReceive('getId')->andReturns(123);
    }

    public function testReturnValueAtWithoutArguments()
    {
        $foo = \Mockery::spy(\Foo::class);
        $foo->shouldReceive('searchAncestorIds')->once()->andReturns('result');
        $foo->shouldReceive('searchAncestorIds')->once()->andReturns('faaaaa');
    }

    public function testExpectAt()
    {
        $foo = \Mockery::spy(\Foo::class);
        $foo->shouldReceive('searchAncestorIds')->with(1, 2)->ordered();
        $foo->shouldReceive('searchAncestorIds')->with(3, 4)->ordered();
    }

    public function testReturnSeveralMethodCalls()
    {
        $foo = \Mockery::spy(\Foo::class);
        $foo->shouldReceive('add')->andReturns('result');
        $foo->shouldReceive('mul')->andReturns('faaaaa');
    }

    public function testExpectNeverWithoutArguments()
    {
        $foo = \Mockery::spy(\Foo::class);
        $foo->shouldReceive('searchAncestorIds')->never();
    }

    public function testExpectCallCount()
    {
        $foo = \Mockery::spy(\Foo::class);
        $foo->shouldReceive('searchAncestorIds')->times(2);
    }

    public function testMultipleReturns()
    {
        $foo = \Mockery::spy(\Foo::class);

        $bar01 = \Mockery::spy(\Bar::class);
        $foo->shouldReceive('searchByTitle')->with(array('Project documentation'), 569, 0)->andReturns($bar01);

        $bar02 = \Mockery::spy(\Bar::class);
        $foo->shouldReceive('searchByTitle')->with(array('Folder 1', 'Folder 2'), 569, 35)->andReturns($bar02);
    }

    public function testReturnWithParamsFollowedByReturnWithoutParam()
    {
        $foo = \Mockery::spy(\Foo::class);

        $foo->shouldReceive('searchByTitle')->with(1, 2)->andReturns(false);
        $foo->shouldReceive('searchByTitle')->andReturns(true);
    }

    public function testConvertStubOfClassInMockeryStubs()
    {
        \Mockery::spy(\FooDirectStub::class)->shouldReceive('searchByTitle')->with(1, 2)->andReturns(true);
        $dao->shouldReceive('save')->times(3);

        new BazBaz(\Mockery::spy(\FooDirectStubInCall::class)->shouldReceive('searchByTitle')->with(1, 2)->andReturns(true)->getMock());
        $baz = \Mockery::spy(\FooDirectStubInAssignment::class)->shouldReceive('searchByTitle')->with(1, 2)->andReturns(true)->getMock();
    }

    public function testConvertReturnsVariousDar()
    {
        $this->another_foo->shouldReceive('getSomeDBResults')->andReturns(\TestHelper::emptyDar());
        $this->another_foo->shouldReceive('getSomeDBResults')->andReturns(\TestHelper::arrayToDar(['some_id' => 12], ['some_id' => 13]));
        $this->another_foo->shouldReceive('getSomeDBResults')->andReturns(\TestHelper::argListToDar([['some_id' => 12], ['some_id' => 14]]));
    }

    public function testConvertExpectOfClassInMockeryStubs()
    {
        $foo = \Mockery::spy(\Foo::class);
        $foo->shouldReceive('searchByTitle')->once();
    }

    public function testConvertExpectsWithAt()
    {
        $this->another_foo->shouldReceive('savePermissions')->times(2);
        $this->another_foo->shouldReceive('savePermissions')->with('', array(2), 'v1')->ordered();
        $this->another_foo->shouldReceive('savePermissions')->with('', array(3), 'v2')->ordered();
    }

    public function testConvertReturnsAt()
    {
        $this->widget_dao->shouldReceive('createColumn')->andReturns(122)->ordered();
        $this->widget_dao->shouldReceive('createColumn')->andReturns(222)->ordered();
    }

    public function testConvertThrows()
    {
        $widget->shouldReceive('create')->andThrows(new \Exception("foo"));
    }

    public function testExpectAtLeastOnce()
    {
        $dao->shouldReceive('updateWidgetRankByWidgetId')->atLeast()->once();
    }

    public function testExpectWithWildcard()
    {
        $this->another_foo->shouldReceive('savePermissionsWildcard')->with(1, \Mockery::any())->once();
    }

    public function testConvertAMockTracker()
    {
        aMockeryTracker()->withId(12)->build();
    }

    public function testConvertAMockProject()
    {
        $project = \Mockery::spy(\Project::class, ['getID' => 111, 'getUnixName' => 'foo', 'isPublic' => false]);
    }

    public function testConvertAUser()
    {
        $this->user = (new \UserTestBuilder())->build();
    }

    public function testConvertThrowOn()
    {
        $foo = \Mockery::spy(\Foo::class);
        $foo->shouldReceive('purgeFiles')->andThrows(new RuntimeException("Error while doing things"));
    }

    public function testConvertStubOnGlobals()
    {
        $GLOBALS['Response']->shouldReceive('addFeedback')->never();
    }

    public function testExpectations()
    {
        $this->expectException(\User\XML\Import\UserCannotBeCreatedException::class);
        $this->expectException(PFUser::class);
    }

    public function testAnonymousClass()
    {
        $us1 = 'foo';
        $em = new class($u1) extends EventManager {

        };
    }
}
