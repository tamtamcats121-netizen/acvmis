<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'

import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

interface FilterOption {
    value: string
    label: string
}

const props = defineProps<{
    filters: {
        selected: {
            sport_id: number | null
            team_id: number | null
            coach_id: number | null
            schedule_type: string | null
            status: string | null
            start_date: string | null
            end_date: string | null
        }
        options: {
            sports: Array<{ id: number; name: string }>
            teams: Array<{ id: number; team_name: string; sport_name: string }>
            coaches: Array<{ coach_id: number; name: string }>
            schedule_types: string[]
            statuses: FilterOption[]
        }
    }
    kpis: {
        total_records: number
        total_schedules: number
        total_teams: number
        attendance_rate_percent: number
        response_rate_percent: number
        counts: {
            present: number
            absent: number
            late: number
            excused: number
            no_response: number
        }
    }
    charts: {
        status_distribution: Array<{ status: string; label: string; value: number }>
        attendance_trend: Array<{
            date: string
            total: number
            present: number
            absent: number
            late: number
            excused: number
            no_response: number
            attendance_rate_percent: number
        }>
        team_comparison: Array<{
            team_id: number
            team_name: string
            sport_name: string
            coach_name: string
            total: number
            present: number
            absent: number
            late: number
            excused: number
            no_response: number
            attendance_rate_percent: number
        }>
    }
    tables: {
        teams: any[]
        schedules: any[]
        at_risk_athletes: any[]
    }
    meta: {
        generated_at: string
        records_scope: number
    }
}>()

const form = reactive({
    sport_id: props.filters.selected.sport_id ? String(props.filters.selected.sport_id) : '',
    team_id: props.filters.selected.team_id ? String(props.filters.selected.team_id) : '',
    coach_id: props.filters.selected.coach_id ? String(props.filters.selected.coach_id) : '',
    schedule_type: props.filters.selected.schedule_type ?? '',
    status: props.filters.selected.status ?? '',
    period: '',
})
const isApplyingFilters = ref(false)

function formatDateOnly(date: Date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

function getPeriodDates(period: string) {
    if (!period) return { start_date: undefined, end_date: undefined }

    const now = new Date()
    const start = new Date(now)
    const end = new Date(now)

    if (period === 'day') {
        return {
            start_date: formatDateOnly(start),
            end_date: formatDateOnly(end),
        }
    }

    if (period === 'week') {
        const day = now.getDay()
        const diffToMonday = (day + 6) % 7
        start.setDate(now.getDate() - diffToMonday)
        end.setDate(start.getDate() + 6)
        return {
            start_date: formatDateOnly(start),
            end_date: formatDateOnly(end),
        }
    }

    if (period === 'month') {
        start.setDate(1)
        end.setMonth(now.getMonth() + 1, 0)
        return {
            start_date: formatDateOnly(start),
            end_date: formatDateOnly(end),
        }
    }

    if (period === 'year') {
        start.setMonth(0, 1)
        end.setMonth(11, 31)
        return {
            start_date: formatDateOnly(start),
            end_date: formatDateOnly(end),
        }
    }

    return { start_date: undefined, end_date: undefined }
}

function applyFilters() {
    isApplyingFilters.value = true
    const periodDates = getPeriodDates(form.period)

    router.get('/operations/attendance', {
        sport_id: form.sport_id || undefined,
        team_id: form.team_id || undefined,
        coach_id: form.coach_id || undefined,
        schedule_type: form.schedule_type || undefined,
        status: form.status || undefined,
        start_date: periodDates.start_date,
        end_date: periodDates.end_date,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isApplyingFilters.value = false
        },
    })
}

function resetFilters() {
    form.sport_id = ''
    form.team_id = ''
    form.coach_id = ''
    form.schedule_type = ''
    form.status = ''
    form.period = ''
    applyFilters()
}

let applyTimer: ReturnType<typeof setTimeout> | null = null
watch(
    () => [form.sport_id, form.team_id, form.coach_id, form.schedule_type, form.status, form.period],
    () => {
        if (applyTimer) clearTimeout(applyTimer)
        applyTimer = setTimeout(() => applyFilters(), 250)
    }
)

