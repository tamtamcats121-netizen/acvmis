# AC-VMIS System Documentation

## System Overview

The Asian College Varsity Management Information System (AC-VMIS) is a web-based, role-based information system developed to centralize the administration of varsity operations at Asian College. The implemented system consolidates student-athlete records, coach assignments, team rosters, schedules, attendance, academic submission review, performance monitoring, announcements, notifications, reports, and audit-oriented administrative records within one controlled platform.

The current implementation emphasizes institutional control and operational traceability. Student-athletes register through the public registration flow and remain subject to administrative approval. Coach accounts are not publicly self-registered; instead, they are created by administrators and activated through a controlled onboarding process. Administrator accounts are provisioned through invitation links. This structure reflects the present access model of the application and should be treated as the official user lifecycle of AC-VMIS.

AC-VMIS also implements schedule-based attendance and OCR-supported academic validation. Attendance is posted by authorized coaches against official team schedules. Academic submissions are uploaded by student-athletes, processed through OCR, summarized by rule-based parsing, and finalized through administrative review when required. These workflows define the current operational design of the system.

## Problem Statement

Before the implementation of AC-VMIS, varsity operations were vulnerable to fragmented record-keeping, delayed communication, and inconsistent monitoring. Athlete details, coach assignments, schedules, attendance records, and academic compliance documents were difficult to monitor in a unified and accountable manner. As a result, coordination among administrators, coaches, and student-athletes became time-consuming and prone to error.

The institution needed a centralized system that could:

- maintain complete and current varsity records in a single platform;
- formalize account approval and onboarding processes;
- support coach-led attendance based on actual scheduled activities;
- streamline academic submission review through automated assistance and administrative oversight;
- preserve post-session performance observations for follow-up monitoring;
- distribute internal announcements and notifications to the proper recipients; and
- generate reliable reports and audit-ready activity records for institutional review.

The development of AC-VMIS addresses these operational gaps by replacing loosely connected manual practices with standardized, role-specific workflows.

## Objectives

### General Objective

To design and implement a centralized, web-based varsity management information system for Asian College that improves the accuracy, efficiency, and coordination of varsity administration.

### Specific Objectives

- To centralize student-athlete, coach, team, schedule, attendance, academic, and performance records within one role-based system.
- To implement a student self-registration workflow subject to administrative approval.
- To implement administrator-managed coach account creation and onboarding.
- To implement invitation-based administrator account creation.
- To support coach-led attendance posting based on official team schedules.
- To implement OCR-assisted academic document validation with administrator review support.
- To provide structured announcements, notifications, reports, and audit-oriented monitoring for varsity operations.
- To provide role-appropriate access for administrators, coaches, and student-athletes.

## Scope and Limitations

### Scope

The current scope of AC-VMIS includes the following implemented modules and processes:

- student-athlete account registration, approval, rejection, activation, and deactivation;
- administrator invitation and acceptance for new admin accounts;
- administrator-created coach accounts with onboarding activation;
- people management for users, students, and coaches;
- team creation, editing, archiving, reactivation, roster assignment, and coach assignment;
- coach and admin schedule management for team practices, games, and meetings;
- coach-led, schedule-based attendance posting and admin attendance review;
- academic period management, academic document upload, OCR processing, parsed summary generation, and eligibility evaluation;
- performance monitoring logging and performance review monitoring;
- announcements, user notifications, and notification preference settings;
- printable and CSV-exportable reports for attendance, roster, and academics;
- audit trail and account action history; and
- profile, settings, notification, preference, and help/support access pages.

### Limitations

The current implementation has the following limitations:

- The system is intended for Asian College varsity operations only and is not designed as a multi-school platform.
- It does not replace the institution's official registrar or student information system; academic monitoring is limited to varsity compliance workflows.
- OCR-assisted academic validation depends on document quality and may still require manual administrative confirmation.
- Performance records are observational and operational in nature; they do not constitute medical diagnosis or clinical health management.
- Attendance depends on the prior creation of valid team schedules and the availability of authorized coaching staff to encode records.
- The current implementation provides support guidance and contact information through the help module, but it does not provide a full internal ticketing or case-management subsystem.

### Excluded or Deprecated Features

The following concepts should not be described as active features of the current AC-VMIS implementation:

- QR-dependent attendance workflows;
- health-clearance management as an active varsity module;
- academic hold enforcement as an active user-facing process;
- academic period message publishing as an active user-facing process;
- public coach self-registration; and
- deprecated legacy tables retained only as development artifacts or unfinished cleanup targets.

