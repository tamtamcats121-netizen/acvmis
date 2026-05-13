# AC-VMIS Coach-Centered Workflow

## Overview

AC-VMIS now uses a coach-centered operational workflow:

- Students register under a supported sport.
- Coaches review student applications for their own sport.
- Coaches create teams only within their assigned sport.
- Admin manages oversight, records, logs, coach accounts, and monitoring.

## Supported Sports

Student-athlete registration and coach assignment are limited to:

- Basketball
- Soccer
- Volleyball

## Student Registration Flow

1. A student-athlete registers and selects `Sport Applying For`.
2. The registration is stored with `approval_status = pending`.
3. Coaches assigned to the same sport receive the student application.
4. The coach reviews the student profile and required registration documents.
5. The coach approves or rejects the application.
6. Once approved, the student-athlete becomes eligible for team assignment only within that same sport.

## Coach Responsibilities

Coaches now handle the operational decisions that depend on tryouts and roster fit:

- Review student-athlete applications under their assigned sport
- Approve or reject student-athletes for that sport
- Create teams under their assigned sport only
- Add only approved, same-sport student-athletes who are not already assigned to another active team
- Archive or reactivate their teams when needed

## Admin Responsibilities

Admins now focus on institutional oversight:

- Create coach accounts
- Assign each coach to one supported sport
- View user records and account status
- Monitor created teams, rosters, sport assignments, and archive status
- Review logs, reports, announcements, and system activity
- Archive or reactivate teams for oversight purposes

Admins no longer handle:

- Student-athlete approval decisions
- Team creation
- Direct roster building

## Team Assignment Rules

When coaches add players to a team, the system should only show student-athletes who are:

- approved
- assigned to the same sport as the coach and team
- not already assigned to another active team

## UI Summary

- `Student`: register by sport and wait for coach review
- `Coach`: approve students by sport and create/manage teams by sport
- `Admin`: monitor records, teams, logs, and system-wide activity
