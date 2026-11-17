<?php

use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\RunningTasks\Commands\Install;

$autoload = getenv('RUNNING_TASKS_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
spl_autoload_register(function (string $class): void {
    $paths = [
        'Tualo\\Office\\RunningTasks\\' => dirname(__DIR__) . '/src/',
        'Tualo\\Office\\Basic\\' => dirname(__DIR__, 2) . '/bsc/src/',
        'Tualo\\Office\\ExtJSCompiler\\' => dirname(__DIR__, 2) . '/extjscmplr/src/',
    ];
    foreach ($paths as $prefix => $path) {
        if (strpos($class, $prefix) === 0) {
            $file = $path . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
});
require_once dirname(__DIR__) . '/src/functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectException(string $class, callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        check($e instanceof $class, 'Expected ' . $class . ', got ' . get_class($e) . ': ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected ' . $class);
}

if (!class_exists(\Ramsey\Uuid\Uuid::class)) {
    throw new RuntimeException('Missing Composer dependencies. Run composer install or set RUNNING_TASKS_AUTOLOAD.');
}
$port = getenv('RUNNING_TASKS_TEST_PORT');
if ($port === false || !ctype_digit($port)) {
    throw new RuntimeException('RUNNING_TASKS_TEST_PORT must point to a disposable MariaDB on 127.0.0.1.');
}

App::set('configuration', ['database' => ['client.mysql.persistent' => false]]);
$schema = 'running_tasks_test_' . bin2hex(random_bytes(6));
$db = new Database('root', '', '', '127.0.0.1', (int)$port);
$db->execute('CREATE DATABASE ' . $schema . ' CHARACTER SET utf8mb4');
try {
    $db->execute('USE ' . $schema);
    for ($i = 0; $i < 2; $i++) {
        foreach (Install::getFiles() as $file => $description) {
            $sql = file_get_contents(Install::getDir() . '/sql/' . $file . '.sql');
            foreach (explode(';', $sql) as $statement) {
                if (trim($statement) !== '') {
                    $db->execute($statement);
                }
            }
        }
    }
    $passed = 0;
    foreach (['lifecycle.php', 'routes.php'] as $file) {
        $db->execute('DELETE FROM sync_lock_file_message');
        $db->execute('DELETE FROM sync_lock_file');
        $suite = require __DIR__ . '/' . $file;
        $suite($db);
        $passed++;
    }
} finally {
    $db->execute('DROP DATABASE ' . $schema);
    $db->close();
}
echo $passed . " integration suites passed.\n";
