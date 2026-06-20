# AC-VMIS Resource Manual

## System Overview

AC-VMIS, or Assumption College Varsity Management Information System, is a web-based platform for managing varsity student-athletes, coaches, teams, schedules, attendance, academic eligibility, documents, announcements, and reports. The system organizes daily varsity operations into role-based workspaces for administrators, coaches, and student-athletes.

The main purpose of AC-VMIS is to keep varsity records centralized, traceable, and easier to monitor. It supports student-athlete registration, coach review, team assignment, schedule management, attendance recording, academic submission, document access, notification delivery, and administrative reporting.

## User Roles

### Administrator

Administrators manage institutional oversight and system records. They monitor users, create coach accounts, review system activity, view documents, generate reports, manage academic periods, and monitor teams and schedules.

Main resources available to administrators:

- Admin Dashboard
- User Records
- Coach account creation and activation links
- Team monitoring
- Operations workspace
- Documents
- Academic period management
- Academic evaluations
- Reports
- Audit trail
- Announcements

### Coach

Coaches manage sport-specific operations. A coach is assigned to a supported sport and can review student-athlete applications for that sport, create and manage teams, maintain rosters, create schedules, record attendance, assign training requirements, and review team documents.

Main resources available to coaches:

- Coach Dashboard
- Student Applications
- My Team
- Team Management
- Schedule and Attendance
- Team Documents
- Training Requirements
- Team invite code tools

### Student-Athlete

Student-athletes use the system to register, wait for coach approval, join teams, view schedules, submit academic documents, track eligibility status, access submitted documents, and receive announcements.

Main resources available to student-athletes:

- Student-Athlete Dashboard
- Team
- Schedule
- Academics
- My Documents
- Join Team by invite code
- Announcements
- Account settings
- Help and Support

## Supported Sports

AC-VMIS currently supports the following varsity sports:

- Basketball
- Soccer
- Volleyball

Student-athletes register under one supported sport. Coaches are also assigned to a sport, and coach approval or team assignment is limited by that sport.

## Account and Approval Resources

### Student-Athlete Registration

A student-athlete registers through the public registration page and submits required information. After registration, the account is placed under pending review. The coach assigned to the selected sport reviews the application.

Possible approval outcomes:

- `Pending`: The account is waiting for coach review.
- `Approved`: The student-athlete may access the student workspace and become available for team assignment.
- `Rejected`: The student-athlete cannot access the varsity workspace unless reviewed again through an authorized process.

### Coach Accounts

Coach accounts are created by administrators. The administrator assigns the coach to a supported sport and sends an activation link. The coach uses the activation link to set the account password and access the coach workspace.

### Account States

The system uses account states to control access:

- `Active`: The user can access allowed system features based on role.
- `Deactivated`: The user is temporarily blocked from system access.

## Team Management Resources

Teams are created and managed primarily by coaches. A coach may create teams under the coach's assigned sport only. Coaches can add approved student-athletes from the same sport, provided they are not already assigned to another active team.

Team resources include:

- Team name
- Sport
- School year or season
- Team avatar
- Description
- Head coach
- Assistant coach
- Player roster
- Player status
- Jersey number
- Position
- Archive status
- Team invite code

### Team Invite Codes

Team invite codes allow eligible student-athletes to join a team through a controlled link or code. Coaches may generate, regenerate, copy, or disable invite codes.

Regenerating a code invalidates the old code. This is useful if the code was shared incorrectly, became too public, or needs to be reset for security.

Disabling a code prevents students from joining through that link.

### Team Archive

Archiving removes a team from active team operations while preserving its records. Archived teams can be restored when needed.

## Schedule and Attendance Resources

Coaches create schedules for practices and games. Schedules are connected to teams and can be viewed by student-athletes assigned to those teams.

Schedule resources include:

- Team
- Title
- Type, such as practice or game
- Venue
- Start time
- End time
- Notes

Attendance records track student participation per schedule. Coaches can mark attendance for team members.

Attendance statuses include:

- `present`
- `absent`
- `late`
- `excused`

Administrators can monitor attendance records and generate attendance reports.

## Academic Resources

Academic resources support eligibility monitoring. Administrators manage academic periods, student-athletes submit grade reports, and the system evaluates academic standing through OCR-assisted processing and manual review workflows.

Academic period resources include:

- School year
- Term
- Start date
- End date
- Period status

Student academic submission resources include:

- Student
- Academic period
- Document type
- Uploaded file
- OCR result
- Parsed grade value
- Validation status
- Evaluation result
- Review status

Academic evaluation outcomes include:

- `Eligible`: Varsity access is allowed for the active academic period.
- `Pending Review`: The submission needs review or final evaluation.
- `Ineligible`: Varsity access is temporarily limited until eligibility is restored.

When a student-athlete is ineligible or pending review, access to varsity features such as dashboard, team, and schedule may be restricted. The student may still access Academics and Documents to submit or review requirements.

## Document Resources

The system stores student documents for registration and academic workflows.

Common document types include:

- Grade Report
- Transcript of Records
- Medical Document or Health Clearance
- Supporting Document

Document actions differ by role:

- Student-athletes can preview and download their own submitted documents.
- Coaches can preview and download visible documents for assigned student-athletes.
- Administrators can preview and download uploaded student documents for monitoring.

Document review statuses include:

- `pending`
- `auto_processed`
- `needs_review`
- `reviewed`

## Announcements and Notifications

Announcements notify users about important system events. Notifications may be generated from account approval, academic submission, team activity, schedule activity, or general system announcements.

Announcement resources include:

- Announcement event
- Recipient
- Title
- Message
- Type
- Published date
- Read status

## Reports

Administrators can generate and review system reports for monitoring and documentation.

Available report areas include:

- Attendance reports
- Team roster reports
- Academic reports

Reports may include print views and CSV exports depending on the report type.

## Audit and Record Monitoring

AC-VMIS keeps action records for accountability. Administrators can use the audit trail and user records to monitor system activity.

Tracked records may include:

- Account actions
- Student approval history
- Academic evaluations
- Team and roster changes
- Document review references

## Access Control Summary

Access is controlled by user role and account state.

- Guests can access public pages, login, registration, password reset, and invite links.
- Pending student-athletes are redirected to the pending approval page.
- Rejected accounts are redirected to the rejected status page.
- Deactivated accounts are redirected to the deactivated status page.
- Administrators access system-wide monitoring and reports.
- Coaches access sport-specific applications, teams, schedules, attendance, and team documents.
- Student-athletes access personal team, schedule, academics, and document resources after approval.

## System Support Notes

When reporting an issue, users should include:

- Full name
- Role
- Page or module involved
- Team name, if applicable
- Schedule date, if attendance-related
- Academic period, if academic-related
- Screenshot or exported report, if available

For account access problems, users should specify whether the account is pending, approved, rejected, or deactivated.

For document upload problems, users should specify the document type, academic period, and file format used.

## Maintenance Notes

System maintainers should regularly review:

- Pending student applications
- Coach account status
- Active and archived teams
- Academic period status
- Attendance exceptions
- OCR and document processing issues
- Failed jobs and system logs
- Report accuracy
- User access state

Backups should include the application database, uploaded documents, configuration files, and deployment environment variables.

