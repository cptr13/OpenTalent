# Changelog

All notable changes to the OpenTalent ATS project will be documented in this file.

---

## [v0.6] - 2025-04-10

### 🎉 Major Highlights

- 🚀 First formal release of OpenTalent ATS with core modules complete
- 🔧 Clean Bootstrap 5 UI applied across major pages
- ✅ Git rebase and merge conflicts resolved into a clean main branch

---

### ✨ New Features

- **Clients Module**  
  Add/edit/view clients, and associate jobs with client records

- **Contacts Module**  
  Manage individual people tied to client companies

- **View Pages**  
  New `view_candidate.php`, `view_job.php`, `view_client.php`, `view_contact.php`

- **Delete Support**  
  Soft delete functionality for:
  - Candidates
  - Clients
  - Contacts
  - Jobs
  - Notes

- **User Account Tools**
  - Change password page
  - Edit profile page

- **Notes System**  
  Add/edit/delete notes linked to any entity (candidates, jobs, clients, contacts)

---

### 🧹 Improvements

- Refactored navigation bar (added: Candidates, Jobs, Clients, Contacts)
- Cleaned up assign flow and removed deprecated "email opt-out"
- "Add" buttons moved into top of each module page (removed from navbar)
- Moved toward consistent page structure and styling across all modules

---

### 🐛 Bug Fixes

- Fixed missing `client_id` pre-selection in job forms
- Resolved PHP warnings related to undefined form fields
- Corrected `SELECT` dropdown formatting and encoding issues

---

### 🔨 DevOps & Infra

- Completed interactive rebase and merged conflicting files across 19+ views
- Pushed and tagged `v0.6` on GitHub
- Prepared foundation for versioned releases and GitHub release notes

---

## 📌 Upcoming

See [ROADMAP.md](./ROADMAP.md) for planned features in v0.7 and beyond.
