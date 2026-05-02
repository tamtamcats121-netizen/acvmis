# AC-VMIS Data Dictionary

This data dictionary describes the active operational entities of AC-VMIS. It is intended for thesis documentation and therefore focuses on implemented, production-facing tables and relationships. Deprecated or unused concepts are excluded from this document even if residual tables still exist in a local development schema.

## Conventions

- `PK` means primary key.
- `FK` means foreign key.
- `UK` means unique key.
- Data types are presented in simplified thesis-ready form.
- Only the operationally relevant tables are documented below.

## 1. Identity and Access Tables

### `users`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique user identifier. |
| `first_name` | varchar | Required | User first name. |
| `middle_name` | varchar | Nullable | User middle name. |
| `last_name` | varchar | Required | User last name. |
| `email` | varchar | UK; required | Login email address. |
| `password` | varchar | Required | Encrypted account password. |
| `role` | enum/varchar | Required | User role: admin, coach, or student-athlete. |
| `account_state` | enum | Required | Current lifecycle state such as active or deactivated. |
| `must_change_password` | boolean | Required | Indicates whether the user must set a new password after onboarding. |
| `email_verified_at` | timestamp | Nullable | Email verification timestamp. |
| `avatar` | varchar | Nullable | Stored avatar path or URL reference. |

### `students`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique student-athlete profile identifier. |
| `user_id` | bigint | FK to `users.id`; UK | Linked identity record. |
| `student_id_number` | varchar | UK; required | Official student number. |
| `course_or_strand` | varchar | Nullable | Academic program or strand. |
| `current_grade_level` | varchar | Nullable | Current year or grade level. |
| `approval_status` | enum | Required | Pending, approved, or rejected registration state. |
| `student_status` | enum | Nullable | Internal student-athlete status. |
| `phone_number` | varchar | Nullable | Student-athlete contact number. |

### `coaches`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique coach profile identifier. |
| `user_id` | bigint | FK to `users.id`; UK | Linked identity record. |
| `phone_number` | varchar | Nullable | Coach contact number. |
| `coach_status` | enum | Required | Active or inactive coach state. |

### `user_settings`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique user settings identifier. |
| `user_id` | bigint | FK to `users.id`; UK | Owner of the settings record. |
| `notification_email_enabled` | boolean | Required | Enables or disables email notification delivery. |
| `notify_approvals` | boolean | Required | Approval-related alert preference. |
| `notify_schedule_changes` | boolean | Required | Schedule-change alert preference. |
| `notify_attendance_changes` | boolean | Required | Attendance alert preference. |
| `notify_wellness_alerts` | boolean | Required | Wellness alert preference. |
| `notify_academic_alerts` | boolean | Required | Academic alert preference. |
| `notify_attendance_exceptions` | boolean | Required | Attendance exception alert preference. |
| `notify_wellness_injury_threshold` | boolean | Required | Injury-threshold notification preference. |
| `wellness_injury_threshold_level` | tinyint | Required | User-configured threshold level for injury alerts. |

### `admin_invites`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique administrator invitation identifier. |
| `email` | varchar | Required | Email address of the invited administrator. |
| `token_hash` | varchar | UK; required | Hashed invitation token. |
| `created_by` | bigint | FK to `users.id` | Admin who issued the invitation. |
| `expires_at` | timestamp | Required | Invitation expiration timestamp. |
| `used_at` | timestamp | Nullable | Timestamp when the invitation was accepted. |

## 2. Team and Participation Tables

### `sports`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique sport identifier. |
| `name` | varchar | UK; required | Official sport name. |

### `teams`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique team identifier. |
| `sport_id` | bigint | FK to `sports.id` | Sport classification of the team. |
| `team_name` | varchar | Required | Team name. |
| `team_avatar` | varchar | Nullable | Team image path. |
| `year` | year/int | Nullable | Team season or year level designation. |
| `description` | text | Nullable | Descriptive team notes. |
| `archived_at` | timestamp | Nullable | Archive timestamp for inactive historical teams. |
| `archived_by` | bigint | FK to `users.id`; nullable | User who archived the team. |

