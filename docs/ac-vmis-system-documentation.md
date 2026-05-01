# AC-VMIS System Documentation

## System Overview

The Asian College Varsity Management Information System (AC-VMIS) is a centralized, web-based information system developed to organize and support the day-to-day operations of varsity programs within Asian College. The system provides a structured environment for managing student-athlete registration, coach assignment, team organization, schedule monitoring, attendance recording, academic compliance, wellness monitoring, announcements, and administrative reporting.

AC-VMIS addresses the operational limitations of fragmented record-keeping practices such as paper files, isolated spreadsheets, and informal messaging. Through a single role-based platform, the system promotes timely record updates, clearer accountability, and more reliable access to varsity information for authorized personnel.

The current implementation follows a role-based access model with distinct dashboards and process controls for administrators, coaches, and student-athletes. Each role is restricted to functions that correspond to its institutional responsibilities. Administrative control is emphasized in account lifecycle management, academic review oversight, and operational monitoring, while coaches and student-athletes interact with approved functions relevant to team participation and compliance.

The present system design also incorporates an OCR-assisted academic validation process. Uploaded academic documents are processed through an automated extraction pipeline, after which the system interprets the detected grade data and classifies the result as eligible, ineligible, or pending review. When extracted data is unclear or confidence is low, the document remains subject to administrator review before final academic disposition.

## Problem Statement

Varsity operations require continuous coordination among administrators, coaches, and student-athletes. In conventional practice, these activities are often managed through separate documents, manual attendance sheets, informal follow-ups, and non-standardized submission procedures. This arrangement creates delays in verification, inconsistent record maintenance, and difficulty in monitoring the academic and health compliance of student-athletes.

The absence of a unified system makes it difficult to:

- maintain complete and current student-athlete records;
- control coach account access through formal administrative procedures;
- monitor attendance based on actual team schedules;
- evaluate academic submissions promptly and consistently;
- track wellness observations and follow-up concerns;
- distribute role-appropriate notifications and maintain auditable administrative actions; and
- generate reliable reports for operational review and institutional decision-making.

These issues justify the need for an integrated varsity management information system that formalizes workflows, centralizes records, and improves the accuracy and timeliness of varsity-related decisions.

## Objectives

### General Objective

To develop a centralized varsity management information system that improves the administration, monitoring, and coordination of Asian College varsity operations through structured, role-based, and data-driven processes.

### Specific Objectives

- To centralize student-athlete, coach, team, schedule, attendance, academic, and wellness-related records in a single platform.
- To implement administrator-managed account control, including student approval and coach account provisioning.
- To support a schedule-based attendance workflow in which authorized coaches record attendance during official team activities.
- To automate the initial evaluation of academic submissions through OCR-based grade extraction and rule-based classification.
- To provide role-based dashboards and access control that limit each user to appropriate system functions.
- To improve communication through in-application announcements and configurable notification settings.
- To support monitoring, audit review, and reporting for attendance, academics, health, and account actions.

## System Features

### 1. Account and Access Management

The system uses authenticated, role-based access for administrators, coaches, and student-athletes. Student-athlete accounts are created through registration and remain subject to administrative approval. Coach accounts are not self-registered; they are provisioned by administrators and activated through a controlled onboarding process. The system also supports account activation, rejection, deactivation, password management, and forced password change on initial access when applicable.

### 2. Role-Based Dashboards

AC-VMIS provides separate dashboard views for each user role:

- The administrator dashboard presents institutional summaries, approval queues, attendance indicators, academic risk indicators, wellness alerts, and recent activity logs.
- The coach dashboard presents team-based metrics such as upcoming schedules, roster condition, attendance progress, wellness follow-up needs, and missing academic submissions.
- The student-athlete dashboard presents upcoming schedules, recent attendance standing, academic eligibility status, and personal participation records.

### 3. Team and Roster Management

Administrators create and maintain teams, assign sports classifications, manage roster membership, and associate coaches with teams through staff assignment records. The current database structure supports head and assistant coach assignments through normalized team staff assignment records rather than fixed coach columns inside the team table, allowing authorized coaching staff to participate in schedule and attendance workflows for the same team.

### 4. Schedule Management

Team schedules are recorded per team and may represent practices, games, or meetings. Schedules include title, type, venue, start time, end time, and notes. These schedule records serve as the operational basis for attendance monitoring and related coaching activities.

### 5. Schedule-Based Attendance Monitoring

Attendance in the current system is schedule-based and coach-led. Attendance is recorded per student-athlete against a specific team schedule. Authorized coaches, including assistant coaches assigned to the team, may record attendance only once the scheduled activity has started. Supported attendance outcomes include present, late, absent, and excused. Administrators may later review attendance records and apply operational updates when necessary.

