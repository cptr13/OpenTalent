USE `databasename`;

SET @now := NOW();
SET @admin := 1; -- changed_by -> Admin User ID

-- Conversations for ALL candidates
INSERT INTO status_history
  (candidate_id, job_id, new_status, kpi_bucket, event_type, changed_by, changed_at)
SELECT c.id, NULL, 'Screening / Conversation', 'conversation', NULL, @admin,
       DATE_SUB(@now, INTERVAL 10 DAY)
FROM candidates c;

-- Submittals for first half of candidates
INSERT INTO status_history
  (candidate_id, job_id, new_status, kpi_bucket, event_type, changed_by, changed_at)
SELECT c.id, NULL, 'Submitted to Client', 'submittal', NULL, @admin,
       DATE_SUB(@now, INTERVAL 8 DAY)
FROM candidates c
WHERE c.id % 2 = 0;

-- Interviews for a quarter of candidates
INSERT INTO status_history
  (candidate_id, job_id, new_status, kpi_bucket, event_type, changed_by, changed_at)
SELECT c.id, NULL, 'Interview Scheduled', 'interview', 'interview_scheduled', @admin,
       DATE_SUB(@now, INTERVAL 6 DAY)
FROM candidates c
WHERE c.id % 4 = 0;

-- Hires for a couple of candidates
INSERT INTO status_history
  (candidate_id, job_id, new_status, kpi_bucket, event_type, changed_by, changed_at)
SELECT c.id, NULL, 'Hired', 'placement', NULL, @admin,
       DATE_SUB(@now, INTERVAL 2 DAY)
FROM candidates c
WHERE c.id % 6 = 0;

-- Check KPI distribution for last 30 days
SELECT kpi_bucket, COUNT(*) AS events_30d
FROM status_history
WHERE changed_at >= DATE_SUB(@now, INTERVAL 30 DAY)
GROUP BY kpi_bucket;
