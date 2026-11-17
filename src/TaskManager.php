<?php

namespace Tualo\Office\RunningTasks;

use Ramsey\Uuid\Uuid;
use Tualo\Office\Basic\MYSQL\Database;

class TaskManager
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function start(?string $id = null): string
    {
        $id = $id ?? Uuid::uuid4()->toString();
        $this->validateId($id);
        $this->write(
            'INSERT INTO sync_lock_file (id, started, stoped, finished) VALUES (?, NOW(), NULL, 0)',
            [$id]
        );
        return $id;
    }

    public function get(string $id): array
    {
        $this->validateId($id);
        $row = $this->db->singleRow(
            'SELECT id, started, stoped, finished FROM sync_lock_file WHERE id = {id}',
            ['id' => $id]
        );
        if ($row === false) {
            throw new \OutOfBoundsException('Prozess nicht gefunden.');
        }
        return $this->normalize($row);
    }

    public function shouldStop(string $id): bool
    {
        $task = $this->get($id);
        return $task['stoped'] !== null || $task['finished'];
    }

    public function requestStop(string $id): void
    {
        $this->get($id);
        $this->write(
            'UPDATE sync_lock_file SET stoped = COALESCE(stoped, NOW()) WHERE id = ? AND finished = 0',
            [$id]
        );
    }

    public function finish(string $id): void
    {
        $this->get($id);
        $this->write('UPDATE sync_lock_file SET finished = 1 WHERE id = ?', [$id]);
    }

    public function addMessage(string $id, string $message, ?float $progress = null): void
    {
        $this->validateId($id);
        if (trim($message) === '' || strlen($message) > 65535) {
            throw new \InvalidArgumentException('Eine Meldung muss 1 bis 65535 Bytes enthalten.');
        }
        if ($progress !== null && (!is_finite($progress) || $progress < 0 || $progress > 100)) {
            throw new \InvalidArgumentException('Der Fortschritt muss zwischen 0 und 100 liegen.');
        }
        $this->write(
            'INSERT INTO sync_lock_file_message (task_id, message, progress)
             SELECT id, ?, ? FROM sync_lock_file WHERE id = ? AND finished = 0',
            [$message, $progress, $id]
        );
        if ((int)$this->db->singleValue('SELECT ROW_COUNT() AS affected', [], 'affected') !== 1) {
            throw new \LogicException('Meldungen sind nur fuer vorhandene, nicht abgeschlossene Prozesse erlaubt.');
        }
    }

    public function tasks(bool $includeFinished = false, int $start = 0, int $limit = 100): array
    {
        if ($start < 0 || $limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Ungueltige Seitengroesse oder Startposition.');
        }
        $where = $includeFinished ? '' : ' WHERE finished = 0';
        $rows = $this->db->direct(
            'SELECT id, started, stoped, finished FROM sync_lock_file' . $where .
            ' ORDER BY started DESC, id LIMIT ' . $limit . ' OFFSET ' . $start
        );
        if (!is_array($rows)) {
            throw new \RuntimeException('Die Prozesse konnten nicht gelesen werden.');
        }
        return [
            'data' => array_map([$this, 'normalize'], $rows),
            'total' => (int)$this->db->singleValue('SELECT COUNT(*) AS total FROM sync_lock_file' . $where, [], 'total'),
        ];
    }

    public function messages(string $id, int $limit = 200): array
    {
        $this->get($id);
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Ungueltige Anzahl von Meldungen.');
        }
        // BSC's numeric conversion turns SQL NULL into zero; read the nullable value as text.
        $rows = $this->db->direct(
            'SELECT id, task_id, created, message, CAST(progress AS CHAR) AS progress FROM sync_lock_file_message
             WHERE task_id = {id} ORDER BY id DESC LIMIT ' . $limit,
            ['id' => $id]
        );
        if (!is_array($rows)) {
            throw new \RuntimeException('Die Meldungen konnten nicht gelesen werden.');
        }
        foreach ($rows as &$row) {
            $row['progress'] = $row['progress'] === null ? null : (float)$row['progress'];
        }
        unset($row);
        return array_reverse($rows);
    }

    private function validateId(string $id): void
    {
        if (trim($id) === '' || strlen($id) > 36) {
            throw new \InvalidArgumentException('Eine Prozess-ID muss 1 bis 36 Bytes enthalten.');
        }
    }

    private function write(string $sql, array $parameters): void
    {
        if ($this->db->execute_with_params($sql, $parameters) === false) {
            throw new \RuntimeException('Der Prozessstatus konnte nicht gespeichert werden.');
        }
    }

    private function normalize(array $row): array
    {
        $row['finished'] = (bool)$row['finished'];
        $row['status'] = $row['finished']
            ? ($row['stoped'] === null ? 'finished' : 'stopped')
            : ($row['stoped'] === null ? 'running' : 'stopping');
        return $row;
    }
}