This workflow replaces earlier legacy attendance approaches and should be treated as the official attendance model of the current system.

### 6. Academic Submission and OCR-Based Validation

Student-athletes submit academic documents within defined academic periods. When a grade report is uploaded, the system stores the file, runs OCR processing, preserves the extracted text, produces a parsed summary, and evaluates the detected grade using academic interpretation rules.

The current logic supports automated classification using the available grade value:

- Higher education scale values from `1.00` to `3.00` are classified as eligible.
- Higher education scale values of `5.00` and above are classified as ineligible.
- Basic education values from `75` to `100` are classified as eligible.
- Basic education values below `75` are classified as ineligible.
- Ambiguous values or low-confidence OCR results are flagged for manual review.

Academic evaluations are preserved per student and academic period, with support for administrator review, remarks, and final status confirmation.

### 7. Wellness Monitoring

The system maintains wellness logs linked to scheduled activities, allowing authorized personnel to record observed injury concerns, fatigue level, performance condition, and remarks for student-athletes.

### 8. Notifications and User Support Access

AC-VMIS includes an in-application notification mechanism supported by announcement event and recipient records. Notifications may also be sent through email, subject to user preference settings. Notification categories currently support approval, academic, schedule, general, and system-related communication.

The current implementation also provides a role-sensitive help interface and official support contact channels for account, process, and record-related concerns. However, the present production schema does not implement a database-backed support ticket lifecycle. Accordingly, system documentation should refer to support access and contact guidance, not to a full internal ticket-tracking subsystem.

### 9. Reporting and Audit Monitoring

The system supports reporting for attendance, roster, academic, and wellness records. It also preserves administrative and process history through dedicated records such as student approval histories, account action logs, academic OCR runs, and evaluation actions. These records strengthen accountability and provide institutional reference for review and decision-making.

## Current Data Design Notes

The present schema reflects several completed normalization and cleanup decisions that are important to the current system description:

- personal identity fields are centralized in `users`, while role-specific details are stored in `students` and `coaches`;
- coach-to-team relationships are maintained through `team_staff_assignments` rather than direct legacy coach columns in `teams`;
- notifications are modeled through `announcement_events` for reusable content and `announcement_recipients` for user-level delivery and read state;
- attendance is modeled only through `team_schedules` and `schedule_attendances` as part of the official schedule-based workflow; and
- legacy QR attendance, legacy approval, and deprecated intermediate academic parsing structures are excluded from the active production design.

The current production-oriented schema should therefore be interpreted as the normalized operational model of AC-VMIS rather than as a record of every historical migration step used during development.

## User Roles and Responsibilities

### Administrator

The Administrator is the primary control authority of the system. This role is responsible for:

- reviewing and approving or rejecting student-athlete accounts;
- provisioning coach accounts and managing onboarding access;
- managing teams, roster composition, and coach assignments;
- monitoring schedules, attendance records, and wellness records;
- managing academic periods, reviewing academic submissions, and finalizing academic evaluations when needed;
- issuing announcements and overseeing notification-driven communication; and
- generating reports and reviewing audit-related records.

### Coach

The Coach is responsible for team-level operational management within assigned teams. This role is responsible for:

- viewing assigned teams and roster information;
- creating and maintaining team schedules;
- recording attendance during scheduled activities through the official attendance workflow;
- monitoring athlete participation, wellness conditions, and selected academic submission visibility; and
- receiving and responding to operational notifications related to team activities.

The current implementation allows both head and assistant coaches assigned to a team to participate in authorized schedule and attendance processes.

### Student-Athlete

The Student-Athlete is the subject of varsity monitoring and compliance workflows. This role is responsible for:

- registering an account and submitting required profile information;
- maintaining personal and emergency contact details;
- submitting academic documents during active academic periods;
- viewing approved team assignment, schedules, announcements, attendance standing, and academic results; and
- referring to the help and support interface when assistance is required.

## System Workflow

### Administrator Workflow

1. The administrator accesses the administrative dashboard after authentication.
2. Student-athlete registrations awaiting approval are reviewed together with required supporting records, including academic document presence.
3. The administrator approves or rejects eligible student-athlete accounts and the decision is preserved in approval history.
4. The administrator provisions coach accounts and issues onboarding access for coach activation.
5. The administrator creates and maintains teams, assigns coaches, and manages roster records.
6. The administrator monitors schedules, attendance outcomes, wellness alerts, and academic submission queues.
7. The administrator reviews OCR-assisted academic results and performs manual confirmation or override when review is required.
8. The administrator generates reports and consults audit records for institutional monitoring.