## Features

### 1. Account and Access Management

AC-VMIS implements authentication, email verification support, password reset, forced password change where applicable, and role-based access control. Student-athlete accounts are created through normal registration and remain pending until reviewed by administrators. Coach accounts are created by administrators and activated through a coach onboarding flow. Administrator accounts are created through invitation acceptance.

### 2. People and Approval Management

The administrator may review pending student-athlete registrations, inspect profile and document information, approve or reject applicants, deactivate or reactivate accounts, and manage user records through a dedicated people workspace. Approval outcomes are preserved in approval history records.

### 3. Team and Roster Management

The system supports team creation, editing, archiving, reactivation, roster assignment, and staff assignment. Coaches may be assigned as head coach or assistant coach through normalized staff assignment records. Student-athletes may be added to or removed from team rosters, and archived teams may still be reviewed for historical reference.

### 4. Schedule Management

The system supports the creation, editing, deletion, viewing, printing, and calendar export of team schedules. Schedules may represent practices, games, or meetings and include the title, type, venue, start time, end time, and notes. Coach and admin views provide schedule-centered oversight of varsity activities.

### 5. Attendance Management

Attendance is schedule-based and coach-led. Authorized coaches post attendance for scheduled sessions using the implemented status set: present, late, absent, and excused. Administrators may review attendance records and print attendance summaries through the operations and reports modules. This schedule-centered attendance workflow is the official attendance model of the system.

### 6. Academic Submission and Eligibility Monitoring

Student-athletes submit academic documents during active academic periods. Uploaded files are stored securely, processed through OCR, and evaluated by rule-based logic. Parsed values such as GPA or GWA are summarized, and final eligibility results may be classified as eligible, ineligible, or pending review. Administrators can review documents, revise evaluations, and manage academic periods and submission records through the academics workspace.

### 7. Performance Monitoring

The system stores coach-recorded performance records linked to student-athletes and schedules. Logged values include athlete condition details such as injury observation, injury notes, fatigue level, performance condition, and coach remarks. Coaches record post-session evaluations, and administrators monitor the resulting records through the performance workspace.

### 8. Announcements and Notifications

AC-VMIS provides internal announcements and recipient-level notification delivery. Users may mark announcements as read, review notification history, and configure notification preferences such as approvals, schedule changes, attendance changes, performance monitoring alerts, academic alerts, and injury threshold alerts. Email delivery may also be triggered depending on configured preferences and system processing.

### 9. Reports

The current reporting module supports:

- attendance summary reports;
- team roster reports; and
- academic submission and eligibility reports.

Reports may be viewed in-app, exported to CSV, and generated in printable format.

### 10. Audit and Administrative Traceability

The system preserves important administrative activity through account action logs, student approval histories, OCR run records, evaluation timestamps, and announcement recipient read states. These records strengthen accountability and support administrative review.

### 11. Help and Support Access

The system includes a role-sensitive help module accessible from account settings. This module provides workflow guidance, support contact information, and role-specific frequently asked questions for administrators, coaches, and student-athletes.

## User Roles

### Administrator

The Administrator is the principal control role of the system. Administrators can:

- review and approve student-athlete registrations;
- invite new administrators;
- create coach accounts and regenerate onboarding access;
- manage people, teams, rosters, and coach assignments;
- monitor schedules, attendance, performance monitoring, and academics;
- review OCR-supported academic outputs and finalize evaluations;
- publish announcements and monitor notifications;
- generate reports; and
- review audit trail and account activity history.

### Coach

The Coach manages assigned team operations. Coaches can:

- access coach dashboard summaries;
- view assigned teams and rosters;
- create, edit, print, and export schedules;
- post attendance for scheduled sessions;
- encode post-session performance evaluations and review performance records;
- view academic visibility information for team members; and
- receive announcements and notifications relevant to team operations.

### Student-Athlete

The Student-Athlete is the primary participant being monitored by the varsity system. Student-athletes can:

- register through the public registration process;
- wait for administrative approval before accessing the protected student dashboard;
- view team assignments, schedules, attendance, and performance history;
- submit academic documents during active periods;
- review academic status and submission history; and
- access account settings and help/support guidance.

## System Workflow

### 1. Student Registration and Approval Workflow

1. A student-athlete completes the public registration form.
2. The system creates the user and student records and stores the required registration-context academic document.
3. The account remains pending approval.
4. An administrator reviews the applicant's profile and supporting records.
5. The administrator approves or rejects the account.
6. Once approved, the student-athlete gains access to protected student features.

