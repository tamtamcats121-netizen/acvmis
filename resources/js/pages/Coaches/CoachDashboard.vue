<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import type { ApexOptions } from 'apexcharts'
import { computed, ref, useSlots } from 'vue'
import VueApexCharts from 'vue3-apexcharts'

import CoachMobileShell from '@/components/coach/CoachMobileShell.vue'
import { useSportColors } from '@/composables/useSportColors'
import { useTheme } from '@/composables/useTheme'

type TeamInfo = {
  id: number
  team_name: string
  sport: string
}

type Metrics = {
  upcoming_sessions: number
  attendance_needs_review: number
  roster_total: number
}

type AttendanceTrend = {
  labels: string[]
  series: {
    present: number[]
    late: number[]
    absent: number[]
    excused: number[]
  }
}

type ScheduleLoadTrend = {
  labels: string[]
  series: number[]
}

type AttendanceActionSchedule = {
  id: number
  title: string
  type?: string | null
  venue?: string | null
  end_time?: string | null
}

const slots = useSlots()
const hasDefaultSlot = computed(() => Boolean(slots.default))

const props = defineProps<{
  team?: TeamInfo | null
  metrics?: Partial<Metrics>
  actions?: {
    attendance_pending_schedule?: AttendanceActionSchedule | null
  }
  trends?: {
    attendance?: Partial<AttendanceTrend>
    schedule_load?: Partial<ScheduleLoadTrend>
  }
}>()

const { sportLabel } = useSportColors()
const { isDarkMode } = useTheme()
const attendanceChartMode = ref<'stacked' | 'line'>('stacked')

function cardMotion(order: number) {
  return { '--card-order': String(order) }
}

const safeMetrics = computed<Metrics>(() => ({
  upcoming_sessions: props.metrics?.upcoming_sessions ?? 0,
  attendance_needs_review: props.metrics?.attendance_needs_review ?? 0,
  roster_total: props.metrics?.roster_total ?? 0,
}))

const attendanceTrend = computed<AttendanceTrend>(() => ({
  labels: props.trends?.attendance?.labels ?? [],
  series: {
    present: props.trends?.attendance?.series?.present ?? [],
    late: props.trends?.attendance?.series?.late ?? [],
    absent: props.trends?.attendance?.series?.absent ?? [],
    excused: props.trends?.attendance?.series?.excused ?? [],
  },
}))

const attendanceTrendSeries = computed(() => [
  { name: 'Present', data: attendanceTrend.value.series.present },
  { name: 'Late', data: attendanceTrend.value.series.late },
  { name: 'Absent', data: attendanceTrend.value.series.absent },
  { name: 'Excused', data: attendanceTrend.value.series.excused },
])

const attendanceStatusTotals = computed(() => [
  attendanceTrend.value.series.present.reduce((sum, value) => sum + Number(value || 0), 0),
  attendanceTrend.value.series.late.reduce((sum, value) => sum + Number(value || 0), 0),
  attendanceTrend.value.series.absent.reduce((sum, value) => sum + Number(value || 0), 0),
  attendanceTrend.value.series.excused.reduce((sum, value) => sum + Number(value || 0), 0),
])

const scheduleLoadTrend = computed<ScheduleLoadTrend>(() => ({
  labels: props.trends?.schedule_load?.labels ?? [],
  series: props.trends?.schedule_load?.series ?? [],
}))

const hasAttendanceTrendData = computed(() =>
  attendanceTrendSeries.value.some((entry) => entry.data.some((value) => value > 0)),
)

const hasAttendanceStatusData = computed(() =>
  attendanceStatusTotals.value.some((value) => value > 0),
)

const hasScheduleLoadData = computed(() =>
  scheduleLoadTrend.value.series.some((value) => value > 0),
)

