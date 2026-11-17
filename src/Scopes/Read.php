<?php

namespace Tualo\Office\RunningTasks\Scopes;

use Tualo\Office\Basic\Scope;

class Read extends Scope
{
    protected static string $identifier = 'running-tasks.read';
    protected static array $name = ['EN' => 'View running tasks', 'DE' => 'Laufende Prozesse anzeigen'];
    protected static array $description = [
        'EN' => 'View processes and their status messages in the current client.',
        'DE' => 'Prozesse und Statusmeldungen im aktuellen Mandanten anzeigen.',
    ];
}
