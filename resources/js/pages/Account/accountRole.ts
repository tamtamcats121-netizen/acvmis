import { coachPrimaryNav } from '@/config/coachNav'
import { studentPrimaryNav } from '@/config/studentNav'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'


export type WorkspaceRole = 'admin' | 'coach' | 'student'

export function normalizeWorkspaceRole(rawRole: unknown): WorkspaceRole {
    const role = String(rawRole ?? '').trim().toLowerCase()

    if (role === 'admin') return 'admin'
    if (role === 'coach') return 'coach'
    if (role === 'student' || role === 'student-athlete') return 'student'

    return 'student'
}

export function resolveAccountLayout(page: any) {
    const role = normalizeWorkspaceRole(page?.props?.auth?.user?.role)

    if (role === 'admin') return AdminDashboard
    if (role === 'coach') return CoachDashboard
    return StudentAthleteDashboard
}

export function workspaceNavigationPreview(rawRole: unknown): string[] {
    const role = normalizeWorkspaceRole(rawRole)

    if (role === 'admin') {
        return ['Dashboard', 'Users', 'Teams', 'Operations', 'Academics']
    }

    if (role === 'coach') {
        return coachPrimaryNav.map((item) => item.label)
    }

    return studentPrimaryNav.map((item) => item.label)
}
