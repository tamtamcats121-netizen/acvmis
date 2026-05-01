# AC-VMIS Data Dictionary

This document presents the current AC-VMIS database structure as of April 26, 2026. The `Constraints` column summarizes each field's primary key, foreign key, uniqueness, nullability, and default-value requirements using formal and consistent terminology suitable for thesis documentation.

The academic eligibility module now uses an OCR-assisted workflow centered on uploaded documents, OCR runs, parsed summaries, and final eligibility evaluations with administrator override support.

Legacy development tables such as `account_approvals`, `announcements`, `schedule_qr_tokens`, `academic_evaluation_documents`, `academic_document_parsed_subjects`, and `wellness_attachments` are intentionally excluded because they do not represent the current production-oriented schema.

## `academic_documents`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the academic document record. |
| `student_id` | `bigint(20) unsigned` | Foreign key to `students.id`; not null | Identifies the student to whom the academic document belongs. |
| `document_type_id` | `bigint(20) unsigned` | Foreign key to `academic_document_types.id`; not null | Identifies the type classification assigned to the academic document. |
| `academic_period_id` | `bigint(20) unsigned` | Foreign key to `academic_periods.id`; nullable only for registration-context documents | Identifies the academic period associated with the document; required for period submissions and empty for registration documents. |
| `file_path` | `varchar(255)` | Not null | Stores the file location of the uploaded academic document. |
| `uploaded_by` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the user who uploaded the document. |
| `uploaded_at` | `timestamp` | Nullable | Records the date and time when the document was uploaded. |
| `notes` | `text` | Nullable | Stores supplementary remarks regarding the document. |
| `review_status` | `enum('pending','auto_processed','needs_review','reviewed')` | Not null; default `'pending'` | Indicates whether the document is waiting for processing, system-classified, waiting for human action, or explicitly reviewed by an administrator. |
| `reviewed_by` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the administrator who manually reviewed the document. |
| `reviewed_at` | `timestamp` | Nullable | Records the date and time of manual human review. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `academic_document_ocr_runs`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the OCR processing run. |
| `academic_document_id` | `bigint(20) unsigned` | Foreign key to `academic_documents.id`; not null | Identifies the academic document processed during the OCR run. |
| `ocr_engine` | `varchar(50)` | Not null | Stores the name of the OCR engine used for processing. |
| `ocr_engine_version` | `varchar(50)` | Nullable | Stores the version of the OCR engine used for processing. |
| `run_status` | `enum('pending','processed','failed','needs_review')` | Not null; default `'pending'` | Indicates the processing outcome or current state of the OCR run. |
| `raw_text` | `longtext` | Nullable | Stores the raw text extracted from the document. |
| `mean_confidence` | `decimal(5,2)` | Nullable | Stores the average OCR confidence score for the processed document. |
| `validation_status` | `enum('pending','valid','manual_review')` | Not null; default `'pending'` | Indicates the result of the post-OCR validation check applied to the extracted document data. |
| `validation_summary` | `text` | Nullable | Stores a human-readable summary of the validation outcome. |
| `validation_flags` | `json` | Nullable | Stores structured validation flags or issues detected during automated review. |
| `validation_checked_at` | `timestamp` | Nullable | Records the date and time when validation was last evaluated for the OCR run. |
| `processed_at` | `timestamp` | Nullable | Records the date and time when OCR processing was completed. |
| `error_message` | `text` | Nullable | Stores the error details when the OCR run fails. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `academic_document_parsed_summaries`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the parsed summary record. |
| `academic_document_ocr_run_id` | `bigint(20) unsigned` | Foreign key to `academic_document_ocr_runs.id`; not null; unique | Identifies the OCR run to which the parsed summary belongs. |
| `gwa` | `decimal(4,2)` | Nullable | Stores the general weighted average extracted from the document. |
| `total_units` | `decimal(6,2)` | Nullable | Stores the total number of academic units extracted from the document. |
| `parser_status` | `enum('pending','parsed','failed','needs_review')` | Not null; default `'pending'` | Indicates the parsing outcome or current state of the summary extraction process. |
| `parser_confidence` | `decimal(5,2)` | Nullable | Stores the confidence score for the parsed summary. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `academic_document_types`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the academic document type. |
| `context` | `enum('registration','period_submission')` | Not null; part of unique combination with `code` | Indicates the business context in which the document type is used. |
| `code` | `varchar(50)` | Not null; part of unique combination with `context` | Stores the machine-readable code of the document type. |
| `label` | `varchar(120)` | Not null | Stores the descriptive name of the document type. |

