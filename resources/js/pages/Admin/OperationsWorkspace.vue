<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed, reactive, ref, watch } from 'vue'
import { VueCal } from 'vue-cal'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { supportedSports, useSportColors } from '@/composables/useSportColors'
import { useTheme } from '@/composables/useTheme'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import 'vue-cal/style'

defineOptions({
    layout: AdminDashboard,
})

type FilterOption = { value: string; label: string }
type AttendanceStatus = 'present' | 'absent' | 'late' | 'excused' | 'no_response'
type AttendanceDrilldown = {
    schedule: {
        id: number
        title: string
        type: string
        team_name: string
        sport_name: string
        start_time: string
        end_time: string
        notes: string | null
    }
    counts: {
        total: number
        present: number
        absent: number
        late: number
        excused: number
        no_response: number
    }
    roster: Array<{
        student_id: number
        student_id_number: string | null
        student_name: string
        attendance_status: AttendanceStatus
        recorded_at: string | null
        notes: string | null
        override_reason: string | null
    }>
}

const props = defineProps<{
    filters: {
        selected: {
            tab: 'calendar' | 'attendance' | 'exceptions'
            search: string | null
            sport_id: number | null
            team_id: number | null
            coach_id: number | null
            schedule_id: number | null
            schedule_type: string | null
            status: string | null
            period: 'today' | 'week' | 'month' | null
            start_date: string | null
            end_date: string | null
            sort: 'schedule_start' | 'team_name' | 'student_name' | 'status'
            direction: 'asc' | 'desc'
            page: number
            per_page: number
        }
        options: {
            sports: Array<{ id: number; name: string }>
            teams: Array<{ id: number; team_name: string; sport_name: string }>
            coaches: Array<{ coach_id: number; name: string }>
            schedule_types: string[]
            statuses: FilterOption[]
        }
    }
    tabs: {
        active: 'calendar'
        available: Array<'calendar'>
    }
    calendarSchedules: Array<{
        id: number
        title: string
        type: string
        venue: string
        notes: string | null
        team_name: string
        sport: string
        coach_name: string
        start: string
        end: string
        counts: {
            roster_total: number
            present: number
            absent: number
            late: number
            excused: number
            no_response: number
        }
    }>
    attendanceRecords: unknown
    exceptionsRecords: unknown
    kpis: {
        summary: {
            total_records: number
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
        needs_attention: {
            no_response: number
            late_spike_delta: number
            at_risk_teams: number
        }
    }
}>()

const { normalizeSport, sportColor, sportTextColor, sportLabel } = useSportColors()
const { isDarkMode } = useTheme()

const showFilters = ref(false)
const showCalendar = ref(true)
const calendarSportFilter = ref<string>('all')
const isLoadingAttendance = ref(false)
const attendanceDialogOpen = ref(false)
const attendanceDetails = ref<AttendanceDrilldown | null>(null)
const noticeDialog = ref<{ open: boolean; title: string; description: string }>({
    open: false,
    title: '',
    description: '',
})
let searchDebounce: ReturnType<typeof setTimeout> | null = null
let suppressAutoReload = false

const filterForm = reactive({
    search: props.filters.selected.search ?? '',
    sport_id: props.filters.selected.sport_id ? String(props.filters.selected.sport_id) : '',
    team_id: props.filters.selected.team_id ? String(props.filters.selected.team_id) : '',
    coach_id: props.filters.selected.coach_id ? String(props.filters.selected.coach_id) : '',
    schedule_type: props.filters.selected.schedule_type ?? '',
    status: props.filters.selected.status ?? '',
    period: props.filters.selected.period ?? '',
    start_date: props.filters.selected.start_date ?? '',
    end_date: props.filters.selected.end_date ?? '',
    sort: props.filters.selected.sort,
    direction: props.filters.selected.direction,
    per_page: String(props.filters.selected.per_page),
})

const quickPeriods: Array<{ key: '' | 'today' | 'week' | 'month'; label: string }> = [
    { key: '', label: 'All Time' },
    { key: 'today', label: 'Today' },
    { key: 'week', label: 'This Week' },
    { key: 'month', label: 'This Month' },
]
const sportOptions = computed(() => [
    { label: 'All Sports', value: '' },
    ...props.filters.options.sports.map((sport) => ({ label: sport.name, value: String(sport.id) })),
])
const teamOptions = computed(() => [
    { label: 'All Teams', value: '' },
    ...filteredTeams.value.map((team) => ({ label: `${team.team_name} (${team.sport_name})`, value: String(team.id) })),
])
const scheduleTypeOptions = computed(() => [
    { label: 'All Schedule Types', value: '' },
    ...props.filters.options.schedule_types.map((type) => ({ label: type, value: type })),
])
const attendanceStatusOptions = computed(() => [
    { label: 'All Attendance Statuses', value: '' },
    ...props.filters.options.statuses.map((status) => ({ label: status.label, value: status.value })),
])
const coachOptions = computed(() => [
    { label: 'All Coaches', value: '' },
    ...props.filters.options.coaches.map((coach) => ({ label: coach.name, value: String(coach.coach_id) })),
])

const activeFilterCount = computed(() => {
    let count = 0
    if (filterForm.search.trim()) count++
    if (filterForm.sport_id) count++
    if (filterForm.team_id) count++
    if (filterForm.coach_id) count++
    if (filterForm.schedule_type) count++
    if (filterForm.status) count++
    if (filterForm.period) count++
    if (filterForm.start_date || filterForm.end_date) count++
    return count
})

const filteredTeams = computed(() => {
    if (!filterForm.sport_id) return props.filters.options.teams

    const selectedSport = props.filters.options.sports.find((sport) => String(sport.id) === filterForm.sport_id)
    if (!selectedSport) return props.filters.options.teams

    return props.filters.options.teams.filter((team) => team.sport_name === selectedSport.name)
})

const filteredSchedules = computed(() => props.calendarSchedules)
const availableCalendarSports = computed(() => {
    const sportMap = new Map<string, { key: string; label: string; color: string; textColor: string }>()

    supportedSports.forEach((sport) => {
        const normalizedSport = normalizeSport(sport)
        sportMap.set(normalizedSport, {
            key: normalizedSport,
            label: sportLabel(sport),
            color: sportColor(sport),
            textColor: sportTextColor(sport),
        })
    })

    return Array.from(sportMap.values()).sort((left, right) => left.label.localeCompare(right.label))
})
const calendarVisibleSchedules = computed(() => {
    if (calendarSportFilter.value === 'all') return filteredSchedules.value
    return filteredSchedules.value.filter((item) => normalizeSport(item.sport) === normalizeSport(calendarSportFilter.value))
})
const selectedSportLabel = computed(() => {
    if (!filterForm.sport_id) return null
    return props.filters.options.sports.find((sport) => String(sport.id) === filterForm.sport_id)?.name ?? null
})
const selectedTeamLabel = computed(() => {
    if (!filterForm.team_id) return null
    return filteredTeams.value.find((team) => String(team.id) === filterForm.team_id)?.team_name ?? null
})
const selectedCoachLabel = computed(() => {
    if (!filterForm.coach_id) return null
    return props.filters.options.coaches.find((coach) => String(coach.coach_id) === filterForm.coach_id)?.name ?? null
})
const activeFilterChips = computed(() => {
    const chips: string[] = []
    if (filterForm.search.trim()) chips.push(`Search: ${filterForm.search.trim()}`)
    if (selectedSportLabel.value) chips.push(`Sport: ${selectedSportLabel.value}`)
    if (selectedTeamLabel.value) chips.push(`Team: ${selectedTeamLabel.value}`)
    if (selectedCoachLabel.value) chips.push(`Coach: ${selectedCoachLabel.value}`)
    if (filterForm.schedule_type) chips.push(`Type: ${filterForm.schedule_type}`)
    if (filterForm.status) chips.push(`Attendance: ${filterForm.status.replaceAll('_', ' ')}`)
    if (filterForm.period) chips.push(`Period: ${quickPeriods.find((period) => period.key === filterForm.period)?.label ?? filterForm.period}`)
    if (filterForm.start_date) chips.push(`Start: ${filterForm.start_date}`)
    if (filterForm.end_date) chips.push(`End: ${filterForm.end_date}`)
    return chips
})
const defaultFilterChips = computed(() => {
    if (activeFilterChips.value.length > 0) return activeFilterChips.value

    const defaults = [
        'All Sports',
        'All Teams',
        'All Schedule Types',
        'All Attendance Statuses',
        'All Coaches',
    ]

    if (filterForm.period) {
        defaults.push(quickPeriods.find((period) => period.key === filterForm.period)?.label ?? filterForm.period)
    } else if (filterForm.start_date || filterForm.end_date) {
        defaults.push(`${filterForm.start_date || 'Any start'} to ${filterForm.end_date || 'Any end'}`)
    } else {
        defaults.push('All Dates')
    }

    return defaults
})
const calendarEvents = computed(() =>
    calendarVisibleSchedules.value.map((item) => ({
        id: item.id,
        title: item.title,
        start: new Date(item.start),
        end: new Date(item.end),
        content: `${item.team_name} • ${item.type}`,
        backgroundColor: sportColor(item.sport),
        color: sportTextColor(item.sport),
    })),
)

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
    get: () => parseFilterDate(filterForm.start_date),
    set: (value) => {
        filterForm.start_date = formatFilterDate(value)
        if (value) filterForm.period = ''
    },
})

