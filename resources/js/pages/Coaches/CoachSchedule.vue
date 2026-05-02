<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { VueCal } from 'vue-cal'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { showAppToast } from '@/composables/useAppToast'
import { supportedSports, useSportColors } from '@/composables/useSportColors'
import { useTheme } from '@/composables/useTheme'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import 'vue-cal/style'

defineOptions({
    layout: CoachDashboard,
})

const props = defineProps<{
    schedules: any[]
    teams: Array<{ id: number; team_name: string; sport: string }>
    selectedTeamId: number | null
}>()

// Layout mode
const layout = ref<'list' | 'calendar'>('list')
const calendarContainer = ref<HTMLElement | null>(null)

const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)
watch(
    () => props.selectedTeamId,
    (val) => {
        selectedTeamId.value = val ?? null
    }
)

watch(
    () => props.schedules,
    (items) => {
        if (!selectedSchedule.value?.id) return
        const refreshed = items.find((item: any) => item.id === selectedSchedule.value.id)
        if (refreshed) {
            selectedSchedule.value = refreshed
        }
    }
)

// Modal state
const showModal = ref(false)
const editingId = ref<number | null>(null)
const modalMode = ref<'view' | 'form' | 'attendance'>('form')
const selectedSchedule = ref<any | null>(null)
const attendanceRows = ref<Array<{
    student_id: number
    student_id_number: string | null
    full_name: string
    jersey_number: string | number | null
    athlete_position: string | null
    status: 'present' | 'absent' | 'late' | 'excused' | null
    notes: string | null
    recorded_at: string | null
    verification_method: string | null
}>>([])
const attendanceLoading = ref(false)
const attendanceSaving = ref(false)
const attendanceError = ref<string | null>(null)
const attendanceMessage = ref<string | null>(null)

// VueCal drag creation resolver
const pendingResolve = ref<any>(null)

// Form state
const form = ref({
    title: '',
    type: 'practice',
    venue: '',
    start_time: '',
    end_time: '',
    notes: '',
})
const canManage = computed(() => selectedTeamId.value !== null)
const ownerSchedules = computed(() => props.schedules.filter((item: any) => item.is_owner))
const { sportColor, sportTextColor } = useSportColors()
const { isDarkMode } = useTheme()
const APP_TIMEZONE = 'Asia/Manila'
const deleteDialogOpen = ref(false)
const pendingDeleteId = ref<number | null>(null)
const sportsLegend = computed(() =>
    supportedSports.map((sport) => ({
        key: sport,
        label: sport.charAt(0).toUpperCase() + sport.slice(1),
        color: sportColor(sport),
    }))
)

function tintHex(hex: string, amount: number) {
    const clean = hex.replace('#', '')
    if (clean.length !== 6) return hex
    const r = parseInt(clean.slice(0, 2), 16)
    const g = parseInt(clean.slice(2, 4), 16)
    const b = parseInt(clean.slice(4, 6), 16)
    const mix = (channel: number) => Math.round(channel + (255 - channel) * amount)
    return `#${mix(r).toString(16).padStart(2, '0')}${mix(g).toString(16).padStart(2, '0')}${mix(b).toString(16).padStart(2, '0')}`
}

function stripeColors(sport: any) {
    const base = sportColor(sport)
    const lighter = tintHex(base, 0.55)
    return { base, lighter }
}

function cardMotion(order: number) {
    return { '--card-order': String(order) }
}

let dragPlaceholderObserver: MutationObserver | null = null

function toLocalInput(dt: string | null) {
    if (!dt) return ''
    return dt.replace(' ', 'T').slice(0, 16)
}

/**
 * Calendar events
 */
const visibleSchedules = computed(() =>
    ownerSchedules.value.filter((item: any) => scheduleStatus(item) !== 'completed')
)

const calendarEvents = computed(() =>
    visibleSchedules.value
        .filter(i => i.start && i.end)
        .map((item: any) => ({
            id: item.id,
            title: item.title,
            start: new Date(item.start),
            end: new Date(item.end),
            backgroundColor: sportColor(item.sport),
            color: sportTextColor(item.sport),
            is_locked: item.is_locked,
            draggable: item.is_owner && !item.is_locked,
            resizable: item.is_owner && !item.is_locked,
        }))
)

