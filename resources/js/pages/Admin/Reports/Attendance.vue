<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import DatePicker from 'primevue/datepicker'

import { useTheme } from '@/composables/useTheme'
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

const reportTabs = [
    { label: 'Attendance', href: '/reports/attendance' },
    { label: 'Roster', href: '/reports/roster' },
    { label: 'Academics', href: '/reports/academics' },
]

const props = defineProps<{
    filters: {
        selected: {
            team_id: number | null
            status: string | null
            start_date: string | null
            end_date: string | null
        }
        options: {
            teams: TeamOption[]
            statuses: { value: string; label: string }[]
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
}>()

const { isDarkMode } = useTheme()
const form = reactive({
    team_id: props.filters.selected.team_id ? String(props.filters.selected.team_id) : '',
    status: props.filters.selected.status ?? '',
    start_date: props.filters.selected.start_date ?? '',
    end_date: props.filters.selected.end_date ?? '',
})

function parseFilterDate(value: string): Date | null {
    if (!value) return null

    const date = new Date(`${value}T00:00:00`)
    return Number.isNaN(date.getTime()) ? null : date
}

function formatFilterDate(value: Date | null): string {
    if (!value) return ''

    const year = value.getFullYear()
    const month = String(value.getMonth() + 1).padStart(2, '0')
    const day = String(value.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

const startDateModel = computed<Date | null>({
    get: () => parseFilterDate(form.start_date),
    set: (value) => {
        form.start_date = formatFilterDate(value)
    },
})

const endDateModel = computed<Date | null>({
    get: () => parseFilterDate(form.end_date),
    set: (value) => {
        form.end_date = formatFilterDate(value)
    },
})

const queryString = computed(() => {
    const params = new URLSearchParams()

    if (form.team_id) params.set('team_id', form.team_id)
    if (form.status) params.set('status', form.status)
    if (form.start_date) params.set('start_date', form.start_date)
    if (form.end_date) params.set('end_date', form.end_date)

    const query = params.toString()
    return query ? `?${query}` : ''
})

function applyFilters() {
    router.get('/reports/attendance', {
        team_id: form.team_id || undefined,
        status: form.status || undefined,
        start_date: form.start_date || undefined,
        end_date: form.end_date || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    form.team_id = ''
    form.status = ''
    form.start_date = ''
    form.end_date = ''
    applyFilters()
}

function formatPercent(value: number) {
    return `${Number(value || 0).toFixed(2)}%`
}

</script>

<template>
    <Head title="Attendance Report" />

    <div class="space-y-5">
        <section class="page-card rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Attendance Report</h1>
                    <p class="text-sm text-slate-600">Generate per-student attendance summaries with focused filters and export-ready output.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        v-for="tab in reportTabs"
                        :key="tab.href"
                        :href="tab.href"
                        class="rounded-md border px-3 py-2 text-sm font-semibold transition"
                        :class="tab.href === '/reports/attendance' ? 'border-[#1f2937] bg-[#1f2937] text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100'"
                    >
                        {{ tab.label }}
                    </a>
                </div>
            </div>
        </section>

        <section class="page-card rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
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
                    <label class="mb-1 block text-sm font-medium text-slate-700">Start Date</label>
                    <DatePicker
                        v-model="startDateModel"
                        showIcon
                        iconDisplay="input"
                        inputClass="w-full rounded-md border px-3 py-2 text-sm"
                        :pt="{
                            pcInputText: {
                                root: {
                                    class: isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-900',
                                },
                            },
                        }"
                        panelClass="text-sm"
                        placeholder="Start date"
                        dateFormat="yy-mm-dd"
                        :manualInput="false"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">End Date</label>
                    <DatePicker
                        v-model="endDateModel"
                        showIcon
                        iconDisplay="input"
                        inputClass="w-full rounded-md border px-3 py-2 text-sm"
                        :pt="{
                            pcInputText: {
                                root: {
                                    class: isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-900',
                                },
                            },
                        }"
                        panelClass="text-sm"
                        placeholder="End date"
                        dateFormat="yy-mm-dd"
                        :manualInput="false"
                    />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="applyFilters">
                    Apply
                </button>
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="resetFilters">
                    Reset
                </button>
                <a :href="`/reports/attendance/export.csv${queryString}`" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Export CSV
                </a>
                <a :href="`/reports/attendance/print${queryString}`" target="_blank" rel="noreferrer" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Open PDF View
                </a>
            </div>
        </section>

        <section class="page-card space-y-4 rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Overall Attendance</p>
                    <p class="mt-1 text-2xl font-bold text-[#034485]">{{ props.attendanceReport.summary.present }} / {{ props.attendanceReport.summary.total_records }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Present</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ props.attendanceReport.summary.present }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Absent</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ props.attendanceReport.summary.absent }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Late</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ props.attendanceReport.summary.late }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Excused</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.attendanceReport.summary.excused }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">No Response</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.attendanceReport.summary.no_response }}</p>
                </article>
            </div>

            <div v-if="props.attendanceReport.rows.length === 0" class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-sm text-slate-500">
                No attendance data found for the selected filters.
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-[#034485]/45">
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
    </div>
</template>
