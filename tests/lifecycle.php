<?php

use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\RunningTasks\TaskManager;

return function (Database $db): void {
    $manager = new TaskManager($db);
    $id = $manager->start();
    check(strlen($id) === 36 && !$manager->shouldStop($id), 'New task must be running.');
    check($manager->get($id)['status'] === 'running', 'Running status.');
    expectException(Exception::class, fn() => $manager->start($id));
    check($manager->tasks()['total'] === 1, 'Duplicate starts must not replace a task.');

    $message = "Step 'one' {id} {progress} ? <script>alert(1)</script>\nDetails";
    $manager->addMessage($id, $message, 0);
    $manager->addMessage($id, 'Half way', 50.25);
    $manager->addMessage($id, 'No percentage');
    $messages = $manager->messages($id);
    check(count($messages) === 3 && $messages[0]['message'] === $message, 'Messages must round-trip literally.');
    check($messages[0]['progress'] === 0.0 && $messages[2]['progress'] === null, 'Zero and NULL must stay distinct.');
    check($manager->messages($id, 2)[0]['message'] === 'Half way', 'Return latest messages chronologically.');
    foreach ([-0.01, 100.01, INF, NAN] as $progress) {
        expectException(InvalidArgumentException::class, fn() => $manager->addMessage($id, 'Invalid', $progress));
    }
    expectException(InvalidArgumentException::class, fn() => $manager->addMessage($id, ' '));

    $manager->requestStop($id);
    check($manager->shouldStop($id) && !$manager->get($id)['finished'], 'Stop request must not mark completion.');
    check($manager->get($id)['status'] === 'stopping', 'Stopping status.');
    $db->direct("UPDATE sync_lock_file SET stoped = '2020-01-01 01:02:03' WHERE id = {id}", ['id' => $id]);
    $manager->requestStop($id);
    check($manager->get($id)['stoped'] === '2020-01-01 01:02:03', 'Repeated stop requests must preserve the timestamp.');
    $manager->addMessage($id, 'Stopping safely', 100);
    $manager->finish($id);
    $manager->finish($id);
    check($manager->get($id)['status'] === 'stopped', 'Completion must acknowledge cancellation.');
    check($manager->tasks()['total'] === 0 && $manager->tasks(true)['total'] === 1, 'Default list excludes completed tasks.');
    expectException(LogicException::class, fn() => $manager->addMessage($id, 'Too late'));

    $normal = $manager->start('normal');
    $manager->finish($normal);
    $manager->requestStop($normal);
    check($manager->get($normal)['status'] === 'finished' && $manager->get($normal)['stoped'] === null, 'Late stop must not reclassify normal completion.');
    $manager->start("quoted'{id}");
    check(count($manager->tasks(true, 1, 1)['data']) === 1 && $manager->tasks(true)['total'] === 3, 'Pagination must retain full totals.');
    foreach ([
        fn() => $manager->get('missing'),
        fn() => $manager->requestStop('missing'),
        fn() => $manager->finish('missing'),
        fn() => $manager->messages('missing'),
    ] as $callback) {
        expectException(OutOfBoundsException::class, $callback);
    }
    expectException(LogicException::class, fn() => $manager->addMessage('missing', 'Unknown'));
    expectException(InvalidArgumentException::class, fn() => $manager->start(''));
    expectException(InvalidArgumentException::class, fn() => $manager->tasks(false, -1));
    expectException(InvalidArgumentException::class, fn() => $manager->tasks(false, 0, 1001));
};
