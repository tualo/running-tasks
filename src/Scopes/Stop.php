<?php

namespace Tualo\Office\RunningTasks\Scopes;

use Tualo\Office\Basic\Scope;

class Stop extends Scope
{
    protected static string $identifier = 'running-tasks.stop';
    protected static array $name = ['EN' => 'Stop running tasks', 'DE' => 'Laufende Prozesse stoppen'];
    protected static array $description = [
        'EN' => 'Request cooperative process cancellation in the current client.',
        'DE' => 'Kooperativen Prozessabbruch im aktuellen Mandanten anfordern.',
    ];
}
