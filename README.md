# 🟦 OpenTalent

**OpenTalent** is a lightweight, self-hosted applicant tracking system (ATS) built for recruiting agencies and independent recruiters. It’s designed to keep your entire workflow in one place — from managing candidates, clients, and job orders to tracking outreach and results.  

Unlike bloated enterprise systems, OpenTalent focuses on the essentials: fast search, clear status tracking, bulk resume importing, and built-in email and KPI dashboards. Everything runs on your own server, with no external dependencies or paid APIs.  

👉 **OpenTalent is 100% open source (MIT License)** — you’re free to use it, modify it, and run it however you like.  

---

## ✨ Features

### 🚪 Secure Access
- Password-protected login for all users.  
- First-time users are required to set a new password for security.  

### 👤 Candidate Management
- Add, edit, view, and manage candidate profiles.  
- Store resumes, formatted resumes, cover letters, and other attachments.  
- View full resume text inside each profile.  
- Track candidate status (screening, interviews, offers, etc.).  

### 🏢 Client & Contact Management
- Maintain company/client records with industry, location, and notes.  
- Add contacts under each client, with titles, emails, and outreach tracking.  
- Mark a **primary contact** for each client.  
- Log follow-ups and track outreach stages/statuses.  

### 📄 Job Management
- Add and edit job orders with details like type, location, and description.  
- Link each job to the correct client.  
- Track job status (open, filled, on hold, etc.).  

### 🔗 Associations
- Link candidates to jobs (applications).  
- Link contacts to job orders as hiring managers or decision makers.  
- Track the status of each candidate-job match separately.  

### 📝 Notes & Activity
- Add notes on candidates, clients, contacts, or jobs.  
- Notes are shared across linked records.  
- Full activity log with user + timestamp.  

### 📊 KPIs & Dashboard
- Built-in dashboard to track recruiting and sales activity.  
- Daily and overall performance views.  
- Automatically logs key actions (status changes, outreach, etc.).  

### 🔍 Smart Search
- Quick search and autocomplete for candidates, clients, contacts, or jobs.  
- Used throughout the system to link records quickly.  

### 📂 Resume Handling
- Upload and preview resumes right inside the browser.  
- Parse resumes to extract candidate details (name, email, phone, etc.).  
- **Bulk resume importing** — upload a ZIP, parse them all, detect duplicates, and review before import.  

### 📎 Attachments
- Store and manage multiple files per candidate.  
- View or download files directly from each profile.  

### 📥 Bulk Data Import
- Import **candidates**, **contacts**, and **clients** via CSV spreadsheets.  
- Automatic mapping to database fields.  

### 📧 Email Integration
- Built-in SMTP support for sending emails directly from the system.  
- Outgoing emails are logged as notes under the correct record.  

### 🛠️ System Tools
- Central schema and installer for easy setup.  
- Backup/restore utilities included.  
- Clean Bootstrap interface with consistent layout across all modules.  

---

## 🛠️ Roadmap

### 🔒 Security & Stability
- Hardening review for shared hosting (uploads, HTTPS).  
- Logging system for all adds/updates/deletes.  
- Smooth factory reset + installer polish.  

### 👤 Candidate Features
- Status tracking per job association.  
- Improved resume parser (more fields, higher accuracy).  
- Resume preview inline for PDFs/DOCs.  
- Organize attachments into type-based folders.  

### 🏢 Client & Contact Features
- Autocomplete linking for all contact ↔ client flows.  
- More detailed outreach tracking (stages, attempts, email/call logging).  
- Show all linked jobs on contact pages.  

### 📄 Job Features
- Job order parser (extract details from pasted job descriptions).  
- Dropdown for job type (full-time, part-time, contract).  
- Consistent layouts across job pages.  

### 🔗 Associations & Notes
- Centralize all assignment flows into `associate.php`.  
- Smarter notes (automatically show all linked records).  
- Edit/delete notes consistently.  

### 📊 KPIs & Dashboard
- Ensure all status changes flow into KPIs.  
- Add missing KPIs (e.g., “agreement signed” for clients).  
- Daily/weekly target setting.  

### 📥 Import/Export
- Unify single and bulk resume parsing logic.  
- Cleanup bulk CSV imports for clients/contacts.  
- Add demo data + factory reset for new installs.  

### 📧 Email Integration
- Inbound email logging (IMAP).  
- Outgoing emails update status + notes.  
- Workflow automation (e.g., follow-ups after X days).  

### 🎨 Usability & Look/Feel
- Theme customization (multiple CSS themes).  
- Consistent Bootstrap layout across all view screens.  
- Scrollable cards for long text sections.  

### 💾 Backup & Restore
- Full backup/restore in admin.  
- Factory reset option.  
- Scheduled backups (later).  

### 🚀 Future Ideas
- Workflow automation engine.  
- AI-powered resume & job parsing.  
- Demo mode for showcasing.  
- Record “owner” fields polished across all modules.  

---

## ⚖️ License

This project is open source under the **MIT License**.  
You can use it, modify it, and redistribute it freely — even for commercial use.  

See the [LICENSE](./LICENSE) file for details.  

---