## `academic_eligibility_evaluations`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the academic eligibility evaluation record. |
| `student_id` | `bigint(20) unsigned` | Foreign key to `students.id`; not null; part of unique combination with `academic_period_id` | Identifies the student being evaluated. |
| `academic_period_id` | `bigint(20) unsigned` | Foreign key to `academic_periods.id`; not null; part of unique combination with `student_id` | Identifies the academic period for which eligibility is evaluated. |
| `document_id` | `bigint(20) unsigned` | Foreign key to `academic_documents.id`; nullable | Identifies the supporting academic document used in the evaluation. |
| `academic_document_ocr_run_id` | `bigint(20) unsigned` | Foreign key to `academic_document_ocr_runs.id`; nullable | Identifies the OCR run used as the basis of the evaluation, when applicable. |
| `gpa` | `decimal(4,2)` | Nullable | Stores the GPA or equivalent rating used during evaluation. |
| `evaluation_source` | `enum('manual','rule_based','rule_based_reviewed')` | Not null; default `'manual'` | Indicates whether the evaluation was produced manually or through rule-based processing. |
| `final_status` | `enum('eligible','pending_review','ineligible')` | Nullable | Stores the official eligibility outcome confirmed for the student after automated processing, review, or override. |
| `review_required` | `tinyint(1)` | Not null; default `0` | Indicates whether the evaluation requires additional human review. |
| `evaluated_by` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the user who finalized or recorded the evaluation. |
| `evaluated_at` | `timestamp` | Nullable | Records the date and time when the evaluation was completed. |
| `remarks` | `text` | Nullable | Stores remarks or explanatory notes regarding the evaluation. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `academic_holds`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the academic hold record. |
| `student_id` | `bigint(20) unsigned` | Foreign key to `students.id`; not null | Identifies the student subject to the hold. |
| `source_period_id` | `bigint(20) unsigned` | Foreign key to `academic_periods.id`; nullable | Identifies the academic period from which the hold originated. |
| `reason` | `enum('missing_submissions','legacy_student_status','manual_hold')` | Not null | Stores the formal reason for placing the hold. |
| `status` | `enum('suspended','unenrolled','resolved')` | Not null | Indicates the current state of the academic hold. |
| `started_at` | `timestamp` | Nullable | Records the date and time when the hold took effect. |
| `resolved_at` | `timestamp` | Nullable | Records the date and time when the hold was resolved. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `academic_periods`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the academic period. |
| `school_year` | `varchar(9)` | Not null; part of unique combination with `term` | Stores the school year designation of the academic period. |
| `term` | `enum('1st_sem','2nd_sem','summer')` | Not null; part of unique combination with `school_year` | Stores the academic term within the school year. |
| `starts_on` | `date` | Not null | Stores the official start date of the academic period. |
| `ends_on` | `date` | Not null | Stores the official end date of the academic period. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `academic_period_messages`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the academic period message. |
| `academic_period_id` | `bigint(20) unsigned` | Foreign key to `academic_periods.id`; not null | Identifies the academic period to which the message belongs. |
| `message` | `text` | Not null | Stores the announcement or message content for the academic period. |
| `published_at` | `timestamp` | Nullable | Records the date and time when the message was published. |
| `created_by` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the user who created the message. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `account_action_logs`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the account action log entry. |
| `user_id` | `bigint(20) unsigned` | Foreign key to `users.id`; not null | Identifies the user account affected by the action. |
| `admin_id` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the administrator who performed the action. |
| `action` | `varchar(80)` | Not null | Stores the specific administrative action performed on the account. |
| `remarks` | `varchar(255)` | Nullable | Stores remarks explaining the recorded action. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `admin_invites`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the administrative invitation record. |
| `email` | `varchar(255)` | Not null | Stores the email address of the invited administrator. |
| `token_hash` | `varchar(64)` | Unique; not null | Stores the hashed invitation token used for secure verification. |
| `created_by` | `bigint(20) unsigned` | Foreign key to `users.id`; not null | Identifies the user who generated the invitation. |
| `expires_at` | `timestamp` | Not null; default `current_timestamp()` | Stores the expiration date and time of the invitation. |
| `used_at` | `timestamp` | Nullable | Records the date and time when the invitation was used. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `announcement_events`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the announcement event. |
| `title` | `varchar(255)` | Not null | Stores the title of the announcement. |
| `message` | `text` | Not null | Stores the content body of the announcement. |
| `type` | `enum('approval','general','academic','schedule','system')` | Not null; default `'general'` | Classifies the announcement according to its functional category. |
| `published_at` | `timestamp` | Nullable | Records the date and time when the announcement was published. |
| `created_by` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the user who created the announcement. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `announcement_recipients`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the announcement recipient record. |
| `event_id` | `bigint(20) unsigned` | Foreign key to `announcement_events.id`; not null; part of unique combination with `user_id` | Identifies the announcement event assigned to the recipient. |
| `user_id` | `bigint(20) unsigned` | Foreign key to `users.id`; not null; part of unique combination with `event_id` | Identifies the user designated to receive the announcement. |
| `read_at` | `timestamp` | Nullable | Records the date and time when the recipient read the announcement. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `cache`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `key` | `varchar(255)` | Primary key; not null | Stores the unique cache key. |
| `value` | `mediumtext` | Not null | Stores the serialized cached value. |
| `expiration` | `int(11)` | Not null | Stores the cache expiration time in Unix timestamp format. |

