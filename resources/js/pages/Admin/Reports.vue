<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type TeamOption = {
    id: number
    team_name: string
    sport_name: string
}

type AttendanceReportRow = {
    student_id: number
    student_id_number: string | null
    student_name: string
    team_name: string
    sport_name: string
    total_sessions: number
    present: number
    absent: number
    late: number
    excused: number
    no_response: number
    attendance_rate: number
}

type RosterReportRow = {
    student_id: number
    student_id_number: string | null
    student_name: string
    team_name: string
    sport_name: string
    year: string | null
    jersey_number: string
    athlete_position: string
    player_status: string
}

const props = defineProps<{
    filters: {
        selected: {
            period_id: number | null
            sport_id: number | null
            team_id: number | null
            status: string | null
            academic_status: string | null
            clearance_status: string | null
            player_status: string | null
            review_state: string | null
            year: string | null
            start_date: string | null
            end_date: string | null
        }
        options: {
            periods: { id: number; label: string }[]
            sports: { id: number; name: string }[]
            teams: TeamOption[]
            statuses: { value: string; label: string }[]
            academic_statuses: { value: string; label: string }[]
            clearance_statuses: { value: string; label: string }[]
            player_statuses: { value: string; label: string }[]
            review_states: { value: string; label: string }[]
            years: string[]
        }
    }
    attendanceReport: {
        summary: {
            total_records: number
            present: number
            absent: number
            late: number
            excused: number
            no_response: number
            attendance_rate: number
        }
        rows: AttendanceReportRow[]
    }
    rosterReport: {
        summary: {
            total_players: number
            active: number
            injured: number
            suspended: number
            jersey_pending: number
            position_pending: number
        }
        rows: RosterReportRow[]
    }
    academicReport: {
        summary: {
            total_submissions: number
            eligible: number
            pending_review: number
            ineligible: number
            pending: number
        }
        rows: Array<{
            document_id: number
            student_id: number
            student_name: string
            student_id_number: string | null
            team_name: string
            document_type: string
            uploaded_at: string | null
            period_label: string
            academic_status: string
            gpa: string
        }>
    }
    healthReport: {
        summary: {
            total_records: number
            fit: number
            fit_with_restrictions: number
            not_fit: number
            expired: number
            reviewed: number
        }
        rows: Array<{
            id: number
            student_name: string
            student_id_number: string | null
            team_name: string
            clearance_date: string | null
            valid_until: string | null
            physician_name: string
            clearance_status: string
            review_state: string
            reviewed_by: string
        }>
    }
}>()

const form = reactive({
    period_id: props.filters.selected.period_id ? String(props.filters.selected.period_id) : '',
    sport_id: props.filters.selected.sport_id ? String(props.filters.selected.sport_id) : '',
    team_id: props.filters.selected.team_id ? String(props.filters.selected.team_id) : '',
    status: props.filters.selected.status ?? '',
    academic_status: props.filters.selected.academic_status ?? '',
    clearance_status: props.filters.selected.clearance_status ?? '',
    player_status: props.filters.selected.player_status ?? '',
    review_state: props.filters.selected.review_state ?? '',
    year: props.filters.selected.year ?? '',
    start_date: props.filters.selected.start_date ?? '',
    end_date: props.filters.selected.end_date ?? '',
})

const queryString = computed(() => {
    const params = new URLSearchParams()

    if (form.period_id) params.set('period_id', form.period_id)
    if (form.sport_id) params.set('sport_id', form.sport_id)
    if (form.team_id) params.set('team_id', form.team_id)
    if (form.status) params.set('status', form.status)
    if (form.academic_status) params.set('academic_status', form.academic_status)
    if (form.clearance_status) params.set('clearance_status', form.clearance_status)
    if (form.player_status) params.set('player_status', form.player_status)
    if (form.review_state) params.set('review_state', form.review_state)
    if (form.year) params.set('year', form.year)
    if (form.start_date) params.set('start_date', form.start_date)
    if (form.end_date) params.set('end_date', form.end_date)

    const query = params.toString()
    return query ? `?${query}` : ''
})

