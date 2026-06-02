CREATE TABLE IF NOT EXISTS lab_supervisor_history (
    history_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lab_id BIGINT(20) UNSIGNED NOT NULL,
    previous_supervisor_id BIGINT(20) UNSIGNED DEFAULT NULL,
    supervisor_id BIGINT(20) UNSIGNED DEFAULT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_lab_history_lab (lab_id, ended_at, started_at),
    KEY idx_lab_history_supervisor (supervisor_id),
    KEY idx_lab_history_previous_supervisor (previous_supervisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO lab_supervisor_history (
    lab_id,
    previous_supervisor_id,
    supervisor_id,
    started_at,
    ended_at,
    created_at,
    updated_at
)
SELECT
    l.lab_id,
    NULL,
    l.supervisor_id,
    COALESCE(l.updated_at, l.created_at, NOW()),
    NULL,
    NOW(),
    NOW()
FROM labs l
LEFT JOIN lab_supervisor_history h
    ON h.lab_id = l.lab_id
   AND h.ended_at IS NULL
WHERE l.supervisor_id IS NOT NULL
  AND h.history_id IS NULL;
