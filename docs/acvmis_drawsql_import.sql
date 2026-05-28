CREATE TABLE `academic_periods` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_year` VARCHAR(9) NOT NULL,
  `term` ENUM('1st_sem','2nd_sem','summer') NOT NULL,
  `starts_on` DATE NOT NULL,
  `ends_on` DATE NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `document_types` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `context` ENUM('registration','period_submission') NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `sports` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `users` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(255) NULL,
  `middle_name` VARCHAR(255) NULL,
  `last_name` VARCHAR(255) NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL,
  `password` VARCHAR(255) NOT NULL,
  `must_change_password` TINYINT(1) NOT NULL,
  `role` VARCHAR(255) NOT NULL,
  `account_state` ENUM('active','deactivated') NOT NULL,
  `avatar` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `students` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `student_id_number` VARCHAR(255) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `gender` ENUM('Male','Female','Other') NOT NULL,
  `home_address` TEXT NOT NULL,
  `course_or_strand` VARCHAR(255) NULL,
  `current_grade_level` VARCHAR(255) NOT NULL,
  `approval_status` ENUM('pending','approved','rejected') NOT NULL,
  `applied_sport_id` BIGINT(20) UNSIGNED NULL,
  `student_status` ENUM('Enrolled','Dropped','Graduated') NOT NULL,
  `phone_number` VARCHAR(255) NOT NULL,
  `height` DECIMAL(5,2) NULL,
  `weight` DECIMAL(5,2) NULL,
  `emergency_contact_name` VARCHAR(255) NULL,
  `emergency_contact_relationship` VARCHAR(255) NULL,
  `emergency_contact_phone` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_students_applied_sport` FOREIGN KEY (`applied_sport_id`) REFERENCES `sports` (`id`)
);

CREATE TABLE `coaches` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `phone_number` VARCHAR(20) NULL,
  `date_of_birth` DATE NULL,
  `gender` ENUM('Male','Female','Other') NULL,
  `home_address` VARCHAR(255) NULL,
  `coach_status` ENUM('Active','Inactive') NOT NULL,
  `sport_id` BIGINT(20) UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_coaches_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_coaches_sport` FOREIGN KEY (`sport_id`) REFERENCES `sports` (`id`)
);

CREATE TABLE `teams` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_name` VARCHAR(255) NOT NULL,
  `team_avatar` VARCHAR(255) NULL,
  `sport_id` BIGINT(20) UNSIGNED NULL,
  `year` YEAR NULL,
  `description` TEXT NULL,
  `archived_at` TIMESTAMP NULL,
  `archived_by` BIGINT(20) UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `join_code` VARCHAR(20) NULL,
  `join_code_enabled` TINYINT(1) NOT NULL,
  `join_code_expires_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_teams_sport` FOREIGN KEY (`sport_id`) REFERENCES `sports` (`id`),
  CONSTRAINT `fk_teams_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`)
);

CREATE TABLE `team_schedules` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` BIGINT(20) UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `type` ENUM('practice','game') NOT NULL,
  `venue` VARCHAR(255) NOT NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_team_schedules_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
);

CREATE TABLE `team_players` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` BIGINT(20) UNSIGNED NOT NULL,
  `student_id` BIGINT(20) UNSIGNED NOT NULL,
  `jersey_number` VARCHAR(255) NULL,
  `athlete_position` VARCHAR(255) NULL,
  `player_status` ENUM('active','injured','suspended','inactive') NOT NULL,
  `manual_inactive` TINYINT(1) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_team_players_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `fk_team_players_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
);

CREATE TABLE `team_staff_assignments` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` BIGINT(20) UNSIGNED NOT NULL,
  `coach_id` BIGINT(20) UNSIGNED NOT NULL,
  `role` ENUM('head','assistant') NOT NULL,
  `starts_at` TIMESTAMP NULL,
  `ends_at` TIMESTAMP NULL,
  `created_by` BIGINT(20) UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_team_staff_assignments_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `fk_team_staff_assignments_coach` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`),
  CONSTRAINT `fk_team_staff_assignments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
);

CREATE TABLE `student_documents` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` BIGINT(20) UNSIGNED NOT NULL,
  `document_type_id` BIGINT(20) UNSIGNED NOT NULL,
  `academic_period_id` BIGINT(20) UNSIGNED NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `uploaded_by` BIGINT(20) UNSIGNED NULL,
  `uploaded_at` TIMESTAMP NULL,
  `notes` TEXT NULL,
  `review_status` ENUM('pending','auto_processed','needs_review','reviewed') NOT NULL,
  `reviewed_by` BIGINT(20) UNSIGNED NULL,
  `reviewed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_student_documents_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `fk_student_documents_document_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`),
  CONSTRAINT `fk_student_documents_academic_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`),
  CONSTRAINT `fk_student_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_student_documents_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
);

CREATE TABLE `academic_document_ocr_runs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_document_id` BIGINT(20) UNSIGNED NOT NULL,
  `ocr_engine` VARCHAR(50) NOT NULL,
  `ocr_engine_version` VARCHAR(50) NULL,
  `run_status` ENUM('pending','processed','failed','needs_review') NOT NULL,
  `raw_text` LONGTEXT NULL,
  `mean_confidence` DECIMAL(5,2) NULL,
  `validation_status` ENUM('pending','valid','manual_review') NOT NULL,
  `validation_summary` TEXT NULL,
  `validation_flags` LONGTEXT NULL,
  `validation_checked_at` TIMESTAMP NULL,
  `processed_at` TIMESTAMP NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_academic_document_ocr_runs_document` FOREIGN KEY (`academic_document_id`) REFERENCES `student_documents` (`id`)
);

