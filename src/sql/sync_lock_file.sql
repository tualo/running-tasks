CREATE TABLE IF NOT EXISTS sync_lock_file (
    id VARCHAR(36) PRIMARY KEY,
    started DATETIME NOT NULL,
    stoped DATETIME DEFAULT NULL,
    finished TINYINT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS sync_lock_file_message (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(36) NOT NULL,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    message TEXT NOT NULL,
    progress DECIMAL(5,2) DEFAULT NULL,
    KEY idx_sync_lock_file_message_task (task_id, id)
);
