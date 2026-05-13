<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import { useTheme } from '@/composables/useTheme'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import { resolveUserAvatarUrl } from '@/utils/media'

defineOptions({
    layout: AdminDashboard,
})

type TeamAssignment = {
    id: number
    name: string
    sport_name: string
    year: string | null
    role_label: string
    position: string | null
    status: string | null
    archived: boolean
}

type UserProfile = {
    id: number
    name: string
    email: string
    role: 'student-athlete' | 'student' | 'coach'
    status: 'active' | 'deactivated'
    avatar: string | null
    created_at: string | null
    assignment: {
        sport_label: string | null
        status_label: string
        current_teams: TeamAssignment[]
        history_teams: TeamAssignment[]
        needs_action: boolean
    }
    student: null | {
        student_id_number: string | null
        course_or_strand: string | null
        current_grade_level: string | null
        academic_level_label: string | null
        student_status: string | null
        approval_status: string | null
        applied_sport: string | null
        phone_number: string | null
        emergency_contact_name: string | null
        emergency_contact_relationship: string | null
        emergency_contact_phone: string | null
        date_of_birth: string | null
        gender: string | null
        height: string | null
        weight: string | null
    }
    coach: null | {
        coach_status: string | null
        sport: string | null
        phone_number: string | null
        date_of_birth: string | null
        gender: string | null
    }
}

const props = defineProps<{
    user: UserProfile
}>()

const { isDarkMode } = useTheme()
const pageTitle = computed(() => `${props.user.name} Profile`)
const activitySummary = computed(() => [
    { label: 'Current Teams', value: String(props.user.assignment.current_teams.length) },
    { label: 'History Teams', value: String(props.user.assignment.history_teams.length) },
    { label: 'Assignment State', value: props.user.assignment.needs_action ? 'Needs Assignment' : 'Assigned' },
    { label: 'Joined', value: formatDateTime(props.user.created_at) },
])