## `cache_locks`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `key` | `varchar(255)` | Primary key; not null | Stores the unique cache lock key. |
| `owner` | `varchar(255)` | Not null | Stores the identifier of the lock owner. |
| `expiration` | `int(11)` | Not null | Stores the lock expiration time in Unix timestamp format. |

## `coaches`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the coach record. |
| `user_id` | `bigint(20) unsigned` | Foreign key to `users.id`; unique; not null | Identifies the user account linked to the coach profile. |
| `phone_number` | `varchar(20)` | Nullable | Stores the coach's contact number. |
| `date_of_birth` | `date` | Nullable | Stores the coach's date of birth. |
| `gender` | `enum('Male','Female','Other')` | Nullable | Stores the coach's gender. |
| `home_address` | `varchar(255)` | Nullable | Stores the coach's residential address. |
| `coach_status` | `enum('Active','Inactive')` | Not null; default `'Active'` | Indicates whether the coach profile is active or inactive. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `failed_jobs`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the failed queued job record. |
| `uuid` | `varchar(255)` | Unique; not null | Stores the globally unique identifier of the failed job. |
| `connection` | `text` | Not null | Stores the queue connection name used by the failed job. |
| `queue` | `text` | Not null | Stores the queue name from which the job was processed. |
| `payload` | `longtext` | Not null | Stores the serialized job payload. |
| `exception` | `longtext` | Not null | Stores the exception details generated by the failed job. |
| `failed_at` | `timestamp` | Not null; default `current_timestamp()` | Records the date and time when the job failed. |

## `jobs`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the queued job record. |
| `queue` | `varchar(255)` | Not null | Stores the queue name assigned to the job. |
| `payload` | `longtext` | Not null | Stores the serialized job payload. |
| `attempts` | `tinyint(3) unsigned` | Not null | Stores the number of processing attempts made for the job. |
| `reserved_at` | `int(10) unsigned` | Nullable | Stores the reservation time of the job in Unix timestamp format. |
| `available_at` | `int(10) unsigned` | Not null | Stores the time when the job becomes available for processing in Unix timestamp format. |
| `created_at` | `int(10) unsigned` | Not null | Stores the record creation time in Unix timestamp format. |

