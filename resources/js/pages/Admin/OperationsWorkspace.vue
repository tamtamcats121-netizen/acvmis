<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { VueCal } from 'vue-cal'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { supportedSports, useSportColors } from '@/composables/useSportColors'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import 'vue-cal/style'

defineOptions({
    layout: AdminDashboard,
})

type FilterOption = { value: string; label: string }
type AttendanceRow = {
    schedule_id: number
    schedule_title: string
    schedule_type: string
    schedule_start: string
    team_id: number
    team_name: string
    sport_name: string
    student_id: number
    student_id_number: string | null
    student_name: string
    status: 'present' | 'absent' | 'late' | 'excused' | 'no_response'
    verification_method: string | null
    recorded_at: string | null
    override_reason: string | null
}

type PaginatedPayload = {
    data: AttendanceRow[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number | null
        to: number | null
    }
    links: {
        next: string | null
        prev: string | null
    }
    totals: {
        total_records: number
        present: number
        absent: number
        late: number
        excused: number
        no_response: number
    }
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
        active: 'calendar' | 'attendance' | 'exceptions'
        available: Array<'calendar' | 'attendance' | 'exceptions'>
    }
    calendarSchedules: Array<{
        id: number
        title: string
        type: string
        venue: string
        team_name: string
        sport: string
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
    attendanceRecords: PaginatedPayload
    exceptionsRecords: PaginatedPayload
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

const { sportColor, sportTextColor, sportLabel, normalizeSport } = useSportColors()

const activeTab = ref<'calendar' | 'attendance' | 'exceptions'>(props.tabs.active)
const showFilters = ref(false)
const isLoadingRecords = ref(false)
const isSavingOverride = ref(false)
const showExcuseDialog = ref(false)
const excuseReason = ref('')
const pendingExcuse = ref<{ scheduleId: number; studentId: number } | null>(null)
const noticeDialog = ref<{ open: boolean; title: string; description: string }>({
    open: false,
    title: '',
    description: '',
})

const attendanceState = ref<PaginatedPayload>(props.attendanceRecords)
const exceptionsState = ref<PaginatedPayload>(props.exceptionsRecords)

const selectedScheduleId = ref<number | null>(props.filters.selected.schedule_id)
const drilldownLoading = ref(false)
const scheduleDrawerOpen = ref(false)
const drilldown = ref<any>(null)

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

const calendarSportFilter = ref<'all' | string>('all')

const sportsLegend = computed(() =>
    supportedSports.map((sport) => ({
        key: sport,
        label: sportLabel(sport),
        color: sportColor(sport),
        textColor: sportTextColor(sport),
    })),
)

const activeFilterCount = computed(() => {
    let count = 0
    if (filterForm.sport_id) count++
    if (filterForm.team_id) count++
    if (filterForm.coach_id) count++
    if (filterForm.schedule_type) count++
    if (filterForm.status) count++
    if (filterForm.search.trim()) count++
    if (filterForm.period) count++
    if (filterForm.start_date || filterForm.end_date) count++
    if (filterForm.sort !== 'schedule_start' || filterForm.direction !== 'desc') count++
    return count
})

const filteredCalendarSchedules = computed(() =>
    calendarSportFilter.value === 'all'
        ? props.calendarSchedules
        : props.calendarSchedules.filter((item) => normalizeSport(item.sport) === normalizeSport(calendarSportFilter.value)),
)

const calendarEvents = computed(() =>
    filteredCalendarSchedules.value.map((item) => ({
        id: item.id,
        title: item.title,
        start: new Date(item.start),
        end: new Date(item.end),
        content: `${item.team_name} • ${item.type}`,
        backgroundColor: sportColor(item.sport),
        color: sportTextColor(item.sport),
    })),
)

const visibleTable = computed(() => (activeTab.value === 'exceptions' ? exceptionsState.value : attendanceState.value))

watch(() => props.attendanceRecords, (value) => {
    attendanceState.value = value
})

watch(() => props.exceptionsRecords, (value) => {
    exceptionsState.value = value
})

watch(() => props.tabs.active, (value) => {
    activeTab.value = value
})

function buildQuery(extra: Record<string, string | number | undefined> = {}) {
    return {
        tab: activeTab.value,
        search: filterForm.search.trim() || undefined,
        sport_id: filterForm.sport_id || undefined,
        team_id: filterForm.team_id || undefined,
        coach_id: filterForm.coach_id || undefined,
        schedule_id: selectedScheduleId.value || undefined,
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
    selectedScheduleId.value = null
    applyFilters()
}

function setPeriod(period: '' | 'today' | 'week' | 'month') {
    filterForm.period = period
    if (period) {
        filterForm.start_date = ''
        filterForm.end_date = ''
    }
    applyFilters()
}

function setTab(tab: 'calendar' | 'attendance' | 'exceptions') {
    activeTab.value = tab
    if (tab === 'exceptions' && !filterForm.status) {
        filterForm.status = ''
    }
    applyFilters()
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

function tableStatusClass(status: AttendanceRow['status']) {
    if (status === 'present') return 'bg-emerald-100 text-emerald-700'
    if (status === 'late') return 'bg-amber-100 text-amber-700'
    if (status === 'absent') return 'bg-red-100 text-red-700'
    if (status === 'excused') return 'bg-slate-100 text-slate-700'
    return 'bg-slate-100 text-slate-700'
}

function scheduleCardStyle(sport: unknown) {
    const textColor = sportTextColor(sport)
    const isDarkText = textColor === '#111827'
    return {
        backgroundColor: sportColor(sport),
        color: textColor,
        borderColor: isDarkText ? 'rgba(15,23,42,0.2)' : 'rgba(255,255,255,0.35)',
    }
}

function scheduleSubTextClass(sport: unknown) {
    return sportTextColor(sport) === '#111827' ? 'text-slate-700' : 'text-white/80'
}

function scheduleAlertTextClass(sport: unknown) {
    return sportTextColor(sport) === '#111827' ? 'text-amber-700' : 'text-amber-200'
}

async function fetchRecords(page = 1) {
    isLoadingRecords.value = true

    const params = new URLSearchParams()
    const query = buildQuery({ page })
    Object.entries(query).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return
        params.set(key, String(value))
    })

    if (activeTab.value === 'exceptions') {
        params.set('exception_only', '1')
    }

    const response = await fetch(`/operations/attendance/records?${params.toString()}`, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    })

    isLoadingRecords.value = false

    if (!response.ok) {
        return
    }

    const payload = await response.json() as PaginatedPayload
    if (activeTab.value === 'exceptions') {
        exceptionsState.value = payload
    } else {
        attendanceState.value = payload
    }
}

