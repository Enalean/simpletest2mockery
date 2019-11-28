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

class SimpleTest extends \PHPUnit\Framework\TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    /**
     * @var UserStatusChecker
     */
    private $user_status_checker;

    protected function setUp() : void
    {
        parent::setUp();
        ForgeConfig::store();

        $this->user = \Mockery::spy(\PFUser::class);
    }

    protected function tearDown() : void
    {
        ForgeConfig::restore();
        parent::tearDown();
    }

    public function testItRetrievesRestrictedStatusWhenPlatformAllowsRestricted() : void
    {
        ForgeConfig::set(ForgeAccess::CONFIG, ForgeAccess::RESTRICTED);
        $this->user->shouldReceive('isRestricted')->andReturns(false);

        $this->assertEquals($this->user_status_builder->getStatus($this->user), $this->status_with_restricted);
    }

    function getSomeBuilder() {
        return new Stuff();
    }
}
