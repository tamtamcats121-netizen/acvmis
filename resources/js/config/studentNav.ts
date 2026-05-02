export type StudentNavItem = {
  key: string
  label: string
  route: string
  icon: string
  mobileLabel?: string
}

export const studentPrimaryNav: StudentNavItem[] = [
  { key: 'home', label: 'Home', route: '/StudentAthleteDashboard', icon: 'layout-grid', mobileLabel: 'Home' },
  { key: 'team', label: 'Team', route: '/MyTeam', icon: 'users', mobileLabel: 'Team' },
  { key: 'schedule', label: 'Schedule', route: '/MySchedule', icon: 'calendar', mobileLabel: 'Schedule' },
  { key: 'wellness', label: 'Performance', route: '/WellnessHistory', icon: 'heart-pulse', mobileLabel: 'Performance' },
  { key: 'academics', label: 'Academics', route: '/AcademicSubmissions', icon: 'graduation-cap', mobileLabel: 'Academics' },
]

export const studentSecondaryNav: StudentNavItem[] = [
  { key: 'help', label: 'Help & Support', route: '/account/help', icon: 'help' },
  { key: 'settings', label: 'Settings', route: '/account/settings', icon: 'settings' },
]
