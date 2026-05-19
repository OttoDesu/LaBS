-- Migrate labs supervisor fields into supervisors + supervisor_id.
-- Run once after adding supervisors table and labs.supervisor_id.

INSERT INTO supervisors (cluster_id, supervisor_name, supervisor_email, created_at, updated_at)
SELECT DISTINCT
    l.cluster_id,
    l.lab_supervisor_name,
    l.lab_supervisor_email,
    NOW(),
    NOW()
FROM labs l
WHERE l.lab_supervisor_name IS NOT NULL
  AND l.lab_supervisor_name <> ''
ON DUPLICATE KEY UPDATE
    supervisor_email = VALUES(supervisor_email),
    updated_at = NOW();

UPDATE labs l
JOIN supervisors s
    ON s.cluster_id = l.cluster_id
   AND s.supervisor_name = l.lab_supervisor_name
SET l.supervisor_id = s.supervisor_id
WHERE l.lab_supervisor_name IS NOT NULL
  AND l.lab_supervisor_name <> '';

-- After verifying data, you can drop legacy columns:
-- ALTER TABLE labs DROP COLUMN lab_supervisor_name, DROP COLUMN lab_supervisor_email;
