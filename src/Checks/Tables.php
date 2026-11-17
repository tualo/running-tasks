<?php

namespace Tualo\Office\RunningTasks\Checks;

use Tualo\Office\Basic\PostCheck;
use Tualo\Office\Basic\TualoApplication as App;

class Tables extends PostCheck
{
    public static function test(array $config)
    {
        $db = App::get('clientDB');
        if (is_null($db)) {
            return;
        }
        $command = './tm install-sql-running-tasks --client ' . $db->dbname;
        self::tableCheck('running-tasks', [
            'sync_lock_file' => [],
            'sync_lock_file_message' => [],
        ], 'please run: ' . $command, 'please run: ' . $command);
    }
}
