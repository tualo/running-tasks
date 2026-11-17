# tualo/running-tasks

Kooperative Verwaltung lang laufender Prozesse fuer Tualo Office.

## Installation

Im Tualo-Office-Projekt installieren und fuer den gewuenschten Mandanten einrichten:

```sh
composer require tualo/running-tasks
./tm install-sql-running-tasks --client <mandant>
./tm install-sql-bsc-scopes --client <mandant>
./tm compile --client <mandant>
```

Das Paket muss zuvor ueber ein Composer-Repository (z. B. ein lokales
`path`-Repository oder ein veroeffentlichtes VCS-Repository) verfuegbar sein.
Den vorgesehenen Benutzergruppen die Scopes `running-tasks.read` und bei Bedarf
`running-tasks.stop` zuweisen. Die Ansicht ist ueber `#running-tasks/panel` und
den Routeneintrag **Laufende Prozesse** erreichbar (ExtJS Classic).

## Tabellen und Lebenszyklus

Das Installationskommando erstellt die Tabellen nur, wenn sie noch nicht existieren;
bestehende Eintraege werden nicht veraendert.

`sync_lock_file` enthaelt genau die vorgegebenen Spalten:

| Spalte | Bedeutung |
| --- | --- |
| `id` | Prozess-ID, maximal 36 Bytes; standardmaessig eine neue UUID |
| `started` | Startzeit in Datenbankzeit |
| `stoped` | Zeitpunkt der Stopp-Anforderung, sonst `NULL` (Schreibweise absichtlich beibehalten) |
| `finished` | `0` bis der Prozess seinen Abschluss mit `finish()` bestaetigt |

`sync_lock_file_message` speichert zusaetzliche Detailzeilen mit Zeit, Meldung
und optionalem Fortschritt von 0 bis 100 Prozent. Die Tabelle ist ueber `task_id`
indiziert; bewusst ohne Fremdschluessel, damit bereits vorhandene
`sync_lock_file`-Tabellen mit anderer Engine oder Kollation weiter nutzbar bleiben.
Der Schreibzugriff laesst Meldungen nur fuer vorhandene, offene Prozesse zu.
Eintraege und Meldungen bleiben nach Abschluss erhalten.

Zustaende: `running` -> `stopping` -> `stopped` bzw. `running` -> `finished`.
Wiederholte Stopp-Anforderungen behalten den ersten Zeitstempel bei. Eine
Stopp-Anforderung fuer einen bereits abgeschlossenen Prozess aendert nichts.
Bestehende Programme, die `stoped` erst beim tatsaechlichen Ende schreiben,
muessen an diese Semantik angepasst werden.

## Prozesse einbinden

```php
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\RunningTasks\TaskManager;

$tasks = new TaskManager(App::get('session')->getDB());
$id = $tasks->start();

try {
    $tasks->addMessage($id, 'Import gestartet', 0);
    foreach ($items as $index => $item) {
        if ($tasks->shouldStop($id)) {
            $tasks->addMessage($id, 'Stopp-Anforderung erkannt');
            break;
        }
        processItem($item);
        $tasks->addMessage($id, 'Datensatz verarbeitet', 100 * ($index + 1) / count($items));
    }
} catch (\Exception $error) {
    $tasks->addMessage($id, 'Fehler: ' . $error->getMessage());
    throw $error;
} finally {
    $tasks->finish($id);
}
```

Die Datenbankverbindung muss zum gewuenschten Mandanten gehoeren. Schreibzugriffe
muessen zeitnah committed werden: Die Steuerung darf nicht in einer lange offenen
Transaktion der eigentlichen Verarbeitung liegen (ggf. separate Verbindung nutzen).
Eigene IDs koennen mit `start($id)` uebergeben werden; eine bereits vorhandene ID
wird nicht ueberschrieben. Fuer einen erneuten Lauf eine neue ID verwenden.

`requestStop($id)` beendet keinen Betriebssystemprozess. Der Worker muss
`shouldStop($id)` regelmaessig pruefen und selbst aufraeumen. Nach Absturz oder
hartem Abbruch bleibt ein Prozess offen; es gibt keine PID-Erkennung oder
automatische Wiederaufnahme. `finished` kennzeichnet nur den Abschluss,
nicht dessen Erfolg; Fehler gehoeren in die Meldungen.

## Ansicht und HTTP-Schnittstelle

Die Ansicht aktualisiert sich alle fuenf Sekunden, zeigt standardmaessig offene
Prozesse (100 pro Seite) und auf Auswahl die letzten 200 Meldungen als eigene
Zeilen. Abgeschlossene Prozesse koennen eingeblendet werden. Bei einem Ladefehler
stoppt das Polling; **Aktualisieren** startet es erneut.

Alle Endpunkte benoetigen eine aktive Sitzung und den jeweiligen Scope:

| Methode und Pfad | Parameter | Scope |
| --- | --- | --- |
| `GET running-tasks/read` | `include_finished=0/1`, `start=0`, `limit=100` (max. 1000) | `running-tasks.read` |
| `GET running-tasks/messages` | `id`, `limit=200` (max. 1000) | `running-tasks.read` |
| `POST running-tasks/stop` | `id` als Formularparameter | `running-tasks.stop` |

Lesen liefert `{success, data: [...], total}`. Stoppen liefert
`{success, data: {...}}`. Fehler liefern `{success: false, msg}` mit HTTP 400,
404 oder 500. Prozesse starten und Meldungen schreiben erfolgt ueber die PHP-API,
nicht ueber einen generischen Shell-/HTTP-Starter.

## Tests

Die Integrationstests brauchen eine **disposable MariaDB** auf `127.0.0.1`
mit Root-Zugang ohne Passwort. Keine produktive Datenbank verwenden.
Sie erstellen eine zufaellig benannte Testdatenbank und entfernen sie wieder.
Composer-Abhaengigkeiten muessen installiert sein.

```sh
RUNNING_TASKS_TEST_PORT=<testport> composer test
```

Fuer einen externen Composer-Autoloader kann `RUNNING_TASKS_AUTOLOAD` gesetzt werden.
Die Controller-Tests benoetigen Node.js mit integriertem Test-Runner (Version 18+).
Die PHP-Tests pruefen Installation, Lebenszyklus, Meldungen, Pagination und
Routenvertraege gegen MariaDB. Die Node-Tests pruefen Polling, asynchrone
Detailantworten, Fehleranzeige und Stopp-Anforderungen. Sie ersetzen keinen
Browser-Test innerhalb einer vollstaendig konfigurierten Tualo-Office-Installation.
# running-tasks
