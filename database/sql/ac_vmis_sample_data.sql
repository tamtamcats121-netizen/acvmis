-- AC-VMIS sample/testing data
-- phpMyAdmin-compatible import script
-- Default seeded password for all users: password

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM announcement_recipients;
DELETE FROM academic_document_parsed_summaries;
DELETE FROM academic_document_ocr_runs;
DELETE FROM academic_eligibility_evaluations;
DELETE FROM academic_documents;
DELETE FROM schedule_attendances;
DELETE FROM team_players;
DELETE FROM team_staff_assignments;
DELETE FROM team_schedules;
DELETE FROM teams;
DELETE FROM sports;
DELETE FROM academic_document_types;
DELETE FROM academic_periods;
DELETE FROM student_approval_histories;
DELETE FROM account_action_logs;
DELETE FROM admin_invites;
DELETE FROM announcement_events;
DELETE FROM user_settings;
DELETE FROM coaches;
DELETE FROM students;
DELETE FROM users;

INSERT INTO users (
    id, first_name, middle_name, last_name, email, email_verified_at, password,
    must_change_password, role, account_state, avatar, created_at, updated_at
) VALUES
    (1, 'Maria', 'S.', 'Gonzales', 'maria.gonzales@ac.edu.ph', '2026-01-05 08:00:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'admin', 'active', 'avatars/admin-maria-gonzales.jpg', '2026-01-05 08:00:00', '2026-04-30 09:10:00'),
    (2, 'Paolo', 'R.', 'Navarro', 'paolo.navarro@ac.edu.ph', '2026-01-06 08:15:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'admin', 'active', 'avatars/admin-paolo-navarro.jpg', '2026-01-06 08:15:00', '2026-04-30 09:10:00'),
    (3, 'Antonio', 'M.', 'Reyes', 'antonio.reyes@ac.edu.ph', '2026-01-08 10:00:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'coach', 'active', 'avatars/coach-antonio-reyes.jpg', '2026-01-08 10:00:00', '2026-04-30 09:10:00'),
    (4, 'Liza', 'T.', 'Santos', 'liza.santos@ac.edu.ph', '2026-01-09 10:00:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'coach', 'active', 'avatars/coach-liza-santos.jpg', '2026-01-09 10:00:00', '2026-04-30 09:10:00'),
    (5, 'Ramon', 'D.', 'Cruz', 'ramon.cruz@ac.edu.ph', '2026-01-10 10:00:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'coach', 'active', 'avatars/coach-ramon-cruz.jpg', '2026-01-10 10:00:00', '2026-04-30 09:10:00'),
    (6, 'Juan', 'A.', 'Cruz', 'juan.cruz@student.ac.edu.ph', '2026-01-12 07:45:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'student-athlete', 'active', 'avatars/student-juan-cruz.jpg', '2026-01-12 07:45:00', '2026-04-30 09:10:00'),
    (7, 'Miguel', 'B.', 'Lopez', 'miguel.lopez@student.ac.edu.ph', '2026-01-12 07:48:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'student-athlete', 'active', 'avatars/student-miguel-lopez.jpg', '2026-01-12 07:48:00', '2026-04-30 09:10:00'),
    (8, 'Carla', 'P.', 'Mendoza', 'carla.mendoza@student.ac.edu.ph', '2026-01-12 07:50:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'student-athlete', 'active', 'avatars/student-carla-mendoza.jpg', '2026-01-12 07:50:00', '2026-04-30 09:10:00'),
    (9, 'Angela', 'R.', 'Torres', 'angela.torres@student.ac.edu.ph', '2026-01-12 07:53:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'student-athlete', 'active', 'avatars/student-angela-torres.jpg', '2026-01-12 07:53:00', '2026-04-30 09:10:00'),
    (10, 'Nicole', 'F.', 'Garcia', 'nicole.garcia@student.ac.edu.ph', '2026-01-12 07:55:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'student-athlete', 'active', 'avatars/student-nicole-garcia.jpg', '2026-01-12 07:55:00', '2026-04-30 09:10:00'),
    (11, 'Paula', 'C.', 'Villanueva', 'paula.villanueva@student.ac.edu.ph', '2026-01-12 07:57:00', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'student-athlete', 'active', 'avatars/student-paula-villanueva.jpg', '2026-01-12 07:57:00', '2026-04-30 09:10:00'),
    (12, 'Ethan', 'J.', 'Soriano', 'ethan.soriano@student.ac.edu.ph', NULL, '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'student-athlete', 'active', NULL, '2026-04-27 13:00:00', '2026-04-30 09:10:00'),
    (13, 'Bianca', 'L.', 'Aquino', 'bianca.aquino@student.ac.edu.ph', NULL, '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'student-athlete', 'active', NULL, '2026-04-27 13:05:00', '2026-04-30 09:10:00');

INSERT INTO students (
    id, user_id, student_id_number, date_of_birth, gender, home_address,
    current_grade_level, approval_status, student_status, phone_number,
    height, weight, emergency_contact_name, emergency_contact_relationship,
    emergency_contact_phone, course_or_strand, created_at, updated_at
) VALUES
    (1, 6, '2026-001', '2008-10-11', 'Male', 'Bacoor, Cavite', '11', 'approved', 'Enrolled', '0900839324', 167.00, 63.00, 'Ana Cruz', 'Mother', '09171234567', 'ABM', '2026-01-12 07:45:00', '2026-04-30 09:10:00'),
    (2, 7, '2026-002', '2007-08-19', 'Male', 'Imus, Cavite', '12', 'approved', 'Enrolled', '09181234567', 175.50, 70.20, 'Mario Lopez', 'Father', '09182345678', 'HUMSS', '2026-01-12 07:48:00', '2026-04-30 09:10:00'),
    (3, 8, '2026-003', '2008-03-09', 'Female', 'Dasmarinas, Cavite', '11', 'approved', 'Enrolled', '09221234567', 160.20, 54.30, 'Ruth Mendoza', 'Mother', '09223456789', 'STEM', '2026-01-12 07:50:00', '2026-04-30 09:10:00'),
    (4, 9, '2026-004', '2007-11-02', 'Female', 'General Trias, Cavite', '12', 'approved', 'Enrolled', '09331234567', 168.40, 58.70, 'Jose Torres', 'Father', '09332345678', 'ABM', '2026-01-12 07:53:00', '2026-04-30 09:10:00'),
    (5, 10, '2026-005', '2005-07-25', 'Female', 'Tagaytay, Cavite', '2', 'approved', 'Enrolled', '09441234567', 162.00, 55.40, 'Lorna Garcia', 'Mother', '09442345678', 'BSBA', '2026-01-12 07:55:00', '2026-04-30 09:10:00'),
    (6, 11, '2026-006', '2004-12-14', 'Female', 'Naic, Cavite', '3', 'approved', 'Enrolled', '09551234567', 164.80, 57.10, 'Pedro Villanueva', 'Father', '09552345678', 'BS Psychology', '2026-01-12 07:57:00', '2026-04-30 09:10:00'),
    (7, 12, '2026-007', '2008-02-16', 'Male', 'Silang, Cavite', '11', 'pending', 'Enrolled', '09661234567', 171.20, 61.50, 'Cecilia Soriano', 'Mother', '09662345678', 'GAS', '2026-04-27 13:00:00', '2026-04-30 09:10:00'),
    (8, 13, '2026-008', '2007-06-01', 'Female', 'Tanza, Cavite', '12', 'rejected', 'Enrolled', '09771234567', 158.90, 52.20, 'Helen Aquino', 'Mother', '09772345678', 'HUMSS', '2026-04-27 13:05:00', '2026-04-30 09:10:00');

INSERT INTO coaches (
    id, user_id, phone_number, date_of_birth, gender, home_address, coach_status, created_at, updated_at
) VALUES
    (1, 3, '09190000001', '1985-04-12', 'Male', 'Bacoor, Cavite', 'Active', '2026-01-08 10:00:00', '2026-04-30 09:10:00'),
    (2, 4, '09190000002', '1988-09-03', 'Female', 'Imus, Cavite', 'Active', '2026-01-09 10:00:00', '2026-04-30 09:10:00'),
    (3, 5, '09190000003', '1982-01-17', 'Male', 'Dasmarinas, Cavite', 'Active', '2026-01-10 10:00:00', '2026-04-30 09:10:00');

INSERT INTO user_settings (
    id, user_id, notification_email_enabled, notify_approvals, notify_schedule_changes,
    notify_attendance_changes, notify_academic_alerts, notify_attendance_exceptions, created_at, updated_at
) VALUES
    (1, 1, 1, 1, 1, 1, 1, 1, '2026-01-05 08:05:00', '2026-04-30 09:10:00'),
    (2, 2, 1, 1, 1, 1, 1, 1, '2026-01-06 08:20:00', '2026-04-30 09:10:00'),
    (3, 3, 1, 1, 1, 1, 1, 1, '2026-01-08 10:05:00', '2026-04-30 09:10:00'),
    (4, 4, 1, 1, 1, 1, 1, 1, '2026-01-09 10:05:00', '2026-04-30 09:10:00'),
    (5, 5, 1, 1, 1, 1, 1, 1, '2026-01-10 10:05:00', '2026-04-30 09:10:00'),
    (6, 6, 1, 0, 1, 1, 1, 1, '2026-01-12 07:50:00', '2026-04-30 09:10:00'),
    (7, 7, 1, 0, 1, 1, 1, 1, '2026-01-12 07:50:00', '2026-04-30 09:10:00'),
    (8, 8, 1, 0, 1, 1, 1, 1, '2026-01-12 07:52:00', '2026-04-30 09:10:00'),
    (9, 9, 1, 0, 1, 1, 1, 1, '2026-01-12 07:55:00', '2026-04-30 09:10:00'),
    (10, 10, 1, 0, 1, 1, 1, 1, '2026-01-12 07:57:00', '2026-04-30 09:10:00'),
    (11, 11, 1, 0, 1, 1, 1, 1, '2026-01-12 07:59:00', '2026-04-30 09:10:00'),
    (12, 12, 1, 0, 1, 1, 1, 1, '2026-04-27 13:10:00', '2026-04-30 09:10:00'),
    (13, 13, 1, 0, 1, 1, 1, 1, '2026-04-27 13:10:00', '2026-04-30 09:10:00');

INSERT INTO sports (id, name, created_at, updated_at) VALUES
    (1, 'Basketball', '2026-01-15 09:00:00', '2026-01-15 09:00:00'),
    (2, 'Volleyball', '2026-01-15 09:00:00', '2026-01-15 09:00:00'),
    (3, 'Badminton', '2026-01-15 09:00:00', '2026-01-15 09:00:00'),
    (4, 'Track and Field', '2026-01-15 09:00:00', '2026-01-15 09:00:00');

INSERT INTO teams (
    id, team_name, team_avatar, sport_id, year, description, archived_at, archived_by, created_at, updated_at
) VALUES
    (1, 'Blue Hawks', 'teams/blue-hawks.png', 1, 2026, 'Senior high varsity basketball roster for the 2026 competitive season.', NULL, NULL, '2026-02-01 09:00:00', '2026-04-30 09:10:00'),
    (2, 'Lady Falcons', 'teams/lady-falcons.png', 2, 2026, 'Women''s varsity volleyball training and competition roster.', NULL, NULL, '2026-02-01 09:10:00', '2026-04-30 09:10:00'),
    (3, 'Red Comets', 'teams/red-comets.png', 3, 2025, 'Archived badminton roster retained for reporting and historical review.', '2026-03-25 16:00:00', 1, '2025-06-10 10:00:00', '2026-03-25 16:00:00');

INSERT INTO team_staff_assignments (
    id, team_id, coach_id, role, starts_at, ends_at, created_by, created_at, updated_at
) VALUES
    (1, 1, 1, 'head', '2026-02-01 09:05:00', NULL, 1, '2026-02-01 09:05:00', '2026-04-30 09:10:00'),
    (2, 1, 2, 'assistant', '2026-02-01 09:05:00', NULL, 1, '2026-02-01 09:05:00', '2026-04-30 09:10:00'),
    (3, 2, 3, 'head', '2026-02-01 09:15:00', NULL, 1, '2026-02-01 09:15:00', '2026-04-30 09:10:00'),
    (4, 3, 2, 'head', '2025-06-10 10:05:00', NULL, 1, '2025-06-10 10:05:00', '2026-03-25 16:00:00');

INSERT INTO team_players (
    id, team_id, student_id, jersey_number, athlete_position, player_status,
    manual_inactive, created_at, updated_at
) VALUES
    (1, 1, 1, '7', 'Point Guard', 'active', 0, '2026-02-02 08:00:00', '2026-04-30 09:10:00'),
    (2, 1, 2, '12', 'Shooting Guard', 'active', 0, '2026-02-02 08:00:00', '2026-04-30 09:10:00'),
    (3, 1, 5, '10', 'Small Forward', 'active', 0, '2026-02-02 08:05:00', '2026-04-30 09:10:00'),
    (4, 1, 6, '15', 'Center', 'inactive', 1, '2026-02-02 08:05:00', '2026-04-30 09:10:00'),
    (5, 2, 3, '3', 'Setter', 'active', 0, '2026-02-02 08:10:00', '2026-04-30 09:10:00'),
    (6, 2, 4, '9', 'Outside Hitter', 'active', 0, '2026-02-02 08:10:00', '2026-04-30 09:10:00'),
    (7, 3, 6, '2', 'Singles', 'active', 0, '2025-06-10 10:15:00', '2026-03-25 16:00:00');

INSERT INTO team_schedules (
    id, team_id, title, type, venue, start_time, end_time, notes, created_at, updated_at
) VALUES
    (1, 1, 'Blue Hawks Conditioning Practice', 'practice', 'AC Gymnasium', '2026-04-28 16:00:00', '2026-04-28 18:00:00', 'Focus on transition drills and conditioning.', '2026-04-20 09:00:00', '2026-04-20 09:00:00'),
    (2, 1, 'Blue Hawks Scrimmage vs Alumni', 'game', 'Main Covered Court', '2026-04-29 17:00:00', '2026-04-29 19:00:00', 'Internal tune-up game before regional qualifiers.', '2026-04-20 09:10:00', '2026-04-20 09:10:00'),
    (3, 1, 'Blue Hawks Tactical Session', 'practice', 'AC Gymnasium', '2026-05-02 15:30:00', '2026-05-02 17:30:00', 'Half-court offensive sets and defensive rotations.', '2026-04-20 09:15:00', '2026-04-20 09:15:00'),
    (4, 1, 'Blue Hawks Travel Walkthrough', 'practice', 'Room 204', '2026-05-04 16:00:00', '2026-05-04 17:00:00', 'Travel and eligibility reminders for the next week.', '2026-04-20 09:20:00', '2026-04-20 09:20:00'),
    (5, 2, 'Lady Falcons Serve Receive Practice', 'practice', 'Volleyball Court A', '2026-04-27 15:00:00', '2026-04-27 17:00:00', 'Passing patterns and libero coverage.', '2026-04-18 08:30:00', '2026-04-18 08:30:00'),
    (6, 2, 'Lady Falcons Match Prep', 'practice', 'Athletics Office', '2026-05-01 14:00:00', '2026-05-01 15:00:00', 'Opponent scouting review and rotation plan.', '2026-04-18 08:35:00', '2026-04-18 08:35:00'),
    (7, 3, 'Red Comets Season Wrap-Up', 'practice', 'Club Room', '2025-11-20 14:00:00', '2025-11-20 15:30:00', 'Archived team closeout and equipment turnover.', '2025-11-10 09:00:00', '2025-11-10 09:00:00');

INSERT INTO schedule_attendances (
    id, schedule_id, student_id, status, verification_method, recorded_by,
    recorded_at, verified_at, notes, override_reason, created_at, updated_at
) VALUES
    (1, 1, 1, 'present', 'manual_override', 3, '2026-04-28 18:10:00', '2026-04-28 18:15:00', 'Completed full practice workload.', NULL, '2026-04-28 18:10:00', '2026-04-28 18:15:00'),
    (2, 1, 2, 'late', 'manual_override', 3, '2026-04-28 18:10:00', '2026-04-28 18:15:00', 'Arrived after class dismissal.', NULL, '2026-04-28 18:10:00', '2026-04-28 18:15:00'),
    (3, 1, 5, 'excused', 'manual_override', 3, '2026-04-28 18:10:00', '2026-04-28 18:15:00', 'Light participation only due to ankle pain.', 'Medical rest from trainer observation.', '2026-04-28 18:10:00', '2026-04-28 18:15:00'),
    (4, 1, 6, 'absent', 'manual_override', 3, '2026-04-28 18:10:00', '2026-04-28 18:15:00', 'Did not report to session.', 'Roster marked inactive for current week.', '2026-04-28 18:10:00', '2026-04-28 18:15:00'),
    (5, 5, 3, 'present', 'manual_override', 5, '2026-04-27 17:15:00', '2026-04-27 17:18:00', 'Completed reception drills.', NULL, '2026-04-27 17:15:00', '2026-04-27 17:18:00'),
    (6, 5, 4, 'present', 'manual_override', 5, '2026-04-27 17:15:00', '2026-04-27 17:18:00', 'No attendance issues recorded.', NULL, '2026-04-27 17:15:00', '2026-04-27 17:18:00');

INSERT INTO academic_periods (
    id, school_year, term, starts_on, ends_on, created_at, updated_at
) VALUES
    (1, '2025-2026', '2nd_sem', '2025-11-10', '2026-03-20', '2025-11-01 08:00:00', '2026-03-20 18:00:00'),
    (2, '2026-2027', '1st_sem', '2026-04-26', '2026-05-05', '2026-04-20 09:00:00', '2026-04-30 09:10:00'),
    (3, '2026-2027', '2nd_sem', '2026-09-10', '2026-09-20', '2026-04-25 10:00:00', '2026-04-25 10:00:00');

INSERT INTO academic_document_types (id, context, code, label) VALUES
    (1, 'registration', 'tor', 'Transcript of Records'),
    (2, 'registration', 'supporting_document', 'Supporting Document'),
    (3, 'period_submission', 'grade_report', 'Grade Report'),
    (4, 'period_submission', 'supporting_document', 'Supporting Document');

INSERT INTO academic_documents (
    id, student_id, academic_period_id, file_path, uploaded_by, uploaded_at, notes,
    created_at, updated_at, document_type_id, review_status, reviewed_by, reviewed_at
) VALUES
    (1, 1, NULL, 'academic/registration/juan-cruz-tor.pdf', 6, '2026-01-12 08:30:00', 'Initial registration transcript copy.', '2026-01-12 08:30:00', '2026-01-12 08:30:00', 1, 'reviewed', 1, '2026-01-13 09:00:00'),
    (2, 2, NULL, 'academic/registration/miguel-lopez-tor.pdf', 7, '2026-01-12 08:35:00', 'Registrar-certified transcript submitted during onboarding.', '2026-01-12 08:35:00', '2026-01-12 08:35:00', 1, 'reviewed', 1, '2026-01-13 09:05:00'),
    (3, 3, NULL, 'academic/registration/carla-mendoza-tor.pdf', 8, '2026-01-12 08:40:00', 'Registration academic document set.', '2026-01-12 08:40:00', '2026-01-12 08:40:00', 1, 'reviewed', 1, '2026-01-13 09:10:00'),
    (4, 4, NULL, 'academic/registration/angela-torres-support.pdf', 9, '2026-01-12 08:45:00', 'Supporting document for transfer evaluation.', '2026-01-12 08:45:00', '2026-01-12 08:45:00', 2, 'reviewed', 2, '2026-01-13 09:20:00'),
    (5, 1, 2, 'academic/submissions/2026-2027-1stsem-juan-cruz-grade-report.jpg', 6, '2026-04-27 14:10:00', 'Uploaded from mobile device after receiving report card.', '2026-04-27 14:10:00', '2026-04-27 14:20:00', 3, 'auto_processed', NULL, NULL),
    (6, 2, 2, 'academic/submissions/2026-2027-1stsem-miguel-lopez-grade-report.pdf', 7, '2026-04-27 14:20:00', 'PDF export from school portal.', '2026-04-27 14:20:00', '2026-04-27 14:40:00', 3, 'reviewed', 1, '2026-04-28 08:30:00'),
    (7, 3, 2, 'academic/submissions/2026-2027-1stsem-carla-mendoza-grade-report.jpg', 8, '2026-04-28 09:00:00', 'Image capture with visible general average.', '2026-04-28 09:00:00', '2026-04-28 09:15:00', 3, 'auto_processed', NULL, NULL),
    (8, 4, 2, 'academic/submissions/2026-2027-1stsem-angela-torres-grade-report.jpg', 9, '2026-04-28 09:10:00', 'Low-light upload requiring manual verification.', '2026-04-28 09:10:00', '2026-04-28 09:25:00', 3, 'needs_review', NULL, NULL),
    (9, 5, 2, 'academic/submissions/2026-2027-1stsem-nicole-garcia-grade-report.pdf', 10, '2026-04-29 10:15:00', 'College grade summary with unit load.', '2026-04-29 10:15:00', '2026-04-29 10:30:00', 3, 'reviewed', 2, '2026-04-29 14:00:00'),
    (10, 6, 1, 'academic/submissions/2025-2026-2ndsem-paula-villanueva-grade-report.pdf', 11, '2026-03-10 15:00:00', 'Previous term historical submission retained for reference.', '2026-03-10 15:00:00', '2026-03-10 16:00:00', 3, 'reviewed', 1, '2026-03-11 08:00:00');

INSERT INTO academic_document_ocr_runs (
    id, academic_document_id, ocr_engine, ocr_engine_version, run_status, raw_text,
    mean_confidence, validation_status, validation_summary, validation_flags,
    validation_checked_at, processed_at, error_message, created_at, updated_at
) VALUES
    (1, 5, 'tesseract', '5.4.0', 'processed', 'General Average 86.00', 93.40, 'valid', 'Average and period details matched successfully.', '[]', '2026-04-27 14:19:00', '2026-04-27 14:18:00', NULL, '2026-04-27 14:10:30', '2026-04-27 14:19:00'),
    (2, 6, 'tesseract', '5.4.0', 'processed', 'GPA 2.25', 95.10, 'valid', 'OCR output matched expected college grading structure.', '[]', '2026-04-27 14:38:00', '2026-04-27 14:35:00', NULL, '2026-04-27 14:20:30', '2026-04-27 14:38:00'),
    (3, 7, 'tesseract', '5.4.0', 'processed', 'General Average 91.00', 91.70, 'valid', 'Document quality acceptable and values extracted.', '[]', '2026-04-28 09:12:00', '2026-04-28 09:10:00', NULL, '2026-04-28 09:00:20', '2026-04-28 09:12:00'),
    (4, 8, 'tesseract', '5.4.0', 'needs_review', 'Average unreadable due to shadowed photo edges.', 63.20, 'manual_review', 'Automatic scan found likely grade data but confidence was too low.', '["low_confidence","blur_detected"]', '2026-04-28 09:22:00', '2026-04-28 09:20:00', NULL, '2026-04-28 09:10:20', '2026-04-28 09:22:00'),
    (5, 9, 'tesseract', '5.4.0', 'processed', 'GPA 3.40', 92.60, 'valid', 'Values extracted successfully but academic result still requires policy review.', '[]', '2026-04-29 10:28:00', '2026-04-29 10:26:00', NULL, '2026-04-29 10:15:20', '2026-04-29 10:28:00');

INSERT INTO academic_document_parsed_summaries (
    id, academic_document_ocr_run_id, gwa, total_units, parser_status,
    parser_confidence, created_at, updated_at
) VALUES
    (1, 1, 86.00, NULL, 'parsed', 94.10, '2026-04-27 14:18:30', '2026-04-27 14:18:30'),
    (2, 2, 2.25, 24.00, 'parsed', 95.50, '2026-04-27 14:36:00', '2026-04-27 14:36:00'),
    (3, 3, 91.00, NULL, 'parsed', 92.00, '2026-04-28 09:11:00', '2026-04-28 09:11:00'),
    (4, 4, 78.00, NULL, 'needs_review', 62.00, '2026-04-28 09:21:00', '2026-04-28 09:21:00'),
    (5, 5, 3.40, 21.00, 'parsed', 91.80, '2026-04-29 10:27:00', '2026-04-29 10:27:00');

INSERT INTO academic_eligibility_evaluations (
    id, student_id, academic_period_id, document_id, gpa, evaluated_by,
    evaluated_at, remarks, created_at, updated_at, academic_document_ocr_run_id,
    evaluation_source, final_status, review_required
) VALUES
    (1, 1, 2, 5, 86.00, 1, '2026-04-27 14:25:00', 'Basic education average satisfies eligibility threshold.', '2026-04-27 14:25:00', '2026-04-27 14:25:00', 1, 'rule_based', 'eligible', 0),
    (2, 2, 2, 6, 2.25, 1, '2026-04-28 08:35:00', 'College GPA is within eligible range.', '2026-04-28 08:35:00', '2026-04-28 08:35:00', 2, 'rule_based_reviewed', 'eligible', 0),
    (3, 3, 2, 7, 91.00, 1, '2026-04-28 09:18:00', 'Strong average extracted from grade report.', '2026-04-28 09:18:00', '2026-04-28 09:18:00', 3, 'rule_based', 'eligible', 0),
    (4, 4, 2, 8, 78.00, 1, '2026-04-28 10:00:00', 'OCR requires manual review before final athletic eligibility confirmation.', '2026-04-28 10:00:00', '2026-04-28 10:00:00', 4, 'rule_based', 'pending_review', 1),
    (5, 5, 2, 9, 3.40, 2, '2026-04-29 14:05:00', 'College GPA falls into review-required range.', '2026-04-29 14:05:00', '2026-04-29 14:05:00', 5, 'rule_based_reviewed', 'pending_review', 1),
    (6, 6, 1, 10, 2.80, 1, '2026-03-11 08:10:00', 'Previous term eligibility archived as historical record.', '2026-03-11 08:10:00', '2026-03-11 08:10:00', NULL, 'manual', 'eligible', 0);

INSERT INTO announcement_events (
    id, title, message, type, published_at, created_by, created_at, updated_at
) VALUES
    (1, 'Academic Submission Window Open', 'The 2026-2027 1st semester academic submission window is now open for all approved student-athletes.', 'academic', '2026-04-26 08:00:00', 1, '2026-04-26 08:00:00', '2026-04-26 08:00:00'),
    (2, 'Attendance Reminder', 'Coaches must post attendance within the same day after each completed session.', 'schedule', '2026-04-28 07:30:00', 2, '2026-04-28 07:30:00', '2026-04-28 07:30:00'),
    (3, 'System Maintenance Notice', 'The portal will be briefly unavailable on May 3 from 10:00 PM to 10:30 PM for routine maintenance.', 'system', '2026-04-29 17:00:00', 1, '2026-04-29 17:00:00', '2026-04-29 17:00:00');

INSERT INTO announcement_recipients (
    id, event_id, user_id, read_at, created_at, updated_at
) VALUES
    (1, 1, 6, '2026-04-26 08:30:00', '2026-04-26 08:00:00', '2026-04-26 08:30:00'),
    (2, 1, 7, NULL, '2026-04-26 08:00:00', '2026-04-26 08:00:00'),
    (3, 1, 8, '2026-04-26 09:10:00', '2026-04-26 08:00:00', '2026-04-26 09:10:00'),
    (4, 1, 9, NULL, '2026-04-26 08:00:00', '2026-04-26 08:00:00'),
    (5, 1, 10, NULL, '2026-04-26 08:00:00', '2026-04-26 08:00:00'),
    (6, 1, 11, '2026-04-26 08:40:00', '2026-04-26 08:00:00', '2026-04-26 08:40:00'),
    (7, 2, 3, '2026-04-28 08:00:00', '2026-04-28 07:30:00', '2026-04-28 08:00:00'),
    (8, 2, 5, NULL, '2026-04-28 07:30:00', '2026-04-28 07:30:00'),
    (9, 3, 1, NULL, '2026-04-29 17:00:00', '2026-04-29 17:00:00'),
    (10, 3, 2, NULL, '2026-04-29 17:00:00', '2026-04-29 17:00:00'),
    (11, 3, 3, NULL, '2026-04-29 17:00:00', '2026-04-29 17:00:00'),
    (12, 3, 6, NULL, '2026-04-29 17:00:00', '2026-04-29 17:00:00');

INSERT INTO student_approval_histories (
    id, student_id, admin_id, decision, remarks, created_at, updated_at
) VALUES
    (1, 1, 1, 'approved', 'Verified registration details and transcript submission.', '2026-01-13 09:00:00', '2026-01-13 09:00:00'),
    (2, 2, 1, 'approved', 'Approved for varsity onboarding.', '2026-01-13 09:05:00', '2026-01-13 09:05:00'),
    (3, 3, 1, 'approved', 'School credentials complete.', '2026-01-13 09:10:00', '2026-01-13 09:10:00'),
    (4, 4, 2, 'approved', 'Supporting document reviewed and accepted.', '2026-01-13 09:20:00', '2026-01-13 09:20:00'),
    (5, 8, 2, 'rejected', 'Submitted school credentials were incomplete. Resubmission required.', '2026-04-28 11:00:00', '2026-04-28 11:00:00');

INSERT INTO account_action_logs (
    id, user_id, admin_id, action, remarks, created_at, updated_at
) VALUES
    (1, 6, 1, 'student_approved', 'Initial student-athlete approval completed.', '2026-01-13 09:00:00', '2026-01-13 09:00:00'),
    (2, 9, 2, 'supporting_document_reviewed', 'Transfer-related academic support file accepted.', '2026-01-13 09:20:00', '2026-01-13 09:20:00'),
    (3, 13, 2, 'student_rejected', 'Registration rejected pending complete academic requirements.', '2026-04-28 11:00:00', '2026-04-28 11:00:00'),
    (4, 10, 1, 'academic_submission_window_opened', 'Student access is now computed from the active period submission and evaluation result.', '2026-04-26 08:05:00', '2026-04-26 08:05:00'),
    (5, 10, 2, 'academic_review_flagged', 'Eligibility needs manual academic review.', '2026-04-29 14:05:00', '2026-04-29 14:05:00');

INSERT INTO admin_invites (
    id, email, token_hash, created_by, expires_at, used_at, created_at, updated_at
) VALUES
    (1, 'new.admin@ac.edu.ph', '13b4b5f1f6dca9c94d2a8b8dd5ef4f73c4b8585bd74b4608d95fa6a85f6b1c21', 1, '2026-05-07 18:00:00', NULL, '2026-04-30 09:00:00', '2026-04-30 09:00:00');

SET FOREIGN_KEY_CHECKS = 1;
