
-- Disable foreign key checks during table creation
SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'user',
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


-- Clients table
CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  location VARCHAR(255) DEFAULT NULL,
  industry VARCHAR(255) DEFAULT NULL,
  url VARCHAR(255) DEFAULT NULL,
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




-- Contacts table
CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT(11) DEFAULT NULL,
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
  last_touch_date DATE DEFAULT NULL,
  outreach_status VARCHAR(50) DEFAULT 'Active',
  source VARCHAR(100) DEFAULT NULL,
  contact_owner VARCHAR(100) DEFAULT NULL,
  is_primary_contact TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Jobs table
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


-- Association Table
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


-- Notes table
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
  INDEX idx_contact_id (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Attachments table
CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT DEFAULT NULL,
  filename VARCHAR(255) DEFAULT NULL,
  filepath VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
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

-- Outreach Templates Structure
CREATE TABLE IF NOT EXISTS outreach_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stage_number INT(11) NOT NULL,
  channel VARCHAR(100) DEFAULT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  body TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outreach Templates content
INSERT INTO outreach_templates (id, stage_number, channel, subject, body) VALUES
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


-- Insert default admin user
INSERT INTO users (id, full_name, email, password, role, created_at, force_password_change) VALUES
(1, 'Admin User', 'admin@example.com', '$2y$10$RYuAPo70Z5HB5NH.lLeZ9.NNPm0gmTixDAkM0pEfPQ88b1b65BZxm', 'admin', NOW(), 1);