function formatPHT(dt: string | Date | null, withDate = true) {
    if (!dt) return ''

    const d = typeof dt === 'string' ? new Date(dt) : dt

    return d.toLocaleString('en-PH', {
        timeZone: APP_TIMEZONE,
        month: withDate ? 'short' : undefined,
        day: withDate ? 'numeric' : undefined,
        year: withDate ? 'numeric' : undefined,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}
function formatPHTime(dt: string | Date | null) {
    if (!dt) return ''
    const d = typeof dt === 'string' ? new Date(dt) : dt

    return d.toLocaleTimeString('en-PH', {
        timeZone: APP_TIMEZONE,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function to12HourTime(hhmm: string) {
    const [hoursRaw, minutes] = hhmm.split(':')
    const hours = Number(hoursRaw)
    if (!Number.isFinite(hours) || !minutes) return hhmm

    const meridiem = hours >= 12 ? 'PM' : 'AM'
    const hour12 = hours % 12 || 12

    return `${hour12}:${minutes} ${meridiem}`
}

function toPHDragPlaceholder(text: string) {
    const match = text.trim().match(/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/)
    if (!match) return text

    return `${to12HourTime(match[1])} - ${to12HourTime(match[2])}`
}

function normalizeDragPlaceholders() {
    const root = calendarContainer.value
    if (!root) return

    root.querySelectorAll('.vuecal__event-placeholder').forEach((node) => {
        const el = node as HTMLElement
        const raw = el.textContent?.trim()
        if (!raw) return

        const formatted = toPHDragPlaceholder(raw)
        if (formatted !== raw) el.textContent = formatted
    })
}

function startDragPlaceholderObserver() {
    stopDragPlaceholderObserver()

    const root = calendarContainer.value
    if (!root) return

    normalizeDragPlaceholders()
    dragPlaceholderObserver = new MutationObserver(() => normalizeDragPlaceholders())
    dragPlaceholderObserver.observe(root, {
        childList: true,
        subtree: true,
        characterData: true,
    })
}
function stopDragPlaceholderObserver() {
    if (dragPlaceholderObserver) {
        dragPlaceholderObserver.disconnect()
        dragPlaceholderObserver = null
    }
}

/**
 * ===== DRAG CREATE FROM CALENDAR =====
 */
function onCalendarCreate({ event, resolve }: any) {
    if (!canManage.value) {
        resolve(false)
        return
    }

    // ❗ Cancel VueCal temporary event immediately
    resolve(false)

    editingId.value = null
    selectedSchedule.value = null
    modalMode.value = 'form'

    form.value.start_time = toLocalInput(toMySQLLocal(event.start))
    form.value.end_time = toLocalInput(toMySQLLocal(event.end))

    showModal.value = true
}
function toMySQLLocal(dt: Date) {
    const pad = (n: number) => String(n).padStart(2, '0')

    return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())} `
        + `${pad(dt.getHours())}:${pad(dt.getMinutes())}:00`
}

function deleteSchedule(id: number) {
    const s = props.schedules.find(i => i.id === id)
    if (!s?.is_owner) return
    if (isLocked(s)) return

    pendingDeleteId.value = id
    deleteDialogOpen.value = true
}

function confirmDeleteSchedule() {
    if (!pendingDeleteId.value) return
    const id = pendingDeleteId.value
    deleteDialogOpen.value = false

    router.delete(`/coach/schedules/${id}`, {
        data: selectedTeamId.value ? { team_id: selectedTeamId.value } : {},
        onSuccess: () => {
            if (selectedSchedule.value?.id === id) closeModal()
        },
    })
}

function isOwnerSchedule(id: number) {
    return !!props.schedules.find(i => i.id === id)?.is_owner
}

function scheduleStatus(item: any): 'upcoming' | 'in_progress' | 'completed' {
    if (item.status) return item.status
    const now = new Date()
    const start = new Date(item.start)
    const end = new Date(item.end)
    if (end < now) return 'completed'
    if (start <= now && end >= now) return 'in_progress'
    return 'upcoming'
}

function statusLabel(status: string) {
    return status === 'in_progress' ? 'In Progress' : status === 'completed' ? 'Completed' : 'Upcoming'
}

function statusTone(status: string) {
    if (status === 'in_progress') return 'bg-amber-100 text-amber-800'
    if (status === 'completed') return 'bg-slate-100 text-slate-700'
    return 'bg-slate-100 text-slate-700'
}

function attendanceState(item: any) {
    if (item.attendance_state) return item.attendance_state
    const attendanceCount = Number(item.attendance_count ?? 0)
    const rosterCount = Number(item.roster_count ?? 0)
    if (attendanceCount === 0) {
        return scheduleStatus(item) === 'completed' ? 'pending' : 'not_started'
    }
    if (rosterCount > 0 && attendanceCount >= rosterCount) return 'completed'
    return 'in_progress'
}

function attendanceLabel(item: any) {
    const attendanceCount = Number(item.attendance_count ?? 0)
    const rosterCount = Number(item.roster_count ?? 0)
    if (rosterCount === 0) return attendanceCount > 0 ? `Attendance ${attendanceCount}` : 'No roster'
    if (attendanceCount === 0) {
        return scheduleStatus(item) === 'completed' ? 'Attendance Pending' : 'Attendance Not Started'
    }
    if (attendanceCount >= rosterCount) return `Attendance Complete ${attendanceCount}/${rosterCount}`
    return `Attendance ${attendanceCount}/${rosterCount}`
}

function attendanceTone(item: any) {
    const state = attendanceState(item)
    if (state === 'completed') return 'bg-emerald-100 text-emerald-700'
    if (state === 'in_progress') return 'bg-amber-100 text-amber-800'
    if (state === 'pending') return 'bg-rose-100 text-rose-700'
    return 'bg-rose-100 text-rose-700'
}

function isLocked(item: any) {
    if (typeof item.is_locked === 'boolean') return item.is_locked
    return scheduleStatus(item) === 'completed' && Number(item.attendance_count ?? 0) > 0
}

function attendanceActionLabel(item: any) {
    const status = scheduleStatus(item)
    const hasAttendance = Number(item.attendance_count ?? 0) > 0
    if (status === 'upcoming') return 'Prepare Attendance'
    if (status === 'completed') return hasAttendance ? 'Review Attendance' : 'Encode Attendance'
    return 'Take Attendance'
}

function canOpenWellness(item: any) {
    return scheduleStatus(item) === 'completed'
        && ['practice', 'game'].includes(String(item.type || '').toLowerCase())
        && Number(item.attendance_count ?? 0) > 0
}

function openWellness(item: any) {
    if (!canOpenWellness(item)) return

    router.get(`/coach/wellness/${item.id}/review`, {}, {
        preserveScroll: true,
        preserveState: false,
    })
}

function openCalendar(url: string | null | undefined) {
    if (!url) return
    const link = document.createElement('a')
    link.href = url
    link.download = ''
    link.target = '_blank'
    link.rel = 'noopener'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}

const groupedSchedules = computed(() => {
    const groups = {
        upcoming: [] as any[],
        in_progress: [] as any[],
        completed: [] as any[],
    }
    for (const item of ownerSchedules.value) {
        const status = scheduleStatus(item)
        groups[status].push(item)
    }
    const asc = (a: any, b: any) => new Date(a.start).getTime() - new Date(b.start).getTime()
    const desc = (a: any, b: any) => new Date(b.start).getTime() - new Date(a.start).getTime()
    groups.upcoming.sort(asc)
    groups.in_progress.sort(asc)
    groups.completed.sort(desc)
    return groups
})

const groupedScheduleSections = computed(() => [
    { key: 'in_progress', items: groupedSchedules.value.in_progress },
    { key: 'upcoming', items: groupedSchedules.value.upcoming },
    { key: 'completed', items: groupedSchedules.value.completed },
])


/**
 * Drag move
 */
function onEventDrag(event: any) {
    const s = props.schedules.find(i => i.id === event.id)
    if (!s?.is_owner) return
    if (isLocked(s)) return

    router.put(`/coach/schedules/${event.id}`, {
        start_time: toMySQLLocal(event.start),
        end_time: toMySQLLocal(event.end),
        team_id: selectedTeamId.value ?? undefined,
    }, {
        preserveScroll: true,
    })
}


/**
 * Resize
 */
function onEventResize(event: any) {
    const s = props.schedules.find(i => i.id === event.id)
    if (!s?.is_owner) return
    if (isLocked(s)) return

    router.put(`/coach/schedules/${event.id}`, {
        start_time: toMySQLLocal(event.start),
        end_time: toMySQLLocal(event.end),
        team_id: selectedTeamId.value ?? undefined,
    }, {
        preserveScroll: true,
    })
}


/**
 * Click existing → edit
 */
function onEventClick({ event }: any) {
    const s = props.schedules.find(i => i.id === event.id)
    if (!s) return

    openViewSchedule(s)
}


/**
 * Click empty cell → create
 */
function onCellClick(date: any) {
    if (!canManage.value) return

    editingId.value = null
    selectedSchedule.value = null
    modalMode.value = 'form'

    const iso = toLocalInput(toMySQLLocal(date.date))
    form.value.start_time = iso
    form.value.end_time = iso

    showModal.value = true
}

/**
 * Save schedule
 */
function toMySQLFromInput(local: string) {
    if (!local) return null
    return local.replace('T', ' ') + ':00'
}

function saveSchedule() {
    if (!canManage.value) return

    const payload = {
        ...form.value,
        start_time: toMySQLFromInput(form.value.start_time),
        end_time: toMySQLFromInput(form.value.end_time),
        team_id: selectedTeamId.value ?? undefined,
    }

    if (editingId.value) {
        router.put(`/coach/schedules/${editingId.value}`, payload, {
            onSuccess: finalizeCreation,
        })
    } else {
        router.post('/coach/schedules', payload, {
            onSuccess: finalizeCreation,
        })
    }
}


/**
 * Called after successful save
 */
function finalizeCreation() {
    if (pendingResolve.value) {
        pendingResolve.value(true)
        pendingResolve.value = null
    }

    closeModal()
}

function openCreateModal() {
    if (!canManage.value) return

    editingId.value = null
    selectedSchedule.value = null
    modalMode.value = 'form'
    resetForm()
    showModal.value = true
}

function startEditFromSelected() {
    const s = selectedSchedule.value
    if (!s || !s.is_owner) return
    if (isLocked(s)) return

    editingId.value = s.id
    modalMode.value = 'form'

    form.value = {
        title: s.title,
        type: s.type,
        venue: s.venue,
        start_time: toLocalInput(toMySQLLocal(new Date(s.start))),
        end_time: toLocalInput(toMySQLLocal(new Date(s.end))),
        notes: s.notes ?? '',
    }
}

function openEditSchedule(item: any) {
    if (!item?.is_owner) return
    if (isLocked(item)) return

    selectedSchedule.value = item
    startEditFromSelected()
    showModal.value = true
}

function openViewSchedule(item: any) {
    selectedSchedule.value = item
    modalMode.value = 'view'
    showModal.value = true
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

function attendanceModalWidthClass() {
    return modalMode.value === 'attendance' ? 'max-w-6xl' : 'max-w-2xl'
}

async function loadAttendanceRoster(scheduleId: number) {
    attendanceLoading.value = true
    attendanceError.value = null

    try {
        const response = await fetch(`/coach/schedules/${scheduleId}/attendance-roster`, {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        })

        const data = await response.json()
        if (!response.ok) {
            attendanceError.value = data?.message ?? 'Unable to load attendance roster.'
            return
        }

        attendanceRows.value = Array.isArray(data?.rows)
            ? data.rows.map((row: any) => ({
                ...row,
                status: row.status ?? null,
                notes: row.notes ?? '',
            }))
            : []
    } catch {
        attendanceError.value = 'Unable to load attendance roster right now.'
    } finally {
        attendanceLoading.value = false
    }
}

function attendanceCanBeSaved(item: any) {
    return scheduleStatus(item) !== 'upcoming'
}

function attendanceModalMessage(item: any) {
    const status = scheduleStatus(item)
    if (status === 'upcoming') {
        return 'Attendance opens when the schedule begins. You can review the roster here before the session starts.'
    }
    if (status === 'completed') {
        return 'This schedule has already ended. You can still encode attendance now if no authorized coach was available during the session.'
    }
    return 'Select each student as present, late, excused, or absent, then save the attendance sheet.'
}

function setRosterStatus(status: 'present' | 'absent' | 'late' | 'excused' | null) {
    attendanceRows.value = attendanceRows.value.map((row) => ({
        ...row,
        status,
        notes: status === 'present' ? '' : row.notes,
    }))
}

function attendanceSelectionCount(status: 'present' | 'absent' | 'late' | 'excused') {
    return attendanceRows.value.filter((row) => row.status === status).length
}

function attendancePendingCount() {
    return attendanceRows.value.filter((row) => !row.status).length
}

function rosterStatusLabel(status: 'present' | 'absent' | 'late' | 'excused' | null) {
    if (status === 'present') return 'Present'
    if (status === 'late') return 'Late'
    if (status === 'excused') return 'Excused'
    if (status === 'absent') return 'Absent'
    return 'Not set'
}

function rosterStatusBadgeTone(status: 'present' | 'absent' | 'late' | 'excused' | null) {
    if (status === 'present') return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    if (status === 'late') return 'border-amber-200 bg-amber-50 text-amber-800'
    if (status === 'excused') return 'border-sky-200 bg-sky-50 text-sky-700'
    if (status === 'absent') return 'border-rose-200 bg-rose-50 text-rose-700'
    return 'border-slate-200 bg-slate-50 text-slate-500'
}

async function saveAttendanceSheet() {
    if (!selectedSchedule.value || !attendanceCanBeSaved(selectedSchedule.value) || attendanceSaving.value) return

    attendanceSaving.value = true
    attendanceError.value = null
    attendanceMessage.value = null

    try {
        const response = await fetch(`/coach/schedules/${selectedSchedule.value.id}/attendance/bulk`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                rows: attendanceRows.value.map((row) => ({
                    student_id: row.student_id,
                    status: row.status,
                    notes: row.status && row.status !== 'present' ? (row.notes ?? '') : '',
                })),
            }),
        })

        const data = await response.json()
        if (!response.ok) {
            attendanceError.value = data?.message ?? 'Unable to save attendance sheet.'
            showAppToast(attendanceError.value, 'error', {
                summary: 'Attendance',
            })
            return
        }

        attendanceMessage.value = data?.message ?? 'Attendance saved successfully.'
        showAppToast(attendanceMessage.value, 'success', {
            summary: 'Attendance',
        })
        await loadAttendanceRoster(selectedSchedule.value.id)
        router.reload({
            only: ['schedules'],
            preserveScroll: true,
            preserveState: true,
        })
    } catch {
        attendanceError.value = 'Unable to save attendance right now.'
        showAppToast(attendanceError.value, 'error', {
            summary: 'Attendance',
        })
    } finally {
        attendanceSaving.value = false
    }
}

function openAttendance(item: any) {
    selectedSchedule.value = item
    modalMode.value = 'attendance'
    attendanceRows.value = []
    attendanceError.value = null
    attendanceMessage.value = null
    showModal.value = true
    void loadAttendanceRoster(item.id)
}

function changeTeam() {
    if (!selectedTeamId.value) return
    router.get('/coach/schedule', { team_id: selectedTeamId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function printScheduleList() {
    if (!selectedTeamId.value) return
    const params = new URLSearchParams()
    params.set('team_id', String(selectedTeamId.value))
    window.open(`/coach/schedule/print?${params.toString()}`, '_blank')
}

/**
 * Cancel modal
 */
function closeModal() {
    if (pendingResolve.value) {
        pendingResolve.value(false)
        pendingResolve.value = null
    }

    showModal.value = false
    editingId.value = null
    modalMode.value = 'form'
    selectedSchedule.value = null
    attendanceRows.value = []
    attendanceLoading.value = false
    attendanceSaving.value = false
    attendanceError.value = null
    attendanceMessage.value = null
    resetForm()
}

function resetForm() {
    form.value = {
        title: '',
        type: 'practice',
        venue: '',
        start_time: '',
        end_time: '',
        notes: '',
    }
}

watch(layout, async (mode) => {
    if (mode === 'calendar') {
        await nextTick()
        startDragPlaceholderObserver()
        return
    }

    stopDragPlaceholderObserver()
})

onMounted(async () => {
    if (layout.value === 'calendar') {
        await nextTick()
        startDragPlaceholderObserver()
    }
})

onBeforeUnmount(() => {
    stopDragPlaceholderObserver()
})
</script>


<template>
    <div class="space-y-4">
        <section class="page-card rounded-3xl border border-[#034485]/35 bg-[#034485] p-5 text-white" :style="cardMotion(1)">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Team scheduling</p>
            <h1 class="mt-2 text-2xl font-bold text-white">Schedule</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-white/85">
                Plan sessions, review upcoming and completed fixtures, and connect attendance and performance monitoring actions from one coaching calendar.
            </p>
        </section>

        <!-- Header -->
        <div class="mb-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div v-if="props.teams.length > 1" class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Team</span>
                        <select
                            v-model.number="selectedTeamId"
                            @change="changeTeam"
                            class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700"
                        >
                            <option v-for="team in props.teams" :key="team.id" :value="team.id">
                                {{ team.team_name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div v-if="props.teams.length" class="flex flex-wrap items-center gap-2">
                <div class="view-toggle" :class="layout === 'calendar' ? 'is-calendar' : 'is-list'">
                    <span class="view-toggle__indicator" :style="layout === 'calendar' ? { transform: 'translateX(100%)' } : { transform: 'translateX(0%)' }" />
                    <button
                        type="button"
                        class="view-toggle__btn"
                        :class="layout === 'list' ? 'is-active' : ''"
                        :aria-pressed="layout === 'list'"
                        @click="layout = 'list'"
                    >
                        List View
                    </button>
                    <button
                        type="button"
                        class="view-toggle__btn"
                        :class="layout === 'calendar' ? 'is-active' : ''"
                        :aria-pressed="layout === 'calendar'"
                        @click="layout = 'calendar'"
                    >
                        Calendar View
                    </button>
                </div>

                <button
                    type="button"
                    @click="printScheduleList"
                    :disabled="!selectedTeamId"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 sm:w-auto"
                >
                    Print
                </button>

                <button @click="openCreateModal" :disabled="!canManage"
                    class="w-full rounded-lg bg-[#1f2937] px-4 py-2 text-sm font-medium text-white hover:bg-[#111827] sm:w-auto">
                    + Create Schedule
                </button>
            </div>
        </div>

        <div v-if="!props.teams.length" class="page-card rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500" :style="cardMotion(2)">
            You are not assigned to a team yet.
        </div>

        <transition name="view-slide" mode="out-in">
            <div v-if="layout === 'list' && props.teams.length" key="list" class="space-y-6">
                <div v-if="ownerSchedules.length === 0" class="page-card rounded-xl border border-slate-200 bg-white py-10 text-center text-sm text-slate-500" :style="cardMotion(3)">
                    No schedules have been created yet.
                </div>

                <div v-else class="space-y-6">
                    <div v-for="(section, sectionIndex) in groupedScheduleSections" :key="section.key" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-slate-900">{{ statusLabel(section.key) }}</h3>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ section.items.length }}</span>
                            </div>
                            <span v-if="section.key === 'completed'" class="text-xs text-slate-500">Past schedules</span>
                        </div>

                        <div
                            v-if="section.items.length === 0"
                            class="rounded-xl border border-dashed px-4 py-6 text-sm"
                            :class="(section.key === 'upcoming' || section.key === 'in_progress')
                                ? 'border-[#034485]/40 text-[#034485]'
                                : 'border-slate-200 bg-white text-slate-500'"
                            :style="(section.key === 'upcoming' || section.key === 'in_progress')
                                ? {
                                    backgroundColor: '#f6f9ff',
                                    backgroundImage: 'repeating-linear-gradient(135deg, rgba(3, 68, 133, 0.12) 0 10px, rgba(255, 255, 255, 0) 10px 20px)',
                                  }
                                : {}"
                        >
                            No {{ statusLabel(section.key).toLowerCase() }} schedules.
                        </div>

                        <article v-for="(item, itemIndex) in section.items" :key="item.id" class="page-card relative overflow-hidden rounded-3xl border border-[#034485]/45 bg-white p-4 shadow-[0_20px_44px_-30px_rgba(3,68,133,0.45)]" :style="cardMotion(4 + sectionIndex * 6 + itemIndex)">
                            <div class="pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-r from-[#034485] via-[#0b5aa6] to-[#034485]/85"></div>
                            <div class="pointer-events-none absolute inset-x-0 top-14 h-14 bg-gradient-to-b from-[#034485]/18 to-transparent"></div>
                            <div class="pointer-events-none absolute left-1/2 top-1/2 flex h-[140%] -translate-x-1/2 -translate-y-1/2 -rotate-6 gap-1 opacity-60">
                                <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(item.sport).base }"></span>
                                <span class="h-full w-1.5" :style="{ backgroundColor: stripeColors(item.sport).lighter }"></span>
                            </div>
                            <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-base font-semibold text-white">{{ item.title }}</div>
                                    <div class="mt-1 inline-flex rounded-full border border-white bg-white px-2.5 py-1 text-[11px] font-semibold text-[#034485] shadow-[0_10px_24px_-18px_rgba(15,23,42,0.7)]">{{ item.type || '-' }} • {{ item.venue || '-' }}</div>
                                    <div class="mt-3 rounded-2xl border border-[#034485]/18 bg-[#edf4ff] px-3 py-2 text-xs font-medium text-slate-700">{{ formatPHT(item.start) }} → {{ formatPHT(item.end) }}</div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusTone(scheduleStatus(item))">
                                        {{ statusLabel(scheduleStatus(item)) }}
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="attendanceTone(item)">
                                        {{ attendanceLabel(item) }}
                                    </span>
                                    <span v-if="scheduleStatus(item) === 'completed' && Number(item.attendance_count ?? 0) === 0"
                                        class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                                        Attendance required
                                    </span>
                                    <span v-if="isLocked(item)" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                        Locked
                                    </span>
                                </div>
                            </div>

                            <div class="relative z-10 mt-4 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    @click="openCalendar(item.calendar_url)"
                                    class="rounded-md border border-[#034485]/25 bg-[#edf4ff] px-3 py-1.5 text-xs font-semibold text-[#034485] hover:border-[#034485]/45 hover:bg-[#ddeaff]"
                                >
                                    + Add to Calendar
                                </button>
                                <button
                                    @click="openAttendance(item)"
                                    class="rounded-md bg-[#034485] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#02396f]"
                                >
                                    {{ attendanceActionLabel(item) }}
                                </button>
                                <button
                                    v-if="canOpenWellness(item)"
                                    @click="openWellness(item)"
                                    class="rounded-md border border-[#034485]/25 bg-white px-3 py-1.5 text-xs font-semibold text-[#034485] hover:border-[#034485]/45 hover:bg-[#f7fbff]"
                                >
                                    Open Performance
                                </button>
                                <button
                                    v-if="item.is_owner && !isLocked(item)"
                                    @click="openEditSchedule(item)"
                                    class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:border-indigo-300"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="item.is_owner && !isLocked(item)"
                                    @click="deleteSchedule(item.id)"
                                    class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:border-rose-300"
                                >
                                    Delete
                                </button>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
            <div v-else-if="layout === 'calendar' && props.teams.length" key="calendar">
                <div class="page-card mb-3 flex flex-wrap gap-4 text-xs" :style="cardMotion(4)">
                    <div v-for="sport in sportsLegend" :key="sport.key" class="flex items-center gap-1 text-slate-700">
                        <span class="w-3 h-3 rounded" :style="{ backgroundColor: sport.color }"></span> {{ sport.label }}
                    </div>
                </div>
                <p class="page-card mb-3 text-xs text-slate-500" :style="cardMotion(5)">
                    Select an open time slot on the calendar to create a schedule.
                </p>
                <div ref="calendarContainer" class="page-card flex justify-center rounded-xl border border-slate-200 bg-white p-4 sm:p-6" :style="cardMotion(6)">
                    <VueCal sm style="height: 500px; width: 100%; max-width: 1150px;" :events="calendarEvents"
                        default-view="month" :time="true" :twelve-hour="true" time-format="h:mm {am}" events-on-month-view
                        :editable-events="canManage" :event-create-min-drag="15" @event-create="onCalendarCreate"
                        @event-click="onEventClick" @cell-click="onCellClick" @event-drop="onEventDrag"
                        @event-duration-change="onEventResize">
                        <!-- CUSTOM EVENT DISPLAY -->
                        <template #event="{ event }">
                            <div class="relative text-sm group pr-5">
                                <button v-if="isOwnerSchedule(event.id) && !event.is_locked"
                                    class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-red-600 text-white text-xs font-bold leading-none opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700"
                                    title="Delete schedule" @mousedown.stop @click.stop="deleteSchedule(event.id)">
                                    ×
                                </button>

                                <div class="text-xs font-medium leading-tight">
                                    {{ event.title }}
                                </div>

                                <div class="text-[10px] opacity-70 leading-tight">
                                    {{ formatPHTime(event.start) }} – {{ formatPHTime(event.end) }}
                                </div>
                            </div>
                        </template>
                    </VueCal>
                </div>
            </div>
        </transition>

        <!-- ========== MODAL ========== -->
        <div v-if="showModal" @click.self="closeModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 px-4 py-6">

            <div class="flex min-h-full items-center justify-center">

            <div
                class="flex max-h-[calc(100vh-3rem)] w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                :class="attendanceModalWidthClass()"
            >

                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Schedule</p>
                        <h2 class="text-lg font-semibold" :class="modalMode === 'view' ? 'text-[#034485]' : 'text-slate-900'">
                            {{ modalMode === 'view'
                                ? 'Schedule Details'
                                : modalMode === 'attendance'
                                    ? 'Attendance Sheet'
                                    : (editingId ? 'Edit Schedule' : 'Create Schedule') }}
                        </h2>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="modalMode === 'view' && selectedSchedule?.is_owner && !isLocked(selectedSchedule)"
                            @click="startEditFromSelected"
                            class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:border-indigo-300"
                        >
                            Edit Schedule
                        </button>

                        <button @click="closeModal" class="rounded-full border border-transparent px-2 py-1 text-slate-400 hover:text-slate-700">
                            ✕
                        </button>
                    </div>
                </div>

                <div v-if="modalMode === 'view'" class="space-y-4 overflow-y-auto p-6 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="selectedSchedule"
                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="statusTone(scheduleStatus(selectedSchedule))"
                        >
                            {{ statusLabel(scheduleStatus(selectedSchedule)) }}
                        </span>
                        <span
                            v-if="selectedSchedule"
                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="attendanceTone(selectedSchedule)"
                        >
                            {{ attendanceLabel(selectedSchedule) }}
                        </span>
                        <span v-if="selectedSchedule && isLocked(selectedSchedule)" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                            Locked
                        </span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Title</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ selectedSchedule?.title || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Type</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ selectedSchedule?.type || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Venue</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ selectedSchedule?.venue || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Time</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">
                                {{ formatPHT(selectedSchedule?.start || null) || '-' }} → {{ formatPHT(selectedSchedule?.end || null) || '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</p>
                        <p class="mt-2 text-sm text-slate-700 whitespace-pre-line">{{ selectedSchedule?.notes || 'No additional notes provided.' }}</p>
                    </div>
                </div>

                <div v-else-if="modalMode === 'attendance'" class="space-y-5 overflow-y-auto p-6">
                    <div class="-mx-6 -mt-6 rounded-t-2xl bg-[#034485] px-6 py-5 text-white">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-white/75">Attendance Sheet</p>
                        <h3 class="mt-1 text-lg font-semibold text-white">{{ selectedSchedule?.title || 'Schedule Attendance' }}</h3>
                        <p class="mt-1 text-sm text-white/80">
                            {{ selectedSchedule?.type || '-' }} • {{ selectedSchedule?.venue || '-' }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border p-4 shadow-sm" :class="isDarkMode ? 'border-slate-700 bg-[#161616]' : 'border-[#034485]/15 bg-[#f7fbff]'">
                            <p class="text-xs font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-slate-300' : 'text-[#034485]'">Schedule</p>
                            <p class="mt-2 text-sm font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ selectedSchedule?.title || '-' }}</p>
                            <p class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-400' : 'text-slate-600'">{{ selectedSchedule?.type || '-' }} • {{ selectedSchedule?.venue || '-' }}</p>
                        </div>
                        <div class="rounded-2xl border p-4 shadow-sm" :class="isDarkMode ? 'border-slate-700 bg-[#161616]' : 'border-[#034485]/15 bg-[#f7fbff]'">
                            <p class="text-xs font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-slate-300' : 'text-[#034485]'">Window</p>
                            <p class="mt-2 text-sm font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ statusLabel(scheduleStatus(selectedSchedule)) }}</p>
                            <p class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-400' : 'text-slate-600'">{{ formatPHT(selectedSchedule?.start || null) }}</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Present</p>
                            <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ attendanceSelectionCount('present') }}</p>
                        </div>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Needs Review</p>
                            <p class="mt-2 text-2xl font-semibold text-amber-700">{{ attendanceSelectionCount('absent') + attendanceSelectionCount('late') + attendanceSelectionCount('excused') }}</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border p-4 text-sm shadow-sm" :class="isDarkMode ? 'border-slate-700 bg-[#161616] text-slate-200' : 'border-[#034485]/20 bg-[#f2f7ff] text-slate-700'">
                        {{ selectedSchedule ? attendanceModalMessage(selectedSchedule) : '' }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
                            @click="setRosterStatus('present')"
                            :disabled="attendanceLoading"
                        >
                            Mark All Present
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:border-rose-300"
                            @click="setRosterStatus('absent')"
                            :disabled="attendanceLoading"
                        >
                            Mark All Absent
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 hover:border-amber-300"
                            @click="setRosterStatus('late')"
                            :disabled="attendanceLoading"
                        >
                            Mark All Late
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-sky-200 px-3 py-2 text-xs font-semibold text-sky-700 hover:border-sky-300"
                            @click="setRosterStatus('excused')"
                            :disabled="attendanceLoading"
                        >
                            Mark All Excused
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-slate-400"
                            @click="setRosterStatus(null)"
                            :disabled="attendanceLoading"
                        >
                            Clear Selection
                        </button>
                    </div>

                    <p v-if="attendanceError" class="text-sm text-rose-700">{{ attendanceError }}</p>
                    <p v-if="attendanceMessage" class="text-sm text-emerald-700">{{ attendanceMessage }}</p>

                    <div v-if="attendanceLoading" class="rounded-2xl border p-6 text-sm" :class="isDarkMode ? 'border-slate-700 bg-[#161616] text-slate-300' : 'border-[#034485]/15 bg-[#f7fbff] text-slate-600'">
                        Loading attendance roster...
                    </div>

                    <div v-else-if="attendanceRows.length === 0" class="rounded-2xl border p-6 text-sm" :class="isDarkMode ? 'border-slate-700 bg-[#161616] text-slate-300' : 'border-[#034485]/15 bg-[#f7fbff] text-slate-600'">
                        No student-athletes are assigned to this team yet.
                    </div>

                    <div v-else class="overflow-hidden rounded-2xl border shadow-[0_18px_40px_-32px_rgba(3,68,133,0.28)]" :class="isDarkMode ? 'border-slate-700' : 'border-[#034485]/15'">
                        <div class="max-h-[50vh] overflow-auto">
                            <table class="w-full min-w-[760px] text-left text-sm" :class="isDarkMode ? 'bg-[#101010]' : 'bg-white'">
                                <thead class="bg-[#034485] text-white">
                                    <tr>
                                        <th class="px-4 py-3">Student</th>
                                        <th class="px-4 py-3">ID Number</th>
                                        <th class="px-4 py-3">Jersey</th>
                                        <th class="px-4 py-3">Position</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Coach Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in attendanceRows" :key="row.student_id" class="border-t align-top" :class="isDarkMode ? 'border-slate-800 even:bg-[#161616]' : 'border-[#034485]/10 even:bg-[#f9fbff]'">
                                        <td class="px-4 py-3 font-medium" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ row.full_name }}</td>
                                        <td class="px-4 py-3" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">{{ row.student_id_number || '-' }}</td>
                                        <td class="px-4 py-3" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">{{ row.jersey_number || '-' }}</td>
                                        <td class="px-4 py-3" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">{{ row.athlete_position || '-' }}</td>
                                        <td class="px-4 py-3">
                                            <div class="mb-2">
                                                <span
                                                    class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                                    :class="rosterStatusBadgeTone(row.status)"
                                                >
                                                    Current: {{ rosterStatusLabel(row.status) }}
                                                </span>
                                            </div>
                                            <div class="grid min-w-[17rem] grid-cols-2 gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                                    :class="row.status === 'present'
                                                        ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm'
                                                        : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100'"
                                                    @click="row.status = 'present'; row.notes = ''"
                                                >
                                                    Present
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                                    :class="row.status === 'late'
                                                        ? 'border-amber-500 bg-amber-500 text-slate-950 shadow-sm'
                                                        : 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-300 hover:bg-amber-100'"
                                                    @click="row.status = 'late'"
                                                >
                                                    Late
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                                    :class="row.status === 'excused'
                                                        ? 'border-sky-600 bg-sky-600 text-white shadow-sm'
                                                        : 'border-sky-200 bg-sky-50 text-sky-700 hover:border-sky-300 hover:bg-sky-100'"
                                                    @click="row.status = 'excused'"
                                                >
                                                    Excused
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                                    :class="row.status === 'absent'
                                                        ? 'border-rose-600 bg-rose-600 text-white shadow-sm'
                                                        : 'border-rose-200 bg-rose-50 text-rose-700 hover:border-rose-300 hover:bg-rose-100'"
                                                    @click="row.status = 'absent'"
                                                >
                                                    Absent
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <textarea
                                                v-model="row.notes"
                                                rows="2"
                                                :disabled="row.status === 'present' || row.status === null"
                                                class="w-full rounded-lg border px-3 py-2 text-xs disabled:bg-slate-100 disabled:text-slate-400"
                                                :class="isDarkMode ? 'border-slate-700 bg-[#161616] text-slate-200' : 'border-[#034485]/20 bg-[#f7fbff] text-slate-700'"
                                                placeholder="Optional note for absent, late, or excused"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-else class="space-y-5 overflow-y-auto p-6">
                    <div class="-mx-6 -mt-6 rounded-t-2xl bg-[#034485] px-6 py-5 text-white">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-white/75">Schedule Planner</p>
                        <h3 class="mt-1 text-lg font-semibold text-white">{{ editingId ? 'Update Session Details' : 'Create a New Session' }}</h3>
                        <p class="mt-1 text-sm text-white/80">Set the title, timing, venue, and optional notes for this team activity.</p>
                    </div>

                    <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] p-4 shadow-sm">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="mt-2 w-full rounded-xl border border-[#034485]/20 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#034485] focus:outline-none focus:ring-2 focus:ring-[#034485]/10"
                            placeholder="e.g. Morning Practice"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] p-4 shadow-sm">
                            <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Type</label>
                            <select
                                v-model="form.type"
                                class="mt-2 w-full rounded-xl border border-[#034485]/20 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#034485] focus:outline-none focus:ring-2 focus:ring-[#034485]/10"
                            >
                                <option value="practice">Practice</option>
                                <option value="game">Game</option>
                                <option value="meeting">Meeting</option>
                            </select>
                        </div>

                        <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] p-4 shadow-sm">
                            <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Venue</label>
                            <input
                                v-model="form.venue"
                                type="text"
                                class="mt-2 w-full rounded-xl border border-[#034485]/20 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#034485] focus:outline-none focus:ring-2 focus:ring-[#034485]/10"
                                placeholder="e.g. Main Gym"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] p-4 shadow-sm">
                            <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Start</label>
                            <input
                                v-model="form.start_time"
                                type="datetime-local"
                                class="mt-2 w-full rounded-xl border border-[#034485]/20 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#034485] focus:outline-none focus:ring-2 focus:ring-[#034485]/10"
                            />
                        </div>
                        <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] p-4 shadow-sm">
                            <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">End</label>
                            <input
                                v-model="form.end_time"
                                type="datetime-local"
                                class="mt-2 w-full rounded-xl border border-[#034485]/20 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#034485] focus:outline-none focus:ring-2 focus:ring-[#034485]/10"
                            />
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] p-4 shadow-sm">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Notes</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="mt-2 w-full rounded-xl border border-[#034485]/20 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#034485] focus:outline-none focus:ring-2 focus:ring-[#034485]/10"
                            placeholder="Optional notes for the team"
                        ></textarea>
                    </div>
                </div>

                <div v-if="modalMode === 'form'" class="flex flex-col-reverse gap-2 border-t border-slate-200 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeModal" class="rounded-md border border-[#034485]/20 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-[#034485]/35 hover:bg-[#f7fbff]">
                        Cancel
                    </button>

                    <button type="button" @click="saveSchedule"
                        class="rounded-md bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#033a70]">
                        {{ editingId ? 'Save Changes' : 'Save Schedule' }}
                    </button>
                </div>

                <div v-else-if="modalMode === 'attendance'" class="flex flex-col-reverse gap-2 border-t border-slate-200 px-6 py-4 sm:flex-row sm:justify-between">
                    <p class="text-xs text-slate-500">
                        Absent: {{ attendanceSelectionCount('absent') }} • Late: {{ attendanceSelectionCount('late') }} • Excused: {{ attendanceSelectionCount('excused') }} • Unset: {{ attendancePendingCount() }}
                    </p>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400">
                            Close
                        </button>
                        <button
                            type="button"
                            @click="saveAttendanceSheet"
                            :disabled="attendanceSaving || !selectedSchedule || !attendanceCanBeSaved(selectedSchedule)"
                            class="rounded-md bg-[#1f2937] px-4 py-2 text-sm font-semibold text-white hover:bg-[#111827] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ attendanceSaving ? 'Saving...' : 'Save Attendance' }}
                        </button>
                    </div>
                </div>
            </div>
            </div>
        </div>

    </div>

    <ConfirmDialog
        :open="deleteDialogOpen"
        title="Delete Schedule"
        description="Delete this schedule? This action cannot be undone."
        confirm-text="Delete"
        confirm-variant="destructive"
        @update:open="deleteDialogOpen = $event"
        @confirm="confirmDeleteSchedule"
    />
</template>

<style scoped>
.view-slide-enter-active,
.view-slide-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.view-slide-enter-from,
.view-slide-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.page-card {
  opacity: 0;
  transform: translateY(18px) scale(0.985);
  animation: coach-schedule-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
  animation-delay: calc(var(--card-order, 0) * 45ms);
  will-change: transform, opacity;
}

@keyframes coach-schedule-card-rise {
  from {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.view-toggle {
  position: relative;
  display: inline-grid;
  grid-template-columns: 1fr 1fr;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgba(3, 68, 133, 0.35);
  background: #ffffff;
  padding: 4px;
  min-width: 220px;
}

.view-toggle__indicator {
  position: absolute;
  top: 4px;
  left: 4px;
  width: calc(50% - 4px);
  height: calc(100% - 8px);
  border-radius: 999px;
  background: #034485;
  transition: transform 0.25s ease;
  z-index: 0;
}

.view-toggle__btn {
  position: relative;
  z-index: 1;
  padding: 0.4rem 0.75rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: #334155;
  background: transparent;
  border: none;
  border-radius: 999px;
  transition: color 0.2s ease;
}

.view-toggle__btn.is-active {
  color: #ffffff;
}

@media (prefers-reduced-motion: reduce) {
  .page-card {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