const attendanceChartOptions = computed<ApexOptions>(() => ({
  chart: {
    type: attendanceChartMode.value === 'stacked' ? 'bar' : 'line',
    stacked: attendanceChartMode.value === 'stacked',
    toolbar: { show: false },
    fontFamily: 'inherit',
    foreColor: '#475569',
    background: 'transparent',
  },
  colors: ['#034485', '#2563eb', '#60a5fa', '#93c5fd'],
  stroke: {
    width: attendanceChartMode.value === 'stacked' ? 0 : [3, 3, 3, 3],
    curve: 'smooth',
  },
  dataLabels: { enabled: false },
  fill: {
    opacity: attendanceChartMode.value === 'stacked' ? 0.95 : 0.2,
    type: attendanceChartMode.value === 'stacked' ? 'solid' : 'gradient',
    gradient: {
      shadeIntensity: 0.2,
      opacityFrom: 0.35,
      opacityTo: 0.05,
      stops: [0, 90, 100],
    },
  },
  legend: {
    position: 'top',
    horizontalAlign: 'left',
    labels: { colors: isDarkMode.value ? '#cbd5e1' : '#475569' },
  },
  plotOptions: {
    bar: {
      borderRadius: 6,
      columnWidth: '50%',
    },
  },
  xaxis: {
    categories: attendanceTrend.value.labels,
    axisBorder: { color: 'rgba(148, 163, 184, 0.32)' },
    axisTicks: { color: 'rgba(148, 163, 184, 0.32)' },
    labels: {
      style: {
        colors: Array(attendanceTrend.value.labels.length).fill(isDarkMode.value ? '#94a3b8' : '#64748b'),
        fontSize: '11px',
      },
    },
  },
  yaxis: {
    min: 0,
    forceNiceScale: true,
    labels: {
      style: {
        colors: [isDarkMode.value ? '#94a3b8' : '#64748b'],
        fontSize: '11px',
      },
    },
  },
  grid: {
    borderColor: isDarkMode.value ? 'rgba(148, 163, 184, 0.14)' : 'rgba(148, 163, 184, 0.18)',
    strokeDashArray: 4,
  },
  tooltip: {
    theme: isDarkMode.value ? 'dark' : 'light',
  },
  responsive: [
    {
      breakpoint: 768,
      options: {
        plotOptions: {
          bar: {
            columnWidth: '68%',
          },
        },
        legend: {
          position: 'bottom',
        },
      },
    },
  ],
}))

const attendanceStatusDonutOptions = computed<ApexOptions>(() => ({
  chart: {
    type: 'donut',
    toolbar: { show: false },
    fontFamily: 'inherit',
    foreColor: '#475569',
    background: 'transparent',
  },
  labels: ['Present', 'Late', 'Absent', 'Excused'],
  colors: ['#034485', '#2563eb', '#60a5fa', '#93c5fd'],
  stroke: {
    colors: ['#ffffff'],
    width: 4,
  },
  dataLabels: {
    enabled: true,
    formatter: (value: number) => `${Math.round(value)}%`,
  },
  legend: {
    position: 'bottom',
    fontSize: '12px',
    labels: {
      colors: isDarkMode.value ? '#94a3b8' : '#64748b',
    },
  },
  plotOptions: {
    pie: {
      donut: {
        size: '68%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Total',
            color: isDarkMode.value ? '#94a3b8' : '#64748b',
            formatter: () => String(attendanceStatusTotals.value.reduce((sum, value) => sum + Number(value || 0), 0)),
          },
          value: {
            show: true,
            color: isDarkMode.value ? '#f8fafc' : '#0f172a',
            fontSize: '24px',
            fontWeight: 700,
          },
        },
      },
    },
  },
  tooltip: {
    theme: isDarkMode.value ? 'dark' : 'light',
    y: {
      formatter: (value: number) => `${Number(value)} record${Number(value) === 1 ? '' : 's'}`,
    },
  },
}))

const scheduleLoadChartOptions = computed<ApexOptions>(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit',
    foreColor: '#475569',
    background: 'transparent',
  },
  colors: ['#034485'],
  dataLabels: { enabled: false },
  plotOptions: {
    bar: {
      borderRadius: 6,
      columnWidth: '52%',
    },
  },
  xaxis: {
    categories: scheduleLoadTrend.value.labels,
    axisBorder: { color: 'rgba(148, 163, 184, 0.32)' },
    axisTicks: { color: 'rgba(148, 163, 184, 0.32)' },
    labels: {
      style: {
        colors: Array(scheduleLoadTrend.value.labels.length).fill(isDarkMode.value ? '#94a3b8' : '#64748b'),
        fontSize: '11px',
      },
    },
  },
  yaxis: {
    min: 0,
    forceNiceScale: true,
    labels: {
      style: {
        colors: [isDarkMode.value ? '#94a3b8' : '#64748b'],
        fontSize: '11px',
      },
    },
  },
  grid: {
    borderColor: isDarkMode.value ? 'rgba(148, 163, 184, 0.14)' : 'rgba(148, 163, 184, 0.18)',
    strokeDashArray: 4,
  },
  tooltip: {
    theme: isDarkMode.value ? 'dark' : 'light',
    y: {
      formatter: (value: number) => `${Number(value)} session${Number(value) === 1 ? '' : 's'}`,
    },
  },
  responsive: [
    {
      breakpoint: 768,
      options: {
        plotOptions: {
          bar: {
            columnWidth: '66%',
          },
        },
      },
    },
  ],
}))