## `job_batches`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `varchar(255)` | Primary key; not null | Unique identifier of the job batch record. |
| `name` | `varchar(255)` | Not null | Stores the descriptive name of the job batch. |
| `total_jobs` | `int(11)` | Not null | Stores the total number of jobs included in the batch. |
| `pending_jobs` | `int(11)` | Not null | Stores the number of jobs in the batch that remain pending. |
| `failed_jobs` | `int(11)` | Not null | Stores the number of failed jobs in the batch. |
| `failed_job_ids` | `longtext` | Not null | Stores the identifiers of failed jobs in serialized form. |
| `options` | `mediumtext` | Nullable | Stores serialized batch configuration options. |
| `cancelled_at` | `int(11)` | Nullable | Stores the batch cancellation time in Unix timestamp format. |
| `created_at` | `int(11)` | Not null | Stores the batch creation time in Unix timestamp format. |
| `finished_at` | `int(11)` | Nullable | Stores the batch completion time in Unix timestamp format. |

## `migrations`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `int(10) unsigned` | Primary key; auto-increment; not null | Unique identifier of the migration log entry. |
| `migration` | `varchar(255)` | Not null | Stores the name of the migration file that was executed. |
| `batch` | `int(11)` | Not null | Stores the batch number under which the migration was executed. |

## `password_reset_tokens`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `email` | `varchar(255)` | Primary key; not null | Stores the email address associated with the password reset request. |
| `token` | `varchar(255)` | Not null | Stores the password reset token. |
| `created_at` | `timestamp` | Nullable | Records the date and time when the token was created. |

## `schedule_attendances`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the attendance record. |
| `schedule_id` | `bigint(20) unsigned` | Foreign key to `team_schedules.id`; not null; part of unique combination with `student_id` | Identifies the scheduled activity for which attendance was recorded. |
| `student_id` | `bigint(20) unsigned` | Foreign key to `students.id`; not null; part of unique combination with `schedule_id` | Identifies the student whose attendance was recorded. |
| `status` | `enum('present','absent','late','excused')` | Not null; default `'present'` | Stores the attendance outcome assigned to the student. |
| `verification_method` | `varchar(255)` | Not null; default `'manual_override'` | Indicates the workflow used to record the attendance entry. |
| `recorded_by` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the user who recorded or updated the attendance entry. |
| `recorded_at` | `timestamp` | Nullable | Records the date and time when attendance was recorded. |
| `verified_at` | `timestamp` | Nullable | Records the date and time when attendance was verified. |
| `notes` | `text` | Nullable | Stores supplementary remarks regarding attendance. |
| `override_reason` | `text` | Nullable | Stores the justification for a manual attendance override. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `sessions`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `varchar(255)` | Primary key; not null | Unique identifier of the application session. |
| `user_id` | `bigint(20) unsigned` | Nullable | Identifies the authenticated user linked to the session, when applicable. |
| `ip_address` | `varchar(45)` | Nullable | Stores the IP address associated with the session. |
| `user_agent` | `text` | Nullable | Stores the client user-agent string associated with the session. |
| `payload` | `longtext` | Not null | Stores the serialized session data. |
| `last_activity` | `int(11)` | Not null | Stores the last activity time in Unix timestamp format. |

