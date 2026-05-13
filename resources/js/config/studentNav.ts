export type StudentNavItem = {
  key: string
  label: string
  route: string
  icon: string
  mobileLabel?: string
}

export const studentPrimaryNav: StudentNavItem[] = [
  { key: 'dashboard', label: 'Dashboard', route: '/StudentAthleteDashboard', icon: 'layout-grid', mobileLabel: 'Dashboard' },
  { key: 'team', label: 'Team', route: '/MyTeam', icon: 'users', mobileLabel: 'Team' },
  { key: 'join-team', label: 'Join Team', route: '/join-team', icon: 'plus-circle', mobileLabel: 'Join' },
  { key: 'schedule', label: 'Schedule', route: '/MySchedule', icon: 'calendar', mobileLabel: 'Schedule' },
  { key: 'academics', label: 'Academics', route: '/AcademicSubmissions', icon: 'graduation-cap', mobileLabel: 'Academics' },
  { key: 'documents', label: 'My Documents', route: '/documents/my', icon: 'file-text', mobileLabel: 'Documents' },
]

export const studentSecondaryNav: StudentNavItem[] = [
  { key: 'help', label: 'Help & Support', route: '/account/help', icon: 'help' },
  { key: 'settings', label: 'Settings', route: '/account/settings', icon: 'settings' },
]