async function openScheduleDrawer(scheduleId: number) {
    selectedScheduleId.value = scheduleId
    drilldownLoading.value = true
    scheduleDrawerOpen.value = true

    const response = await fetch(`/operations/schedules/${scheduleId}/drilldown`, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    })

    drilldownLoading.value = false

    if (!response.ok) {
        drilldown.value = null
        return
    }

    drilldown.value = await response.json()
}

function onCalendarEventClick(payload: any) {
    const event = payload?.event ?? payload
    if (!event?.id) return
    openScheduleDrawer(Number(event.id))
}

function openAttendanceFromDrawer() {
    if (!drilldown.value?.schedule?.id) return
    activeTab.value = 'attendance'
    selectedScheduleId.value = drilldown.value.schedule.id
    filterForm.status = ''
    applyFilters()
}

function openNoResponseFromDrawer() {
    if (!drilldown.value?.schedule?.id) return
    activeTab.value = 'exceptions'
    selectedScheduleId.value = drilldown.value.schedule.id
    filterForm.status = 'no_response'
    applyFilters()
}

function closeDrawer() {
    scheduleDrawerOpen.value = false
}

function showNotice(title: string, description: string) {
    noticeDialog.value = { open: true, title, description }
}

function markExcused(scheduleId: number, studentId: number, currentStatus: AttendanceRow['status']) {
    if (currentStatus === 'excused') return
    pendingExcuse.value = { scheduleId, studentId }
    excuseReason.value = ''
    showExcuseDialog.value = true
}

async function confirmMarkExcused() {
    if (!pendingExcuse.value || !excuseReason.value.trim()) return

    isSavingOverride.value = true

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch(`/operations/attendance/${pendingExcuse.value.scheduleId}/${pendingExcuse.value.studentId}`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken ?? '',
        },
        body: JSON.stringify({
            status: 'excused',
            reason: excuseReason.value.trim(),
        }),
    })

    isSavingOverride.value = false

    if (!response.ok) {
        showNotice('Save Failed', 'Unable to save attendance override.')
        return
    }

    showExcuseDialog.value = false
    pendingExcuse.value = null

    if (scheduleDrawerOpen.value && selectedScheduleId.value) {
        await openScheduleDrawer(selectedScheduleId.value)
    }

    await fetchRecords(visibleTable.value.meta.current_page)
}