const endDateModel = computed<Date | null>({
    get: () => parseFilterDate(filterForm.end_date),
    set: (value) => {
        filterForm.end_date = formatFilterDate(value)
        if (value) filterForm.period = ''
    },
})

function buildQuery(extra: Record<string, string | number | undefined> = {}) {
    return {
        tab: 'calendar',
        search: filterForm.search.trim() || undefined,
        sport_id: filterForm.sport_id || undefined,
        team_id: filterForm.team_id || undefined,
        coach_id: filterForm.coach_id || undefined,
        schedule_type: filterForm.schedule_type || undefined,
        status: filterForm.status || undefined,
        period: filterForm.period || undefined,
        start_date: filterForm.start_date || undefined,
        end_date: filterForm.end_date || undefined,
        sort: filterForm.sort,
        direction: filterForm.direction,
        per_page: filterForm.per_page,
        ...extra,
    }
}

function applyFilters() {
    router.get('/operations', buildQuery({ page: 1 }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function resetFilters() {
    suppressAutoReload = true
    filterForm.search = ''
    filterForm.sport_id = ''
    filterForm.team_id = ''
    filterForm.coach_id = ''
    filterForm.schedule_type = ''
    filterForm.status = ''
    filterForm.period = ''
    filterForm.start_date = ''
    filterForm.end_date = ''
    filterForm.sort = 'schedule_start'
    filterForm.direction = 'desc'
    filterForm.per_page = '15'
    showFilters.value = false
    applyFilters()
    setTimeout(() => {
        suppressAutoReload = false
    }, 0)
}

function setPeriod(period: '' | 'today' | 'week' | 'month') {
    filterForm.period = period
    if (period) {
        filterForm.start_date = ''
        filterForm.end_date = ''
    }
}

function formatDateTime(dt: string | Date | null) {
    if (!dt) return '-'
    const date = typeof dt === 'string' ? new Date(dt) : dt
    return date.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function formatDateOnly(dt: string | Date | null) {
    if (!dt) return '-'
    const date = typeof dt === 'string' ? new Date(dt) : dt
    return date.toLocaleDateString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

function formatTimeOnly(dt: string | Date | null) {
    if (!dt) return '-'
    const date = typeof dt === 'string' ? new Date(dt) : dt
    return date.toLocaleTimeString('en-PH', {
        timeZone: 'Asia/Manila',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function showNotice(title: string, description: string) {
    noticeDialog.value = { open: true, title, description }
}

function attendanceStatusClass(status: AttendanceStatus) {
    if (status === 'present') return isDarkMode.value ? 'bg-[#0b3158] text-sky-100' : 'bg-emerald-100 text-emerald-700'
    if (status === 'late') return isDarkMode.value ? 'bg-[#11335a] text-blue-100' : 'bg-amber-100 text-amber-700'
    if (status === 'absent') return isDarkMode.value ? 'bg-[#102942] text-slate-100' : 'bg-rose-100 text-rose-700'
    if (status === 'excused') return isDarkMode.value ? 'bg-[#102942] text-slate-100' : 'bg-slate-100 text-slate-700'
    return isDarkMode.value ? 'bg-[#102942] text-slate-100' : 'bg-slate-100 text-slate-700'
}

async function openAttendance(scheduleId: number) {
    isLoadingAttendance.value = true

    try {
        const response = await fetch(`/operations/schedules/${scheduleId}/drilldown`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        })

        if (!response.ok) {
            throw new Error('Unable to load attendance details right now.')
        }

        attendanceDetails.value = await response.json() as AttendanceDrilldown
        attendanceDialogOpen.value = true
    } catch (error) {
        showNotice(
            'Attendance Unavailable',
            error instanceof Error ? error.message : 'Unable to load attendance details right now.',
        )
    } finally {
        isLoadingAttendance.value = false
    }
}

watch(
    () => filterForm.sport_id,
    () => {
        if (!filterForm.team_id) return

        const teamStillAvailable = filteredTeams.value.some((team) => String(team.id) === filterForm.team_id)
        if (!teamStillAvailable) {
            filterForm.team_id = ''
        }
    },
)

watch(
    () => filterForm.search,
    () => {
        if (suppressAutoReload) return
        if (searchDebounce) clearTimeout(searchDebounce)
        searchDebounce = setTimeout(() => applyFilters(), 250)
    },
)

watch(
    () => [filterForm.sport_id, filterForm.team_id, filterForm.coach_id, filterForm.schedule_type, filterForm.status, filterForm.period, filterForm.start_date, filterForm.end_date],
    () => {
        if (suppressAutoReload) return
        applyFilters()
    },
)
</script>

<template>
    <div class="space-y-5">
        <section class="operations-workspace-hero page-card rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Operations Workspace</p>
            <h1 class="mt-2 text-2xl font-bold">Schedule Monitoring</h1>
            <p class="mt-2 max-w-3xl text-sm text-white/85">
                Review schedules across all teams, then open each schedule's attendance in a read-only admin view.
            </p>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/20 bg-white'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">Schedule Records</p>
                <p class="mt-2 text-2xl font-bold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ filteredSchedules.length }}</p>
            </div>
            <div class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-amber-200 bg-amber-50'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-amber-300' : 'text-amber-700'">No Response</p>
                <p class="mt-2 text-2xl font-bold" :class="isDarkMode ? 'text-amber-200' : 'text-amber-800'">{{ kpis.needs_attention.no_response }}</p>
            </div>
            <div class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-rose-200 bg-rose-50'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-rose-300' : 'text-rose-700'">Present Total</p>
                <p class="mt-2 text-2xl font-bold" :class="isDarkMode ? 'text-rose-200' : 'text-rose-800'">{{ kpis.summary.counts.present }}</p>
            </div>
        </section>

        <section class="page-card rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/35 bg-white'">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <InputText
                    v-model="filterForm.search"
                    placeholder="Search schedule, team, sport, coach, venue, type, or date"
                    class="w-full lg:flex-1"
                />
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
                        :class="isDarkMode ? 'border-slate-600 text-slate-200 hover:bg-slate-800' : 'border-[#034485]/30 text-[#034485] hover:bg-[#eef5ff]'"
                        @click="showFilters = !showFilters"
                    >
                        {{ showFilters ? 'Hide Filters' : 'Filters' }}
                        <span v-if="activeFilterCount" class="ml-1 rounded-full bg-[#dcecff] px-1.5 py-0.5 text-xs text-[#034485]">{{ activeFilterCount }}</span>
                    </button>
                </div>
            </div>

            <div v-if="showFilters" class="mt-3 space-y-3 border-t border-slate-200 pt-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="chip in defaultFilterChips"
                            :key="chip"
                            class="inline-flex rounded-full border px-3 py-1 text-xs font-medium"
                            :class="activeFilterChips.length
                                ? (isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-200' : 'border-[#034485]/15 bg-[#edf4ff] text-[#034485]')
                                : (isDarkMode ? 'border-[#4a90e2]/25 bg-[#0a2a4d] text-sky-100' : 'border-[#034485]/12 bg-[#f4f8ff] text-[#034485]')"
                        >
                            {{ chip }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="w-full rounded-xl border px-3 py-2 text-sm font-semibold transition sm:w-auto"
                        :class="isDarkMode ? 'border-slate-600 text-slate-200 hover:bg-slate-800' : 'border-slate-300 text-slate-700 hover:bg-slate-50'"
                        @click="resetFilters"
                    >
                        Clear Filters
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="period in quickPeriods"
                        :key="period.key || 'all-time'"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium"
                        :class="filterForm.period === period.key ? 'border border-[#034485]/35 bg-[#034485] text-white' : 'border border-[#034485]/15 bg-[#034485]/5 text-[#034485] hover:bg-[#034485]/10'"
                        @click="setPeriod(period.key)"
                    >
                        {{ period.label }}
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
                <Select v-model="filterForm.sport_id" :options="sportOptions" optionLabel="label" optionValue="value" class="w-full" placeholder="All Sports" />

                <Select v-model="filterForm.team_id" :options="teamOptions" optionLabel="label" optionValue="value" class="w-full" placeholder="All Teams" />

                <Select v-model="filterForm.schedule_type" :options="scheduleTypeOptions" optionLabel="label" optionValue="value" class="w-full" placeholder="All Schedule Types" />

                <Select v-model="filterForm.status" :options="attendanceStatusOptions" optionLabel="label" optionValue="value" class="w-full" placeholder="All Attendance Statuses" />

                <Select v-model="filterForm.coach_id" :options="coachOptions" optionLabel="label" optionValue="value" class="w-full" placeholder="All Coaches" />

                <DatePicker
                    v-model="startDateModel"
                    showIcon
                    iconDisplay="input"
                    inputClass="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                    :pt="{
                        pcInputText: {
                            root: {
                                class: isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-900',
                            },
                        },
                    }"
                    panelClass="text-sm"
                    placeholder="All Dates"
                    dateFormat="yy-mm-dd"
                    :manualInput="false"
                />

                <DatePicker
                    v-model="endDateModel"
                    showIcon
                    iconDisplay="input"
                    inputClass="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                    :pt="{
                        pcInputText: {
                            root: {
                                class: isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-900',
                            },
                        },
                    }"
                    panelClass="text-sm"
                    placeholder="All Dates"
                    dateFormat="yy-mm-dd"
                    :manualInput="false"
                />
                </div>
            </div>
        </section>

        <section class="page-card rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-800 bg-black' : 'border-[#034485]/35 bg-white'">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">All Team Schedules</h2>
                        <p class="text-sm" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">
                            Review schedule details and open attendance for any team session.
                        </p>
                        <p class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-500' : 'text-slate-400'">
                            Showing {{ filteredSchedules.length }} matching schedule{{ filteredSchedules.length === 1 ? '' : 's' }}.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
                        :class="isDarkMode ? 'border-slate-600 text-slate-200 hover:bg-slate-800' : 'border-[#034485]/30 text-[#034485] hover:bg-[#eef5ff]'"
                        @click="showCalendar = !showCalendar"
                    >
                        {{ showCalendar ? 'Hide Calendar View' : 'Show Calendar View' }}
                    </button>
                </div>

                <section v-if="showCalendar" class="rounded-2xl border p-3" :class="isDarkMode ? 'border-slate-800 bg-black' : 'border-[#034485]/25 bg-white'">
                    <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Calendar View</h3>
                            <p class="text-xs" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">
                                Use this as a secondary visual timeline. The schedule cards remain the primary admin workflow.
                            </p>
                        </div>
                    </div>
                    <div class="mb-3 flex flex-wrap items-center gap-2" aria-label="Calendar sport filters">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-semibold transition"
                            :class="calendarSportFilter === 'all'
                                ? 'border border-[#034485]/35 bg-[#034485] text-white'
                                : isDarkMode
                                    ? 'border border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800'
                                    : 'border border-[#034485]/15 bg-[#034485]/5 text-[#034485] hover:bg-[#034485]/10'"
                            @click="calendarSportFilter = 'all'"
                        >
                            All
                        </button>
                        <button
                            v-for="sport in availableCalendarSports"
                            :key="sport.key"
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                            :style="calendarSportFilter === sport.key
                                ? { backgroundColor: sport.color, color: sport.textColor, borderColor: sport.color }
                                : undefined"
                            :class="calendarSportFilter === sport.key
                                ? ''
                                : isDarkMode
                                    ? 'border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            @click="calendarSportFilter = sport.key"
                        >
                            {{ sport.label }}
                        </button>
                    </div>

                    <VueCal
                        sm
                        style="height: 660px"
                        :events="calendarEvents"
                        default-view="week"
                        :time="true"
                        :twelve-hour="true"
                        time-format="h:mm {am}"
                        events-on-month-view
                    />
                </section>

                <div v-if="filteredSchedules.length === 0" class="rounded-2xl border border-dashed px-5 py-10 text-center text-sm" :class="isDarkMode ? 'border-slate-700 text-slate-400' : 'border-[#034485]/20 text-slate-500'">
                    No schedules matched the current search and filters.
                </div>

                <div v-else class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                    <article
                        v-for="item in filteredSchedules"
                        :key="item.id"
                        class="page-card rounded-3xl border p-5 shadow-[0_18px_40px_-30px_rgba(3,68,133,0.24)]"
                        :class="isDarkMode ? 'border-slate-800 bg-black' : 'border-[#034485]/25 bg-white'"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="truncate text-lg font-semibold leading-tight" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ item.title }}</div>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex max-w-full rounded-full border px-2.5 py-1 text-[11px] font-semibold" :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-200' : 'border-[#034485]/20 bg-[#edf4ff] text-[#034485]'">
                                        {{ item.type }}
                                    </span>
                                    <span
                                        class="rounded border px-2.5 py-1 text-xs font-semibold"
                                        :style="{ backgroundColor: sportColor(item.sport), color: sportTextColor(item.sport), borderColor: sportColor(item.sport) }"
                                    >
                                        {{ sportLabel(item.sport) }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right text-xs" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">
                                <p>{{ formatDateOnly(item.start) }}</p>
                                <p class="mt-1 font-medium" :class="isDarkMode ? 'text-slate-200' : 'text-slate-700'">{{ formatTimeOnly(item.start) }} - {{ formatTimeOnly(item.end) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#034485]/18 backdrop-blur-sm' : 'border-[#034485]/15 bg-[#edf4ff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Team</p>
                                <p class="mt-1 text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ item.team_name }}</p>
                            </div>
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#034485]/18 backdrop-blur-sm' : 'border-[#034485]/15 bg-[#edf4ff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Coach</p>
                                <p class="mt-1 text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ item.coach_name || 'Unassigned' }}</p>
                            </div>
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#034485]/18 backdrop-blur-sm' : 'border-[#034485]/15 bg-[#edf4ff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Venue</p>
                                <p class="mt-1 text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ item.venue || 'No venue listed' }}</p>
                            </div>
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#034485]/18 backdrop-blur-sm' : 'border-[#034485]/15 bg-[#edf4ff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Roster</p>
                                <p class="mt-1 text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ item.counts.roster_total }} players</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs lg:grid-cols-4">
                            <div class="rounded-2xl border px-3 py-2" :class="isDarkMode ? 'border-[#4a90e2]/18 bg-[#0a2747] text-slate-100' : 'border-slate-200 bg-slate-50 text-slate-700'">Present: {{ item.counts.present }}</div>
                            <div class="rounded-2xl border px-3 py-2" :class="isDarkMode ? 'border-[#4a90e2]/18 bg-[#0a2747] text-slate-100' : 'border-slate-200 bg-slate-50 text-slate-700'">Absent: {{ item.counts.absent }}</div>
                            <div class="rounded-2xl border px-3 py-2" :class="isDarkMode ? 'border-[#4a90e2]/18 bg-[#0a2747] text-slate-100' : 'border-slate-200 bg-slate-50 text-slate-700'">Late: {{ item.counts.late }}</div>
                            <div class="rounded-2xl border px-3 py-2" :class="isDarkMode ? 'border-[#4a90e2]/22 bg-[#10345c] text-blue-100' : 'border-amber-200 bg-amber-50 text-amber-700'">No response: {{ item.counts.no_response }}</div>
                        </div>

                        <p v-if="item.notes" class="mt-4 text-sm leading-relaxed" :class="isDarkMode ? 'text-slate-400' : 'text-slate-600'">
                            {{ item.notes }}
                        </p>

                        <div class="mt-4 flex justify-end">
                            <button
                                type="button"
                                class="rounded-xl bg-[#034485] px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-[#02315f] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="isLoadingAttendance"
                                @click="openAttendance(item.id)"
                            >
                                {{ isLoadingAttendance ? 'Loading Attendance...' : 'Open Attendance' }}
                            </button>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <div
            v-if="attendanceDialogOpen && attendanceDetails"
            class="fixed inset-0 z-[100] overflow-y-auto bg-slate-950/55 px-4 py-6 backdrop-blur-sm"
            @click="attendanceDialogOpen = false"
        >
            <div class="flex min-h-full items-center justify-center">
                <div
                    class="w-full max-w-5xl rounded-3xl border shadow-[0_28px_70px_-34px_rgba(2,12,27,0.45)]"
                    :class="isDarkMode ? 'border-slate-800 bg-black text-slate-100' : 'border-[#034485]/35 bg-white text-slate-900'"
                    @click.stop
                >
                    <div class="rounded-t-3xl bg-[#034485] px-6 py-5 text-white sm:px-8">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-white/75">Attendance View</p>
                                <h3 class="mt-1 text-2xl font-bold">{{ attendanceDetails.schedule.title }}</h3>
                                <p class="mt-1 text-sm text-white/80">
                                    {{ attendanceDetails.schedule.team_name }} • {{ attendanceDetails.schedule.sport_name }} • {{ attendanceDetails.schedule.type }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full border border-white/25 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
                                @click="attendanceDialogOpen = false"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                    <div class="space-y-6 p-6 sm:p-8" :class="isDarkMode ? 'bg-black' : ''">
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#034485]/18 backdrop-blur-sm' : 'border-[#034485]/15 bg-[#edf4ff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Date</p>
                                <p class="mt-1 text-sm font-semibold">{{ formatDateOnly(attendanceDetails.schedule.start_time) }}</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#034485]/18 backdrop-blur-sm' : 'border-[#034485]/15 bg-[#edf4ff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Time</p>
                                <p class="mt-1 text-sm font-semibold">{{ formatTimeOnly(attendanceDetails.schedule.start_time) }} - {{ formatTimeOnly(attendanceDetails.schedule.end_time) }}</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#034485]/18 backdrop-blur-sm' : 'border-[#034485]/15 bg-[#edf4ff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Roster Size</p>
                                <p class="mt-1 text-sm font-semibold">{{ attendanceDetails.counts.total }} players</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#034485]/18 backdrop-blur-sm' : 'border-[#034485]/15 bg-[#edf4ff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Notes</p>
                                <p class="mt-1 text-sm font-semibold">{{ attendanceDetails.schedule.notes || 'No notes' }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#0a2747]' : 'border-emerald-200 bg-emerald-50'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-emerald-300' : 'text-emerald-700'">Present</p>
                                <p class="mt-1 text-xl font-bold">{{ attendanceDetails.counts.present }}</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#0a2747]' : 'border-rose-200 bg-rose-50'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-rose-300' : 'text-rose-700'">Absent</p>
                                <p class="mt-1 text-xl font-bold">{{ attendanceDetails.counts.absent }}</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#0a2747]' : 'border-amber-200 bg-amber-50'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-amber-300' : 'text-amber-700'">Late</p>
                                <p class="mt-1 text-xl font-bold">{{ attendanceDetails.counts.late }}</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#0a2747]' : 'border-slate-200 bg-slate-50'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">Excused</p>
                                <p class="mt-1 text-xl font-bold">{{ attendanceDetails.counts.excused }}</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/22 bg-[#10345c]' : 'border-amber-200 bg-amber-50'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-amber-300' : 'text-amber-700'">No Response</p>
                                <p class="mt-1 text-xl font-bold">{{ attendanceDetails.counts.no_response }}</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-[#4a90e2]/20 bg-[#034485]/18 backdrop-blur-sm' : 'border-[#034485]/15 bg-[#edf4ff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Mode</p>
                                <p class="mt-1 text-sm font-semibold">View Only</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-2xl border" :class="isDarkMode ? 'border-slate-800 bg-black' : 'border-[#034485]/15'">
                            <table class="w-full min-w-[720px] text-sm">
                                <thead :class="isDarkMode ? 'bg-[#0a2747] text-sky-100' : 'bg-[#034485] text-white'">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Student</th>
                                        <th class="px-4 py-3 text-left">Student ID</th>
                                        <th class="px-4 py-3 text-left">Status</th>
                                        <th class="px-4 py-3 text-left">Recorded</th>
                                        <th class="px-4 py-3 text-left">Notes</th>
                                        <th class="px-4 py-3 text-left">Override Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in attendanceDetails.roster"
                                        :key="`${attendanceDetails.schedule.id}-${row.student_id}`"
                                        class="border-t"
                                        :class="isDarkMode ? 'border-slate-800 bg-black text-slate-100' : 'border-slate-200 text-slate-700'"
                                    >
                                        <td class="px-4 py-3 font-medium" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ row.student_name }}</td>
                                        <td class="px-4 py-3">{{ row.student_id_number || '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="attendanceStatusClass(row.attendance_status)">
                                                {{ row.attendance_status.replaceAll('_', ' ') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">{{ formatDateTime(row.recorded_at) }}</td>
                                        <td class="px-4 py-3">{{ row.notes || '-' }}</td>
                                        <td class="px-4 py-3">{{ row.override_reason || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="noticeDialog.open"
            :title="noticeDialog.title"
            :description="noticeDialog.description"
            confirm-text="OK"
            :show-cancel="false"
            @update:open="noticeDialog.open = $event"
            @confirm="noticeDialog.open = false"
        />
    </div>
</template>