### Coach Workflow

1. The coach signs in and accesses the coach dashboard.
2. The coach reviews assigned teams, roster status, and the upcoming schedule.
3. The coach creates or updates team schedules for practices, games, or meetings.
4. Once a scheduled activity has started, the coach opens the attendance workflow and records attendance for team members using permitted statuses.
5. The coach reviews attendance progress, follows up on exceptions, and records wellness observations when applicable.
6. The coach monitors academic submission visibility for team members as allowed by the current role configuration.
7. The coach receives schedule, attendance, and academic-related notifications relevant to assigned teams.

### Student-Athlete Workflow

1. The student-athlete registers an account and submits the required personal and supporting information.
2. The account remains pending until reviewed through the administrator approval process.
3. Once approved, the student-athlete gains access to the student-athlete dashboard and role-authorized features.
4. During open academic periods, the student-athlete submits grade reports or supporting academic documents.
5. If a grade report is uploaded, the system processes the file through OCR and evaluates the extracted grade data.
6. The student-athlete views schedules, attendance records, announcements, and academic eligibility status from within the dashboard and related pages.
7. The student-athlete may consult the help page and official support channels when account or record concerns arise.

## Data Management and Processing

AC-VMIS uses a normalized relational data structure to preserve operational integrity and reduce redundancy. Core user identity data is stored in the `users` table, while role-specific profile details are separated into `students` and `coaches`. Team participation is represented through `team_players`, and coach-to-team relationships are represented through `team_staff_assignments`.

Schedule data is stored in `team_schedules`, while attendance transactions are stored in `schedule_attendances` per student-athlete and schedule instance. This structure enables schedule-centered attendance monitoring rather than generic or detached attendance recording.

Academic processing follows a layered structure:

- `academic_documents` stores uploaded academic files and review status;
- `academic_document_ocr_runs` stores OCR output, confidence values, validation results, and processing outcome;
- `academic_document_parsed_summaries` stores extracted summary values such as general weighted average and total units; and
- `academic_eligibility_evaluations` stores the resulting academic interpretation per student and academic period.

This design supports both automated processing and administrator review. Clear OCR outcomes may be auto-processed, while low-confidence or ambiguous outcomes remain subject to manual confirmation.

Wellness-related data is stored through `wellness_logs`, allowing the system to preserve post-session observation records. Notification data is stored through `announcement_events` and `announcement_recipients`, while user notification preferences are stored in `user_settings`.

Administrative accountability is reinforced through `student_approval_histories`, `account_action_logs`, and related timestamped records that document important system actions.

Legacy structures such as `account_approvals`, `announcements`, `schedule_qr_tokens`, `academic_evaluation_documents`, and `academic_document_parsed_subjects` do not represent the current operational design and should not be described as active production tables in final system documentation.

## Scope and Limitations

### Scope

The current scope of AC-VMIS includes:

- student-athlete account registration and administrator approval;
- administrator-managed coach account provisioning and activation;
- role-based dashboards and access control for administrators, coaches, and student-athletes;
- team, roster, and coach assignment management;
- schedule creation and schedule-based attendance recording;
- academic period management and academic document submission;
- OCR-assisted academic validation with rule-based classification and administrator review support;
- wellness monitoring;
- in-application announcements, email-capable notifications, and user support guidance; and
- reporting and audit-oriented monitoring for varsity operations.

### Limitations

The present system has the following limitations:

- It is intended for varsity management within Asian College and is not designed as a public or multi-institution platform.
- It does not replace the official academic records system of the institution; it only supports varsity-related academic compliance monitoring.
- It does not provide medical diagnosis or clinical decision support; health records are limited to coach-recorded wellness observations.
- OCR-based academic interpretation depends on the clarity and quality of uploaded files; unclear or low-confidence outputs still require manual administrative review.
- Attendance recording is dependent on official schedule entries and is limited to authorized coaching and administrative actions within the implemented workflow.
- Some wellness observations remain practical rather than fully decomposed for analytical use, particularly free-text injury notes and remarks.
- The current implementation provides support guidance and contact access, but it does not yet include a full database-backed support ticket tracking subsystem.

## Concluding Statement

The current AC-VMIS implementation demonstrates a structured and institution-oriented approach to varsity management. Its design reflects formal administrative control, role-based access, schedule-centered attendance procedures, OCR-assisted academic validation, and centralized monitoring of operational records. As a result, the system is suitable for presentation as a practical and defense-ready information system aligned with the present application architecture and database design.
