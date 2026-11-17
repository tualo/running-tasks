<?php

use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\Basic\Route;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\RunningTasks\Routes\Tasks;
use Tualo\Office\RunningTasks\TaskManager;

return function (Database $db): void {
    App::set('session', new class($db) {
        private Database $db;
        public function __construct(Database $db)
        {
            $this->db = $db;
        }
        public function getDB(): Database
        {
            return $this->db;
        }
    });
    $GLOBALS['current_cls'] = Tasks::class;
    Tasks::register();
    $routes = [];
    foreach (Route::getRoutes() as $route) {
        if (strpos($route['expression'], '/running-tasks/') === 0) {
            $routes[$route['expression']] = $route;
            check($route['needActiveSession'] === true, 'Every process API requires an active session.');
            check($route['accessScope'] === ($route['method'] === 'post' ? 'running-tasks.stop' : 'running-tasks.read'), 'Read and stop permissions must be separate.');
        }
    }
    check($routes['/running-tasks/stop']['method'] === 'post', 'Stopping must not be a GET action.');
    $call = function (string $path, array $get = [], array $post = []) use ($routes): array {
        $_GET = $get;
        $_POST = $post;
        App::resetResult();
        http_response_code(200);
        $routes[$path]['function']();
        return App::result();
    };

    $manager = new TaskManager($db);
    $id = $manager->start('route-task');
    $manager->addMessage($id, 'Working', 12.5);
    $result = $call('/running-tasks/read');
    check($result['success'] && $result['total'] === 1 && $result['data'][0]['id'] === $id, 'Read response shape.');
    $result = $call('/running-tasks/messages', ['id' => $id]);
    check($result['success'] && $result['data'][0]['progress'] === 12.5, 'Detail response.');
    $result = $call('/running-tasks/stop', [], ['id' => $id]);
    check($result['success'] && $result['data']['status'] === 'stopping', 'Stop response.');
    $manager->finish($id);
    check($call('/running-tasks/read')['total'] === 0, 'Completed process hidden.');
    check($call('/running-tasks/read', ['include_finished' => '1'])['total'] === 1, 'Completed process visible on request.');

    foreach ([
        ['/running-tasks/read', ['limit' => '1001'], []],
        ['/running-tasks/read', ['start' => '-1'], []],
        ['/running-tasks/read', ['limit' => ['10']], []],
        ['/running-tasks/read', ['include_finished' => 'yes'], []],
        ['/running-tasks/messages', [], []],
        ['/running-tasks/stop', ['id' => $id], []],
        ['/running-tasks/stop', [], ['id' => ['bad']]],
    ] as [$path, $get, $post]) {
        $result = $call($path, $get, $post);
        check(!$result['success'] && isset($result['msg']) && http_response_code() === 400, 'Invalid input must return 400 and a message.');
    }
    $result = $call('/running-tasks/stop', [], ['id' => 'missing']);
    check(!$result['success'] && http_response_code() === 404, 'Unknown process must return 404.');
};