### `team_staff_assignments`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique staff assignment identifier. |
| `team_id` | bigint | FK to `teams.id` | Team receiving the assignment. |
| `coach_id` | bigint | FK to `coaches.id` | Assigned coach. |
| `role` | enum | Required | Staff role such as head or assistant coach. |
| `starts_at` | timestamp | Nullable | Assignment start date. |
| `ends_at` | timestamp | Nullable | Assignment end date. |
| `created_by` | bigint | FK to `users.id`; nullable | User who created the assignment. |

### `team_players`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique roster membership identifier. |
| `team_id` | bigint | FK to `teams.id` | Team to which the athlete belongs. |
| `student_id` | bigint | FK to `students.id` | Student-athlete assigned to the team. |
| `jersey_number` | varchar | Nullable | Assigned or requested jersey number. |
| `athlete_position` | varchar | Nullable | Position played by the athlete. |
| `player_status` | enum | Required | Current roster participation status. |

## 3. Schedule, Attendance, and Wellness Tables

### `team_schedules`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique schedule identifier. |
| `team_id` | bigint | FK to `teams.id` | Team associated with the activity. |
| `title` | varchar | Required | Schedule title. |
| `type` | enum | Required | Schedule type such as practice, game, or meeting. |
| `venue` | varchar | Nullable | Activity venue. |
| `start_time` | datetime | Required | Scheduled start date and time. |
| `end_time` | datetime | Required | Scheduled end date and time. |
| `notes` | text | Nullable | Additional schedule notes. |

### `schedule_attendances`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique attendance record identifier. |
| `schedule_id` | bigint | FK to `team_schedules.id` | Scheduled activity being recorded. |
| `student_id` | bigint | FK to `students.id` | Student-athlete whose attendance is being recorded. |
| `status` | enum | Required | Attendance outcome: present, late, absent, or excused. |
| `verification_method` | enum | Required | Method used to encode or validate attendance. |
| `recorded_by` | bigint | FK to `users.id`; nullable | User who posted the attendance. |
| `recorded_at` | timestamp | Nullable | Attendance posting timestamp. |
| `verified_at` | timestamp | Nullable | Administrative or subsequent verification timestamp. |
| `notes` | text | Nullable | Attendance-related note. |
| `override_reason` | text | Nullable | Reason for an attendance override. |

### `wellness_logs`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique wellness log identifier. |
| `student_id` | bigint | FK to `students.id` | Student-athlete being monitored. |
| `schedule_id` | bigint | FK to `team_schedules.id` | Related schedule context. |
| `logged_by` | bigint | FK to `users.id`; nullable | User who recorded the wellness entry. |
| `log_date` | date | Required | Date of the wellness observation. |
| `injury_observed` | boolean | Required | Indicates whether an injury concern was observed. |
| `injury_notes` | text | Nullable | Additional injury remarks. |
| `fatigue_level` | tinyint | Nullable | Recorded fatigue level. |
| `performance_condition` | enum | Nullable | General post-session performance condition. |
| `remarks` | text | Nullable | Free-text coach remarks. |

## 4. Academic Monitoring Tables

### `academic_periods`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique academic period identifier. |
| `school_year` | varchar | Required | School year label. |
| `term` | enum | Required | Academic term. |
| `starts_on` | date | Required | Period start date. |
| `ends_on` | date | Required | Period end date. |
| `status` | enum | Required | Period status used by the current module. |

### `academic_document_types`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique document type identifier. |
| `context` | enum | Required | Usage context such as registration or period submission. |
| `code` | varchar | Required | Machine-readable document type code. |
| `label` | varchar | Required | Human-readable document type name. |

### `academic_documents`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique academic document identifier. |
| `student_id` | bigint | FK to `students.id` | Student-athlete owner of the document. |
| `document_type_id` | bigint | FK to `academic_document_types.id` | Document classification. |
| `academic_period_id` | bigint | FK to `academic_periods.id`; nullable for registration docs | Associated academic period. |
| `file_path` | varchar | Required | Stored file path of the uploaded document. |
| `uploaded_by` | bigint | FK to `users.id`; nullable | User who uploaded the document. |
| `uploaded_at` | timestamp | Nullable | Upload timestamp. |
| `review_status` | enum | Required | Processing and review state of the document. |
| `reviewed_by` | bigint | FK to `users.id`; nullable | Reviewing administrator. |
| `reviewed_at` | timestamp | Nullable | Review completion timestamp. |
| `notes` | text | Nullable | Supplementary document notes. |