## `sports`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the sport record. |
| `name` | `varchar(255)` | Unique; not null | Stores the official name of the sport. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `students`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the student record. |
| `user_id` | `bigint(20) unsigned` | Foreign key to `users.id`; unique; not null | Identifies the user account linked to the student profile. |
| `student_id_number` | `varchar(255)` | Unique; not null | Stores the institutional student identification number. |
| `date_of_birth` | `date` | Not null | Stores the student's date of birth. |
| `gender` | `enum('Male','Female','Other')` | Not null | Stores the student's gender. |
| `home_address` | `text` | Not null | Stores the student's residential address. |
| `course_or_strand` | `varchar(255)` | Nullable | Stores the student's academic course or strand. |
| `current_grade_level` | `varchar(255)` | Not null | Stores the student's current grade level or year level designation as the active academic level field used by the system. |
| `approval_status` | `enum('pending','approved','rejected')` | Not null; default `'pending'` | Indicates the current approval outcome of the student registration. |
| `student_status` | `enum('Enrolled','Dropped','Graduated')` | Not null; default `'Enrolled'` | Indicates the current enrollment status of the student. |
| `phone_number` | `varchar(255)` | Not null | Stores the student's contact number. |
| `height` | `decimal(5,2)` | Nullable | Stores the student's height measurement. |
| `weight` | `decimal(5,2)` | Nullable | Stores the student's weight measurement. |
| `emergency_contact_name` | `varchar(255)` | Nullable | Stores the name of the student's emergency contact person. |
| `emergency_contact_relationship` | `varchar(255)` | Nullable | Stores the relationship of the emergency contact to the student. |
| `emergency_contact_phone` | `varchar(255)` | Nullable | Stores the contact number of the emergency contact person. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `student_approval_histories`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the student approval history entry. |
| `student_id` | `bigint(20) unsigned` | Foreign key to `students.id`; not null | Identifies the student whose approval decision was recorded. |
| `admin_id` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the administrator who made the decision. |
| `decision` | `enum('approved','rejected')` | Not null | Stores the approval decision applied to the student. |
| `remarks` | `varchar(255)` | Nullable | Stores remarks explaining the decision. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `teams`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the team record. |
| `team_name` | `varchar(255)` | Not null | Stores the official name of the team. |
| `team_avatar` | `varchar(255)` | Nullable | Stores the file location of the team avatar image. |
| `sport_id` | `bigint(20) unsigned` | Foreign key to `sports.id`; nullable | Identifies the sport to which the team belongs. |
| `year` | `year(4)` | Nullable | Stores the year associated with the team roster or season. |
| `description` | `text` | Nullable | Stores a description of the team. |
| `archived_at` | `timestamp` | Nullable | Records the date and time when the team was archived. |
| `archived_by` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the user who archived the team record. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `team_players`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the team-player assignment record. |
| `team_id` | `bigint(20) unsigned` | Foreign key to `teams.id`; not null; part of unique combination with `student_id` | Identifies the team to which the student is assigned. |
| `student_id` | `bigint(20) unsigned` | Foreign key to `students.id`; not null; part of unique combination with `team_id` | Identifies the student assigned as a player. |
| `jersey_number` | `varchar(255)` | Nullable; not currently unique per team | Stores the jersey number assigned to the player. |
| `athlete_position` | `varchar(255)` | Nullable | Stores the player's assigned position within the team. |
| `player_status` | `enum('active','injured','suspended')` | Not null; default `'active'` | Indicates the player's current participation status. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `team_schedules`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the team schedule record. |
| `team_id` | `bigint(20) unsigned` | Foreign key to `teams.id`; not null | Identifies the team to which the schedule belongs. |
| `title` | `varchar(255)` | Not null | Stores the title or subject of the scheduled activity. |
| `type` | `enum('practice','game','meeting')` | Not null | Classifies the scheduled activity by type. |
| `venue` | `varchar(255)` | Not null | Stores the venue of the scheduled activity. |
| `start_time` | `datetime` | Not null | Records the start date and time of the activity. |
| `end_time` | `datetime` | Not null | Records the end date and time of the activity. |
| `notes` | `text` | Nullable | Stores supplementary details regarding the scheduled activity. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `team_staff_assignments`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the team staff assignment record. |
| `team_id` | `bigint(20) unsigned` | Foreign key to `teams.id`; not null | Identifies the team receiving the staff assignment. |
| `coach_id` | `bigint(20) unsigned` | Foreign key to `coaches.id`; not null | Identifies the coach assigned to the team. |
| `role` | `enum('head','assistant')` | Not null | Stores the staff role performed by the coach within the team. |
| `starts_at` | `timestamp` | Nullable | Records the date and time when the assignment became effective. |
| `ends_at` | `timestamp` | Nullable | Records the date and time when the assignment ended. |
| `created_by` | `bigint(20) unsigned` | Foreign key to `users.id`; nullable | Identifies the user who created the assignment. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `users`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the user account. |
| `first_name` | `varchar(255)` | Nullable | Stores the user's given name. |
| `middle_name` | `varchar(255)` | Nullable | Stores the user's middle name. |
| `last_name` | `varchar(255)` | Nullable | Stores the user's family name. |
| `email` | `varchar(255)` | Unique; not null | Stores the user's email address used for authentication and communication. |
| `email_verified_at` | `timestamp` | Nullable | Records the date and time when the email address was verified. |
| `password` | `varchar(255)` | Not null | Stores the hashed password of the user account. |
| `must_change_password` | `tinyint(1)` | Not null; default `0` | Indicates whether the user is required to change the password on the next login. |
| `role` | `varchar(255)` | Not null | Stores the access role assigned to the user. |
| `account_state` | `enum('active','deactivated')` | Not null; default `'active'` | Indicates whether the user account is active or deactivated. |
| `avatar` | `varchar(255)` | Nullable | Stores the file location of the user's profile image. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `user_settings`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the user settings record. |
| `user_id` | `bigint(20) unsigned` | Foreign key to `users.id`; unique; not null | Identifies the user to whom the settings apply. |
| `notification_email_enabled` | `tinyint(1)` | Not null; default `1` | Indicates whether email notifications are enabled for the user. |
| `notify_approvals` | `tinyint(1)` | Not null; default `1` | Indicates whether approval-related notifications are enabled. |
| `notify_schedule_changes` | `tinyint(1)` | Not null; default `1` | Indicates whether schedule change notifications are enabled. |
| `notify_attendance_changes` | `tinyint(1)` | Not null; default `1` | Indicates whether attendance-related notifications are enabled. |
| `notify_wellness_alerts` | `tinyint(1)` | Not null; default `1` | Indicates whether wellness alert notifications are enabled. |
| `notify_academic_alerts` | `tinyint(1)` | Not null; default `1` | Indicates whether academic alert notifications are enabled. |
| `notify_attendance_exceptions` | `tinyint(1)` | Not null; default `1` | Indicates whether attendance exception notifications are enabled. |
| `notify_wellness_injury_threshold` | `tinyint(1)` | Not null; default `1` | Indicates whether injury-threshold notifications are enabled. |
| `wellness_injury_threshold_level` | `tinyint(3) unsigned` | Not null; default `3` | Stores the threshold level used for wellness injury alerts. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |

