OpenTalent - README

OpenTalent is a lightweight, open-source applicant tracking system (ATS) built with PHP and MySQL. It is designed to help recruiters, hiring managers, and staffing agencies manage candidates, job orders, clients, and contacts in a user-friendly interface.

Features

✅ Multi-user login with roles (Admin, Recruiter, Viewer)

✅ Forced password change on first login

✅ Full CRUD for Candidates, Jobs, Clients, Contacts

✅ Assign candidates to job orders

✅ Notes system for each record type

✅ Reset user passwords (admin-only)

✅ Edit existing users (admin-only)

✅ Upload and edit profile picture

✅ User profile and settings pages

✅ Responsive Bootstrap 5 layout

✅ Admin dashboard with system metrics

Coming Soon:

🔜 Activity log (per user)

🔜 Resume parsing (PDF, DOCX, etc.)

🔜 Drag and drop file upload with preview

🔜 Sidebar layout and dark mode toggle

🔜 Dashboard charts

Installation

Clone the repo or download the ZIP

Import the opentalent.sql into your MySQL database

Configure your DB settings in config/database.php

Set up a web server with PHP 8+ (Apache or Nginx recommended)

Make sure /uploads/ is writable

Log in as the default admin and start adding records!

Default Admin Account

Email: admin@example.com
Password: admin123

You will be prompted to change your password on first login.

Project Structure

/includes/         Shared layout files (header, footer, etc.)
/config/           DB config
/candidates/       Candidate record handling
/jobs/             Job orders
/clients/          Client records
/contacts/         Client contacts
/uploads/          Profile pictures and resumes

License

MIT License

OpenTalent - Roadmap

🔧 Core System Improvements



🧠 Upcoming Features



🎨 UI/UX Enhancements



🔐 Security & Auth



🚀 Admin Tools