### `academic_document_ocr_runs`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique OCR run identifier. |
| `academic_document_id` | bigint | FK to `academic_documents.id` | Source document processed by OCR. |
| `ocr_engine` | varchar | Required | OCR engine name. |
| `ocr_engine_version` | varchar | Nullable | OCR engine version. |
| `run_status` | enum | Required | Processing state of the OCR run. |
| `raw_text` | longtext | Nullable | Extracted OCR text. |
| `mean_confidence` | decimal | Nullable | Average OCR confidence score. |
| `validation_status` | enum | Required | Validation result of the OCR output. |
| `validation_summary` | text | Nullable | Human-readable validation summary. |
| `validation_flags` | json | Nullable | Structured validation issues or flags. |
| `processed_at` | timestamp | Nullable | OCR completion timestamp. |

### `academic_document_parsed_summaries`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique parsed summary identifier. |
| `academic_document_ocr_run_id` | bigint | FK to `academic_document_ocr_runs.id`; UK | OCR run summarized by the parser. |
| `gwa` | decimal | Nullable | Extracted grade average. |
| `total_units` | decimal | Nullable | Extracted total academic units. |
| `parser_status` | enum | Required | Parser result state. |
| `parser_confidence` | decimal | Nullable | Confidence score of the parsed result. |

### `academic_eligibility_evaluations`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique eligibility evaluation identifier. |
| `student_id` | bigint | FK to `students.id` | Student-athlete under evaluation. |
| `academic_period_id` | bigint | FK to `academic_periods.id` | Period covered by the evaluation. |
| `document_id` | bigint | FK to `academic_documents.id`; nullable | Supporting document used in the evaluation. |
| `academic_document_ocr_run_id` | bigint | FK to `academic_document_ocr_runs.id`; nullable | OCR run referenced by the evaluation. |
| `gpa` | decimal | Nullable | GPA, GWA, or equivalent grade basis. |
| `evaluation_source` | enum | Required | Manual or rule-based evaluation source. |
| `final_status` | enum | Nullable | Final result such as eligible, ineligible, or pending review. |
| `review_required` | boolean | Required | Indicates whether manual review is still needed. |
| `evaluated_by` | bigint | FK to `users.id`; nullable | User who finalized the evaluation. |
| `evaluated_at` | timestamp | Nullable | Finalization timestamp. |
| `remarks` | text | Nullable | Administrative evaluation remarks. |

## 5. Communication and Audit Tables

### `announcement_events`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique announcement event identifier. |
| `title` | varchar | Required | Announcement title. |
| `message` | text | Required | Announcement body. |
| `type` | enum | Required | Announcement category such as approval, academic, schedule, general, or system. |
| `published_at` | timestamp | Nullable | Publication timestamp. |
| `created_by` | bigint | FK to `users.id`; nullable | Authoring user. |

### `announcement_recipients`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique recipient delivery identifier. |
| `event_id` | bigint | FK to `announcement_events.id` | Related announcement event. |
| `user_id` | bigint | FK to `users.id` | Intended recipient. |
| `read_at` | timestamp | Nullable | Timestamp when the recipient read the announcement. |

### `student_approval_histories`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique approval history identifier. |
| `user_id` | bigint | FK to `users.id` | Student user whose status was reviewed. |
| `admin_id` | bigint | FK to `users.id`; nullable | Administrator who made the decision. |
| `decision` | enum/varchar | Required | Approval outcome recorded by the admin. |
| `remarks` | text | Nullable | Administrative remarks regarding the decision. |
| `decided_at` | timestamp | Nullable | Timestamp of the approval action. |

### `account_action_logs`

| Field | Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | bigint | PK | Unique account action log identifier. |
| `user_id` | bigint | FK to `users.id` | User account affected by the action. |
| `admin_id` | bigint | FK to `users.id`; nullable | Administrator who performed the action. |
| `action` | varchar | Required | Administrative action performed on the account. |
| `remarks` | varchar/text | Nullable | Short explanation of the action. |

## Excluded Tables from the Final Thesis Narrative

The following are intentionally excluded from the active data dictionary because they do not represent the current implemented operational workflow:

- QR-attendance-related legacy structures;
- public coach registration structures;
- health-clearance workflow tables;
- academic hold and academic period message tables as active application features; and
- deprecated intermediate or experimental development tables no longer used by the active application flow.