function applyFilters() {
    router.get('/reports', {
        period_id: form.period_id || undefined,
        sport_id: form.sport_id || undefined,
        team_id: form.team_id || undefined,
        status: form.status || undefined,
        academic_status: form.academic_status || undefined,
        clearance_status: form.clearance_status || undefined,
        player_status: form.player_status || undefined,
        review_state: form.review_state || undefined,
        year: form.year || undefined,
        start_date: form.start_date || undefined,
        end_date: form.end_date || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    form.period_id = ''
    form.sport_id = ''
    form.team_id = ''
    form.status = ''
    form.academic_status = ''
    form.clearance_status = ''
    form.player_status = ''
    form.review_state = ''
    form.year = ''
    form.start_date = ''
    form.end_date = ''
    applyFilters()
}

function formatPercent(value: number) {
    return `${Number(value || 0).toFixed(2)}%`
}
</script>

<template>
    <Head title="Reports" />

    <div class="space-y-5">
        <section class="rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Reporting And Analytics</p>
                    <h1 class="mt-2 text-2xl font-bold">Reports Workspace</h1>
                    <p class="mt-2 text-sm text-white/85">Generate exportable attendance and roster reports for meetings, monitoring, and decision support.</p>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-[#034485]/35 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-7">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Period</label>
                    <select v-model="form.period_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Periods</option>
                        <option v-for="period in props.filters.options.periods" :key="period.id" :value="String(period.id)">
                            {{ period.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sport</label>
                    <select v-model="form.sport_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Sports</option>
                        <option v-for="sport in props.filters.options.sports" :key="sport.id" :value="String(sport.id)">
                            {{ sport.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Team</label>
                    <select v-model="form.team_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Teams</option>
                        <option v-for="team in props.filters.options.teams" :key="team.id" :value="String(team.id)">
                            {{ team.team_name }} ({{ team.sport_name }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Attendance Status</label>
                    <select v-model="form.status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Statuses</option>
                        <option v-for="status in props.filters.options.statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Player Status</label>
                    <select v-model="form.player_status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Player Statuses</option>
                        <option v-for="status in props.filters.options.player_statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Academic Status</label>
                    <select v-model="form.academic_status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Academic Statuses</option>
                        <option v-for="status in props.filters.options.academic_statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Clearance Status</label>
                    <select v-model="form.clearance_status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Clearance Statuses</option>
                        <option v-for="status in props.filters.options.clearance_statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
                    <select v-model="form.year" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Years</option>
                        <option v-for="year in props.filters.options.years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Review State</label>
                    <select v-model="form.review_state" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Review States</option>
                        <option v-for="state in props.filters.options.review_states" :key="state.value" :value="state.value">
                            {{ state.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Start Date</label>
                    <input v-model="form.start_date" type="date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">End Date</label>
                    <input v-model="form.end_date" type="date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    @click="applyFilters"
                >
                    Apply
                </button>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>
        </section>

        <section id="attendance-report" class="space-y-4 rounded-3xl border border-[#034485]/35 bg-white p-5 scroll-mt-24">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Attendance Summary Report</h2>
                    <p class="mt-1 text-sm text-slate-500">Per-student attendance totals based on the selected report filters.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        :href="`/reports/attendance/export.csv${queryString}`"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Export CSV
                    </a>
                    <a
                        :href="`/reports/attendance/print${queryString}`"
                        target="_blank"
                        rel="noreferrer"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Open PDF View
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                <article class="rounded-2xl border border-[#034485]/35 bg-white p-4">
                    <p class="text-xs text-slate-500">Attendance Rate</p>
                    <p class="mt-1 text-2xl font-bold text-[#034485]">{{ formatPercent(props.attendanceReport.summary.attendance_rate) }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Present</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ props.attendanceReport.summary.present }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Absent</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ props.attendanceReport.summary.absent }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Late</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ props.attendanceReport.summary.late }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Excused</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.attendanceReport.summary.excused }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">No Response</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.attendanceReport.summary.no_response }}</p>
                </article>
            </div>

            <div v-if="props.attendanceReport.rows.length === 0" class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-sm text-slate-500">
                No attendance data found for the selected filters.
            </div>

            <div v-else class="overflow-x-auto rounded-2xl border border-[#034485]/35">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <th class="px-5 py-4">Student</th>
                            <th class="px-5 py-4">Team</th>
                            <th class="px-5 py-4">Sessions</th>
                            <th class="px-5 py-4">Present</th>
                            <th class="px-5 py-4">Absent</th>
                            <th class="px-5 py-4">Late</th>
                            <th class="px-5 py-4">Excused</th>
                            <th class="px-5 py-4">No Response</th>
                            <th class="px-5 py-4">Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.attendanceReport.rows" :key="`${row.student_id}-${row.team_name}`" class="align-top">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ row.student_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.student_id_number || 'No student ID' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <p>{{ row.team_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.sport_name }}</p>
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-900">{{ row.total_sessions }}</td>
                            <td class="px-5 py-4 text-emerald-700">{{ row.present }}</td>
                            <td class="px-5 py-4 text-rose-700">{{ row.absent }}</td>
                            <td class="px-5 py-4 text-amber-700">{{ row.late }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.excused }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.no_response }}</td>
                            <td class="px-5 py-4 font-semibold text-[#034485]">{{ formatPercent(row.attendance_rate) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="roster-report" class="space-y-4 rounded-3xl border border-[#034485]/35 bg-white p-5 scroll-mt-24">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Team Roster Report</h2>
                    <p class="mt-1 text-sm text-slate-500">Structured roster view with jersey number, position, and player status.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        :href="`/reports/roster/export.csv${queryString}`"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Export CSV
                    </a>
                    <a
                        :href="`/reports/roster/print${queryString}`"
                        target="_blank"
                        rel="noreferrer"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Open PDF View
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                <article class="rounded-2xl border border-[#034485]/35 bg-white p-4">
                    <p class="text-xs text-slate-500">Total Players</p>
                    <p class="mt-1 text-2xl font-bold text-[#034485]">{{ props.rosterReport.summary.total_players }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Active</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ props.rosterReport.summary.active }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Injured</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ props.rosterReport.summary.injured }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Suspended</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ props.rosterReport.summary.suspended }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Jersey Pending</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.rosterReport.summary.jersey_pending }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Position Pending</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.rosterReport.summary.position_pending }}</p>
                </article>
            </div>

            <div v-if="props.rosterReport.rows.length === 0" class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-sm text-slate-500">
                No roster data found for the selected filters.
            </div>

            <div v-else class="overflow-x-auto rounded-2xl border border-[#034485]/35">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <th class="px-5 py-4">Student</th>
                            <th class="px-5 py-4">Team</th>
                            <th class="px-5 py-4">Year</th>
                            <th class="px-5 py-4">Jersey</th>
                            <th class="px-5 py-4">Position</th>
                            <th class="px-5 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.rosterReport.rows" :key="`${row.student_id}-${row.team_name}-${row.year}`" class="align-top">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ row.student_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.student_id_number || 'No student ID' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <p>{{ row.team_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.sport_name }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ row.year || 'N/A' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.jersey_number }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.athlete_position }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ row.player_status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="academic-report" class="space-y-4 rounded-3xl border border-[#034485]/35 bg-white p-5 scroll-mt-24">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Academic Submission Status Report</h2>
                    <p class="mt-1 text-sm text-slate-500">Submission status and latest evaluation result per academic document.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        :href="`/reports/academics/export.csv${queryString}`"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Export CSV
                    </a>
                    <a
                        :href="`/reports/academics/print${queryString}`"
                        target="_blank"
                        rel="noreferrer"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Open PDF View
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <article class="rounded-2xl border border-[#034485]/35 bg-white p-4">
                    <p class="text-xs text-slate-500">Total Submissions</p>
                    <p class="mt-1 text-2xl font-bold text-[#034485]">{{ props.academicReport.summary.total_submissions }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Eligible</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ props.academicReport.summary.eligible }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Probation</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ props.academicReport.summary.pending_review }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Ineligible</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ props.academicReport.summary.ineligible }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Pending</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.academicReport.summary.pending }}</p>
                </article>
            </div>

            <div v-if="props.academicReport.rows.length === 0" class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-sm text-slate-500">
                No academic submission data found for the selected filters.
            </div>

            <div v-else class="overflow-x-auto rounded-2xl border border-[#034485]/35">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <th class="px-5 py-4">Student</th>
                            <th class="px-5 py-4">Team</th>
                            <th class="px-5 py-4">Period</th>
                            <th class="px-5 py-4">Document</th>
                            <th class="px-5 py-4">Uploaded</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">GPA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.academicReport.rows" :key="row.document_id" class="align-top">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ row.student_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.student_id_number || 'No student ID' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ row.team_name }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.period_label }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.document_type }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.uploaded_at || 'N/A' }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ row.academic_status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 font-semibold text-[#034485]">{{ row.gpa }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="health-report" class="space-y-4 rounded-3xl border border-[#034485]/35 bg-white p-5 scroll-mt-24">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Health Clearance Status Report</h2>
                    <p class="mt-1 text-sm text-slate-500">Clearance validity and review status for approved student-athletes.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        :href="`/reports/health/export.csv${queryString}`"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Export CSV
                    </a>
                    <a
                        :href="`/reports/health/print${queryString}`"
                        target="_blank"
                        rel="noreferrer"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Open PDF View
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                <article class="rounded-2xl border border-[#034485]/35 bg-white p-4">
                    <p class="text-xs text-slate-500">Total Records</p>
                    <p class="mt-1 text-2xl font-bold text-[#034485]">{{ props.healthReport.summary.total_records }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Fit</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ props.healthReport.summary.fit }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Fit w/ Restrictions</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ props.healthReport.summary.fit_with_restrictions }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Not Fit</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ props.healthReport.summary.not_fit }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Expired</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.healthReport.summary.expired }}</p>
                </article>
                <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Reviewed</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.healthReport.summary.reviewed }}</p>
                </article>
            </div>

            <div v-if="props.healthReport.rows.length === 0" class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-sm text-slate-500">
                No health clearance data found for the selected filters.
            </div>

            <div v-else class="overflow-x-auto rounded-2xl border border-[#034485]/35">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <th class="px-5 py-4">Student</th>
                            <th class="px-5 py-4">Team</th>
                            <th class="px-5 py-4">Clearance Date</th>
                            <th class="px-5 py-4">Valid Until</th>
                            <th class="px-5 py-4">Physician</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Review</th>
                            <th class="px-5 py-4">Reviewed By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.healthReport.rows" :key="row.id" class="align-top">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ row.student_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.student_id_number || 'No student ID' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ row.team_name }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.clearance_date || 'N/A' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.valid_until || 'N/A' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.physician_name }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ row.clearance_status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ row.review_state }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.reviewed_by }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
