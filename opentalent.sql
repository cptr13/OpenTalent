-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 15, 2025 at 02:05 PM
-- Server version: 10.6.19-MariaDB-cll-lve
-- PHP Version: 8.3.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lqrwlybu_opentalent_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) DEFAULT NULL,
  `job_id` int(11) DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(100) DEFAULT 'Associated to Job'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `candidate_id`, `job_id`, `applied_at`, `assigned_at`, `status`) VALUES
(1, 34, 22, '2025-04-14 06:00:23', '2025-04-14 06:00:23', 'Screening: Associated to Job'),
(2, 30, 20, '2025-04-14 06:32:03', '2025-04-14 06:32:03', 'Screening: Attempted to Contact');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `resume_filename` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `linkedin` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `employer` varchar(255) DEFAULT NULL,
  `experience` varchar(10) DEFAULT NULL,
  `current_salary` varchar(50) DEFAULT NULL,
  `expected_salary` varchar(50) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'New',
  `source` varchar(100) DEFAULT NULL,
  `email_opt_out` tinyint(1) DEFAULT 0,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `secondary_email` varchar(255) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `skype_id` varchar(100) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `additional_info` text DEFAULT NULL,
  `owner` varchar(100) DEFAULT NULL,
  `current_job` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `name`, `email`, `phone`, `resume_filename`, `notes`, `created_at`, `linkedin`, `facebook`, `twitter`, `website`, `job_title`, `employer`, `experience`, `current_salary`, `expected_salary`, `skills`, `status`, `source`, `email_opt_out`, `first_name`, `last_name`, `secondary_email`, `mobile`, `street`, `city`, `state`, `zip`, `country`, `skype_id`, `qualification`, `additional_info`, `owner`, `current_job`) VALUES
