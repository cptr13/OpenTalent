🟦 OpenTalent

Hi — I’m a career recruiter and open-source enthusiast, and I built OpenTalent mostly for myself. I wanted to make sure I’d always have a working, easy-to-install applicant tracking system (ATS) that I could run anywhere, update anytime, and tweak however I want. That’s really all there is to it.

There aren’t many open-source ATS options out there, and most come with restrictive licenses. OpenTalent is different: it’s released under the MIT license, which means you can do absolutely anything you want with it — use it, modify it, resell it, whatever. No limitations, no fine print.

The code was written almost entirely with ChatGPT (be warned). That either impresses you or scares you, and honestly, both reactions are fair. I’m not a programmer — just a recruiter who needed something functional. The system works well, but I make no promises about security or long-term perfection. Still, I think it’s a solid, dependable tool that can serve for years.

✨ What It Is

OpenTalent is a lightweight, self-hosted ATS built with PHP and MySQL.
It runs on any standard WAMP/LAMP stack — even basic shared hosting.
No paid APIs, no subscriptions, no external dependencies.

It’s designed for real recruiting work: keeping candidates, clients, jobs, and outreach organized without all the clutter.

👥 Who It’s For

Solo recruiters who just want something simple that works

Small recruiting agencies who prefer control over their data

Internal HR teams who want a private, in-house ATS

Developers and tinkerers who like open-source projects

⚙️ What It Does

Candidates – Add, edit, and manage profiles. Upload resumes, parse details, import in bulk, and track status by job.

Clients & Contacts – Maintain company records, track key contacts, and log outreach stages.

Jobs – Add and edit job orders, link them to clients, and manage open/filled status.

Associations – Connect candidates and contacts to jobs; track each relationship separately.

Notes & Activity – Add notes across all records; shared and timestamped automatically.

Search – Instant autocomplete across candidates, clients, contacts, and jobs.

Email (SMTP) – Send directly from the system; logs automatically under each record.

Dashboard & KPIs – Simple at-a-glance view of your recruiting activity.

Attachments & Resume Text – Store, view, and preview uploaded files inline.

Admin Tools – Installer, backups, and a clean Bootstrap-based interface.

💼 What It’s Like in Practice

OpenTalent is a straightforward, low-end ATS — it’s not fancy, but it’s solid.
It handles candidates, resumes, clients, jobs, and all the interactions that go with them.
Resume parsing is intentionally simple, and there are no external APIs (yet) to hook into other systems.

What’s there works, and works well.

A couple of features I built specifically for my own workflow make it a bit different:

A KPI dashboard that tracks your daily recruiting targets and progress in real time.

Live activity tracking that shows how you’re performing against those goals as you work.

A dynamic scripting engine that generates personalized call scripts with the most effective tone for each contact — executive, consultative, or friendly — based on the information already in the ATS.

A 12-touch sales cadence built into the contact module for consistent business development follow-up.

Documentation for these features will come later, but they’re already live and functional.

🛠️ Installing It

Setup instructions will be published soon.
For now, if you’ve ever installed WordPress, you can probably handle this.
You’ll need a standard PHP + MySQL environment — WAMP, LAMP, or shared hosting will all work fine.

⚡ Reality Check

OpenTalent isn’t hardened software, and security is minimal.
But it’s not just an experiment — it’s the system I personally use every day in my recruiting work.
It’s a solid, functional ATS that anyone can run and depend on.

If you like to tinker or customize your own tools, you’ll feel right at home.

🔁 Development Philosophy

OpenTalent isn’t “finished,” and it probably never will be.
I plan to keep improving it, adding features as I need them and fixing things as I find them.
It’s practical, evolving, and meant to stay that way.

Contributions, pull requests, and feedback are welcome — or you can just use it privately and make it your own.
If you have a question, suggestion, or idea, feel free to message me through GitHub.

⚖️ License

OpenTalent is open source under the MIT License.
Do anything you want with it — use it, modify it, redistribute it, even commercially.
No limitations. No dependencies. No nonsense.
