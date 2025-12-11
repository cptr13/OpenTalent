-- Disable foreign key checks during table creation
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------
-- System Settings (branding: company name + logo)
-- -------------------------------------------------
CREATE TABLE IF NOT EXISTS system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(255) DEFAULT NULL,
  logo_path VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a default row if table is empty
INSERT INTO system_settings (company_name, logo_path)
SELECT 'OpenTalent', NULL
WHERE NOT EXISTS (SELECT 1 FROM system_settings);

-- Users
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'user',
  job_title VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  force_password_change TINYINT(1) DEFAULT 0,
  UNIQUE KEY unique_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Candidates
CREATE TABLE IF NOT EXISTS candidates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) DEFAULT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) DEFAULT NULL,
  secondary_email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  street VARCHAR(255) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  state VARCHAR(100) DEFAULT NULL,
  zip VARCHAR(20) DEFAULT NULL,
  linkedin VARCHAR(255) DEFAULT NULL,
  resume_text TEXT,
  status VARCHAR(100) DEFAULT NULL,
  owner VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  current_employer VARCHAR(255) DEFAULT NULL,
  additional_info TEXT,
  skills TEXT,
  source VARCHAR(255) DEFAULT NULL,
  resume_filename VARCHAR(255) DEFAULT NULL,
  country VARCHAR(100) DEFAULT NULL,
  experience_years INT(11) DEFAULT NULL,
  current_job VARCHAR(255) DEFAULT NULL,
  expected_pay DECIMAL(10,2) DEFAULT NULL,
  expected_pay_type ENUM('Salary','Hourly') DEFAULT NULL,
  current_pay DECIMAL(10,2) DEFAULT NULL,
  current_pay_type ENUM('Salary','Hourly') DEFAULT NULL,
  formatted_resume_filename VARCHAR(255) DEFAULT NULL,
  cover_letter_filename VARCHAR(255) DEFAULT NULL,
  other_attachment_1 VARCHAR(255) DEFAULT NULL,
  other_attachment_2 VARCHAR(255) DEFAULT NULL,
  contract_filename VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clients
CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  location VARCHAR(255) DEFAULT NULL,
  industry VARCHAR(255) DEFAULT NULL,
  company_size VARCHAR(50) DEFAULT NULL,
  url VARCHAR(255) DEFAULT NULL,
  linkedin VARCHAR(255) DEFAULT NULL,
  account_manager VARCHAR(255) DEFAULT NULL,
  about TEXT,
  contract_filename VARCHAR(255) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'Active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  primary_contact_id INT(11) DEFAULT NULL,
  CONSTRAINT fk_primary_contact FOREIGN KEY (primary_contact_id)
    REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts
CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT(11) DEFAULT NULL,
  contact_status VARCHAR(128) DEFAULT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  full_name VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  phone_mobile VARCHAR(50) DEFAULT NULL,
  secondary_email VARCHAR(255) DEFAULT NULL,
  title VARCHAR(255) DEFAULT NULL,
  department VARCHAR(100) DEFAULT NULL,
  linkedin VARCHAR(255) DEFAULT NULL,
  address_street VARCHAR(255) DEFAULT NULL,
  address_city VARCHAR(100) DEFAULT NULL,
  address_state VARCHAR(100) DEFAULT NULL,
  address_zip VARCHAR(20) DEFAULT NULL,
  address_country VARCHAR(100) DEFAULT NULL,
  follow_up_date DATE DEFAULT NULL,
  follow_up_notes TEXT,
  outreach_stage TINYINT(4) DEFAULT 1,
  outreach_cadence ENUM('voicemail','mixed') DEFAULT NULL,
  last_touch_date DATE DEFAULT NULL,
  outreach_status VARCHAR(50) DEFAULT 'Active',
  source VARCHAR(100) DEFAULT NULL,
  contact_owner VARCHAR(100) DEFAULT NULL,
  is_primary_contact TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobs
CREATE TABLE IF NOT EXISTS jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  client_id INT(11) DEFAULT NULL,
  location VARCHAR(255) DEFAULT NULL,
  type VARCHAR(100) DEFAULT NULL,
  status VARCHAR(100) DEFAULT NULL,
  description TEXT,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Associations (Candidate ↔ Job)
CREATE TABLE IF NOT EXISTS associations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  job_id INT NOT NULL,
  status VARCHAR(100) DEFAULT NULL,
  notes TEXT,
  assigned_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes
CREATE TABLE IF NOT EXISTS notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_type VARCHAR(100) NOT NULL DEFAULT 'general',
  module_id INT(11) NOT NULL,
  candidate_id INT(11) DEFAULT NULL,
  job_id INT(11) DEFAULT NULL,
  client_id INT(11) DEFAULT NULL,
  contact_id INT(11) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  content LONGTEXT NOT NULL,
  INDEX idx_candidate_id (candidate_id),
  INDEX idx_job_id (job_id),
  INDEX idx_client_id (client_id),
  INDEX idx_contact_id (contact_id),
  INDEX idx_module (module_type, module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attachments
CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT DEFAULT NULL,
  filename VARCHAR(255) DEFAULT NULL,
  filepath VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------
-- Email Logs (outbound/inbound)
-- -----------------------------
CREATE TABLE IF NOT EXISTS email_logs (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  direction            ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
  related_module       VARCHAR(50) DEFAULT NULL,
  related_type         VARCHAR(50) DEFAULT NULL,           -- legacy/compat
  related_id           INT UNSIGNED DEFAULT NULL,
  from_name            VARCHAR(255) DEFAULT NULL,
  from_email           VARCHAR(255) DEFAULT NULL,          -- made nullable to allow logging pre-config errors
  to_name              VARCHAR(255) DEFAULT NULL,
  to_email             VARCHAR(255) DEFAULT NULL,          -- legacy/compat
  to_emails            TEXT NULL,                          -- nullable for compat
  cc_emails            TEXT DEFAULT NULL,
  bcc_emails           TEXT DEFAULT NULL,
  subject              VARCHAR(512) NOT NULL,
  body_html            MEDIUMTEXT NULL,
  body_text            MEDIUMTEXT NULL,
  attachments          TEXT DEFAULT NULL,
  headers_json         JSON DEFAULT NULL,
  smtp_account         VARCHAR(100) DEFAULT NULL,
  status               ENUM('queued','sent','failed') NOT NULL DEFAULT 'sent',
  error                TEXT DEFAULT NULL,                  -- legacy/compat
  error_message        TEXT DEFAULT NULL,
  message_id           VARCHAR(255) DEFAULT NULL,
  provider_message_id  VARCHAR(255) DEFAULT NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_message_id (message_id),
  KEY idx_related_module_id (related_module, related_id),
  KEY idx_related_type_id (related_type, related_id),
  KEY idx_created_at (created_at),
  KEY idx_status (status),
  KEY idx_provider_msg (provider_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job Contacts
CREATE TABLE IF NOT EXISTS job_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT(11) NOT NULL,
  contact_id INT(11) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outreach Templates
CREATE TABLE IF NOT EXISTS outreach_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stage_number INT(11) NOT NULL,
  channel VARCHAR(100) DEFAULT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  body TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (Optional) Outreach Templates content seed
INSERT IGNORE INTO outreach_templates (id, stage_number, channel, subject, body) VALUES
(1, 1, 'email', 'Connecting About Hiring Support at {{company_name}}',
'Hi {{first_name}},\n\nI’m reaching out to introduce myself — I run a recruiting firm that helps companies like {{company_name}} find top talent across technology, engineering, operations, and finance.\n\nWould you be open to a quick call this week to see if we might be a fit as a recruiting partner?\n\nBest regards,\n{{your_name}}\n{{your_agency}}'),
(2, 2, 'linkedin', NULL,
'Hi {{first_name}}, I sent a quick note via email and wanted to connect here as well. I’d love to hear how you’re handling talent acquisition at {{company_name}}.'),
(3, 3, 'email', 'Following Up on My Note',
'Hi {{first_name}},\n\nJust following up on my earlier note. My firm provides highly targeted recruiting across professional and technical roles, including direct hire, contract, and interim.\n\nWould it make sense to connect for a few minutes this week?\n\nThanks again,\n{{your_name}}'),
(4, 4, 'linkedin', NULL,
'Hey {{first_name}}, just checking in again. I\'m happy to share a bit more about how we support hiring teams like yours.'),
(5, 5, 'email', 'Talent Gaps at {{company_name}}?',
'Hi {{first_name}},\n\nIs your team facing any hiring challenges right now? We help companies like {{company_name}} fill hard-to-find roles quickly without sacrificing quality.\n\nLet me know if it’s worth a quick chat.\n\nBest,\n{{your_name}}'),
(6, 6, 'linkedin', NULL,
'Still open to a quick conversation, {{first_name}}? Even if there’s nothing urgent, I’d love to learn about your team and keep in touch.'),
(7, 7, 'email', 'Still Worth a Quick Chat?',
'Hi {{first_name}},\n\nI’m still hopeful we can connect. I understand timing is everything. Even if now isn’t ideal, I’d love to introduce what we do and stay in touch for when needs arise.\n\nThanks for considering,\n{{your_name}}'),
(8, 8, 'linkedin', NULL,
'Hi {{first_name}}, I’ll stop chasing for now. If it ever makes sense to connect about talent strategy or hiring support, we’re here to help.'),
(9, 9, 'email', 'Final Follow-Up (For Now)',
'Hi {{first_name}},\n\nThis will be my last outreach for now. If you’re ever open to chatting about talent needs, even informally, I’d be happy to connect.\n\nWishing you continued success,\n{{your_name}}\n{{your_agency}}'),
(10, 10, 'linkedin', NULL,
'Just one last ping in case this got buried. I\'m happy to step back and reconnect down the road if that’s better timing.'),
(11, 11, 'call', NULL,
'Hi {{first_name}}, this is {{your_name}} with {{your_agency}}. I sent over a couple of notes recently and just wanted to quickly introduce myself and see if there’s ever a chance we could support your hiring efforts. You can reach me directly at {{your_phone}}. Thanks!'),
(12, 12, 'email', 'Should I Stay on Your Radar?',
'Hi {{first_name}},\n\nI haven’t heard back, and that’s completely okay. I know how busy things get.\n\nWould it be helpful if I checked back in a few months, or would you prefer I hold off? Either way, wishing you all the best in your role.\n\n– {{your_name}}');

-- KPI / Quota Tracking
CREATE TABLE IF NOT EXISTS kpi_status_map (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module ENUM('recruiting','sales') NOT NULL,
  status_name VARCHAR(100) NOT NULL,
  kpi_bucket ENUM(
    'contact_attempts',
    'conversations',
    'submittals',
    'interviews',
    'offers_made',
    'hires',
    'meetings',
    'agreements_signed',
    'job_orders_received',
    'leads_added',
    'none'
  ) NOT NULL DEFAULT 'none',
  event_type VARCHAR(100) NULL,
  UNIQUE KEY uniq_module_status (module, status_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Canonical status history (no triggers; compatible with PDO/schema runners)
CREATE TABLE IF NOT EXISTS status_history (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('candidate','contact','job') NOT NULL,
  entity_id INT NOT NULL,
  status_name VARCHAR(100) NOT NULL,
  kpi_bucket VARCHAR(100) DEFAULT NULL,
  event_type VARCHAR(100) NULL,
  changed_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Legacy shim fields retained (not auto-synced without triggers)
  candidate_id INT NULL,
  job_id INT NULL,
  new_status VARCHAR(100) NULL,
  changed_at DATETIME NULL,
  INDEX idx_entity_time (entity_type, entity_id, created_at),
  INDEX idx_status_time (status_name, created_at),
  INDEX idx_cand_job_time (candidate_id, job_id, changed_at),
  INDEX idx_changed_at (changed_at),
  CONSTRAINT fk_hist_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goals table (module-aware; matches live DDL)
CREATE TABLE IF NOT EXISTS kpi_goals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  module ENUM('sales','recruiting') NOT NULL DEFAULT 'sales',
  metric ENUM(
    'contact_attempts',
    'conversations',
    'submittals',
    'interviews',
    'offers_made',
    'hires',
    'meetings',
    'agreements_signed',
    'job_orders_received',
    'leads_added'
  ) NOT NULL,
  period ENUM('daily','weekly','monthly','quarterly','half_year','yearly') NOT NULL,
  goal INT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_module_metric_period_user (module, metric, period, user_id),
  KEY fk_goals_user (user_id),
  CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit table (matches live DDL)
CREATE TABLE IF NOT EXISTS kpi_goal_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  goal_id INT(11) DEFAULT NULL,
  target_user_id INT(11) DEFAULT NULL,
  metric ENUM(
    'contact_attempts',
    'conversations',
    'submittals',
    'interviews',
    'offers_made',
    'hires',
    'meetings',
    'agreements_signed',
    'job_orders_received',
    'leads_added'
  ) NOT NULL,
  period ENUM('daily','weekly','monthly','quarterly','half_year','yearly') NOT NULL,
  old_goal INT(11) DEFAULT NULL,
  new_goal INT(11) DEFAULT NULL,
  changed_by INT(11) NOT NULL,
  action ENUM('insert','update','delete') NOT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_goal_id (goal_id),
  KEY idx_target_user_metric_period (target_user_id, metric, period),
  KEY idx_changed_at (changed_at),
  KEY fk_audit_changed_by (changed_by),
  CONSTRAINT fk_audit_changed_by FOREIGN KEY (changed_by) REFERENCES users(id) ON UPDATE CASCADE,
  CONSTRAINT fk_audit_goal FOREIGN KEY (goal_id) REFERENCES kpi_goals(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed status → KPI mappings (idempotent; reflects final Recruiting mapping)
INSERT IGNORE INTO kpi_status_map (module, status_name, kpi_bucket, event_type) VALUES
-- Recruiting statuses (FINAL)
('recruiting','Attempted to Contact','contact_attempts','status_change'),
('recruiting','Contacted','none',NULL),
('recruiting','Screening / Conversation','conversations',NULL),
('recruiting','Submitted to Client','submittals',NULL),
('recruiting','Interview Scheduled','interviews','interview_scheduled'),
('recruiting','Second Interview Scheduled','interviews','second_interview_scheduled'),
('recruiting','Offer Made','offers_made',NULL),
('recruiting','Offer Accepted','none',NULL),
('recruiting','Hired','hires',NULL),

-- Sales statuses (FINALIZED)
('sales','New / Lead Added','leads_added',NULL),
('sales','Contact Attempt - Left Voicemail','contact_attempts','voicemail'),
('sales','Contact Attempt - Email Sent','contact_attempts','email'),
('sales','Contact Attempt - LinkedIn Message','contact_attempts','linkedin'),
('sales','Conversation','conversations',NULL),
('sales','Agreement Sent','none',NULL),
('sales','Agreement Signed','agreements_signed',NULL),
('sales','Meeting to be Scheduled','none',NULL),
('sales','Meeting Scheduled','none',NULL),
('sales','Waiting on Feedback','none',NULL),
('sales','Job Order Received','job_orders_received',NULL),
('sales','No Interest / Lost','none',NULL),
('sales','Future Contact / On Hold','none',NULL);

-- Seed default KPI goals (agency-level; idempotent)
-- Recruiting agency defaults
INSERT IGNORE INTO kpi_goals (user_id, module, metric, period, goal) VALUES
(NULL,'recruiting','contact_attempts','daily',50),
(NULL,'recruiting','conversations','daily',15),
(NULL,'recruiting','submittals','daily',1),
(NULL,'recruiting','interviews','weekly',2),
(NULL,'recruiting','offers_made','monthly',2),
(NULL,'recruiting','hires','monthly',1);

-- Sales agency defaults
INSERT IGNORE INTO kpi_goals (user_id, module, metric, period, goal) VALUES
(NULL,'sales','leads_added','daily',2),
(NULL,'sales','contact_attempts','daily',55),
(NULL,'sales','conversations','daily',10),
(NULL,'sales','agreements_signed','weekly',2),
(NULL,'sales','job_orders_received','weekly',2);

-- -------------------------------------------------------------------
-- Scripts module (v1) — 2025-10-15
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scripts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  context ENUM('sales','recruiting','general') NOT NULL DEFAULT 'sales',
  channel ENUM('phone','email','linkedin','voicemail','sms','other') NOT NULL DEFAULT 'phone',
  subject VARCHAR(255) DEFAULT NULL,                -- used when channel='email'
  stage INT DEFAULT NULL,                           -- cadence step (1..12), NULL = any
  category VARCHAR(100) DEFAULT NULL,               -- Intro, Discovery, Objection, Closing, etc.
  type ENUM('script','rebuttal','template') DEFAULT 'script',
  tags VARCHAR(255) DEFAULT NULL,                   -- comma-separated
  content MEDIUMTEXT NOT NULL,                      -- plain/markdown or HTML
  is_active TINYINT(1) NOT NULL DEFAULT 1,          -- retired = 0 (hidden by default)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ctx_chan_stage (context, channel, stage),
  FULLTEXT KEY ft_scripts (title, content, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- Dynamic Script Personalization (deterministic) — 2025-10-20
-- -------------------------------------------------------------------

-- Script Types (e.g., cold_call, voicemail)
CREATE TABLE IF NOT EXISTS script_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL,
  name VARCHAR(128) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_script_type_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tone Kits (e.g., friendly, consultative, direct)
CREATE TABLE IF NOT EXISTS tone_kits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL,
  name VARCHAR(128) NOT NULL,
  version INT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tone_kit_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tone Phrases (slots inside a tone kit)
CREATE TABLE IF NOT EXISTS tone_phrases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tone_kit_id INT NOT NULL,
  `key` VARCHAR(64) NOT NULL,              -- greeting, value_line, close, permission_check, etc.
  text TEXT NOT NULL,                      -- can reference {{variables}}
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tone_key (tone_kit_id, `key`),
  CONSTRAINT fk_tp_tonekit FOREIGN KEY (tone_kit_id) REFERENCES tone_kits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Script Templates (versioned, one 'active' per type at a time)
CREATE TABLE IF NOT EXISTS script_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  script_type_id INT NOT NULL,
  name VARCHAR(128) NOT NULL,
  version INT NOT NULL DEFAULT 1,
  body MEDIUMTEXT NOT NULL,                -- references {{tone.*}} and variables
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_template_type_status_ver (script_type_id, status, version),
  CONSTRAINT fk_st_type FOREIGN KEY (script_type_id) REFERENCES script_types(id) ON DELETE CASCADE,
  CONSTRAINT fk_st_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_st_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stage/Touch-based Tone Rules
CREATE TABLE IF NOT EXISTS script_rules_stage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  outreach_stage_slug VARCHAR(64) NOT NULL,   -- e.g., 'cold','open','followup'
  touch_min INT NOT NULL,
  touch_max INT NOT NULL,
  default_tone_slug VARCHAR(64) NOT NULL,     -- references tone_kits.slug (by value)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_stage_range (outreach_stage_slug, touch_min, touch_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Persona-based Tone Rules
CREATE TABLE IF NOT EXISTS script_rules_persona (
  id INT AUTO_INCREMENT PRIMARY KEY,
  function_slug VARCHAR(64) NOT NULL,         -- e.g., 'hr','ops','finance','engineering'
  default_tone_slug VARCHAR(64) NOT NULL,     -- references tone_kits.slug (by value)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_function_slug (function_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usage Logging (optional analytics)
CREATE TABLE IF NOT EXISTS script_activity_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  contact_id INT NULL,
  client_id INT NULL,
  job_id INT NULL,
  candidate_id INT NULL,
  script_type_slug VARCHAR(64) DEFAULT NULL,
  tone_used_slug VARCHAR(64) DEFAULT NULL,
  action ENUM('render','copy','print') NOT NULL DEFAULT 'render',
  flags_json JSON DEFAULT NULL,                -- {"smalltalk":true,"micro_offer":false}
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_time (user_id, created_at),
  KEY idx_contact_time (contact_id, created_at),
  KEY idx_client_time (client_id, created_at),
  KEY idx_job_time (job_id, created_at),
  KEY idx_candidate_time (candidate_id, created_at),
  CONSTRAINT fk_sal_user      FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_sal_contact   FOREIGN KEY (contact_id)   REFERENCES contacts(id)   ON DELETE SET NULL,
  CONSTRAINT fk_sal_client    FOREIGN KEY (client_id)    REFERENCES clients(id)    ON DELETE SET NULL,
  CONSTRAINT fk_sal_job       FOREIGN KEY (job_id)       REFERENCES jobs(id)       ON DELETE SET NULL,
  CONSTRAINT fk_sal_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Seeds (idempotent) for the deterministic scripts engine ----

-- Script Types
INSERT IGNORE INTO script_types (id, slug, name, is_active) VALUES
  (1, 'cold_call', 'Cold Call', 1),
  (2, 'voicemail', 'Voicemail', 1);

-- Tone Kits
INSERT IGNORE INTO tone_kits (id, slug, name, version, is_active) VALUES
  (1, 'friendly', 'Friendly', 1, 1),
  (2, 'consultative', 'Consultative', 1, 1),
  (3, 'direct', 'Direct', 1, 1);

-- Tone Phrases (Friendly)
INSERT IGNORE INTO tone_phrases (tone_kit_id, `key`, text) VALUES
  (1, 'greeting', 'Hey {{contact_first}},'),
  (1, 'permission_check', 'Got a quick second?'),
  (1, 'value_line', 'We help teams like yours keep hiring painless.'),
  (1, 'close', 'Want me to send that over?');

-- Tone Phrases (Consultative)
INSERT IGNORE INTO tone_phrases (tone_kit_id, `key`, text) VALUES
  (2, 'greeting', 'Hi {{contact_first}}, appreciate your time.'),
  (2, 'permission_check', 'Okay to take 30 seconds?'),
  (2, 'value_line', 'We typically shorten time-to-slate by tightening intake and aligning early.'),
  (2, 'close', 'Would it help if I sent a one-pager?');

-- Tone Phrases (Direct)
INSERT IGNORE INTO tone_phrases (tone_kit_id, `key`, text) VALUES
  (3, 'greeting', '{{contact_first}}, good {{local_part_of_day}}.'),
  (3, 'permission_check', '30 seconds—yes or no?'),
  (3, 'value_line', 'Vetted slate in 5 days, ~20% fee, 8-week guarantee.'),
  (3, 'close', 'Send one-pager or book 15?');

-- Script Templates (one active per type)
INSERT IGNORE INTO script_templates (script_type_id, name, version, body, status) VALUES
  -- Cold Call v1
  (1, 'Cold Call - v1', 1,
'{{tone.greeting}} {{tone.permission_check}}
Quick note on your {{top_open_role}}—looks open ~{{days_open_top_role}} days.
{{tone.value_line}} {{moment_smalltalk}}
{{micro_offer}}
{{tone.close}}', 'active'),
  -- Voicemail v1
  (2, 'Voicemail - v1', 1,
'{{tone.greeting}} Quick heads-up on {{top_open_role}}.
We’re seeing {{pain_point_snippet}}; {{value_prop_snippet}}.
Call back {{my_phone}} or reply to {{my_email}}. {{tone.close}}', 'active');

-- Stage-based auto-tone defaults (ranges are inclusive)
INSERT IGNORE INTO script_rules_stage (outreach_stage_slug, touch_min, touch_max, default_tone_slug) VALUES
  ('cold', 1, 3, 'friendly'),
  ('open', 4, 6, 'consultative'),
  ('followup', 7, 12, 'direct');

-- Persona-based fallbacks
INSERT IGNORE INTO script_rules_persona (function_slug, default_tone_slug) VALUES
  ('hr', 'friendly'),
  ('ops', 'direct'),
  ('finance', 'direct'),
  ('engineering', 'consultative');

-- Idempotent default admin (survives re-runs and partial imports)
INSERT INTO users (id, full_name, email, password, role, created_at, force_password_change)
VALUES (
  1,
  'Admin User',
  'admin@example.com',
  '$2y$10$RYuAPo70Z5HB5NH.lLeZ9.NNPm0gmTixDAkM0pEfPQ88b1b65BZxm',
  'admin',
  NOW(),
  1
)
ON DUPLICATE KEY UPDATE id = id;

-- -------------------------------------------------------------------
-- NEW: Unified templates for live + cadence (slug-based) — 2025-10-26
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS script_templates_unified (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_slug VARCHAR(191) NOT NULL,
  content_kind ENUM(
    'live_script',
    'voicemail',
    'cadence_email',
    'cadence_linkedin_request',
    'cadence_linkedin_dm',
    'cadence_voicemail',
    'cadence_sms'
  ) NOT NULL,
  touch_number TINYINT NULL,                         -- NULL for live_script
  tone_default ENUM('auto','friendly','consultative','direct') NULL,
  locale VARCHAR(8) NOT NULL DEFAULT 'en',
  status ENUM('draft','active','deprecated') NOT NULL DEFAULT 'draft',
  version INT NOT NULL DEFAULT 1,
  subject VARCHAR(255) NULL,                         -- required at runtime for cadence_email
  body MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_script_templates_unified_slug_ver (template_slug, version),
  KEY idx_stu_kind_touch_status (content_kind, touch_number, status),
  KEY idx_stu_status_slug (status, template_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- NEW: Snapshot backup of unified templates (matches structure)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS script_templates_unified_bak_20251102 (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_slug VARCHAR(191) NOT NULL,
  content_kind ENUM(
    'live_script',
    'voicemail',
    'cadence_email',
    'cadence_linkedin_request',
    'cadence_linkedin_dm',
    'cadence_voicemail',
    'cadence_sms'
  ) NOT NULL,
  touch_number TINYINT NULL,
  tone_default ENUM('auto','friendly','consultative','direct') NULL,
  locale VARCHAR(8) NOT NULL DEFAULT 'en',
  status ENUM('draft','active','deprecated') NOT NULL DEFAULT 'draft',
  version INT NOT NULL DEFAULT 1,
  subject VARCHAR(255) NULL,
  body MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_script_templates_unified_bak_slug_ver (template_slug, version),
  KEY idx_stu_bak_kind_touch_status (content_kind, touch_number, status),
  KEY idx_stu_bak_status_slug (status, template_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- NEW: Objections & Responses (for live conversations) — 2025-10-26
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS outreach_objections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  objection_slug VARCHAR(191) NOT NULL,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(100) NULL,
  locale VARCHAR(8) NOT NULL DEFAULT 'en',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  version INT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_outreach_objections_slug (objection_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outreach_responses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  objection_slug VARCHAR(191) NOT NULL,              -- FK-like to objections by slug
  tone ENUM('friendly','consultative','direct') NOT NULL,
  priority TINYINT NOT NULL,                         -- 1..3
  body MEDIUMTEXT NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  version INT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_outreach_responses (objection_slug, tone, priority, version),
  KEY idx_outreach_responses_objection (objection_slug),
  CONSTRAINT fk_outreach_responses_objection
    FOREIGN KEY (objection_slug) REFERENCES outreach_objections(objection_slug)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
