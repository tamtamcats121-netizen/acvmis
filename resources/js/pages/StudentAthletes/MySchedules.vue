<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import { VueCal } from 'vue-cal'

import { useSportColors } from '@/composables/useSportColors'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'
import 'vue-cal/style'

defineOptions({
    layout: StudentAthleteDashboard,
})

const props = defineProps<{
    team: {
        id: number
        team_name: string
        sport: string
    } | null
    teams: Array<{ id: number; team_name: string; sport: string }>
    selectedTeamId: number | null
    schedules: any[]
    accessLocked?: boolean
    lockStatus?: string | null
    lockMessage?: string | null
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()
const APP_TIMEZONE = 'Asia/Manila'

const selectedScheduleId = ref<number | null>(null)
const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)
const calendarViewDate = ref<Date | string>('')
const calendarSelectedDate = ref<Date | string>('')
const showCalendar = ref(false)
const showCompleted = ref(false)

const sortedSchedules = computed(() => [...(props.schedules || [])].sort((a, b) => +new Date(a.start) - +new Date(b.start)))
const upcomingSchedules = computed(() => sortedSchedules.value.filter((item) => !isPastSchedule(item)))
const completedSchedules = computed(() => sortedSchedules.value.filter((item) => isPastSchedule(item)))
const nextSchedule = computed(() => upcomingSchedules.value[0] || null)
const recordedAttendanceCount = computed(() => sortedSchedules.value.filter((item) => !!item.attendance_status).length)

const calendarEvents = computed(() =>
    sortedSchedules.value
        .filter((item) => item.start && item.end)
        .map((item: any) => ({
            id: item.id,
            title: item.title,
            start: new Date(item.start),
            end: new Date(item.end),
            content: `${item.type} • ${item.venue || '-'}`,
            backgroundColor: sportColor(item.sport),
            color: sportTextColor(item.sport),
            class: selectedScheduleId.value === item.id ? 'student-schedule--focused' : '',
        })),
)

watch(
    sortedSchedules,
    (items) => {
        if (!items.length || calendarViewDate.value) return
        const firstDate = new Date(items[0].start)
        calendarViewDate.value = firstDate
        calendarSelectedDate.value = firstDate
    },
    { immediate: true },
)

watch(
    () => props.selectedTeamId,
    (val) => {
        selectedTeamId.value = val ?? null
    },
)

onMounted(() => {
    showCalendar.value = window.matchMedia('(min-width: 1280px)').matches
})

