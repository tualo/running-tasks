<?php

namespace Tualo\Office\RunningTasks\Commands;

use Tualo\Office\Basic\CommandLineInstallSQL;
use Tualo\Office\Basic\ICommandline;

class Install extends CommandLineInstallSQL implements ICommandline
{
    public static $shortName = 'running-tasks';
    public static $files = [
        'sync_lock_file' => 'setup running tasks',
    ];

    public static function getDir(): string
    {
        return dirname(__DIR__);
    }

    public static function exitOnError(): bool
    {
        return true;
    }
}