const scheduleLoadSeries = computed(() => [
  { name: 'Sessions', data: scheduleLoadTrend.value.series },
])

const attendanceStatusSeries = computed(() => attendanceStatusTotals.value)

function formatScheduleDateTime(value?: string | null) {
  if (!value) return 'Recently completed'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}
</script>

<template>
  <CoachMobileShell>
    <section v-if="hasDefaultSlot">
      <slot />
    </section>

    <section v-else class="space-y-5">
      <Head title="Coach Dashboard" />

      <section class="page-card rounded-2xl border border-[#034485]/35 bg-[#034485] p-5 text-white" :style="cardMotion(1)">
        <div class="space-y-3">
          <div>
            <h1 class="text-2xl font-bold text-white">
              {{ props.team?.team_name ? `${props.team.team_name} Dashboard` : 'Coach Workspace' }}
            </h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-white/85">
              Access schedule coordination, attendance posting, academic visibility, and team oversight from one organized coaching workspace.
            </p>
          </div>

          <div v-if="props.team?.sport" class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-white/80">Sport:</span>
            <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white shadow-sm">
              {{ sportLabel(props.team.sport) }}
            </span>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4" :style="cardMotion(2)">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Upcoming Sessions</p>
          <p class="mt-2 text-2xl font-bold text-[#034485]">{{ safeMetrics.upcoming_sessions }}</p>
        </article>

        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4" :style="cardMotion(3)">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Attendance Pending</p>
          <p class="mt-2 text-2xl font-bold text-[#034485]">{{ safeMetrics.attendance_needs_review }}</p>
        </article>

        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4" :style="cardMotion(4)">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Active Roster</p>
          <p class="mt-2 text-2xl font-bold text-[#034485]">{{ safeMetrics.roster_total }}</p>
        </article>
      </section>

      <section class="page-card rounded-2xl border border-[#034485]/22 bg-white p-5" :style="cardMotion(5)">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#034485]">Primary Actions</p>
          <h2 class="mt-2 text-xl font-bold text-slate-900">Coach Workspace Actions</h2>
          <p class="mt-1 text-sm text-slate-600">Jump directly into the most important team coordination tasks.</p>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <Link
            href="/coach/schedule"
            class="rounded-2xl border border-[#034485]/18 bg-slate-50 px-4 py-4 transition hover:border-[#034485]/35 hover:bg-[#034485]/5"
          >
            <p class="text-sm font-semibold text-slate-900">Open Schedule</p>
            <p class="mt-1 text-xs text-slate-500">Review upcoming practices and games.</p>
          </Link>

          <Link
            href="/coach/schedule"
            class="rounded-2xl border border-[#034485]/18 bg-slate-50 px-4 py-4 transition hover:border-[#034485]/35 hover:bg-[#034485]/5"
          >
            <p class="text-sm font-semibold text-slate-900">Post Attendance</p>
            <template v-if="props.actions?.attendance_pending_schedule">
              <p class="mt-1 text-xs font-medium text-[#034485]">
                {{ props.actions.attendance_pending_schedule.title }}
              </p>
              <p class="mt-2 text-xs leading-5 text-slate-500">
                {{
                  [
                    props.actions.attendance_pending_schedule.type,
                    props.actions.attendance_pending_schedule.venue,
                    formatScheduleDateTime(props.actions.attendance_pending_schedule.end_time),
                  ]
                    .filter(Boolean)
                    .join(' • ')
                }}
              </p>
            </template>
            <p v-else class="mt-1 text-xs text-slate-500">No completed session is currently waiting for attendance posting.</p>
          </Link>

          <Link
            href="/coach/team"
            class="rounded-2xl border border-[#034485]/18 bg-slate-50 px-4 py-4 transition hover:border-[#034485]/35 hover:bg-[#034485]/5"
          >
            <p class="text-sm font-semibold text-slate-900">View Roster</p>
            <p class="mt-1 text-xs text-slate-500">Inspect assigned players, positions, and team details.</p>
          </Link>
        </div>
      </section>

      <section class="page-card rounded-2xl border border-[#034485]/22 bg-white p-5" :style="cardMotion(5.5)">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#034485]">Schedule Load</p>
          <h2 class="mt-2 text-xl font-bold text-slate-900">Upcoming Weekly Session Load</h2>
          <p class="mt-1 text-sm text-slate-600">Review how many team sessions are scheduled each week across the next six weeks.</p>
        </div>

        <div v-if="hasScheduleLoadData" class="mt-5">
          <VueApexCharts
            height="300"
            type="bar"
            :options="scheduleLoadChartOptions"
            :series="scheduleLoadSeries"
          />
        </div>
        <div
          v-else
          class="mt-5 rounded-2xl border border-dashed border-[#034485]/25 bg-[#034485]/5 px-4 py-10 text-center text-sm text-slate-500"
        >
          No upcoming session load is available right now.
        </div>
      </section>

      <section class="page-card rounded-2xl border border-[#034485]/22 bg-white p-5" :style="cardMotion(5.75)">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#034485]">Attendance Status</p>
          <h2 class="mt-2 text-xl font-bold text-slate-900">Attendance Status Breakdown</h2>
          <p class="mt-1 text-sm text-slate-600">See the current mix of present, late, absent, and excused attendance records in one summary chart.</p>
        </div>

        <div v-if="hasAttendanceStatusData" class="mt-5">
          <VueApexCharts
            height="320"
            type="donut"
            :options="attendanceStatusDonutOptions"
            :series="attendanceStatusSeries"
          />
        </div>
        <div
          v-else
          class="mt-5 rounded-2xl border border-dashed border-[#034485]/25 bg-[#034485]/5 px-4 py-10 text-center text-sm text-slate-500"
        >
          No attendance status records are available right now.
        </div>
      </section>

      <section class="page-card rounded-2xl border border-[#034485]/22 bg-white p-5" :style="cardMotion(6)">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#034485]">Attendance Trend</p>
            <h2 class="mt-2 text-xl font-bold text-slate-900">Team Attendance Trend</h2>
            <p class="mt-1 text-sm text-slate-600">Recent attendance posting for present, late, absent, and excused records.</p>
          </div>

          <div class="grid w-full grid-cols-2 rounded-2xl border border-[#034485]/18 bg-slate-50 p-1 sm:inline-flex sm:w-auto sm:items-center sm:rounded-full">
            <button
              type="button"
              class="w-full rounded-xl px-3 py-2 text-center text-xs font-semibold transition sm:rounded-full sm:py-1.5"
              :class="attendanceChartMode === 'stacked' ? 'bg-[#034485] text-white shadow-sm' : 'text-slate-600 hover:text-[#034485]'"
              @click="attendanceChartMode = 'stacked'"
            >
              Stacked Bar
            </button>
            <button
              type="button"
              class="w-full rounded-xl px-3 py-2 text-center text-xs font-semibold transition sm:rounded-full sm:py-1.5"
              :class="attendanceChartMode === 'line' ? 'bg-[#034485] text-white shadow-sm' : 'text-slate-600 hover:text-[#034485]'"
              @click="attendanceChartMode = 'line'"
            >
              Trend Line
            </button>
          </div>
        </div>

        <div v-if="hasAttendanceTrendData" class="mt-5">
          <VueApexCharts
            height="320"
            :type="attendanceChartMode === 'stacked' ? 'bar' : 'line'"
            :options="attendanceChartOptions"
            :series="attendanceTrendSeries"
          />
        </div>
        <div
          v-else
          class="mt-5 rounded-2xl border border-dashed border-[#034485]/22 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500"
        >
          No attendance trend data is available yet for this team.
        </div>
      </section>
    </section>
  </CoachMobileShell>
</template>

<style scoped>
.page-card {
  opacity: 0;
  transform: translateY(18px) scale(0.985);
  animation: coach-page-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
  animation-delay: calc(var(--card-order, 0) * 45ms);
  will-change: transform, opacity;
}

@keyframes coach-page-card-rise {
  from {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .page-card {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
