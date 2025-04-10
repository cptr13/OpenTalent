# OpenTalent Development Roadmap

This file outlines features planned and in progress for OpenTalent ATS.

---

## ✅ Completed (v0.6)

- Bootstrap 5 UI cleanup
- Clients and Contacts modules
- View pages for all record types
- Navigation refactor
- Assignments and record linking
- Delete functionality for all core records
- Change password and edit profile pages
- Initial rebase and release tagging system

---

## 🔄 In Progress — OpenTalent Dev Phase 2

### 🔍 Universal Search (Global)
- Top-of-page search bar
- Searches across candidates, clients, jobs, contacts

### 📊 Interactive Dashboard
- Stats (Candidates, Jobs, Applications)
- Clickable to filtered views

### 📁 Document Generation (DOCX/PDF)
- Generate offer letters, onboarding docs, agreements
- Use PhpWord or TCPDF (open-source only)

### 📥 Resume Parsing (Self-Hosted)
- Extract name, email, phone, etc. from PDF/DOCX
- Auto-fill candidate records
- No external APIs

### 🔐 Multi-User Permissions
- Admin, Recruiter, Viewer roles
- Module-level access controls

### 📬 Email Logging (Future Phase)
- Outbound SMTP
- Inbound IMAP (log to candidate/client)
- Optional Gmail/Outlook integration if license-safe

### 📅 Scheduling Integration
- Optional integration with EasyAppointments (LAMP-based)
- Schedule interviews from job or candidate view

---

## 🧼 Future Polishing & Utilities

- Backup/Restore (system snapshot)
- Field-level deletion/editing (non-core fields)
- Assign flow redesign
- Responsive layout improvements
- Advanced sidebar filtering UI (multi-field)

---

## 📌 Release Tags

- `v0.6` — Core modules locked and UI overhaul complete
- `v0.7` — Search, Dashboard, Resume Parsing, and DOCX Generation (planned)