function printReport() {
    const params = new URLSearchParams()
    const query = buildQuery()
    Object.entries(query).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return
        params.set(key, String(value))
    })

    window.open(`/operations/attendance/print?${params.toString()}`, '_blank')
}

function printCalendarSchedules() {
    const params = new URLSearchParams()
    if (filterForm.team_id) params.set('team_id', filterForm.team_id)
    if (filterForm.schedule_type) params.set('schedule_type', filterForm.schedule_type)
    if (filterForm.period) params.set('period', filterForm.period)
    if (filterForm.start_date) params.set('start_date', filterForm.start_date)
    if (filterForm.end_date) params.set('end_date', filterForm.end_date)
    if (calendarSportFilter.value !== 'all') params.set('sport', calendarSportFilter.value)

    const query = params.toString()
    window.open(`/operations/schedules/print${query ? `?${query}` : ''}`, '_blank')
}
</script>

<template>
    <div class="space-y-5">
        <section class="rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Operations Workspace</p>
            <h1 class="mt-2 text-2xl font-bold">Attendance And Schedule Monitoring</h1>
            <p class="mt-2 max-w-3xl text-sm text-white/85">
                Review team schedules, monitor attendance activity, inspect exception cases, and open schedule drilldowns from one shared operations workspace.
            </p>
        </section>

        <section class="rounded-3xl border border-[#034485]/35 bg-white p-5">
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    v-model="filterForm.search"
                    type="text"
                    placeholder="Search schedule, team, sport, or student"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm sm:flex-1"
                    @keyup.enter="applyFilters"
                />
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="applyFilters">
                    Search
                </button>
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="showFilters = !showFilters">
                    Filters <span v-if="activeFilterCount" class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs">{{ activeFilterCount }}</span>
                </button>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
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
                <button
                    type="button"
                    class="ml-auto rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    @click="printReport"
                >
                    Print
                </button>
            </div>

            <div v-if="showFilters" class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-200 pt-3 md:grid-cols-2 lg:grid-cols-4">
                <select v-model="filterForm.team_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Teams</option>
                    <option v-for="team in filters.options.teams" :key="team.id" :value="String(team.id)">
                        {{ team.team_name }} ({{ team.sport_name }})
                    </option>
                </select>

                <select v-model="filterForm.schedule_type" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Schedule Types</option>
                    <option v-for="type in filters.options.schedule_types" :key="type" :value="type">{{ type }}</option>
                </select>

                <select v-model="filterForm.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    <option v-for="status in filters.options.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>

                <input v-model="filterForm.start_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="filterForm.end_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />

                <div class="grid grid-cols-3 gap-2">
                    <select v-model="filterForm.sort" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="schedule_start">Sort: Date</option>
                        <option value="team_name">Sort: Team</option>
                        <option value="student_name">Sort: Student</option>
                        <option value="status">Sort: Status</option>
                    </select>
                    <select v-model="filterForm.direction" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="desc">Desc</option>
                        <option value="asc">Asc</option>
                    </select>
                    <select v-model="filterForm.per_page" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="applyFilters">Apply</button>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="resetFilters">Reset</button>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-[#034485]/35 bg-white p-4">
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="activeTab === 'calendar' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="setTab('calendar')"
                >
                    Calendar
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="activeTab === 'attendance' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="setTab('attendance')"
                >
                    Attendance
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="activeTab === 'exceptions' ? 'bg-[#1f2937] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="setTab('exceptions')"
                >
                    Exceptions
                </button>
            </div>

            <div v-if="activeTab === 'calendar'" class="grid grid-cols-1 gap-4 xl:grid-cols-4">
                <section class="xl:col-span-3 overflow-hidden rounded-2xl border border-[#034485]/25 bg-white p-3">
                    <div class="mb-3 flex flex-wrap items-center gap-2" aria-label="Sport legend">
                        <button
                            type="button"
                            class="sports-legend-chip inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-medium"
                            :class="
                                calendarSportFilter === 'all'
                                    ? 'border-[#034485] bg-[#034485] text-white'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'
                            "
                            @click="calendarSportFilter = 'all'"
                        >
                            <span class="h-2.5 w-2.5 rounded-full bg-slate-400" />
                            All
                        </button>
                        <button
                            v-for="item in sportsLegend"
                            :key="item.key"
                            type="button"
                            class="sports-legend-chip inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-medium transition"
                            :class="
                                calendarSportFilter === item.key
                                    ? ''
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'
                            "
                            :style="
                                calendarSportFilter === item.key
                                    ? { backgroundColor: item.color, color: item.textColor, borderColor: item.color }
                                    : undefined
                            "
                            @click="calendarSportFilter = item.key"
                        >
                            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: item.color }" />
                            {{ item.label }}
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
                        @event-click="onCalendarEventClick"
                    />
                </section>

                <aside class="max-h-[660px] overflow-y-auto rounded-2xl border border-[#034485]/25 bg-[#034485]/5 p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-slate-800">Schedules</h2>
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-700 hover:border-slate-300"
                            @click="printCalendarSchedules"
                        >
                            {{ calendarSportFilter === 'all' ? 'Print All' : `Print ${sportLabel(calendarSportFilter)}` }}
                        </button>
                    </div>
                    <div v-if="filteredCalendarSchedules.length === 0" class="text-sm text-slate-500">No schedules found.</div>
                    <button
                        v-for="item in filteredCalendarSchedules"
                        :key="item.id"
                        type="button"
                        class="mb-2 w-full rounded-lg border p-3 text-left"
                        :style="scheduleCardStyle(item.sport)"
                        @click="openScheduleDrawer(item.id)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-base font-semibold">{{ item.title }}</p>
                            <span
                                class="rounded border border-white/70 bg-white px-2 py-0.5 text-[11px] font-semibold"
                                :style="{ color: sportColor(item.sport) }"
                            >
                                {{ sportLabel(item.sport) }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm font-semibold" :class="scheduleSubTextClass(item.sport)">{{ item.team_name }}</p>
                        <p class="text-xs" :class="scheduleSubTextClass(item.sport)">{{ item.type }}</p>
                        <p class="text-xs" :class="scheduleSubTextClass(item.sport)">{{ formatDateTime(item.start) }}</p>
                        <p class="mt-1 text-xs" :class="scheduleAlertTextClass(item.sport)">No response: {{ item.counts.no_response }}</p>
                    </button>
                </aside>
            </div>

            <div v-else class="space-y-3">
                <div class="overflow-x-auto rounded-2xl border border-[#034485]/25">
                    <table class="w-full text-sm">
                        <thead class="bg-[#034485] text-white">
                            <tr>
                                <th class="px-3 py-2 text-left">Schedule</th>
                                <th class="px-3 py-2 text-left">Team</th>
                                <th class="px-3 py-2 text-left">Student</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Recorded</th>
                                <th class="px-3 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <transition-group name="table-fade" tag="tbody">
                            <tr v-if="visibleTable.data.length === 0" key="empty">
                                <td colspan="6" class="px-3 py-4 text-center text-slate-500">No records found.</td>
                            </tr>
                            <tr v-for="row in visibleTable.data" :key="`${row.schedule_id}-${row.student_id}`" class="border-t border-slate-200">
                                <td class="px-3 py-2">
                                    <p class="font-medium text-slate-900">{{ row.schedule_title }}</p>
                                    <p class="text-xs text-slate-500">{{ formatDateTime(row.schedule_start) }}</p>
                                </td>
                                <td class="px-3 py-2 text-slate-700">{{ row.team_name }}</td>
                                <td class="px-3 py-2">
                                    <p class="font-medium text-slate-900">{{ row.student_name }}</p>
                                    <p class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="tableStatusClass(row.status)">
                                        {{ row.status.replaceAll('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-600">{{ formatDateTime(row.recorded_at) }}</td>
                                <td class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-100"
                                        :disabled="isSavingOverride"
                                        @click="markExcused(row.schedule_id, row.student_id, row.status)"
                                    >
                                        Mark Excused
                                    </button>
                                </td>
                            </tr>
                        </transition-group>
                    </table>
                </div>

                <div class="flex items-center justify-between">
                    <p class="text-xs text-slate-500">
                        Showing {{ visibleTable.meta.from || 0 }}-{{ visibleTable.meta.to || 0 }} of {{ visibleTable.meta.total }}
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-3 py-1 text-sm"
                            :disabled="isLoadingRecords || visibleTable.meta.current_page <= 1"
                            @click="fetchRecords(visibleTable.meta.current_page - 1)"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-3 py-1 text-sm"
                            :disabled="isLoadingRecords || visibleTable.meta.current_page >= visibleTable.meta.last_page"
                            @click="fetchRecords(visibleTable.meta.current_page + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="scheduleDrawerOpen" class="fixed inset-0 z-40 bg-black/20" @click="closeDrawer"></div>
        <aside
            v-if="scheduleDrawerOpen"
            class="fixed inset-y-0 right-0 z-50 w-full max-w-xl border-l border-[#034485]/25 bg-white p-5"
        >
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Schedule Attendance Drilldown</h2>
                    <p class="text-xs text-slate-500">Click roster row actions for admin overrides.</p>
                </div>
                <button type="button" class="rounded border border-slate-300 px-2 py-1 text-sm" @click="closeDrawer">Close</button>
            </div>

            <div v-if="drilldownLoading" class="text-sm text-slate-500">Loading schedule details...</div>

            <div v-else-if="drilldown" class="space-y-4">
                <div class="rounded-2xl border border-[#034485]/30 bg-[#034485]/5 p-4">
                    <p class="text-base font-semibold text-slate-900">{{ drilldown.schedule.title }}</p>
                    <p class="text-xs text-slate-600">{{ drilldown.schedule.team_name }} • {{ drilldown.schedule.sport_name }}</p>
                    <p class="text-xs text-slate-600">{{ formatDateTime(drilldown.schedule.start_time) }}</p>

                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div class="rounded border border-slate-200 bg-white p-2">Present: <strong>{{ drilldown.counts.present }}</strong></div>
                        <div class="rounded border border-slate-200 bg-white p-2">Late: <strong>{{ drilldown.counts.late }}</strong></div>
                        <div class="rounded border border-slate-200 bg-white p-2">Absent: <strong>{{ drilldown.counts.absent }}</strong></div>
                        <div class="rounded border border-slate-200 bg-white p-2">Excused: <strong>{{ drilldown.counts.excused }}</strong></div>
                        <div class="rounded border border-slate-200 bg-white p-2">No Response: <strong>{{ drilldown.counts.no_response }}</strong></div>
                        <div class="rounded border border-slate-200 bg-white p-2">Roster: <strong>{{ drilldown.counts.total }}</strong></div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="rounded-md bg-[#1f2937] px-3 py-2 text-xs font-semibold text-white" @click="openAttendanceFromDrawer">
                            Open Attendance
                        </button>
                        <button type="button" class="rounded-md bg-amber-600 px-3 py-2 text-xs font-semibold text-white" @click="openNoResponseFromDrawer">
                            View No Response
                        </button>
                    </div>
                </div>

                <div class="max-h-[55vh] overflow-y-auto rounded-2xl border border-[#034485]/25">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-[#034485] text-white">
                            <tr>
                                <th class="px-2 py-2 text-left">Student</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in drilldown.roster" :key="row.student_id" class="border-t border-slate-200">
                                <td class="px-2 py-2">{{ row.student_name }}</td>
                                <td class="px-2 py-2">
                                    <span class="rounded-full px-2 py-0.5" :class="tableStatusClass(row.attendance_status)">
                                        {{ row.attendance_status.replaceAll('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-2 py-2">
                                    <button
                                        type="button"
                                        class="rounded border border-slate-300 px-2 py-1 text-slate-700 hover:bg-slate-100"
                                        @click="markExcused(drilldown.schedule.id, row.student_id, row.attendance_status)"
                                    >
                                        Mark Excused
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>

        <ConfirmDialog
            :open="showExcuseDialog"
            title="Mark Attendance as Excused"
            description="Provide an admin audit reason before applying this override."
            confirm-text="Save Override"
            :loading="isSavingOverride"
            @update:open="showExcuseDialog = $event"
            @confirm="confirmMarkExcused"
        >
            <label class="mb-1 block text-xs font-medium text-slate-600">Override Reason</label>
            <textarea
                v-model="excuseReason"
                rows="3"
                class="w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm"
                placeholder="Required reason"
            />
            <p v-if="!excuseReason.trim()" class="mt-1 text-xs text-red-600">Reason is required.</p>
        </ConfirmDialog>

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

<style scoped>
.table-fade-enter-active,
.table-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.table-fade-enter-from,
.table-fade-leave-to {
    opacity: 0;
    transform: translateY(6px);
}

.table-fade-move {
    transition: transform 0.2s ease;
}
</style>
