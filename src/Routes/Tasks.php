<?php

namespace Tualo\Office\RunningTasks\Routes;

use Tualo\Office\Basic\Route;
use Tualo\Office\Basic\RouteWrapper;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\RunningTasks\Scopes;
use Tualo\Office\RunningTasks\TaskManager;

class Tasks extends RouteWrapper
{
    public static function scope(): string
    {
        return Scopes\Read::identifier();
    }

    public static function register()
    {
        Route::add('/running-tasks/read', function () {
            self::respond(function (TaskManager $manager): array {
                $include = $_GET['include_finished'] ?? '0';
                if (!in_array($include, ['0', '1'], true)) {
                    throw new \InvalidArgumentException('include_finished muss 0 oder 1 sein.');
                }
                return $manager->tasks(
                    $include === '1',
                    self::integerParameter('start', 0, 0, PHP_INT_MAX),
                    self::integerParameter('limit', 100, 1, 1000)
                );
            });
        }, ['get'], true, [], self::scope());

        Route::add('/running-tasks/messages', function () {
            self::respond(function (TaskManager $manager): array {
                $data = $manager->messages(
                    self::idParameter($_GET),
                    self::integerParameter('limit', 200, 1, 1000)
                );
                return ['data' => $data, 'total' => count($data)];
            });
        }, ['get'], true, [], self::scope());

        Route::add('/running-tasks/stop', function () {
            self::respond(function (TaskManager $manager): array {
                $id = self::idParameter($_POST);
                $manager->requestStop($id);
                return ['data' => $manager->get($id)];
            });
        }, ['post'], true, [], Scopes\Stop::identifier());
    }

    private static function idParameter(array $input): string
    {
        if (!isset($input['id']) || !is_string($input['id'])) {
            throw new \InvalidArgumentException('Eine Prozess-ID ist erforderlich.');
        }
        return $input['id'];
    }

    private static function integerParameter(string $key, int $default, int $min, int $max): int
    {
        $value = $_GET[$key] ?? (string)$default;
        if (!is_string($value) || !preg_match('/^\d+$/D', $value)) {
            throw new \InvalidArgumentException('Ungueltiger Parameter: ' . $key);
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($number === false) {
            throw new \InvalidArgumentException('Ungueltiger Parameter: ' . $key);
        }
        return $number;
    }

    private static function respond(callable $callback): void
    {
        App::contenttype('application/json');
        App::result('success', false);
        try {
            $db = App::get('session')->getDB();
            if ($db === null) {
                throw new \RuntimeException('Keine Mandantendatenbank ausgewaehlt.');
            }
            foreach ($callback(new TaskManager($db)) as $key => $value) {
                App::result($key, $value);
            }
            App::result('success', true);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            App::result('msg', $e->getMessage());
        } catch (\OutOfBoundsException $e) {
            http_response_code(404);
            App::result('msg', $e->getMessage());
        } catch (\Exception $e) {
            http_response_code(500);
            App::logger('running-tasks')->error($e->getMessage());
            App::result('msg', 'Die Prozessverwaltung konnte nicht ausgefuehrt werden.');
        }
    }
}
