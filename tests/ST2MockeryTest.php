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

namespace ST2Mockery;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ST2MockeryTest extends TestCase
{
    private $fixtures_dir;
    private $expected_dir;

    public function setUp()
    {
        $tmp_dir            = escapeshellarg(sys_get_temp_dir());
        $this->fixtures_dir = exec("mktemp -d -p $tmp_dir st2mockeryXXXXXX");
        $this->expected_dir = __DIR__ . '/_expected';
        $pristine           = __DIR__ . '/_fixtures';
        exec('cp -r ' . escapeshellarg($pristine) . '/* ' . escapeshellarg($this->fixtures_dir) . '/');
    }

    public function tearDown()
    {
        exec('rm -rf ' . escapeshellarg($this->fixtures_dir));
    }

    /**
     * @dataProvider filenames
     */
    public function testConversion($filename)
    {
        $logger = $this->createMock(LoggerInterface::class);

        $converter = new ST2Mockery($logger);
        $converter->run(array("", $this->fixtures_dir));
        $this->assertEquals(
            file_get_contents($this->expected_dir . '/' . $filename),
            file_get_contents($this->fixtures_dir . '/' . $filename)
        );
    }

    public function filenames()
    {
        return [
            ['DirnameTest.php'],
            ['SetUpTearDownTest.php']
        ];
    }
}