function userInitials() {
    return String(props.user.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'U'
}

function formatRole(role: UserProfile['role']) {
    return role === 'coach' ? 'Coach' : 'Student Athlete'
}

function formatDateTime(value?: string | null) {
    if (!value) return '-'
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return '-'
    return parsed.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function formatSimple(value?: string | null) {
    return value?.trim() ? value : '-'
}

function accountTone() {
    return props.user.status === 'active'
        ? 'border border-[#4a90e2]/35 bg-[#e8f2ff] text-[#034485] dark:border-sky-400/40 dark:bg-sky-500/15 dark:text-sky-100'
        : 'border border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100'
}

function roleTone() {
    return props.user.role === 'coach'
        ? 'border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/35 dark:bg-sky-500/15 dark:text-sky-100'
        : 'border border-[#034485]/20 bg-[#edf4ff] text-[#034485] dark:border-[#4a90e2]/35 dark:bg-[#0a2f57] dark:text-sky-100'
}

function assignmentTone() {
    return props.user.assignment.needs_action
        ? 'border border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100'
        : 'border border-white/20 bg-white/10 text-white'
}

function teamHref(teamId: number) {
    return `/teams/${teamId}/view-roster`
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <Link
                href="/people"
                class="inline-flex items-center rounded-xl border px-3 py-2 text-sm font-semibold transition"
                :class="isDarkMode ? 'border-[#4a90e2]/35 bg-slate-900 text-white hover:border-sky-300/45 hover:bg-[#0a2f57]' : 'border-[#034485]/20 bg-white text-[#034485] hover:bg-[#eef5ff]'"
            >
                Back To Directory
            </Link>
        </div>

        <section class="rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">View Profile</p>
            <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-3xl border border-white/20 bg-white/10 text-lg font-bold">
                        <img
                            v-if="user.avatar"
                            :src="resolveUserAvatarUrl(user.avatar)"
                            :alt="`${user.name} avatar`"
                            class="h-full w-full object-cover"
                        />
                        <span v-else>{{ userInitials() }}</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">{{ user.name }}</h1>
                        <p class="mt-1 text-sm text-white/85">{{ user.email }}</p>
                        <p class="mt-1 text-sm text-white/75">Joined {{ formatDateTime(user.created_at) }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="accountTone()">
                        {{ user.status }}
                    </span>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="roleTone()">
                        {{ formatRole(user.role) }}
                    </span>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="assignmentTone()">
                        {{ user.assignment.needs_action ? 'Needs assignment' : user.assignment.status_label }}
                    </span>
                </div>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-4">
            <article class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">Role</p>
                <p class="mt-2 text-lg font-bold">{{ formatRole(user.role) }}</p>
            </article>
            <article class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">Account Status</p>
                <p class="mt-2 text-lg font-bold capitalize">{{ user.status }}</p>
            </article>
            <article class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">Sport</p>
                <p class="mt-2 text-lg font-bold">{{ user.assignment.sport_label || 'Not set' }}</p>
            </article>
            <article class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">Current Assignment</p>
                <p class="mt-2 text-lg font-bold">{{ user.assignment.status_label }}</p>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <div class="space-y-5">
                <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                    <h2 class="text-lg font-semibold">Account Overview</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Full Name</p><p class="mt-1 text-sm font-medium">{{ user.name }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Email Address</p><p class="mt-1 text-sm font-medium break-all">{{ user.email }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Account Role</p><p class="mt-1 text-sm font-medium">{{ formatRole(user.role) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Member Since</p><p class="mt-1 text-sm font-medium">{{ formatDateTime(user.created_at) }}</p></div>
                    </div>
                </section>

                <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                    <h2 class="text-lg font-semibold">Team And Sport Assignment</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">Current Teams</p>
                            <div class="mt-3 space-y-3">
                                <div
                                    v-for="team in user.assignment.current_teams"
                                    :key="`current-${team.id}-${team.role_label}`"
                                    class="rounded-2xl border p-3"
                                    :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/15 bg-[#f7fbff]'"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold">{{ team.name }}</p>
                                            <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-100' : 'text-slate-600'">{{ team.sport_name }}{{ team.year ? ` • ${team.year}` : '' }}</p>
                                            <p class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">{{ team.role_label }}<span v-if="team.position"> • {{ team.position }}</span></p>
                                        </div>
                                        <Link :href="teamHref(team.id)" class="text-xs font-semibold text-[#034485] dark:text-sky-300">
                                            View Team
                                        </Link>
                                    </div>
                                </div>
                                <p v-if="!user.assignment.current_teams.length" class="text-sm" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">
                                    No active team assignment.
                                </p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">History</p>
                            <div class="mt-3 space-y-3">
                                <div
                                    v-for="team in user.assignment.history_teams"
                                    :key="`history-${team.id}-${team.role_label}-${team.status}`"
                                    class="rounded-2xl border p-3"
                                    :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-slate-50'"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold">{{ team.name }}</p>
                                            <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-100' : 'text-slate-600'">{{ team.sport_name }}{{ team.year ? ` • ${team.year}` : '' }}</p>
                                            <p class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">{{ team.role_label }}</p>
                                        </div>
                                        <Link :href="teamHref(team.id)" class="text-xs font-semibold text-[#034485] dark:text-sky-300">
                                            Open Team
                                        </Link>
                                    </div>
                                </div>
                                <p v-if="!user.assignment.history_teams.length" class="text-sm" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">
                                    No archived or historical team links recorded.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-if="user.student"
                    class="rounded-3xl border p-5"
                    :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'"
                >
                    <h2 class="text-lg font-semibold">Student Profile Details</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Student ID</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.student_id_number) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Applied Sport</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.applied_sport) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Course / Strand</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.course_or_strand) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Grade Level</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.academic_level_label || user.student.current_grade_level) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Student Status</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.student_status) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Approval Status</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.approval_status) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Date Of Birth</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.date_of_birth) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Height / Weight</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.height) }} / {{ formatSimple(user.student.weight) }}</p></div>
                    </div>
                </section>

                <section
                    v-if="user.coach"
                    class="rounded-3xl border p-5"
                    :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'"
                >
                    <h2 class="text-lg font-semibold">Coach Profile Details</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Assigned Sport</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.coach.sport) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Coach Status</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.coach.coach_status) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Date Of Birth</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.coach.date_of_birth) }}</p></div>
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Gender</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.coach.gender) }}</p></div>
                    </div>
                </section>
            </div>

            <div class="space-y-5">
                <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                    <h2 class="text-lg font-semibold">Contact Information</h2>
                    <div class="mt-4 grid gap-4">
                        <div><p class="text-xs text-slate-600 dark:text-slate-200">Primary Email</p><p class="mt-1 text-sm font-medium break-all">{{ user.email }}</p></div>
                        <div v-if="user.student"><p class="text-xs text-slate-600 dark:text-slate-200">Phone Number</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.phone_number) }}</p></div>
                        <div v-if="user.coach"><p class="text-xs text-slate-600 dark:text-slate-200">Phone Number</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.coach.phone_number) }}</p></div>
                        <template v-if="user.student">
                            <div><p class="text-xs text-slate-600 dark:text-slate-200">Emergency Contact</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.emergency_contact_name) }}</p></div>
                            <div><p class="text-xs text-slate-600 dark:text-slate-200">Relationship</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.emergency_contact_relationship) }}</p></div>
                            <div><p class="text-xs text-slate-600 dark:text-slate-200">Emergency Phone</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.emergency_contact_phone) }}</p></div>
                            <div><p class="text-xs text-slate-600 dark:text-slate-200">Gender</p><p class="mt-1 text-sm font-medium">{{ formatSimple(user.student.gender) }}</p></div>
                        </template>
                    </div>
                </section>

                <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                    <h2 class="text-lg font-semibold">Activity Summary</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="item in activitySummary"
                            :key="item.label"
                            class="rounded-2xl border px-4 py-3"
                            :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-slate-50'"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">{{ item.label }}</p>
                            <p class="mt-2 text-sm font-medium">{{ item.value }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </div>
</template>
