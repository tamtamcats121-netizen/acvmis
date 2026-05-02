export type CoachNavItem = {
  key: string
  label: string
  route: string
  icon: string
  mobileLabel?: string
}

export const coachPrimaryNav: CoachNavItem[] = [
  { key: 'dashboard', label: 'Dashboard', route: '/coach/dashboard', icon: 'layout-grid', mobileLabel: 'Home' },
  { key: 'team', label: 'My Team', route: '/coach/team', icon: 'users', mobileLabel: 'Team' },
  { key: 'schedule', label: 'Schedule & Attendance', route: '/coach/schedule', icon: 'calendar', mobileLabel: 'Schedule' },
  { key: 'wellness', label: 'Performance', route: '/coach/wellness', icon: 'heart-pulse', mobileLabel: 'Performance' },
  { key: 'academics', label: 'Academics', route: '/coach/academics', icon: 'graduation-cap', mobileLabel: 'Academics' },
]

export const coachSecondaryNav: CoachNavItem[] = []