function changeTeam() {
    if (!selectedTeamId.value) return
    router.get(
        '/MySchedule',
        { team_id: selectedTeamId.value },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
}

function onCalendarEventClick({ event }: any) {
    focusSchedule(sortedSchedules.value.find((item) => item.id === event.id) ?? null)
}

function focusSchedule(item: any | null) {
    if (!item) return
    selectedScheduleId.value = item.id
    calendarViewDate.value = new Date(item.start)
    calendarSelectedDate.value = new Date(item.start)
}

function formatPHT(dt: string | Date | null) {
    if (!dt) return '-'

    const d = typeof dt === 'string' ? new Date(dt) : dt

    return d.toLocaleString('en-PH', {
        timeZone: APP_TIMEZONE,
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function isPastSchedule(item: any) {
    if (item?.end) return new Date(item.end).getTime() < Date.now()
    if (item?.start) return new Date(item.start).getTime() < Date.now()
    return false
}

function isToday(item: any) {
    if (!item?.start) return false
    const d = new Date(item.start)
    const now = new Date()
    return d.toDateString() === now.toDateString()
}

function statusLabel(status: string | null) {
    if (status === 'present') return 'Present'
    if (status === 'absent') return 'Absent'
    if (status === 'excused') return 'Excused'
    if (status === 'late') return 'Late'
    return 'Waiting for coach'
}

function attendanceLabel(item: any) {
    if (item.attendance_status) return statusLabel(item.attendance_status)
    if (isPastSchedule(item)) return 'Pending encoding'
    return 'Attendance pending'
}

function statusClass(item: any) {
    const status = item.attendance_status
    if (status === 'present') return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
    if (status === 'absent') return 'bg-rose-50 text-rose-700 border border-rose-200'
    if (status === 'excused') return 'bg-amber-50 text-amber-700 border border-amber-200'
    if (status === 'late') return 'bg-slate-100 text-slate-700 border border-slate-300'
    if (isPastSchedule(item)) return 'bg-rose-50 text-rose-700 border border-rose-200'
    return 'bg-[#034485]/5 text-slate-600 border border-[#034485]/20'
}

function timingLabel(item: any) {
    if (isPastSchedule(item)) return 'Completed'
    return 'Upcoming'
}

function timingClass(item: any) {
    return isPastSchedule(item)
        ? 'bg-[#034485]/5 text-slate-600 border border-[#034485]/25'
        : 'bg-[#034485]/5 text-[#1f2937] border border-[#034485]/20'
}

function hexToRgb(value: string) {
    const hex = value.replace('#', '')
    const normalized =
        hex.length === 3
            ? hex
                .split('')
                .map((c) => c + c)
                .join('')
            : hex
    const num = parseInt(normalized, 16)
    return {
        r: (num >> 16) & 255,
        g: (num >> 8) & 255,
        b: num & 255,
    }
}

function mixWithWhite(color: string, amount = 0.4) {
    const { r, g, b } = hexToRgb(color)
    const mix = (channel: number) => Math.round(channel + (255 - channel) * amount)
    return `rgb(${mix(r)}, ${mix(g)}, ${mix(b)})`
}

function stripeColors(sport: any) {
    const base = sportColor(sport)
    return {
        base,
        lighter: mixWithWhite(base, 0.5),
    }
}

function cardMotion(order: number) {
    return { '--card-order': String(order) }
}
</script>

<template>
    <div class="schedule-page-view space-y-5">
        <section class="page-card rounded-3xl border border-[#034485]/35 bg-[#034485] p-5 text-white" :style="cardMotion(1)">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">My Schedule</h1>
                <p class="text-sm text-white/80">View your team schedule. Attendance is now recorded by the coach or assistant coach during the session.</p>
            </div>
            <div v-if="!accessLocked" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <button
                    @click="showCalendar = !showCalendar"
                    class="w-full rounded-lg border border-white/25 bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/15 sm:w-auto"
                >
                    {{ showCalendar ? 'Hide Calendar' : 'Show Calendar' }}
                </button>
                <button
                    @click="showCompleted = !showCompleted"
                    class="w-full rounded-lg border border-white/25 bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/15 sm:w-auto"
                >
                    {{ showCompleted ? 'Hide Completed' : 'Show Completed' }}
                </button>
            </div>
        </div>
        </section>

        <div v-if="accessLocked" class="page-card rounded-xl border border-[#034485]/30 bg-[#034485]/5 p-6 text-slate-700" :style="cardMotion(2)">
            <h2 class="text-sm font-semibold text-slate-800">Schedule Access Paused</h2>
            <p class="mt-1 text-sm text-slate-600">{{ lockMessage || 'Schedule access is paused during the academic submission window.' }}</p>
            <div class="mt-3 text-xs text-slate-600">
                Status:
                <span class="ml-2 inline-flex rounded-full bg-[#034485] px-2 py-0.5 text-[10px] font-semibold text-white">
                    {{ lockStatus || 'Suspended' }}
                </span>
            </div>
            <Link
                href="/AcademicSubmissions"
                class="mt-4 inline-flex rounded-full border border-[#034485]/40 px-3 py-1 text-xs font-semibold text-[#034485] hover:bg-[#034485]/10"
            >
                Open Academic Submissions
            </Link>
        </div>

        <template v-else>
            <div v-if="props.teams.length" class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                <div v-if="props.teams.length > 1" class="flex items-center gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Team</span>
                    <select
                        v-model.number="selectedTeamId"
                        @change="changeTeam"
                        class="rounded-md border border-[#034485]/40 px-2 py-1 text-xs text-slate-700"
                    >
                        <option v-for="teamOption in props.teams" :key="teamOption.id" :value="teamOption.id">
                            {{ teamOption.team_name }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="team" class="flex flex-wrap items-center gap-2 text-sm text-slate-700">
                <span class="rounded px-2 py-0.5" :style="{ backgroundColor: sportColor(team.sport), color: sportTextColor(team.sport) }">
                    {{ sportLabel(team.sport) }}
                </span>
                <span class="text-slate-500">{{ team.team_name }}</span>
            </div>

            <div v-if="!team" class="page-card rounded-xl border border-[#034485]/35 bg-white p-6 text-slate-600" :style="cardMotion(2)">You are not assigned to a team yet.</div>

            <div v-else class="space-y-4">
                <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="page-card rounded-xl border border-[#034485]/35 bg-white p-4" :style="cardMotion(3)">
                        <p class="text-xs text-slate-500">Next session</p>
                        <p v-if="nextSchedule" class="mt-1 text-sm font-semibold text-slate-900">{{ nextSchedule.title }}</p>
                        <p v-if="nextSchedule" class="text-xs text-slate-500">{{ formatPHT(nextSchedule.start) }}</p>
                        <p v-else class="mt-1 text-sm text-slate-500">No upcoming sessions have been scheduled.</p>
                    </div>
                    <div class="page-card rounded-xl border border-[#034485]/35 bg-white p-4" :style="cardMotion(4)">
                        <p class="text-xs text-slate-500">Upcoming sessions</p>
                        <p class="mt-1 text-2xl font-semibold text-[#1f2937]">{{ upcomingSchedules.length }}</p>
                        <p class="text-xs text-slate-500">Visible to your team</p>
                    </div>
                    <div class="page-card rounded-xl border border-[#034485]/35 bg-white p-4" :style="cardMotion(5)">
                        <p class="text-xs text-slate-500">Attendance recorded</p>
                        <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ recordedAttendanceCount }}</p>
                        <p class="text-xs text-slate-500">Schedules with posted status</p>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
                    <section v-if="showCalendar" class="page-card rounded-xl border border-[#034485]/35 bg-white p-4 xl:col-span-3" :style="cardMotion(6)">
                        <p class="mb-3 text-xs text-slate-500">Tip: Click a schedule on the calendar to focus it on the right panel.</p>

                        <VueCal
                            sm
                            style="height: 650px"
                            :events="calendarEvents"
                            v-model:view-date="calendarViewDate"
                            v-model:selected-date="calendarSelectedDate"
                            default-view="week"
                            :time="true"
                            :twelve-hour="true"
                            time-format="h:mm {am}"
                            events-on-month-view
                            @event-click="onCalendarEventClick"
                        />
                    </section>

                    <aside
                        class="page-card max-h-[650px] overflow-y-auto rounded-xl border border-[#034485]/35 bg-white p-4"
                        :style="cardMotion(7)"
                        :class="showCalendar ? 'xl:col-span-2' : 'xl:col-span-5'"
                    >
                        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="font-semibold text-slate-900">Upcoming Sessions</h2>
                                <p class="text-xs text-slate-500">{{ upcomingSchedules.length }} scheduled</p>
                            </div>
                            <span v-if="selectedScheduleId" class="text-xs break-words text-[#1f2937] sm:text-right">Focused schedule selected</span>
                        </div>

                        <div v-if="upcomingSchedules.length === 0" class="text-sm text-slate-500">No upcoming sessions are available at this time.</div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="(item, index) in upcomingSchedules"
                                :key="item.id"
                                class="page-card student-schedule-card relative overflow-hidden rounded-3xl border border-[#034485]/35 bg-white p-5 transition"
                                :style="cardMotion(8 + index)"
                                :class="item.id === selectedScheduleId ? 'border-[#034485] bg-[#034485]/5' : ''"
                            >
                                <div class="pointer-events-none absolute bottom-4 left-4 top-4 flex w-2 gap-1 opacity-75" aria-hidden="true">
                                    <span class="h-full w-1 rounded-full" :style="{ backgroundColor: stripeColors(item.sport).base }"></span>
                                    <span class="h-full w-1 rounded-full" :style="{ backgroundColor: stripeColors(item.sport).lighter }"></span>
                                </div>
                                <div class="relative z-10 pl-6">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="font-semibold leading-tight text-slate-900">{{ item.title }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ item.type }} • {{ item.venue || '-' }}</div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded px-2 py-0.5 text-[10px]" :class="statusClass(item)">
                                            {{ attendanceLabel(item) }}
                                        </span>
                                        <button
                                            @click="focusSchedule(item)"
                                            class="rounded border border-[#034485]/35 bg-white px-2.5 py-1 text-xs text-slate-600 hover:bg-[#034485]/5"
                                        >
                                            Focus
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                    <span class="rounded-full border px-2 py-0.5" :class="timingClass(item)">{{ timingLabel(item) }}</span>
                                    <span v-if="isToday(item)" class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">Today</span>
                                </div>

                                <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
                                    <div class="rounded-2xl border border-[#034485]/12 bg-[#f8fbff] px-3 py-2">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Start</p>
                                        <p class="mt-1">{{ formatPHT(item.start) }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-[#034485]/12 bg-[#f8fbff] px-3 py-2">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">End</p>
                                        <p class="mt-1">{{ formatPHT(item.end) }}</p>
                                    </div>
                                </div>
                                <div v-if="item.notes" class="mt-3 text-xs text-slate-500">{{ item.notes }}</div>
                                <div v-if="item.attendance_notes" class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">Coach note: {{ item.attendance_notes }}</div>
                                </div>
                            </div>
                        </div>

                        <div v-if="showCompleted" class="mt-6">
                            <div class="mb-2 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-800">Completed</h3>
                                <span class="text-xs text-slate-500">{{ completedSchedules.length }}</span>
                            </div>
                            <div v-if="completedSchedules.length === 0" class="text-sm text-slate-500">No completed sessions are available at this time.</div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="(item, index) in completedSchedules"
                                    :key="item.id"
                                    class="page-card student-schedule-card relative overflow-hidden rounded-3xl border border-[#034485]/35 bg-white p-5 transition"
                                    :style="cardMotion(20 + index)"
                                    :class="item.id === selectedScheduleId ? 'border-[#034485] bg-[#034485]/5' : ''"
                                >
                                    <div class="pointer-events-none absolute bottom-4 left-4 top-4 flex w-2 gap-1 opacity-75" aria-hidden="true">
                                        <span class="h-full w-1 rounded-full" :style="{ backgroundColor: stripeColors(item.sport).base }"></span>
                                        <span class="h-full w-1 rounded-full" :style="{ backgroundColor: stripeColors(item.sport).lighter }"></span>
                                    </div>
                                    <div class="relative z-10 pl-6">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="font-semibold leading-tight text-slate-900">{{ item.title }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ item.type }} • {{ item.venue || '-' }}</div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded px-2 py-0.5 text-[10px]" :class="statusClass(item)">
                                                {{ attendanceLabel(item) }}
                                            </span>
                                            <button
                                                @click="focusSchedule(item)"
                                                class="rounded border border-[#034485]/35 bg-white px-2.5 py-1 text-xs text-slate-600 hover:bg-[#034485]/5"
                                            >
                                                Focus
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                        <span class="rounded-full border px-2 py-0.5" :class="timingClass(item)">{{ timingLabel(item) }}</span>
                                    </div>

                                    <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
                                        <div class="rounded-2xl border border-[#034485]/12 bg-[#f8fbff] px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Start</p>
                                            <p class="mt-1">{{ formatPHT(item.start) }}</p>
                                        </div>
                                        <div class="rounded-2xl border border-[#034485]/12 bg-[#f8fbff] px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">End</p>
                                            <p class="mt-1">{{ formatPHT(item.end) }}</p>
                                        </div>
                                    </div>
                                    <div v-if="item.notes" class="mt-3 text-xs text-slate-500">{{ item.notes }}</div>
                                    <div v-if="item.attendance_notes" class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">Coach note: {{ item.attendance_notes }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.schedule-page-view .page-card {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
    animation: student-page-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: calc(var(--card-order, 0) * 55ms);
    will-change: transform, opacity;
}

@keyframes student-page-card-rise {
    from {
        opacity: 0;
        transform: translateY(18px) scale(0.985);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

:deep(.vuecal__event.student-schedule--focused) {
    outline: 2px solid #034485;
    outline-offset: 1px;
}

@media (prefers-reduced-motion: reduce) {
    .schedule-page-view .page-card {
        animation: none;
        opacity: 1;
        transform: none;
    }
}
</style>
