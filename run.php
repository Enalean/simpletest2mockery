#!/usr/bin/env php
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
 *
 */

require_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;

$log = new Logger('log');
$log->pushHandler(new ErrorLogHandler());

try {

    $app = new \Symfony\Component\Console\Application();
    $app->add(new ST2Mockery\ST2Mockery($log));
    $app->add(new \ST2Mockery\PHPUnit\ToPHPUnitCommand($log));
    $app->run();
} catch (Exception $exception) {
    $log->critical($exception->getMessage());
    exit(1);
}
