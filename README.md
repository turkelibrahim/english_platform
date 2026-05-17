ENGLISH LEARNING PLATFORM
AI-Supported Adaptive Learning System
1. Project Overview

This project is an AI-supported English learning platform designed to provide personalized learning experiences for students.
Unlike traditional static learning systems, this platform dynamically adapts content, feedback, and learning paths based on user performance data.

The system distinguishes between student and admin roles and provides role-based access control.
Students interact with learning content, while administrators monitor system activity and learning analytics.

2. System Architecture

The system follows a modular PHP architecture using:

Frontend: HTML5, CSS3 (custom design, no template)

Backend: PHP (procedural + PDO)

Database: MySQL (via XAMPP / phpMyAdmin)

Visualization: Chart.js

Session Management: PHP Sessions

Security: Role-based access & authentication guards

---

## Quick Setup (XAMPP)

1) Copy the project folder into XAMPP:
- `C:\xampp\htdocs\english_platform`

2) Start **Apache** and **MySQL** from XAMPP Control Panel.

3) Database (single DB)

Option A (recommended): **Auto-setup**
- Open: `http://localhost/english_platform/`
- If the database doesn't exist yet, the app will automatically initialize it using **install.sql**.

Option B (manual import in phpMyAdmin)
- Open phpMyAdmin → Import → select `install.sql`
- This creates the DB named **english_platform** and all tables.

Legacy SQL files (not needed for fresh installs) are kept under: `sql/legacy/`

---

3. User Roles
👨‍🎓 Student

Takes placement tests

Practices English questions

Receives feedback and hints

Tracks progress with analytics

Uses notebook and gamification features

🛠️ Admin

Monitors users

Views error reports

Sends email reminders

Observes overall system health

4. Functional Requirements (FR) Mapping
✅ FR-1: User Authentication

Implemented Features:

- Login
- Student Register (admins cannot self-register; admin accounts are assigned in DB)

  Note: users now have a required unique `username` (e.g. `admin_1`).
  When inserting an admin manually, include the username column.
- Logout
- Session-based authentication

Related Files:

- public/index.php (unified login/register screen)
- auth/login.php, auth/register.php (legacy redirects)
- public/logout.php
- includes/auth.php, includes/rbac.php

✅ FR-2: Role-Based Authorization

Users are redirected based on their role:

Students → Student Dashboard

Admins → Admin Dashboard

Related Files:

includes/auth_guard.php

student/dashboard.php

admin/dashboard.php

✅ FR-3: Placement Test

Students can assess their English level to initialize a personalized learning path.

Related Files:

student/placement_test.php

✅ FR-4: Practice with Feedback

Students answer practice questions.
The system:

Evaluates correctness

Stores results in the database

Provides instant feedback and hints

Related Files:

- student/practice.php
- student/api/practice_submit.php

Database table: question_attempts

✅ FR-5: Progress Tracking & Analytics

Progress is calculated using real user data:

Total attempts

Correct / incorrect answers

Accuracy percentage

Estimated CEFR level (A1–B2)

Visualization is provided using Chart.js.

Related Files:

student/progress.php

✅ FR-6: Gamification

Motivational elements such as:

Points

Achievements

Progress indicators

Related Files:

student/gamification.php

✅ FR-7: Notebook

Students can save personal learning notes.

Related Files:

student/notebook.php

✅ FR-8: Error Reporting

Students report issues.
Admins review reported problems.

Related Files:

student/error_report.php

admin/error_reports.php

✅ FR-9: Admin Monitoring

Admins can:

See total users

See total students

View system status

Access reminders

Related Files:

admin/dashboard.php

✅ FR-10: Email Reminder

Admins can notify inactive users via email.

Related Files:

admin/email_reminder.php

✅ FR-11: Profile Management

- Both students and admins have a Profile page to update their information (name, email, avatar, theme) and change password.

Related Files:

- student/profile.php
- admin/profile.php

✅ FR-12: AI Mode Detection (Reading vs Audio)

- The system estimates whether the student understands better via text (reading) or audio explanations.
- It updates users.preferred_mode based on recent reading vs listening practice performance.

Related Files:

- includes/ai_mode.php
- student/api/practice_submit.php
- student/api/placement_submit.php
- student/api/recommend_next_lesson.php

5. Database Design Summary
Main Tables:

- users
- questions
- lessons
- question_attempts
- user_tasks, user_task_items
- favorites
- notebook_entries
- reports
- badges, user_badges
- reminder_log

The database design supports:

Data persistence

User-specific analytics

Expandability

6. Visual Design Philosophy

Instead of using ready-made templates, the UI was:

Designed from scratch

Focused on clarity and usability

Enhanced with glassmorphism effects

Unified across all pages (login, dashboard, practice)

This ensures a professional and coherent user experience.

7. How to Run the Project
Requirements:

XAMPP

PHP 8+

MySQL

Web browser

Steps:

Place project folder inside:

C:\xampp\htdocs\english_platform


Start Apache & MySQL from XAMPP

Import database via phpMyAdmin

Open browser:

http://localhost/english_platform

8. Educational Value

This project demonstrates:

Real-world web application development

Backend–frontend integration

Data-driven learning systems

Practical use of analytics

Modular and scalable design

9. Conclusion

The English Learning Platform is a complete, functional, and extensible educational system.
It goes beyond static content delivery by incorporating adaptive logic, data analysis, and user-centered design.

This project satisfies all required functional requirements and provides a solid foundation for future AI-driven enhancements.

## Database (Important)
- For a fresh setup: import **install.sql** only.
- Migration files under `sql/` are for legacy/older databases; you do **not** need to import them if you used install.sql.