(25, 'Jason Cooper', 'jason.cooper@gmail.com', '(312) 555-8701', 'resume_jason_cooper_1744606880.txt', NULL, '2025-04-14 09:01:23', 'https://linkedin.com/in/jason-cooper', '', '', '', NULL, '', '', '', '', 'Python, Django, REST APIs, PostgreSQL, Docker, Kubernetes, AWS', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
(26, 'Natalia Ivanova', 'natalia.ivanova@gmail.com', '(212) 555-2021', 'resume_natalia_ivanova_1744606891.txt', NULL, '2025-04-14 09:01:35', 'https://www.nataliaivanova.com', '', '', '', NULL, '', '', '', '', 'Adobe Creative Suite, Figma, Branding, Campaign Strategy, Copywriting', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
(27, 'Miguel Ramirez', 'm.ramirez@gmail.com', '(737) 555-9083', 'resume_miguel_ramirez_1744606904.txt', NULL, '2025-04-14 09:02:01', 'https://linkedin.com/in/miguel-ramirez', '', '', '', NULL, '', '', '', '', 'AutoCAD, Project Scheduling, OSHA Compliance, Contractor Management, MS Project', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
(28, 'Priya Desai', 'priyadesai.health@gmail.com', '(617) 555-7720', 'resume_priya_desai_1744606929.txt', NULL, '2025-04-14 09:02:12', 'https://linkedin.com/in/priyadesai', '', '', '', NULL, '', '', '', '', 'Healthcare Operations, Remote Workforce, KPIs, EHR Systems, HIPAA', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
(29, 'Derek Chan', 'derekchan.finance@gmail.com', '(312) 555-7182', 'resume_derek_chan_1744606938.txt', NULL, '2025-04-14 09:02:30', 'https://linkedin.com/in/derek-chan-cfa', '', '', '', NULL, '', '', '', '', 'Financial Modeling, Excel, Bloomberg, DCF, M&A, Public Markets', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
(30, 'Amanda Reyes', 'amanda.reyes.design@gmail.com', '(646) 555-1423', 'resume_amanda_reyes_1744606970.txt', NULL, '2025-04-14 09:02:55', 'https://amandareyes.design', '', '', '', NULL, '', '', '', '', 'Figma, Sketch, HTML/CSS, Design Systems, A/B Testing, Wireframing', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
(31, 'Benjamin Carter', 'ben.carter@velocityhealth.com', '(617) 555-6621', 'resume_benjamin_carter_1744606980.txt', NULL, '2025-04-14 09:03:06', 'https://linkedin.com/in/ben-carter-ops', '', '', '', NULL, '', '', '', '', 'Linux, Windows Server, Active Directory, EHR Integrations, Network Security, HIPAA Compliance', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
(32, 'Rachel Stein', 'r.stein@apexfinancial.co', '(312) 555-1882', 'resume_rachel_stein_1744606992.txt', NULL, '2025-04-14 09:03:16', 'https://linkedin.com/in/rachelstein', '', '', '', NULL, '', '', '', '', 'Excel, SQL, Netsuite, Tableau, Reconciliation, Forecasting', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
(33, 'Marcus Lee', 'marcuslee.tech@gmail.com', '(415) 555-2998', 'resume_marcus_lee_1744607004.txt', NULL, '2025-04-14 09:03:27', 'https://github.com/marcuslee', '', '', '', NULL, '', '', '', '', 'JavaScript, React, Node.js, PostgreSQL, AWS, CI/CD', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
(34, 'Stephanie Lin', 'steph.lin@greenfieldbuild.com', '(737) 555-3910', 'resume_stephanie_lin_1744607014.txt', NULL, '2025-04-14 09:03:36', 'https://linkedin.com/in/steph-lin', '', '', '', NULL, '', '', '', '', 'Recruitment, Onboarding, HRIS, Policy Compliance, Payroll, Employee Relations', 'New', NULL, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `account_manager` varchar(255) DEFAULT NULL,
  `parent_client` varchar(255) DEFAULT NULL,
  `fax` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `about` text DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `billing_street` varchar(255) DEFAULT NULL,
  `billing_city` varchar(100) DEFAULT NULL,
  `billing_state` varchar(100) DEFAULT NULL,
  `billing_code` varchar(50) DEFAULT NULL,
  `billing_country` varchar(100) DEFAULT NULL,
  `shipping_street` varchar(255) DEFAULT NULL,
  `shipping_city` varchar(100) DEFAULT NULL,
  `shipping_state` varchar(100) DEFAULT NULL,
  `shipping_code` varchar(50) DEFAULT NULL,
  `shipping_country` varchar(100) DEFAULT NULL,
  `contract_filename` varchar(255) DEFAULT NULL,
  `other_filename` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_size` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `description` text DEFAULT NULL,
  `primary_contact_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `contact_number`, `account_manager`, `parent_client`, `fax`, `website`, `industry`, `location`, `about`, `source`, `billing_street`, `billing_city`, `billing_state`, `billing_code`, `billing_country`, `shipping_street`, `shipping_city`, `shipping_state`, `shipping_code`, `shipping_country`, `contract_filename`, `other_filename`, `created_at`, `phone`, `address`, `notes`, `company_name`, `company_size`, `status`, `description`, `primary_contact_id`) VALUES
(14, 'Campbell-Henderson', '', 'Kim Hodges', NULL, '', 'http://www.gonzalez-schmidt.net/', 'Technology', 'North Ianshire, KS', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-14 03:38:40', NULL, NULL, NULL, NULL, NULL, 'Active', NULL, NULL),
(15, 'HorizonTech Solutions', '+1 (415) 555-2198', 'Lisa Grant', NULL, '', 'https://horizontech.io', 'Technology', 'San Francisco, CA', 'Client prefers passive candidates with 5+ years of SaaS experience. Avoid candidates who recently changed jobs', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-14 03:41:41', NULL, NULL, NULL, NULL, NULL, 'Active', NULL, NULL),
(16, 'GreenField Construction', '+1 (737) 555-1184', 'Mark Torres', NULL, '', 'https://greenfieldbuild.com', 'Construction', 'Austin, TX', 'Client is growing fast and wants bulk hiring support. Will likely need 10+ skilled trades by end of Q2.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-14 03:42:14', NULL, NULL, NULL, NULL, NULL, 'Active', NULL, NULL),
(17, 'BrightLeaf Media Group', '+1 (212) 555-7830', 'Tanya Morales', NULL, '', 'https://brightleafmedia.com', 'Marketing & Advertising', 'New York, NY', 'Hiring freeze until July. Only pipeline candidates for now. They plan to re-engage in Q3.\r\n\r\n', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-14 03:42:58', NULL, NULL, NULL, NULL, NULL, 'Active', NULL, NULL),
(18, 'Apex Financial Partners', '+1 (312) 555-4479', 'Justin Reynolds', NULL, '', 'https://apexfinancial.co', 'Financial Services', 'Chicago, IL', 'Client is extremely selective and wants CFA certification for all analyst roles. Expect longer decision cycles.\r\n\r\n', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-14 03:44:01', NULL, NULL, NULL, NULL, NULL, 'Active', NULL, NULL),
(19, 'Velocity Health Systems', '+1 (617) 555-9032', 'Aisha Carter', NULL, '', 'https://velocityhealth.com', 'Healthcare', 'Boston, MA', 'Client open to remote candidates for non-clinical roles. Emphasizing culture fit heavily.\r\n\r\n', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-14 03:44:33', NULL, NULL, NULL, NULL, NULL, 'Customer', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `secondary_email` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `phone_work` varchar(50) DEFAULT NULL,
  `phone_mobile` varchar(50) DEFAULT NULL,
  `fax` varchar(50) DEFAULT NULL,
  `skype_id` varchar(100) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `address_street` varchar(255) DEFAULT NULL,
  `address_city` varchar(100) DEFAULT NULL,
  `address_state` varchar(100) DEFAULT NULL,
  `address_zip` varchar(20) DEFAULT NULL,
  `address_country` varchar(100) DEFAULT NULL,
  `alt_street` varchar(255) DEFAULT NULL,
  `alt_city` varchar(100) DEFAULT NULL,
  `alt_state` varchar(100) DEFAULT NULL,
  `alt_zip` varchar(20) DEFAULT NULL,
  `alt_country` varchar(100) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `contact_owner` varchar(100) DEFAULT NULL,
  `is_primary_contact` tinyint(1) DEFAULT 0,
  `email_opt_out` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `full_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `client_id`, `first_name`, `last_name`, `email`, `phone`, `company`, `secondary_email`, `department`, `job_title`, `phone_work`, `phone_mobile`, `fax`, `skype_id`, `twitter`, `linkedin`, `address_street`, `address_city`, `address_state`, `address_zip`, `address_country`, `alt_street`, `alt_city`, `alt_state`, `alt_zip`, `alt_country`, `source`, `contact_owner`, `is_primary_contact`, `email_opt_out`, `description`, `created_at`, `full_name`) VALUES
(13, 15, NULL, NULL, 'lisa.grant@horizontech.io', '+1 (415) 555-2198', NULL, NULL, NULL, 'Director of Engineering', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:45:35', 'Lisa Grant'),
(14, 16, NULL, NULL, 'mark.torres@greenfieldbuild.com', '+1 (737) 555-1184', NULL, NULL, NULL, 'Project Manager', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:46:14', 'Mark Torres'),
(15, 15, NULL, NULL, 'james.wu@horizontech.io', '+1 (415) 555-4821', NULL, NULL, NULL, 'CTO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:47:08', 'James Wu'),
(16, 16, NULL, NULL, 'natalie.chen@greenfieldbuild.com', '+1 (737) 555-2229', NULL, NULL, NULL, 'Operations Director', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:48:12', 'Natalie Chen'),
(17, 17, NULL, NULL, 'tanya.morales@brightleafmedia.com', '+1 (212) 555-7830', NULL, NULL, NULL, 'HR Manager', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:48:38', 'Tanya Morales'),
(18, 17, NULL, NULL, 'greg.sandoval@brightleafmedia.com', '+1 (212) 555-1899', NULL, NULL, NULL, 'Creative Director', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:49:04', 'Greg Sandoval'),
(19, 18, NULL, NULL, 'justin.reynolds@apexfinancial.co', '+1 (312) 555-4479', NULL, NULL, NULL, 'Head of Talent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:49:30', 'Justin Reynolds'),
(20, 18, NULL, NULL, 'emily.kwan@apexfinancial.co', '+1 (312) 555-3232', NULL, NULL, NULL, 'Lead Investment Analyst', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:49:54', 'Emily Kwan'),
(21, 19, NULL, NULL, 'aisha.carter@velocityhealth.com', '+1 (617) 555-9032', NULL, NULL, NULL, 'Talent Acquisition Lead', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:50:24', 'Aisha Carter'),
(22, 19, NULL, NULL, 'leonard.fox@velocityhealth.com', '+1 (617) 555-2817', NULL, NULL, NULL, 'Director of Clinical Strategy', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-04-14 03:50:51', 'Dr. Leonard Fox');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `client_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `title`, `description`, `location`, `created_at`, `client_id`, `status`) VALUES
(8, 'Frontend Engineer', 'Develop and maintain modern, responsive web interfaces using React and TypeScript. Strong UX instincts and prior SaaS experience preferred. Collaborate closely with design and backend teams in an agile environment.', 'San Francisco, CA', '2025-04-14 04:42:54', 15, 'Open'),
(9, 'DevOps Engineer', 'Responsible for CI/CD pipelines, cloud infrastructure, and deployment automation. Experience with AWS, Docker, and Terraform required. Must have at least 5 years in a production DevOps role.', 'Remote', '2025-04-14 04:45:01', 15, 'Open'),
(10, 'Product Manager - SaaS', 'Drive product roadmap and feature delivery for HorizonTech’s B2B SaaS platform. Collaborate with engineering and design. Requires strong background in agile product management and a track record of shipping successful features.\r\n\r\n', 'San Francisco, CA', '2025-04-14 04:46:17', 15, 'Open'),
(11, 'Site Supervisor', 'Manage on-site construction operations across residential and light commercial projects. Coordinate trades, enforce safety, and ensure project milestones. 3+ years in construction supervision required.', 'Austin, TX', '2025-04-14 04:46:39', 16, 'Open'),
(12, 'Skilled Carpenter', 'Experienced in framing, drywall, and finish work. Expected to work across multiple active job sites. Must have own tools and reliable transportation.', 'Austin, TX', '2025-04-14 04:47:02', 16, 'Open'),
(13, 'HVAC Installer', 'Install HVAC systems for new builds and renovations. Prior experience with both residential and commercial installs preferred. Certification a plus.\r\n\r\n', 'Austin, TX', '2025-04-14 04:47:27', 16, 'Open'),
(14, 'Content Strategist', 'Lead content planning and brand voice consistency across campaigns. Work with creative and paid media teams. Ideal for someone with agency experience and strong writing chops.\r\n\r\n', 'New York, NY', '2025-04-14 04:48:02', 17, 'Open'),
(15, 'Digital Marketing Analyst', 'Analyze campaign data, optimize ad spend, and generate performance reports. Requires experience with Google Analytics, Meta Ads, and reporting dashboards.\r\n\r\n', 'Remote', '2025-04-14 04:48:29', 17, 'Open'),
(16, 'Graphic Designer', 'Support campaign rollouts with digital and print assets. Must be proficient in Adobe Creative Suite. Portfolio required for consideration.\r\n\r\n', 'New York, NY', '2025-04-14 04:48:58', 17, 'On Hold'),
(17, 'Investment Analyst', 'Support portfolio analysis, modeling, and research on public equities. Must have CFA Level II minimum and 2–3 years in financial services. Strong Excel and analytical writing required.\r\n\r\n', 'Chicago, IL', '2025-04-14 04:49:32', 18, 'Open'),
(18, 'Client Reporting Specialist', 'Prepare client performance reports, pitch decks, and support RFP submissions. Strong attention to detail and PowerPoint skills a must. Background in finance or asset management preferred.\r\n\r\n', 'Chicago, IL', '2025-04-14 04:50:00', 18, 'Open'),
(19, 'Financial Planning Associate', 'Assist senior advisors in developing financial plans. Work directly with clients, update financial software, and schedule reviews. CFP coursework a plus.\r\n\r\n', 'Remote (U.S. only)', '2025-04-14 04:50:20', 18, 'Open'),
(20, 'Healthcare Data Analyst', 'Work with cross-functional teams to analyze patient outcomes, identify trends, and support reporting. Must be comfortable with SQL and data visualization tools. Healthcare background required.\r\n\r\n', 'Boston, MA', '2025-04-14 04:50:44', 19, 'Open'),
(21, 'Clinical Operations Coordinator', 'Support clinical teams with scheduling, compliance, and vendor coordination. Must be highly organized and familiar with healthcare environments.\r\n\r\n', 'Boston, MA', '2025-04-14 04:51:07', 19, 'Open'),
(22, 'Recruiter - Non-Clinical Roles', 'Partner with hiring managers to fill corporate and tech roles. Responsible for sourcing, screening, and coordinating interviews. Culture fit and communication skills emphasized.\r\n\r\n', 'Remote', '2025-04-14 04:51:25', 19, 'Open');

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `job_id` int(11) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `client_id` int(11) DEFAULT NULL,
  `contact_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notes`
--

INSERT INTO `notes` (`id`, `candidate_id`, `application_id`, `job_id`, `content`, `created_at`, `client_id`, `contact_id`) VALUES
(3, 34, NULL, 22, 'Third test, associating to job', '2025-04-14 06:00:23', NULL, NULL),
(4, 30, NULL, NULL, 'Left voicemail', '2025-04-14 07:11:46', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `force_password_change` tinyint(1) NOT NULL DEFAULT 1,
  `role` varchar(50) NOT NULL DEFAULT 'viewer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `job_title`, `phone`, `profile_picture`, `force_password_change`, `role`) VALUES
(3, 'admin@opentalent.org', '$2y$10$cCJxLCgSk.YgmfrU2.Ho1OmwJADHD1zO4DEnNVPYqGO4KDFELx1Di', 'Admin User', 'Super Duper Admin', '555-nope', 'uploads/profile_67fe84316e12b.jpg', 0, 'admin'),
(4, 'recruiter1@example.com', '$2y$10$c.ZeEsi.xOPm2X/Mgr9QquOOcMLP9oTWi1fm4zojj78QdndQDjb56', 'Recruiter One', 'Recruiter', '555-1234', NULL, 1, 'viewer'),
(6, 'Test@recruiting.com', '$2y$10$wV9F6IcG2SP77cTzARCBAulbMxK4YbZGu67cg7.yoIZSECTnRfDwe', 'Test Recruiter', NULL, NULL, NULL, 0, 'recruiter');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_id` (`candidate_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_id` (`candidate_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
