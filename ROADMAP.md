# 🛣️ OpenTalent Roadmap

OpenTalent is an open-source ATS built for real-world, practical recruiting.  
This roadmap outlines planned features for future releases.

---

## ✅ v1 – Core Release (Complete)

- [x] Login/logout with basic session handling
- [x] Dashboard with candidate/job/app counts
- [x] Candidate CRUD (create, view, delete)
- [x] Job CRUD
- [x] Assign candidates to jobs
- [x] Resume uploads and secure downloads
- [x] Activity logs tied to candidates/jobs
- [x] Sample SQL schema and demo data
- [x] A2 Hosting-ready config

---

## 🔜 v2 – Next Milestone (Planned)

### 📬 Email Integration
- Send emails from inside OpenTalent via SMTP
- IMAP connection to fetch and store replies
- Auto-log emails to candidate/client records

### 📎 Resume Parsing (Self-Hosted Only)
- Extract name, email, phone, etc. from PDF/DOCX
- Auto-fill candidate forms
- Use only open-source, offline parsers

### ⚙️ Workflow Automation
- Trigger actions: email, status changes, alerts
- Examples: "send follow-up after 7 days of inactivity"
- Fully configurable

### 📄 Document Generation
- Generate offer letters and onboarding forms
- Templates with placeholders (e.g. `{candidate_name}`)
- Export to DOCX or PDF using PhpWord or TCPDF

### 🗓️ Calendar Scheduling (EasyAppointments)
- Schedule interviews without Calendly
- Self-hosted integration with EasyAppointments
- Store and view booking history per candidate

### 💾 Backup & Restore
- Manual export/import of database and uploads
- Scheduled backups (optional CRON integration)

---

## 💡 Under Consideration

- User roles and permissions (multi-user support)
- Email templates with tags
- REST API access for integrations
- Simple mobile-friendly skin/theme
- Desktop installer (Windows + Linux) with local XAMPP bundle

---

## ❤️ Open Source Values

All features will:
- Be self-hosted
- Use open-source components only
- Avoid 3rd-party API lock-in
- Remain free and MIT licensed

Want to suggest a feature later? We’ll open up GitHub once v1 proves stable.
