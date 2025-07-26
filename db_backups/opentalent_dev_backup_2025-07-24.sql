/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.11-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: opentalent_dev
-- ------------------------------------------------------
-- Server version	10.11.11-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `associations`
--

DROP TABLE IF EXISTS `associations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `associations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `status` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `job_id` (`job_id`),
  CONSTRAINT `associations_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `associations_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `associations`
--

LOCK TABLES `associations` WRITE;
/*!40000 ALTER TABLE `associations` DISABLE KEYS */;
/*!40000 ALTER TABLE `associations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attachments`
--

DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `filepath` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `candidate_id` (`candidate_id`),
  CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attachments`
--

LOCK TABLES `attachments` WRITE;
/*!40000 ALTER TABLE `attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candidates`
--

DROP TABLE IF EXISTS `candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `secondary_email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `resume_text` text DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `owner` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `current_employer` varchar(255) DEFAULT NULL,
  `current_salary` varchar(100) DEFAULT NULL,
  `expected_salary` varchar(100) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `resume_filename` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candidates`
--

LOCK TABLES `candidates` WRITE;
/*!40000 ALTER TABLE `candidates` DISABLE KEYS */;
INSERT INTO `candidates` VALUES
(1,NULL,'Scott','Rafferty','dw7218@gmail.com',NULL,'(770) 990-4535','','','','','SCOTT RAFFERTY \n    Carrollton, Georgia  \n(770) 990-4535 - dw7218@gmail.com   \nPROFESSIONAL SUMMARY    \nMotivated Project Manager with 15 years of experience controlling all stages of \nprojects from inception through monitoring and closing, exceeding expectations of \nbeing on time and on budget. Considerable client-facing project experience has driven \ncommitment to customer satisfaction. Organized and dependable candidate successful \nat managing multiple priorities with a positive attitude. Willingness to take on added \nresponsibilities to meet team goals. \nSKILLS    \n• Advanced problem solving \n• Project planning and development \n• Business process re-engineering \n• Budget administration \n• Budgeting \n• Commercial construction \n• Construction management \n• Staff Management \n• Technical Support \nWORK HISTORY \n04/2024 to 11/2024 Superintendent  \nRBC Construction – Charlotte, North Carolina \n \n• Identified plans and resources required to meet project goals and objectives. \n• Managed pre-construction to commission. \n• Achieved project deadlines by coordinating with contractors to manage \nperformance and manage costs. \n• Modified and directed project plans to meet organizational needs. \n• Fostered relationships with vendors to promote positive working relationships. \n• Provided accurate, detailed quantity take-offs from project drawings and technical \nspecifications. \n• Reined in project costs while meeting key milestones. \n• Maintained open communication by presenting regular updates on project status to \ncustomers. \n• Reviewed plans and inspected ongoing construction to keep work in line with \nproject goals. \n• Maintained safety onsite and upheld all OSHA regulations.\n\n• Managed workers when performing duties in line with local and national building \ncodes in all areas of construction. \n    03/2022 to 7/2023 \n    Superintendent  \n                                   Advanced Systems Inc. -Woodstock, Ga \n• Identified plans and resources required to meet project goals and objectives. \n• Managed projects from procurement to completement.  \n• Managed job costs and monitored performance of all subcontractors. \n•  Achieved project deadlines by coordinating with contractors to manage   \n    performance. \n• Provided accurate, detailed quantity take-offs from project drawings and technical \n   specifications. \n• Reined in project costs while meeting key milestones. \n• Eliminated discrepancies in progress by reviewing performance, spend and timeline. \n• Maintained open communication by presenting regular updates on project status to \n   customers and commercial landlords. \n• Reported regularly to managers on project budget, progress and technical problems. \n \n01/2016 to 03/2022 Project Manager  \nCopper Sky Renovations – Atlanta, GA  \n \n• Identified plans and resources required to meet project goals and objectives. \n• Managed projects from procurement to commission. \n• Developed and initiated projects, managed costs, and monitored performance. \n• Achieved project deadlines by coordinating with contractors to manage \nperformance. \n• Provided accurate, detailed quantity take-offs from project drawings and technical \nspecifications. \n• Reined in project costs while meeting key milestones. \n• Eliminated discrepancies in progress by reviewing performance, spend and \ntimeline. \n• Maintained open communication by presenting regular updates on project status to \ncustomers. \n• Reported regularly to managers on project budget, progress and technical \nproblems. \n \n07/2009 to 06/2016 Superintendent\n\nJohnson Construction and Maintenance Team – Atlanta, Georgia  \n \n• Identified plans and resources required to meet project goals and objectives. \n• Managed projects from procurement to commission. \n• Developed and initiated projects, managed costs, and monitored performance. \n• Achieved project deadlines by coordinating with contractors to manage \nperformance. \n• Orchestrated projects within strict timeframes and budget constraints by solving \ncomplex problems and working closely with senior leaders. \n• Modified and directed project plans to meet organizational needs. \n• Fostered relationships with vendors to promote positive working relationships. \n• Provided management for internal personnel, contractors and vendors. \n• Provided accurate, detailed quantity take-offs from project drawings and technical \nspecifications. \n• Reined in project costs while meeting key milestones. \n• Maintained open communication by presenting regular updates on project status to \ncustomers. \n• Managed complete construction process to maximize quality, cost-controls and \nefficiency. \n• Supported project coordination and smooth workflow by coordinating materials, \ninspections and contractor actions. \n• Reviewed plans and inspected ongoing construction to keep work in line with \nproject goals. \n• Maintained safety onsite and upheld all OSHA regulations. \n• Managed workers when performing duties in line with local and national building \ncodes in all areas of construction. \nEDUCATION     \n \nNew Hampshire College – Manchester, NH  \n \n \nNew Hampshire Institute of Technology - Manchester, NH   \n \n10/2019 Certificate For Building Science Foundation: Understanding The Energy Code  \nHanley Wood University - Atlanta, GA  \n \n06/1987 High School Diploma  \nMemorial High School - Manchester , NH\n\nCERTIFICATIONS     \nGSWCC Certified for Georgia Soil Water Conservation Commission -Level 1A \nErosion Training \n \nSouthFace-EarthCraft Training Certified \n \nSouthFace Certified Professional Homebuilder Program','','Admin User','2025-07-23 10:56:00','','','','','',NULL),
(4,NULL,'Bobby','Meyer','bobbymeyer405@gmail.com',NULL,'(678) 789-4266','Douglasville','GA','30134','','BOBBY MEYER\r\nbobbymeyer405@gmail.com • (678) 789-4266\r\nDouglasville, GA 30134\r\nSUPERINTENDENT\r\nSummary: Seeking a Superintendent position in the Construction industry, where over ten years of experience and proven skills in\r\nConstruction Management and General Construction will be put to good use. As an OSHA certified professional, safety and\r\ncompliance stand at the core of all operations. Strong leadership skills, excellent communication abilities, and a deep commitment\r\nto customer service are key strengths that greatly contribute to the success of every project. Ready to take on new challenges and\r\nleverage these capabilities to ensure project success, while fostering a safe and productive work environment.\r\nKEY SKILLS\r\nCommercial Construction • Shop Drawings • Heating Ventilation & Air Conditioning\r\nSpecifications • Quality Control Inspection • Quality Control • Logistics • OSHA 30 • Punch Out Lists\r\nProcore • OSHA Certified • General Construction • Construction Management\r\nPROFESSIONAL EXPERIENCE & ACHIEVEMENTS\r\nPaulson Cheek Mechanical, Norcross, GA\r\nField Superintendent\r\n2019 to 2024\r\nOverseeing several jobs at once from start to finish. Ordering all materials for installers. Quality Control on all job sites.\r\nCommunication with all other trades on site to make sure no issues arise with installation of my equipment/ductwork and\r\nboiler,chiller or refrigeration piping.\r\n Running big commercial job sites start to finish with little to no issues.\r\n Running 20-50 man crews in a manor in which I got the best results from the crew.\r\n Earning the respect of my elders/peers and other tradesman onsite.\r\nGAR Mechanical, Douglasville, GA\r\nField Superintendent\r\nOverseeing full hotel renovations,ordered all material for job site,quality control and running a crew of 10-25 men.\r\n Running job site in a safe,clean and thought out jobsite\r\nEDUCATION & TRAINING\r\nHigh School Diploma, Paulding County High School, Dallas, GA\r\n2012 to 2019','New','Admin User','2025-07-23 10:56:00',NULL,NULL,NULL,NULL,NULL,NULL),
(5,NULL,'Gregory','Anthony Haynes','senyah02@yahoo.com',NULL,'(770)274-8727','','','','','Gregory Anthony Haynes \n2651 Hillgrove Drive, Dacula, Georgia 30019 \nMobile: 1(770)274-8727 | Whatsapp: 1(876)376-8599 \nEmail: senyah02@yahoo.com \n \nProfessional Summary \nA seasoned construction management and quality assurance professional with over 20 years of \nexperience overseeing multi-million dollar infrastructure projects for both government and private sector \nclients. Skilled in project planning, budgeting, team leadership, and optimizing project efficiency. Strong \nin supervising labour and material management, ensuring high-quality standards, and delivering \nprojects on time and within budget. Effective in fostering collaboration and working with diverse teams, \nwith a focus on strategic and reliable project completion. \n \nEducation & Certifications \n Bachelors of Science degree in Construction Management, University of Technology, \nKingston, Jamaica (Evaluated and recognised by World Education Services WES) \n Diploma in Quantity Surveying, University of Technology, Kingston, Jamaica \n OSHA 30 Certification \n \nProfessional Experience \nManaging Director | Real Tech Construction Company Ltd. | January 2016 – March 2024 \n Led project management and quality assurance efforts for multiple civil engineering and \nbuilding projects. \n Oversaw procurement, budget management, and subcontractor coordination. \n Supervised site operations and provided weekly, monthly, and quarterly progress reports. \n Managed a wide range of projects, including renovations, expansions, and commercial \nbuildings. \nProject Manager / Quantity Surveyor (Part-time) | R.A.J. Davis Haulage and Construction | \nJanuary 2014 – September 2023 \n Managed several healthcare and infrastructure projects, including renovations and construction \nof medical facilities. \n Provided project management, cost estimation, and quality control oversight. \n Responsible for cost estimation, bill of quantities, and procurement. \n Led project teams and ensured timely completion within budget.\n\nProject Manager | Build Rite Construction Company | April 2008 – December 2013 \n Supervised multi-disciplinary teams for large infrastructure, commercial and residential \nprojects. \n Managed project budgets and schedules, coordinated subcontractors, and handled \nprocurement. \n Notable projects included Belmont Academy and the Portmore Villa housing development. \nProject Supervisor / Clerk of Works | Urban Development Corporation | 2006 – 2008 and 1999-\n2001 \n Supervised various construction and renovation projects for community centers, schools, and \npublic facilities. \n Ensured project timelines and quality standards were adhered to. \n Coordinated site activities for public infrastructure projects, focusing on quality control and \nensuring adherence to specifications. \nProject Manager / Quantity Surveyor / Construction Engineer | D. Bissasor & Associates | 2003 – \n2007 \n Managed a range of commercial, residential, and civil engineering projects including toll plazas, \nhousing developments, and road rehabilitation. \n Handled cost analysis, budgeting, and procurement, ensuring project efficiency. \n \nNotable Projects \n Real Tech Construction: Expansion of Alexandria Community Hospital, renovations for \nJamaica Defence Force, Commercial Complex, National Health Fund pharmacies, and more. \n RAJ Davis Haulage & Construction: Coxswain harvest tank, Caanan Heights infrastructure, \nSt Ann\'s Bay Hospital refurbishment, Multiple road works repair \n Build Rite Construction: Belmont Academy, Portmore Villa Phase 2, and multiple housing \nand commercial developments. \n \nSkills & Technical Proficiencies \n Project Management, Cost Estimating & Budgeting, Contract Administration, Team Leadership \n& Collaboration, Microsoft Office Suite \n Skill in various trades \n Financial Analysis: Skilled in analysing financial data, preparing construction budgets, and \nforecasting expenses to support informed decision-making. \n Communication: Excellent interpersonal and communication skills, adept at conveying complex \ninformation clearly and effectively to diverse audiences. \n Problem-Solving: Strong analytical abilities with a track record of identifying issues, developing \nsolutions, and driving resolution.','New','Admin User','2025-07-23 10:56:00',NULL,NULL,NULL,NULL,NULL,NULL),
(6,NULL,'Serco','Business','Jay13467@gmail.com',NULL,'470-965-4646','','','','','1 \n \nSerco Business \nJA’ VIONTAY THOMAS \nGwinnett County, Georgia \n470-965-4646  \nJay13467@gmail.com \n \n \nPROFESSIONAL SUMMARY \n \nEPA Certified HVAC Tech seeking an opportunity to work with a progressive company which offers advancement. While \nin the United States Marine Corp, I was employed as a Radio Operator and Military Veteran with a Secret Security \nClearance and 3 years of service. Led a team of 3 staff members in radio inventory and repairs within a fast-paced \nenvironment, achieving measurable results. \n \nHVAC Skills \n  \n \n Efficiently reading schematics and blueprints \n Clear understanding of how heating and air conditioning systems function \n Brazing and soldering copper tubing \n Utilizing temperature clamps, multimeters and gauges to troubleshoot and diagnose units \n                         \n \n \n \n \nTechnical Skills: Microsoft Office (Excel, Word, PowerPoint, Teams,) | SharePoint | Oracle (Global Combat Support \nSystem) | Preventative Maintenance | Troubleshooting | Harris Communications | Records Management | Risk Management \n| Armory Management | Preventative Maintenance | Troubleshooting | Inventory Control | Warehouse Operations \n \nPROFESSIONAL EXPERIENCE \n \nUNITED STATES MARINE CORPS – Various Locations  November 2019 – November 2023 \nTransmission Operator Supervisor, Okinawa, Japan (August 2020 – Present) \nSet up radios, antennas, computers and troubleshoot telecommunication equipment for a department of 25 military \nprofessionals \n Coordinated the setup and maintenance of telecom equipment, encompassing radios, antennas, and computers, for \na 25-strong military unit, ensuring seamless communication and operational readiness. \n Spearheaded the successful execution of more than 5 critical Marine Corps projects in the Indo-Pacific by deploying \nand managing over 50 telecom devices, directly contributing to mission success and collaboration. \n Attained selection for the prestigious Marine Corps advanced radio operator course because of exceptional \nperformance and professionalism, resulting in an elevated level of expertise and knowledge in radio systems. \n Played a pivotal role in fostering collaboration among U.S. Navy, Air Force, and international military counterparts, \nincluding South Korea, Japan, and Australia, by delivering comprehensive training in radio operations, \ntroubleshooting, and repair techniques. \n Demonstrated unwavering attention to detail and accuracy while handling encrypted equipment and sensitive data, \nensuring the security and integrity of critical information. \n Established and maintained 100% mission-ready two-way radio communication capabilities for military \nprofessionals, enabling effective operational communications across the globe in a highly sensitive and mission-\ncritical environment. \n \nWALMART – Various Locations,   June 2017- September 2019  \nWarehouse Supervisor, Tallahassee, Florida (August 2019 – September 2019) \n Supervised and developed associates in area of responsibility by assigning duties and coordinating workloads. \n Monitored performance and provided feedback to associates and manager.\n\n2 \n \nSerco Business \n Maintained quality and safety standards in area of responsibility by ensuring associates were trained on company \npolicies, standards, and procedures. \n Worked with associated to unload goods from trucks and planned the maneuver and storage of goods around the \nstore.  \n \nWarehouse Stock and Unloading Associate, Duluth, Georgia (June 2017 – August 2018) \n Operated electrical forklift for faster unloading of goods. \n Supervised team members and made sure everything was done correctly. \n Moved inventory in the backroom and unloading trucks. \n Kept aisles neat and area is clean. \n Engaged vendors and drivers with a positive attitude. \n Greeted customers and answer their questions.  \n \nEDUCATION  \n \nHVAC-R Technician Fortis College   December 2024 \nTactical Transmission Operator, Communication Electronics School, Twentynine Palms, July 2020 \nField Radio Operator, Communication Electronics School, Twentynine Palms, July 2020 \nHigh School Diploma, FAMU Development Research School - 2017 \n \nCERTIFICATIONS \nUniversal EPA -2024 \nTactical Transmission Operator - 2020. \nField Radio Operator - 2020 \nUnited States Government Motor Vehicle Operator - Valid until 2029  \n \nSELECT AWARDS \n \n United States Marine Corps Good Conduct Medal  \n National Defense Service Medal  \n Sea Service Deployment Ribbon  \n Global War on Terrorism Ribbon','New','Admin User','2025-07-23 10:56:00',NULL,NULL,NULL,NULL,NULL,NULL),
(7,NULL,'Jake','Wells','jakewells2531@gmail.com',NULL,'706-766-4660','','','','','Jake Wells \nP: 706-766-4660 \nE: jakewells2531@gmail.com \nTrion, Georgia 30747 \n \n \nProfessional Summary and Career Objective:  \n• I have nearly 10 years of experience within the concrete industry, and the various \nresponsibilities I have had over my work history has allowed me to accumulate a \nknowledge of concrete application and its behaviors. I have intentions of finding an \nenvironment where I can continue my experiences and learning, while being a \ndependable asset that fosters progress and productivity. \n \nExperiences: \n• Mechanical Apprentice (September 2023 – Present) Coley and Norton Mechanical -  \nTrion, Ga. \no Assembling/Dismantling of Machinery,  Pouring Structural Concrete, Steel \nerection, Millwrighting work \n \n• Concrete Project Manager (March 2023 – September 2023) Cox Concrete - Chattanooga, \nTn \no Overseen a crew of 5-15 finishers for residential/commercial concrete pours. \nMaintained communication with customers and General Manager, also relayed \njob specifics to finishers to ensure quality. Coordinated materials and equipment \nfor jobs  \n \n• Laborer/Helper (July 2022 – March 2023) Elite Millwright Services - Leesburg, Tn \no Performed millwright and mechanical maintenance services in the paper and \npulp industry mills along the southeastern U.S.  \n \n• Project Engineer  (July 2021-June 2022) C.W. Matthews Contracting Company – Major \nProjects, Atlanta, GA \no Involved in heavy civil projects located within Hartsfield-Jackson Atlanta \nInternational Airport. Responsible for submittals, reporting daily quantities, \nPayroll submissions, Envision Reporting, DBE participation monitoring.  \n \n•  Field Technician (August 2020-January 2021) - Collier Engineering, Nashville, TN \no Continued similar responsibilities as an intern, while increasing exposure to \ndiverse situations involving geotechnical issues \n \n• Intern (May 2020 - August 2020) Collier Engineering, Nashville, TN \no Served as an on-site technician. Performed duties such as fill observation, \nconcrete testing, rebar/formwork inspections, along with lab experience\n\n• Intern ( May 2019-August 2019) - Breckenridge Material Company, St.Louis, MO \no Sampled and tested concrete for quality purposes. Communicated results with \nbatch-men to consistently achieve jobsite specifications of concrete. \n \n• Concrete Finisher / Laborer  (January 2014 – May 2019 )  Dixie Concrete Inc., \nSummerville, GA \no Responsible for grading, sub-grade prep, building and removing formwork, steel \nreinforcement placement, and concrete placing/finishing \n \nEducation: \n• Middle Tennessee State University, College of Basic and Applied Sciences (2017-2021) \nMurfreesboro,  Tn \no Bachelors of Science  \no Major- Concrete Industry Management  \no Minor- Business Administration  \n• Trion High School (2013-2017) Trion, Ga  \n \nSkills: \n• Can operate some heavy machinery (skid steer , fork lift, mini excavator)  \n• Can use tools and methods to excavate, grade, form, place, and finish concrete  \n• Can test ready mixed concrete for temperature, slump, air content, unit weight, and \nmake samples for testing \n• Can utilize laboratory equipment for concrete testing of compressive strength and other \nvarious tests \n• Familiar with utilizing construction management software such as Bluebeam, Revu, \nExcel  \n• Can complete takeoffs for concrete projects and complete project submittals, RFI’s, \nblueprint revisions \n• Can utilize tools and methods practiced in the mechanical/millwright industry \n• Can organize and lead a concrete project  \n \nCertifications/Training: \n• ACI Field Technician - Level 1 \n• ACI Concrete Flatwork Technician \n•.    CPR/First Aid \n•   Worksite Erosion Control Supervisor \n•.    Worksite Traffic Control Supervisor \n• Competent Person Trenching and Excavation \n• Confined Space','New','Admin User','2025-07-23 10:56:00',NULL,NULL,NULL,NULL,NULL,NULL),
(8,NULL,'James','Jenkins','jmjenkins007@gmail.com',NULL,'404-326-7058','Conyers','GA','30094','','James Jenkins\n3860 Troupe Smith Road\nConyers, GA 30094\n404-326-7058 | jmjenkins007@gmail.com\nOBJECTIVE\nApply my 30+ years of Business and Project Development/Estimating/Project Managing/Problem Solving experience to a \nnew team. My motto is, \"Every Project Finished Ahead of Scheduled Time, Each and Every Time, Staying Within Budget, \nWhile Delivering the Highest Of Quality Standards”. \nEMPLOYMENT HISTORY\nEstimator     September 2018 – January 2020\nProduction Wallcovering\n\ncarpet, FFE items (removal and installation), Drywall, Framing, EIFS, etc.\nVP Operations /Sr. Project Manager/Estimator    March 2014— July 2018\nNew Life Hospitality, Inc. \n\nSTARWOOD, WYNDHAM, and more), as well as Business Development - Complete Due Diligence (Review \nReports/Site Inspections/ Projected Costs).Create/Develop a final overall project cost  for a Proposed Development \nto determine financial viability. Creating an Independent Hotel Design and Standards within a Proposed Budget \nand Timeline\n\nsubcontractors, as well as negotiating contracts and subcontracts, developing brand standards, and scheduling and \nphasing full projects\n\n\nRequired Design/Generation of Flag, and FFE Matrix\n\n\nunaddressed issues, purchasing and storing construction materials, and coordinating delivery of all job equipment\n\n\narchitects, designers, engineers, brand representatives, and all inspectors, as well as FOH, BOH, housekeeping, \nengineering, and subcontractors\n\n\n\n\nby Verifying work in place \n\nEstimator /Project Manager/Superintendent             August 2013 — March 2014\nUnited Renovations Hospitality Group, Inc. \n\n\nadditions/renovations, and brand changes\n\n\n\n\n\nof Flag, FFE MATRIX\n\nCommented [DM1]:  I might still get rid of these or incorporate \nthem into other bullet points to save space\n\nJames Jenkins\n3860 Troupe Smith Road\nConyers, GA 30094\n404-326-7058 | jmjenkins007@gmail.com\n\n\nProtocols and Meetings/Job Progress Meetings\nEstimator             February 2009 — June 2014\nProduction Wallcovering \n\nMillwork, Plumbing, Vanities, Bath Tubs and Tub Surrounds, Light Electrical (Plugs/Switches/Fans, Vanity Lighting, \nSconces, Chandeliers, Tall Lamps, Table Lamps, Televisions, Heat Lamps, Smoke Detectors and Strobes), \nDrywall/Acoustic Ceilings/ EIFS/Concrete/Masonry/Light HVAC (Duct/Registers/Vents/PTAC\'s and Cans), Light Low \nVoltage(WIFI), Doors, Frames, Grab Bars, Mirrors, Shelving, Glass Shower Doors, Insulation, Porte Cochere, \nRoofing, Art, Artwork, Murals, Custom Glass Walls, Pool and Sauna Restoration, Athletic Flooring, Windows, \nGlazing\n\nFlashing, Coping, Caulking (Horizontal and Vertical)\nOwner/Project Manager/Estimator/Journeyman          January 1995 — January 2009\nJenkins Painting and Contracting\n\n\nbuildings, manufacturing facilities, warehouses, wastewater treatment plants, entertainment venues (movies and \nsports), mall/strip mall retail, convenience store, museum, mixed use facilities, and custom high-end residential \nspaces\nOwner          January 1991 — January 1995\nAspirations, Inc. \n\n\n\nEstimator & Project Manager          January 1987 — January 1991\nPrecision Wall Tech, Inc. \n\nwarehouses, hospitals, and schools\n\nmanaging 130 painters and 80+ other workers/subcontractors\n\nwith no other estimator/project manager/field supervisor\nEDUCATION\nGeorgia Perimeter College         August 2008 — May 2010 (no diploma) \nNA, Science Medical Engineering\nLincoln Technical Institute    April 1982 — April 1983\nCertificate, HVAC\nPrince Georges Community College August 1978 — April 1980\nNA, Mechanical Engineering/Accounting','New','Admin User','2025-07-23 10:56:00',NULL,NULL,NULL,NULL,NULL,NULL),
(10,NULL,'Ryan','Shanik','ryandshanik@gmail.com',NULL,'404-481-0368','','','','','Ryan Daniel Shanik \nInstallation Technician\nPreventive Maintenance Technician\nWarehouse Manager\nConstruction Foreman\nOther\nOther:Estimator\nContact Information\nJob application form\nAll applicants are considered for all positions without regard to race, religion, color, sex, gender, sexual \norientation, pregnancy, age, national origin, ancestry, physical/mental disability, medical condition, \nmilitary/veteran status, genetic information, marital status, ethnicity, citizenship or immigration status, or \nany other protected classification, in accordance with applicable federal, state, and local laws. By \ncompleting this application, you are seeking to join a team of hardworking professionals dedicated to \nconsistently delivering outstanding service to our customers and contributing to the financial success of \nthe organization, its clients, and its employees. Equal access to programs, services, and employment is \navailable to all qualified persons. Those applicants requiring an accommodation to complete the \napplication and/or interview process should contact a management representative.\nFull Name*\nPosition Applied for*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6TR… 1/15\n\nMableton\nGA\n30126\n404-481-0368\n404-661-8937\nryandshanik@gmail.com\nMost recent employer\nInterior Demolition Services, Inc.\nCity*\nState*\nZip Code*\nMain Phone Number*\nAlernate Phone Number*\nEmail Address*\nMost recent employer name*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6TR… 2/15\n\nMark Tomlinson \nYes\nNo\n1092 West Atlanta Road, Marietta, GA 30360\n770-792-0071\nMM\n/\nDD\n/\nYYYY\nMM\n/\nDD\n/\nYYYY\nSupervisor*\nMay we contact?*\nStreet Address of most recent employer*\nPhone number*\nStart date with of most recent employer*\n12102022\nLast day with of most recent employer*\n02172026\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6TR… 3/15\n\nEstimator \nEstimate demolition, and concrete cutting jobs. Review construction drawings and create bids. Develop \nrelationships with customers (General Contractors) and land new customers. Send bids and obtain work for \nthe company. Manage jobs in progress. Attend pre-construction meetings.  Handle change orders.  \nI’m currently employed here. Please do not contact unless I accept an offer.  I’m looking for career growth \nopportunities. IDS is a small company and they’re planning to stay small. I love the guys I work with but \nthere just isn’t opportunity for career development internally.\nSecond Employer\nConcrete Pump Supply \nHarry Pitts \nJob Title - most recent employer*\nJob Duties - most recent employer*\nReason for leaving - most recent employer*\nSecond employer name*\nSupervisor*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6TR… 4/15\n\nYes\nNo\n5300 Riverview Road, SE, Mableton, GA 30126\n678-245-0210\nMM\n/\nDD\n/\nYYYY\nMM\n/\nDD\n/\nYYYY\nSales Rep \nMay we contact?*\nStreet Address of Second employer*\nPhone number*\nStart date with of Second employer*\n06032019\nLast day with of Second employer*\n03102022\nJob Title - Second employer*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6TR… 5/15\n\nI was responsible for selling concrete pumping parts and pipe.  My main focus was selling boom systems or \npipe kits. I handled those and the department for the company. The job required me to read through pump \ntruck schematics and essentially do take offs on the pipe required for the specific vehicle. With that \ninformation I created a quote for the customer. Once sold the order would go back to production and I was \nresponsible for working with the production manager to perfect the process. I held meetings for the \nproduction team. We were able to turn a failing pipe department into a successful profit generating arm of \nthe business. \nI left to help my dad. He’s had a number of major medical issues including cancer. I left on good terms with \na two week notice and I’ve remained good friends with Harry (Sales Manager) ever since I left. He’s offered \nme more money to come back but I’ve passed. I wanted to get out of direct sales. I was interested in \nEstimating and I found a job doing just that. \nThird Employer\nIndependent Tennis Pro\nMe\nYes\nNo\nJob Duties - Second employer*\nReason for leaving - Second employer*\nThird employer name*\nSupervisor*\nMay we contact?*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6TR… 6/15\n\n590 Pineland Circle SW\n404-481-0368\nMM\n/\nDD\n/\nYYYY\nMM\n/\nDD\n/\nYYYY\nTeaching Tennis Pro\nTeach tennis lessons. Mentor young players.  Coach teams. \nStreet Address of Third employer*\nPhone number*\nStart date with of Third employer*\n03112009\nLast day with of Third employer*\n03112019\nJob Title - Third employer*\nJob Duties - Third employer*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6TR… 7/15\n\nI met my wife and wanted a career and a family. \nThe main gap is between CPS and IDS  from 3/22 to 12/22. I was helping my dad during that time. My dad \nhad many trips to the ER during that time, multiple hospitalizations and surgeries.  I thought we were going \nto lose him and I wanted to be with him. \nI waited tables for a year when I was 19. I feel like that job taught me more about customer service and \ndealing with people than just about any other job. \nEducation\nYes\nNo\nCentennial High School\nReason for leaving - Third employer*\nExplain any gaps in your employment history*\n                    \nList any other experience, job-related skills, additional languages, or other qualifications that\nyou believe should be considered.\n*\nHigh School or GED Diploma/degree?*\nHigh School or GED School Name\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6TR… 8/15\n\nYes\nNo\nN/A\nN/A\nYes\nNo\nGeorgia Southern University \nBusiness and Sports Medicine \nTrade School/Technical College Certificate or Degree?*\nTrade School/Technical College Name*\nTrade School/Technical College Area of study/Degree or Certificate*\n                    \nCollege/University Degree\n*\nUniversity or College Name*\nCollege/University Area of study/Degree*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6TR… 9/15\n\nYes\nNo\nN/A\nN/A\nBusiness or Professional References\nYes\nNo\nHarry Pitts, Sales Manager at CPS, friend and old manager, 678-245-0210, harry@concretepumpsupply.com\nGraduate/professional school Degree*\nGraduate/professional University or College Name*\nCollege/University Area of study/Degree*\nMay we contact your references?*\nReference #1: Name, Title, Relationship and Contact information*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6T… 10/15\n\nChis Dover, Director of Operations at Doggett Concrete, My customer when I was at CPS and now one of my \nfriends, 828-601-1935\nGene Byars (please do not contact unless I accept an offer) PM at IDS, Coworker - he manages a lot of jobs I \nsell and we work closely together as a team, 678-791-6684\nGeneral Information\nI applied to a post on Indeed\nN/A\nYes\nNo\nNo\nReference #2: Name, Title, Relationship and Contact information*\nReference #3: Name, Title, Relationship and Contact information*\nHow were you referred to Barranco?*\nIf Employee Referral, please let us know who referred you.*\nAre you at least 18 years old?*\nIs any additional information relative to name changes, use of an assumed name, or nickname\nnecessary to enable a check on your work and educational record?\n*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6T… 11/15\n\nNo\nNo\nMM\n/\nDD\n/\nYYYY\nYes\nNo\nYes\nNo\nHave you ever worked for this company before? If yes, please provide dates and position:*\nDo you have friends and/or relatives working for this company? If yes, please provide name(s)\nand relationship(s):\n*\nOn what date are you available to begin work?*\n03112025\nIf hired, do you have a reliable means of transportation to and from work?*\nCan you travel if the position requires it?*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6T… 12/15\n\nYes\nNo\nYes\nNo\nYes\nNo\nYes\nNo\nApplicant Statement and Agreement\nPlease read and initial each paragraph below. Ask if there is anything that you do not understand.\nCan you relocate if the position requires it?*\nAre you eligible to work in the United States without Visa sponsorship?*\nHave you ever been convicted of a felony?*\nAre you able to perform the essential job functions of the job for which you are applying, with or\nwithout reasonable accommodation?\n*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6T… 13/15\n\nRS\nRS\nRS\nRS\nRS\nI hereby authorize the company to thoroughly investigate my references, work record,\neducation, and other matters related to my suitability for employment. I also authorize my prior\nemployers and references to disclose any relevant information to the company without prior\nnotice to me. I release the company and all related entities from any claims arising from this\ninvestigation. Please Initial Below\n*\nIn the event of my employment, I agree to comply with all company rules and\nregulations.   Please Initial Below\n*\nIf hired, I understand and agree that my employment with the company is at-will and that either I\nor the company may terminate the employment relationship at any time, with or without cause\nor notice. Please Initial Below\n*\nI acknowledge that workplace safety is important to the company, and I agree to follow all safety\nprocedures, guidelines, and supervisor directions. Please Initial Below\n*\nI certify that the answers provided by me are true and correct to the best of my knowledge. I\nunderstand that any misrepresentation or omission may result in rejection of my application or\nimmediate discharge if I am employed. Please Initial Below\n*\n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6T… 14/15\n\nRS\nRS\nSignature Section\nRyan Daniel Shanik \nMM\n/\nDD\n/\nYYYY\nThis form was created inside of Barranco Enterprises.\nI understand that if selected for hire, I must provide proof of my identity and legal authority to\nwork in the United States. Please Initial Below\n*\nIf any term or portion of this agreement is declared void or unenforceable, it shall be severed,\nand the remainder of the agreement will remain enforceable. Please Initial Below.\n*\nType Full Name*\nDate*\n02172025 \nForms \n2/27/25, 9:30 AM	Job application form\nhttps://docs.google.com/forms/d/1JBLWGJaCexeXuD7c2roiLtA8Teirsb0z_PYbGELSYGM/edit#response=ACYDBNhvA6kOQDbtvmAC60LO5c6T… 15/15','','Admin User','2025-07-23 10:56:01','','','','','',NULL),
(11,NULL,'Senior','Maintenance Technician','lb.corbin@yahoo.com',NULL,'3466683115','','','','','Corbin Lamb \nSenior Maintenance Technician \n Profile \nDedicated and experienced certified Car Mechanic with over five years of \nexperience working in automotive shops. An extremely focused and detail-\noriented technician who is capable of accurately analyzing, diagnosing, and \nrepairing a variety of issues using advanced equipment and comprehensive \nchecklists. Bringing forth extensive hands-on experience with mechanical \nmaintenance and Experienced and resourceful Maintenance Technician with \nover five years of experience providing quality customer service. repairs \nExperienced in leading teams to evaluate system functioning and remain \nforward-thinking in design and progress. Hardworking and experienced \nHandyman able to perform a variety of maintenance duties with skill.  \n Employment History \nDiesel Machinic   at AEER AUTO , Humble \nJanuary 2017 — October 2019 \n● Performed routine inspections to identify potential issues and \nrecommend repair or replacement of components \n● Diagnosed and repaired complex mechanical and electrical issues on a \nvariety of vehicles \n● Followed safety protocols to ensure the safe operation of vehicles \n● Tested and inspected vehicles to ensure quality and safety standards \nwere met \nMaintenance Supervisor     at Creative Solution, Palestine \nOctober 2019 — April 2021 \n● Developed and implemented a maintenance schedule to ensure regular \nservicing and maintenance of HVAC systems \n● Developed and implemented a preventive maintenance program to \nextend the life of machines and reduce maintenance costs \n● Led a team of maintenance technicians in successfully completing a \nmajor facility upgrade on-time and within budget \n● Monitored and managed the team\'s performance to ensure that safety \nand quality standards were met \n● Analyzed maintenance data to identify areas of improvement and \ndevelop strategies to achieve efficiency goals \nMaintenance Tech at Wal-Mart , Crockett \nApril 2021— September 2024 \n● Perform minor repairs on facilities, equipment, or fixtures (for example, \nplumbing, electrical, carpentry, material handling equipment, food \nequipment) \n \n● Complete routine maintenance to ensure safety and proper \nfunctionality \n \n● Manage work orders and routine maintenance schedules by \ncompleting and providing required written and electronic \ninformation (for example, expense vouchers, weekly summaries, \nwork orders, maintenance logs \nDetails \nLeesville, United \nStates, 3466683115 \nlb.corbin@yahoo.com\n\nEducation \nHigh school, Shepherd High, Shepherd  \nAugust 2009 — May 2013 \nase certification, NADC Trade school, Nashville \nAugust 2013 — June 2015 \nAutomotive degree','New','Admin User','2025-07-23 10:56:01',NULL,NULL,NULL,NULL,NULL,NULL),
(19,NULL,'Steven','Barber','barbersd@gmail.com',NULL,'+1 (810) 569-6328','Flint','MI','','','Steven Barber\r\nbarbersd@gmail.com | +1 (810) 569-6328 | Flint, MI\r\nSUMMARY\r\n\r\nA passionate and experienced manager in the food and beverage industry. 15+ years in sales and trade management including B2B, B2C, DSD and DTC. 10+ years in operations/supply chain management, executive leadership and business development. A proven track record of defining enterprise goals and company workflows to ensure excellence and compliance. Adept at partnering with diverse teams and executives to lead change initiatives to include Go-to-Market strategies. \r\nEXPERIENCE\r\n\r\nSales/Trade Marketing Coordinator, Misunderstood Whiskey Co./OATRAGEOUS             Apr. 2024 – \r\n    Lead awareness and sales efforts in MI markets, focusing on on/off-premise accounts.\r\n    Recruit, onboard, and train Brand Ambassadors to meet field performance goals for all markets.\r\n    Align objectives across accounts, the company, and Brand Ambassador teams. \r\nDevelop and refine programs to achieve brand goals while driving cultural improvements.\r\nFreelance Project Manager, Brand Diverse Solutions 		  		                Apr. 2017 –\r\nConsulting in Supply Chain, Operations, Sales, IT &amp; Marketing for start-ups &amp; NPO’s.\r\nNational Account Manager for Rootless Coffee Co. \r\nEvent Specialist for Misunderstood Whiskey; #1 in national demo sales \r\nIT, Marketing &amp; Ops Advisor for Flint Public Art Project\r\nLeverage network resources to mitigate barriers of entry in F&amp;B/CPG. \r\nProfessional Photography &amp; Videography, FAA CFR Part 107 Drone Pilot.\r\nTerritory Sales Manager, Congo Brands					             July 2024 – Jan. 2025\r\nBuilt and reinforced positive brand relations with independent, regional and national DSD accounts. \r\nExecuted according to KPI’s from strategic sales plan to maximize growth opportunities. \r\nExceeded KPI’s consistently maintaining top 10% TSM’s nationally; #1 in achieved in 4 months. \r\nProvided constant communication related to Prime &amp; Alani brands from the market to leadership. \r\nCoordinated with distribution partners to execute product releases and displays. \r\nWholesale Manager, Blake’s Orchard &amp; Cider Mill 	    	                      Sept. 2023 – Mar. 2024\r\nLed a team of up to 10 people in production and supply chain. \r\nOwned the complete process of supply chain and production, reducing COGS by 10%. \r\nDeveloped and executed demo programs with targeted specialty retailers to increase sales.\r\nCollaborated across departments and sister company for marketing materials and strategic sales plan. \r\nProcessed all sales/purchase orders of raw materials and finished goods for NA beverage business.  \r\nOptimized all aspects of manufacturing using GMP’s and SSOP’s to acquire SQF certification. \r\nAcquired co-packing/white label contract with major retailer with a forecasted $2M in revenue.	\r\nNational Events Manager, Three Jerks Jerky 					Apr. 2015 – Jan. 2019\r\nResponsible for managing national events, including trade spend. \r\nProcessed leads acquired at each event/trade show to generate B2B sales. \r\nBuilt relationships with specialty retailers, brokers and distributors and developed sales strategies. \r\nIncreased B2C sales 10x by implementing SOP&#039;s and KPI&#039;s for events. \r\nDirector of Operations, Waiakea Hawaiian Volcanic Water 		            Dec. 2014 – Apr. 2017\r\nManaged global supply chain including R&amp;D, Procurement, Production (co-packer) &amp; Distribution. \r\nNetSuite MRP/ERP implementation from Salesforce, system administrator for both. \r\nProcessed all sales-orders through ERP from Amazon, Shopify and PO’s, et al. \r\nEstablished and managed relationships with vendors and co-packer to drive continuous improvement by implementing SOP&#039;s &amp; KPI’s, yielding a reduction in COGS by 25%. \r\nCo-Engineered company&#039;s vertically integrated facility in Hilo, HI &amp; built out the LA HQ.\r\nCreated data reports to facilitate P&amp;L, Sales Forecast’s, Demand Planning and Carbon Neutral.\r\nChampioned key accounts, fostering relationships with retailer&#039;s, distributor&#039;s, broker&#039;s, et al.\r\nManaged &amp; operated all domestic, industry trade shows such as Expo West, NRA &amp; Fancy Food\r\nCollaborated with Dir. of Sales to implement coupons, sales blitz &amp; data-driven sales decisions. \r\nManaged industry-relevant compliance &amp; certifications for bottled water at the local, state, federal &amp; international levels to include, but not limited to: Public Health Department&#039;s, FDA &amp; IBWA. \r\nMitigated a California Department of Public Health imposed embargo, triggered by competition. \r\nDeveloped &amp; Implemented HACCP plan to ensure a consistent &amp; complaint product.\r\nEDUCATION\r\nUniversity of Michigan – Flint 						          Aug. 2006 – Aug. 2011 \r\nBachelors in Business Administration - Finance\r\nOTHER\r\nTechnical Skills: NetSuite, Salesforce, G-Suite, Microsoft, Adobe, Amazon/Walmart Seller Central, Shopify, WMS, Slack, Asana, HubSpot, WordPress, PLC’s, HVAC, Electrical, Plumbing, General Construction, Plastic Blow-Molding, Aviation. KARMA (VIP, iDIG), 5S, Kaizen, Lean-Manufacturing\r\nCertification &amp; Training: \r\nHACCP Certificate – Michigan State University Food Safety Program\r\nCFR Part 107 License – Federal Aviation Administration\r\nServSafe Alcohol – National Restaurant Association\r\nLanguages: English, Spanish \r\nAffiliations: \r\nFlint Public Art Project Board\r\nU of M Alumni Association',NULL,NULL,'2025-07-24 08:03:48',NULL,NULL,NULL,NULL,NULL,'1753344221_Steven.Barber.Resume.docx');
/*!40000 ALTER TABLE `candidates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `account_manager` varchar(255) DEFAULT NULL,
  `about` text DEFAULT NULL,
  `contract_filename` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `primary_contact_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_primary_contact` (`primary_contact_id`),
  CONSTRAINT `fk_primary_contact` FOREIGN KEY (`primary_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES
(1,'Steel company testclient','251-555-1212','Mobile, AL','Steel','','Test Recruiter','test notes, new client',NULL,'Prospect','2025-07-24 08:26:05','2025-07-24 05:05:51',NULL);
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `phone_mobile` varchar(50) DEFAULT NULL,
  `secondary_email` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `address_street` varchar(255) DEFAULT NULL,
  `address_city` varchar(100) DEFAULT NULL,
  `address_state` varchar(100) DEFAULT NULL,
  `address_zip` varchar(20) DEFAULT NULL,
  `address_country` varchar(100) DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `follow_up_notes` text DEFAULT NULL,
  `outreach_stage` tinyint(4) DEFAULT 1,
  `last_touch_date` date DEFAULT NULL,
  `outreach_status` varchar(50) DEFAULT 'Active',
  `source` varchar(100) DEFAULT NULL,
  `contact_owner` varchar(100) DEFAULT NULL,
  `is_primary_contact` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT INTO `contacts` VALUES
(1,1,'Joe','Contact',NULL,'joe@joe.com','251-555-1212',NULL,NULL,'HR Manager',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'',1,NULL,'Active',NULL,NULL,0,'2025-07-24 09:06:38','2025-07-24 05:06:38');
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_type` varchar(100) NOT NULL DEFAULT 'general',
  `module_id` int(11) NOT NULL,
  `candidate_id` int(11) DEFAULT NULL,
  `job_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `content` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_candidate_id` (`candidate_id`),
  KEY `idx_job_id` (`job_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_contact_id` (`contact_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notes`
--

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
INSERT INTO `notes` VALUES
(1,'candidate',10,NULL,NULL,NULL,NULL,'2025-07-24 07:15:46','testnote3'),
(2,'candidate',10,NULL,NULL,NULL,NULL,'2025-07-24 08:03:22','Test note again'),
(3,'client',1,NULL,NULL,NULL,NULL,'2025-07-24 08:57:43','test note, notes card');
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outreach_templates`
--

DROP TABLE IF EXISTS `outreach_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `outreach_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_number` int(11) NOT NULL,
  `channel` varchar(100) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stage_number` (`stage_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outreach_templates`
--

LOCK TABLES `outreach_templates` WRITE;
/*!40000 ALTER TABLE `outreach_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `outreach_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'Admin User','admin@opentalent.org','$2y$10$hLucTZR0aucb5OHjLVW2e.nEuNnza1jVCabWWZ.UcUrgFsZTvQTwW','admin','2025-07-23 10:31:21');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-24  9:17:37