CREATE TABLE `academic_document_parsed_summaries` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_document_ocr_run_id` BIGINT(20) UNSIGNED NOT NULL,
  `gwa` DECIMAL(4,2) NULL,
  `total_units` DECIMAL(6,2) NULL,
  `parser_status` ENUM('pending','parsed','failed','needs_review') NOT NULL,
  `parser_confidence` DECIMAL(5,2) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_academic_document_parsed_summaries_ocr_run` FOREIGN KEY (`academic_document_ocr_run_id`) REFERENCES `academic_document_ocr_runs` (`id`)
);

CREATE TABLE `academic_eligibility_evaluations` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` BIGINT(20) UNSIGNED NOT NULL,
  `academic_period_id` BIGINT(20) UNSIGNED NOT NULL,
  `document_id` BIGINT(20) UNSIGNED NULL,
  `academic_document_ocr_run_id` BIGINT(20) UNSIGNED NULL,
  `gpa` DECIMAL(4,2) NULL,
  `evaluation_source` ENUM('manual','rule_based','rule_based_reviewed') NOT NULL,
  `final_status` ENUM('eligible','pending_review','ineligible') NULL,
  `review_required` TINYINT(1) NOT NULL,
  `evaluated_by` BIGINT(20) UNSIGNED NULL,
  `evaluated_at` TIMESTAMP NULL,
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_academic_eligibility_evaluations_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `fk_academic_eligibility_evaluations_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`),
  CONSTRAINT `fk_academic_eligibility_evaluations_document` FOREIGN KEY (`document_id`) REFERENCES `student_documents` (`id`),
  CONSTRAINT `fk_academic_eligibility_evaluations_ocr_run` FOREIGN KEY (`academic_document_ocr_run_id`) REFERENCES `academic_document_ocr_runs` (`id`),
  CONSTRAINT `fk_academic_eligibility_evaluations_user` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`)
);

CREATE TABLE `schedule_attendances` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_id` BIGINT(20) UNSIGNED NOT NULL,
  `student_id` BIGINT(20) UNSIGNED NOT NULL,
  `status` ENUM('present','absent','late','excused') NOT NULL,
  `verification_method` VARCHAR(255) NOT NULL,
  `recorded_by` BIGINT(20) UNSIGNED NULL,
  `recorded_at` TIMESTAMP NULL,
  `verified_at` TIMESTAMP NULL,
  `notes` TEXT NULL,
  `override_reason` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_schedule_attendances_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `team_schedules` (`id`),
  CONSTRAINT `fk_schedule_attendances_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `fk_schedule_attendances_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
);

CREATE TABLE `student_approval_histories` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` BIGINT(20) UNSIGNED NOT NULL,
  `coach_id` BIGINT(20) UNSIGNED NULL,
  `decision` ENUM('approved','rejected') NOT NULL,
  `remarks` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_student_approval_histories_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `fk_student_approval_histories_coach` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`)
);

CREATE TABLE `training_requirements` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_id` BIGINT(20) UNSIGNED NOT NULL,
  `student_id` BIGINT(20) UNSIGNED NOT NULL,
  `coach_id` BIGINT(20) UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_training_requirements_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `team_schedules` (`id`),
  CONSTRAINT `fk_training_requirements_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `fk_training_requirements_coach` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`)
);

CREATE TABLE `account_action_logs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `admin_id` BIGINT(20) UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `remarks` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_account_action_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_account_action_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`)
);

CREATE TABLE `admin_invites` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `created_by` BIGINT(20) UNSIGNED NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_admin_invites_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
);

CREATE TABLE `announcement_events` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('approval','general','academic','schedule','system') NOT NULL,
  `published_at` TIMESTAMP NULL,
  `created_by` BIGINT(20) UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_announcement_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
);

CREATE TABLE `announcement_recipients` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT(20) UNSIGNED NOT NULL,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `read_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_announcement_recipients_event` FOREIGN KEY (`event_id`) REFERENCES `announcement_events` (`id`),
  CONSTRAINT `fk_announcement_recipients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
);

CREATE TABLE `user_settings` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `notification_email_enabled` TINYINT(1) NOT NULL,
  `notify_approvals` TINYINT(1) NOT NULL,
  `notify_schedule_changes` TINYINT(1) NOT NULL,
  `notify_attendance_changes` TINYINT(1) NOT NULL,
  `notify_academic_alerts` TINYINT(1) NOT NULL,
  `notify_attendance_exceptions` TINYINT(1) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_user_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
);
