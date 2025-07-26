-- Use target database
USE opentalent_dev;

-- Disable foreign key checks during table creation
SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Candidates table
CREATE TABLE IF NOT EXISTS candidates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255),
  secondary_email VARCHAR(255),
  phone VARCHAR(50),
  city VARCHAR(100),
  state VARCHAR(100),
  zip VARCHAR(20),
  linkedin VARCHAR(255),
  current_employer VARCHAR(255),
  resume_text TEXT,
  resume_filename VARCHAR(255),  -- ✅ Added this line
  status VARCHAR(100),
  owner VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Clients table
CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(50),
  location VARCHAR(255),
  industry VARCHAR(255),
  url VARCHAR(255),
  account_manager VARCHAR(255),
  about TEXT,
  contract_filename VARCHAR(255),
  status VARCHAR(50) DEFAULT 'Active',
  primary_contact_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_primary_contact
    FOREIGN KEY (primary_contact_id) REFERENCES contacts(id)
    ON DELETE SET NULL
);



-- Contacts table
CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  full_name VARCHAR(255),
  email VARCHAR(255),
  phone VARCHAR(50),
  phone_mobile VARCHAR(50),
  secondary_email VARCHAR(255),
  title VARCHAR(255),
  department VARCHAR(100),
  linkedin VARCHAR(255),
  address_street VARCHAR(255),
  address_city VARCHAR(100),
  address_state VARCHAR(100),
  address_zip VARCHAR(20),
  address_country VARCHAR(100),
  follow_up_date DATE,
  follow_up_notes TEXT,
  outreach_stage TINYINT(4) DEFAULT 1,
  last_touch_date DATE,
  outreach_status VARCHAR(50) DEFAULT 'Active',
  source VARCHAR(100),
  contact_owner VARCHAR(100),
  is_primary_contact TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);


-- Jobs table
CREATE TABLE IF NOT EXISTS jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  client_id INT,
  location VARCHAR(255),
  type VARCHAR(100),
  status VARCHAR(100),
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

-- Applications table
CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  job_id INT NOT NULL,
  status VARCHAR(100),
  notes TEXT,
  assigned_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

-- Notes table
CREATE TABLE IF NOT EXISTS notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_type VARCHAR(50) NOT NULL,
  module_id INT NOT NULL,
  content LONGTEXT NOT NULL,
  candidate_id INT DEFAULT NULL,
  job_id INT DEFAULT NULL,
  client_id INT DEFAULT NULL,
  contact_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_candidate_id (candidate_id),
  INDEX idx_job_id (job_id),
  INDEX idx_client_id (client_id),
  INDEX idx_contact_id (contact_id)
);

-- Attachments table
CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT,
  filename VARCHAR(255),
  filepath VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (full_name, email, password, role)
VALUES (
  'Admin User',
  'admin@opentalent.org',
  '$2y$10$hLucTZR0aucb5OHjLVW2e.nEuNnza1jVCabWWZ.UcUrgFsZTvQTwW',
  'admin'
);

