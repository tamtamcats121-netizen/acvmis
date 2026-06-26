export type CoachNavItem = {
  key: string
  label: string
  route: string
  icon: string
  mobileLabel?: string
}

export const coachPrimaryNav: CoachNavItem[] = [
  { key: 'dashboard', label: 'Dashboard', route: '/coach/dashboard', icon: 'layout-grid', mobileLabel: 'Dashboard' },
  { key: 'applications', label: 'Student Applications', route: '/coach/applications', icon: 'clipboard-check', mobileLabel: 'Applications' },
  { key: 'team', label: 'My Team', route: '/coach/team', icon: 'users', mobileLabel: 'Team' },
  { key: 'schedule', label: 'Schedule & Attendance', route: '/coach/schedule', icon: 'calendar', mobileLabel: 'Schedule' },
  { key: 'documents', label: 'Team Documents', route: '/coach/documents', icon: 'file-text', mobileLabel: 'Documents' },
]

export const coachSecondaryNav: CoachNavItem[] = []
