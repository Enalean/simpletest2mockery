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

use Tuleap\User\Admin\UserStatusChecker;

class SimpleTest extends \TuleapTestCase
{
    /**
     * @var UserStatusChecker
     */
    private $user_status_checker;

    public function setUp()
    {
        parent::setUp();
        $this->setUpGlobalsMockery();
        ForgeConfig::store();

        $this->user = \Mockery::spy(\PFUser::class);
    }

    public function tearDown()
    {
        ForgeConfig::restore();
        parent::tearDown();
    }

    public function itRetrievesRestrictedStatusWhenPlatformAllowsRestricted()
    {
        ForgeConfig::set(ForgeAccess::CONFIG, ForgeAccess::RESTRICTED);
        $this->user->shouldReceive('isRestricted')->andReturns(false);

        $this->assertEqual($this->user_status_builder->getStatus($this->user), $this->status_with_restricted);

        $this->assertIsA($generic_user, 'GenericUser');

        $this->assertIdentical($this->user, $generic_user);

        $this->assertCount($all, 1);

        $this->assertArrayEmpty($my_array);
    }

    function getSomeBuilder() {
        return new Stuff();
    }
}