### 2. Administrator Invitation Workflow

1. An existing administrator creates an invitation for a new administrator email address.
2. The system stores the invitation token and sends the acceptance link.
3. The invited recipient opens the acceptance page, completes the required credentials, and activates the admin account.

### 3. Coach Provisioning Workflow

1. An administrator creates a coach account from the people management module.
2. The system generates onboarding credentials or an activation path for the coach.
3. The coach activates the account through the onboarding interface and sets the password.
4. The administrator assigns the coach to one or more teams through team staff assignments.

### 4. Team and Schedule Workflow

1. An administrator creates and maintains teams and roster assignments.
2. Coaches and admins manage schedules for practices, games, or meetings.
3. Authorized users may print schedules or export calendar files where permitted.

### 5. Attendance Workflow

1. A coach opens a scheduled activity.
2. The system loads the team roster for that schedule.
3. The coach records each athlete as present, late, absent, or excused.
4. Attendance is saved per student and per schedule.
5. Administrators may later review and report on the resulting attendance records.

### 6. Academic Submission Workflow

1. A student-athlete uploads a grade report or supporting academic document.
2. The system stores the file and runs OCR processing.
3. Parsed academic values are extracted where possible.
4. The system applies rule-based classification to produce an initial eligibility outcome.
5. When OCR output is unclear or confidence is insufficient, the record remains pending review.
6. An administrator confirms or updates the academic evaluation.

### 7. Performance Monitoring Workflow

1. A coach selects a completed schedule and athlete for post-session evaluation.
2. The coach records athlete condition details including injury observation, injury notes, fatigue level, performance condition, and coach remarks.
3. The system stores the performance record in `wellness_logs`.
4. Administrators review performance records and filter them by athlete, team, injury, fatigue, or date range.

### 8. Communication and Support Workflow

1. Administrators or system processes create announcement events.
2. Announcement recipients receive the relevant record in-app and, where applicable, by email.
3. Users review announcements, mark them as read, and manage notification preferences.
4. Users may consult the help module for workflow guidance and support contact details.

## Database / ERD

The current logical database design of AC-VMIS is normalized around the following active operational entities:

- `users`, `students`, `coaches`, `user_settings`
- `admin_invites`, `student_approval_histories`, `account_action_logs`
- `sports`, `teams`, `team_staff_assignments`, `team_players`
- `team_schedules`, `schedule_attendances`
- `wellness_logs`
- `academic_periods`, `academic_document_types`, `academic_documents`
- `academic_document_ocr_runs`, `academic_document_parsed_summaries`, `academic_eligibility_evaluations`
- `announcement_events`, `announcement_recipients`

The operational ERD is documented separately in [`docs/ac-vmis-erd.md`](./ac-vmis-erd.md). Legacy or deprecated structures are intentionally excluded from the final conceptual ERD even if they still appear in the development database.

## Data Dictionary

The active operational data dictionary is documented separately in [`docs/ac-vmis-data-dictionary.md`](./ac-vmis-data-dictionary.md). The dictionary focuses on the implemented production-facing entities and excludes deprecated features from the final thesis narrative.

## Tools and Technologies

The current AC-VMIS implementation is built with the following tools and technologies:

- **Backend Framework:** Laravel (PHP)
- **Frontend Framework:** Vue 3 with Inertia.js
- **Language Stack:** PHP, TypeScript, JavaScript, HTML, CSS
- **Database:** MySQL / MariaDB-compatible relational database
- **Styling:** Tailwind CSS-based utility styling and component-level CSS
- **Authentication and Security:** Laravel authentication, password reset, email verification, role-based middleware, secure file access
- **OCR Processing:** Server-side OCR service with rule-based academic parsing and validation
- **Reporting:** Blade-based printable reports and CSV export
- **Mail / Notification Support:** queued or direct email notification handling, in-app announcement delivery, user notification preferences
- **Calendar Export:** ICS file generation for authorized schedule access
- **Development Environment:** XAMPP-based local environment with Laravel application structure

## Concluding Statement

The current AC-VMIS implementation is a centralized varsity operations platform aligned with the needs of Asian College. Its implemented workflows now revolve around student self-registration with admin approval, admin-managed coach onboarding, invitation-based admin provisioning, schedule-centered attendance, OCR-assisted academic validation, performance monitoring, structured communication, reporting, and audit-oriented monitoring. This documentation reflects the actual implemented system and is intended to serve as the formal capstone or thesis-ready description of the present AC-VMIS application.
