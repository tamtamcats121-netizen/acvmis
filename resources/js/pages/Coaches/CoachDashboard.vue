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
  wellness_pending: number
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

type WellnessSnapshot = {
  injury_observed_count: number
  avg_fatigue: number
  performance_breakdown: {
    excellent: number
    good: number
    fair: number
    poor: number
  }
  recent_injury_notes: Array<{
    id: number
    student_name: string
    student_id_number?: string | null
    injury_notes: string
    log_date?: string | null
  }>
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
  }
  wellness?: Partial<WellnessSnapshot>
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
  wellness_pending: props.metrics?.wellness_pending ?? 0,
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

const hasAttendanceTrendData = computed(() =>
  attendanceTrendSeries.value.some((entry) => entry.data.some((value) => value > 0)),
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

const wellnessSnapshot = computed<WellnessSnapshot>(() => ({
  injury_observed_count: props.wellness?.injury_observed_count ?? 0,
  avg_fatigue: props.wellness?.avg_fatigue ?? 0,
  performance_breakdown: {
    excellent: props.wellness?.performance_breakdown?.excellent ?? 0,
    good: props.wellness?.performance_breakdown?.good ?? 0,
    fair: props.wellness?.performance_breakdown?.fair ?? 0,
    poor: props.wellness?.performance_breakdown?.poor ?? 0,
  },
  recent_injury_notes: props.wellness?.recent_injury_notes ?? [],
}))

const performanceConditionSeries = computed(() => [
  wellnessSnapshot.value.performance_breakdown.excellent,
  wellnessSnapshot.value.performance_breakdown.good,
  wellnessSnapshot.value.performance_breakdown.fair,
  wellnessSnapshot.value.performance_breakdown.poor,
])

const hasPerformanceConditionData = computed(() => performanceConditionSeries.value.some((value) => value > 0))

const performanceConditionOptions = computed<ApexOptions>(() => ({
  chart: {
    type: 'donut',
    toolbar: { show: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  labels: ['Excellent', 'Good', 'Fair', 'Poor'],
  colors: ['#034485', '#2563eb', '#60a5fa', '#93c5fd'],
  legend: {
    position: 'bottom',
    labels: { colors: isDarkMode.value ? '#cbd5e1' : '#475569' },
  },
  stroke: {
    colors: [isDarkMode.value ? '#171717' : '#ffffff'],
    width: 4,
  },
  dataLabels: {
    enabled: false,
  },
  plotOptions: {
    pie: {
      donut: {
        size: '70%',
        labels: {
          show: true,
          name: {
            show: true,
            color: isDarkMode.value ? '#94a3b8' : '#64748b',
            fontSize: '12px',
            offsetY: 18,
          },
          value: {
            show: true,
            color: isDarkMode.value ? '#f8fafc' : '#0f172a',
            fontSize: '28px',
            fontWeight: 700,
            offsetY: -10,
            formatter: (value: string) => String(Math.round(Number(value))),
          },
          total: {
            show: true,
            label: 'Total Logs',
            color: isDarkMode.value ? '#94a3b8' : '#64748b',
            formatter: () => String(performanceConditionSeries.value.reduce((sum, value) => sum + value, 0)),
          },
        },
      },
    },
  },
  tooltip: {
    theme: isDarkMode.value ? 'dark' : 'light',
  },
}))

function formatIsoDate(value?: string | null) {
  if (!value) return 'Recent'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

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
              Access schedule coordination, attendance posting, wellness monitoring, and team oversight from one organized coaching workspace.
            </p>
          </div>

          <div v-if="props.team?.sport" class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-white/80">Sport:</span>
            <span
              class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white shadow-sm"
            >
              {{ sportLabel(props.team.sport) }}
            </span>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4" :style="cardMotion(2)">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Upcoming Sessions</p>
          <p class="mt-2 text-2xl font-bold text-[#034485]">{{ safeMetrics.upcoming_sessions }}</p>
        </article>

        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4" :style="cardMotion(3)">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Attendance Pending</p>
          <p class="mt-2 text-2xl font-bold text-[#034485]">{{ safeMetrics.attendance_needs_review }}</p>
        </article>

        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4" :style="cardMotion(4)">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Wellness Pending</p>
          <p class="mt-2 text-2xl font-bold text-[#034485]">{{ safeMetrics.wellness_pending }}</p>
        </article>

        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4" :style="cardMotion(5)">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Active Roster</p>
          <p class="mt-2 text-2xl font-bold text-[#034485]">{{ safeMetrics.roster_total }}</p>
        </article>
      </section>

      <section class="page-card rounded-2xl border border-[#034485]/22 bg-white p-5" :style="cardMotion(6)">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#034485]">Primary Actions</p>
          <h2 class="mt-2 text-xl font-bold text-slate-900">Coach Workspace Actions</h2>
          <p class="mt-1 text-sm text-slate-600">Jump directly into the most important team coordination tasks.</p>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <Link
            href="/coach/schedule"
            class="rounded-2xl border border-[#034485]/18 bg-slate-50 px-4 py-4 transition hover:border-[#034485]/35 hover:bg-[#034485]/5"
          >
            <p class="text-sm font-semibold text-slate-900">Open Schedule</p>
            <p class="mt-1 text-xs text-slate-500">Review upcoming practices, games, and meetings.</p>
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
            href="/coach/wellness"
            class="rounded-2xl border border-[#034485]/18 bg-slate-50 px-4 py-4 transition hover:border-[#034485]/35 hover:bg-[#034485]/5"
          >
            <p class="text-sm font-semibold text-slate-900">Open Wellness</p>
            <p class="mt-1 text-xs text-slate-500">Log post-session injury, fatigue, and condition observations.</p>
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

      <section class="page-card rounded-2xl border border-[#034485]/22 bg-white p-5" :style="cardMotion(7)">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#034485]">Attendance Trend</p>
            <h2 class="mt-2 text-xl font-bold text-slate-900">Team Attendance Trend</h2>
            <p class="mt-1 text-sm text-slate-600">Recent attendance posting for present, late, absent, and excused records.</p>
          </div>

          <div class="inline-flex items-center rounded-full border border-[#034485]/18 bg-slate-50 p-1">
            <button
              type="button"
              class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
              :class="attendanceChartMode === 'stacked' ? 'bg-[#034485] text-white shadow-sm' : 'text-slate-600 hover:text-[#034485]'"
              @click="attendanceChartMode = 'stacked'"
            >
              Stacked Bar
            </button>
            <button
              type="button"
              class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
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

      <section class="page-card rounded-2xl border border-[#034485]/22 bg-white p-5" :style="cardMotion(8)">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#034485]">Wellness Snapshot</p>
          <h2 class="mt-2 text-xl font-bold text-slate-900">Team Wellness Snapshot</h2>
          <p class="mt-1 text-sm text-slate-600">Overall team wellness observations based on recent injury flags, fatigue levels, and performance condition logs.</p>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <article class="rounded-2xl border border-[#034485]/16 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Injury Observed Count</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ wellnessSnapshot.injury_observed_count }}</p>
          </article>

          <article class="rounded-2xl border border-[#034485]/16 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Average Fatigue Level</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ wellnessSnapshot.avg_fatigue.toFixed(2) }}</p>
          </article>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
          <div class="rounded-2xl border border-[#034485]/16 bg-slate-50 p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-sm font-semibold text-slate-900">Performance Condition Breakdown</h3>
                <p class="mt-1 text-xs text-slate-500">Distribution of excellent, good, fair, and poor condition logs.</p>
              </div>
            </div>

            <div v-if="hasPerformanceConditionData" class="mt-4">
              <VueApexCharts height="300" type="donut" :options="performanceConditionOptions" :series="performanceConditionSeries" />
            </div>
            <div
              v-else
              class="mt-4 rounded-2xl border border-dashed border-[#034485]/22 bg-white px-4 py-10 text-center text-sm text-slate-500"
            >
              No performance condition data is available yet for this team.
            </div>
          </div>

          <div class="rounded-2xl border border-[#034485]/16 bg-slate-50 p-4">
            <div>
              <h3 class="text-sm font-semibold text-slate-900">Players With Recent Injury Notes</h3>
              <p class="mt-1 text-xs text-slate-500">Latest wellness entries where injury concerns were described by the coach.</p>
            </div>

            <div v-if="wellnessSnapshot.recent_injury_notes.length" class="mt-4 space-y-3">
              <article
                v-for="entry in wellnessSnapshot.recent_injury_notes"
                :key="entry.id"
                class="rounded-2xl border border-[#034485]/12 bg-white px-4 py-3"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-slate-900">{{ entry.student_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ entry.student_id_number || 'No student ID' }}</p>
                  </div>
                  <span class="text-xs font-medium text-slate-500">{{ formatIsoDate(entry.log_date) }}</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-700">{{ entry.injury_notes }}</p>
              </article>
            </div>
            <div
              v-else
              class="mt-4 rounded-2xl border border-dashed border-[#034485]/22 bg-white px-4 py-10 text-center text-sm text-slate-500"
            >
              No recent injury notes have been logged for this team.
            </div>
          </div>
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