## `wellness_logs`

| Field Name | Data Type | Constraints | Description |
| --- | --- | --- | --- |
| `id` | `bigint(20) unsigned` | Primary key; auto-increment; not null | Unique identifier of the wellness log record. |
| `student_id` | `bigint(20) unsigned` | Foreign key to `students.id`; not null | Identifies the student whose wellness status was recorded. |
| `schedule_id` | `bigint(20) unsigned` | Foreign key to `team_schedules.id`; nullable | Identifies the related schedule entry, when the log is tied to a scheduled activity. |
| `logged_by` | `bigint(20) unsigned` | Foreign key to `users.id`; not null | Identifies the user who recorded the wellness log. |
| `log_date` | `date` | Not null | Stores the date covered by the wellness log entry. |
| `injury_observed` | `tinyint(1)` | Not null; default `0` | Indicates whether an injury was observed. |
| `injury_notes` | `text` | Nullable | Stores notes describing the observed injury. |
| `fatigue_level` | `tinyint(3) unsigned` | Not null | Stores the recorded fatigue level of the student. |
| `performance_condition` | `enum('excellent','good','fair','poor')` | Not null | Stores the assessed performance condition of the student. |
| `remarks` | `text` | Nullable | Stores supplementary remarks regarding the wellness record. |
| `created_at` | `timestamp` | Nullable | Timestamp indicating when the record was created. |
| `updated_at` | `timestamp` | Nullable | Timestamp indicating when the record was last updated. |