function formatDateTime(dt: string | null) {
    if (!dt) return '-'
    return new Date(dt).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

const maxStatusValue = computed(() => {
    const max = Math.max(...props.charts.status_distribution.map((item) => item.value), 0)
    return max > 0 ? max : 1
})

function statusBarWidth(value: number) {
    return `${(value / maxStatusValue.value) * 100}%`
}
</script>

<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-white">Attendance Insights</h1>
            <p class="text-sm text-gray-400">Admin analytics across teams, schedules, and student-athletes.</p>
        </div>

        <section class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                <select v-model="form.sport_id" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">All Sports</option>
                    <option v-for="sport in filters.options.sports" :key="sport.id" :value="String(sport.id)">{{ sport.name }}</option>
                </select>

                <select v-model="form.team_id" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">All Teams</option>
                    <option v-for="team in filters.options.teams" :key="team.id" :value="String(team.id)">
                        {{ team.team_name }} ({{ team.sport_name }})
                    </option>
                </select>

                <select v-model="form.coach_id" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">All Coaches</option>
                    <option v-for="coach in filters.options.coaches" :key="coach.coach_id" :value="String(coach.coach_id)">{{ coach.name }}</option>
                </select>

                <select v-model="form.schedule_type" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">All Types</option>
                    <option v-for="type in filters.options.schedule_types" :key="type" :value="type">{{ type }}</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <select v-model="form.status" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">All Statuses</option>
                    <option v-for="status in filters.options.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
                <select v-model="form.period" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">All Time</option>
                    <option value="day">Day</option>
                    <option value="week">Week</option>
                    <option value="month">Month</option>
                    <option value="year">Year</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button @click="resetFilters" class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-700 text-white hover:bg-gray-600">
                    Reset
                </button>
                <span v-if="isApplyingFilters" class="px-3 py-2 text-xs text-slate-300">Refreshing insights...</span>
            </div>
        </section>

        <section v-if="isApplyingFilters" class="grid grid-cols-2 xl:grid-cols-5 gap-3">
            <div v-for="index in 5" :key="`kpi-skeleton-${index}`" class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4 space-y-2">
                <Skeleton class="h-3 w-2/3 bg-gray-700/80" />
                <Skeleton class="h-8 w-1/2 bg-gray-700/80" />
            </div>
        </section>

        <section v-else class="grid grid-cols-2 xl:grid-cols-5 gap-3">
            <div class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4">
                <div class="text-xs text-gray-400">Total Records</div>
                <div class="text-2xl text-white font-bold">{{ kpis.total_records }}</div>
            </div>
            <div class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4">
                <div class="text-xs text-gray-400">Schedules</div>
                <div class="text-2xl text-white font-bold">{{ kpis.total_schedules }}</div>
            </div>
            <div class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4">
                <div class="text-xs text-gray-400">Teams</div>
                <div class="text-2xl text-white font-bold">{{ kpis.total_teams }}</div>
            </div>
            <div class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4">
                <div class="text-xs text-gray-400">Attendance Rate</div>
                <div class="text-2xl text-green-300 font-bold">{{ kpis.attendance_rate_percent }}%</div>
            </div>
            <div class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4">
                <div class="text-xs text-gray-400">Response Rate</div>
                <div class="text-2xl text-slate-300 font-bold">{{ kpis.response_rate_percent }}%</div>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4">
                <h2 class="text-white font-semibold mb-3">Status Distribution</h2>
                <div class="space-y-3">
                    <div v-for="item in charts.status_distribution" :key="item.status">
                        <div class="flex justify-between text-xs text-gray-300 mb-1">
                            <span>{{ item.label }}</span>
                            <span>{{ item.value }}</span>
                        </div>
                        <div class="h-2 bg-gray-800 rounded">
                            <div class="h-2 bg-[#F53003] rounded" :style="{ width: statusBarWidth(item.value) }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4 overflow-x-auto">
                <h2 class="text-white font-semibold mb-3">Attendance Trend</h2>
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-300 bg-gray-800">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Present</th>
                            <th class="px-3 py-2">Absent</th>
                            <th class="px-3 py-2">Late</th>
                            <th class="px-3 py-2">Excused</th>
                            <th class="px-3 py-2">No Response</th>
                            <th class="px-3 py-2">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in charts.attendance_trend" :key="row.date" class="border-t border-gray-800 text-gray-300">
                            <td class="px-3 py-2">{{ row.date }}</td>
                            <td class="px-3 py-2">{{ row.present }}</td>
                            <td class="px-3 py-2">{{ row.absent }}</td>
                            <td class="px-3 py-2">{{ row.late }}</td>
                            <td class="px-3 py-2">{{ row.excused }}</td>
                            <td class="px-3 py-2">{{ row.no_response }}</td>
                            <td class="px-3 py-2 text-white">{{ row.attendance_rate_percent }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4 overflow-x-auto">
            <h2 class="text-white font-semibold mb-3">Team Comparison</h2>
            <table class="w-full text-sm text-left">
                <thead class="text-gray-300 bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Team</th>
                        <th class="px-3 py-2">Sport</th>
                        <th class="px-3 py-2">Coach</th>
                        <th class="px-3 py-2">Present</th>
                        <th class="px-3 py-2">Absent</th>
                        <th class="px-3 py-2">Late</th>
                        <th class="px-3 py-2">No Response</th>
                        <th class="px-3 py-2">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in tables.teams" :key="row.team_id" class="border-t border-gray-800 text-gray-300">
                        <td class="px-3 py-2 text-white">{{ row.team_name }}</td>
                        <td class="px-3 py-2">{{ row.sport_name }}</td>
                        <td class="px-3 py-2">{{ row.coach_name }}</td>
                        <td class="px-3 py-2">{{ row.present }}</td>
                        <td class="px-3 py-2">{{ row.absent }}</td>
                        <td class="px-3 py-2">{{ row.late }}</td>
                        <td class="px-3 py-2">{{ row.no_response }}</td>
                        <td class="px-3 py-2 text-white">{{ row.attendance_rate_percent }}%</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4 overflow-x-auto">
                <h2 class="text-white font-semibold mb-3">Schedules</h2>
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-300 bg-gray-800">
                        <tr>
                            <th class="px-3 py-2">Schedule</th>
                            <th class="px-3 py-2">Team</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tables.schedules" :key="row.schedule_id" class="border-t border-gray-800 text-gray-300">
                            <td class="px-3 py-2 text-white">{{ row.schedule_title }}</td>
                            <td class="px-3 py-2">{{ row.team_name }}</td>
                            <td class="px-3 py-2">{{ formatDateTime(row.schedule_start) }}</td>
                            <td class="px-3 py-2 text-white">{{ row.attendance_rate_percent }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="page-card bg-gray-900 border border-gray-800 rounded-xl p-4 overflow-x-auto">
                <h2 class="text-white font-semibold mb-3">At-Risk Athletes</h2>
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-300 bg-gray-800">
                        <tr>
                            <th class="px-3 py-2">Student</th>
                            <th class="px-3 py-2">Team</th>
                            <th class="px-3 py-2">Absent</th>
                            <th class="px-3 py-2">Late</th>
                            <th class="px-3 py-2">No Response</th>
                            <th class="px-3 py-2">Risk Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tables.at_risk_athletes" :key="row.student_id" class="border-t border-gray-800 text-gray-300">
                            <td class="px-3 py-2 text-white">{{ row.student_name }}</td>
                            <td class="px-3 py-2">{{ row.team_name }}</td>
                            <td class="px-3 py-2">{{ row.absent }}</td>
                            <td class="px-3 py-2">{{ row.late }}</td>
                            <td class="px-3 py-2">{{ row.no_response }}</td>
                            <td class="px-3 py-2 text-red-300 font-semibold">{{ row.risk_score }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs text-gray-500">
            Generated: {{ formatDateTime(meta.generated_at) }} • Scoped records: {{ meta.records_scope }}
        </p>
    </div>
</template>
