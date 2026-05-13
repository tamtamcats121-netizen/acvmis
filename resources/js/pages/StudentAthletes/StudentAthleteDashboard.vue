<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import type { ApexOptions } from 'apexcharts'
import { computed, onMounted, onUnmounted, ref, useSlots, watch } from 'vue'
import VueApexCharts from 'vue3-apexcharts'

import StudentBottomNav from '@/components/student/StudentBottomNav.vue'
import RoleFooter from '@/components/ui/RoleFooter.vue'
import UserAccountMenu from '@/components/UserAccountMenu.vue'
import { useTheme } from '@/composables/useTheme'
import { studentPrimaryNav, studentSecondaryNav } from '@/config/studentNav'

const SIDEBAR_PREF_KEY = 'ac-vmis-student-sidebar-collapsed'

const slots = useSlots()
const page = usePage()
const unreadCount = computed(() => Number(page.props.auth?.announcements?.unread_count ?? 0))
const hasDefaultSlot = computed(() => Boolean(slots.default))
const { isDarkMode } = useTheme()
const currentPath = computed(() => {
    const raw = String(page.url || '')
    const base = raw.split('#')[0].split('?')[0]
    return base || '/'
})
const academicAccess = computed(() => (page.props.auth as any)?.academic_access ?? null)
const isAcademicallyRestricted = computed(() => Boolean(academicAccess.value?.is_restricted))
const dashboard = computed(() => (page.props as any)?.dashboard ?? {})
const kpis = computed(() => dashboard.value.kpis ?? {})
const hasTeamAssignment = computed(() => Boolean(kpis.value.has_team_assignment))
const charts = computed(() => dashboard.value.charts ?? {})
const upcomingSeries = computed(() => charts.value.upcoming_sessions ?? [])
const attendanceBreakdown = computed(() => charts.value.attendance_breakdown ?? { present: 0, absent: 0, excused: 0, no_response: 0 })
const attendanceTotal = computed(() => {
    const values = attendanceBreakdown.value
    return Number(values.present || 0) + Number(values.absent || 0) + Number(values.excused || 0) + Number(values.no_response || 0)
})
const academicSubmissions = computed(() => charts.value.academic_submissions ?? { submitted: 0, pending: 0 })
const upcomingSessionsCount = computed(() =>
    upcomingSeries.value.reduce((sum: number, item: any) => sum + Number(item.count || 0), 0)
)
const upcomingLabels = computed(() => upcomingSeries.value.map((item: any) => String(item.label ?? '')))
const upcomingCountsSeries = computed(() => upcomingSeries.value.map((item: any) => Number(item.count || 0)))
const submissionTotal = computed(() => Number(academicSubmissions.value.submitted || 0) + Number(academicSubmissions.value.pending || 0))
const submissionProgress = computed(() => {
    if (!submissionTotal.value) return 0
    return Math.round((Number(academicSubmissions.value.submitted || 0) / submissionTotal.value) * 100)
})
const academicStatusLabel = computed(() => {
    if (!kpis.value.academic_status) return 'No record available'

    return String(kpis.value.academic_status)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase())
})
const hasActionItems = computed(() =>
    Number(kpis.value.pending_responses || 0) > 0
    || Number(academicSubmissions.value.pending || 0) > 0
    || isAcademicallyRestricted.value
)
const attendanceSeries = computed(() => [
    Number(attendanceBreakdown.value.present || 0),
    Number(attendanceBreakdown.value.absent || 0),
    Number(attendanceBreakdown.value.excused || 0),
    Number(attendanceBreakdown.value.no_response || 0),
])
const attendanceCompletedCount = computed(() =>
    Number(attendanceBreakdown.value.present || 0) + Number(attendanceBreakdown.value.excused || 0)
)
const attendanceKpiValue = computed(() => `${attendanceCompletedCount.value} / ${attendanceTotal.value}`)
const hasAttendanceData = computed(() => attendanceSeries.value.some((value) => value > 0))
const heroSummary = computed(() => {
    const parts: string[] = []

    if (upcomingSessionsCount.value > 0) {
        parts.push(`${upcomingSessionsCount.value} upcoming ${upcomingSessionsCount.value === 1 ? 'session' : 'sessions'} this week`)
    }

    if (Number(academicSubmissions.value.pending || 0) > 0) {
        parts.push(`${academicSubmissions.value.pending} academic ${Number(academicSubmissions.value.pending) === 1 ? 'submission is' : 'submissions are'} still pending`)
    }

    if (parts.length === 0) {
        return 'There are no immediate pending items. Please continue to monitor your schedule and academic requirements.'
    }

    return `${parts.join(' • ')}.`
})

const mobileMenuOpen = ref(false)
const sidebarCollapsed = ref(false)
const primaryItems = studentPrimaryNav
const secondaryItems = studentSecondaryNav
const hasSecondaryItems = secondaryItems.length > 0
const notificationsOpen = ref(false)
const notificationsCloseTimer = ref<number | null>(null)
const bellProcessingIds = ref<number[]>([])

const studentNotifications = ref<Array<{
    id: number | string
    kind?: string | null
    title: string
    message: string
    type: string
    is_read: boolean
    published_at: string | null
    settings_href?: string | null
    send_verification_route?: string | null
    send_verification_label?: string | null
    secondary_action_label?: string | null
}>>([])
const verificationSending = ref(false)

watch(
    () => (page.props.auth as any)?.student_notifications?.recent,
    (items) => {
        studentNotifications.value = Array.isArray(items)
            ? items.map((item: any) => ({ ...item }))
            : []
    },
    { immediate: true }
)

const notificationsCount = computed(() => {
    if (studentNotifications.value.length) {
        return studentNotifications.value.filter((item) => !item.is_read).length
    }
    return unreadCount.value
})

const footerLinks = [
    { label: 'Dashboard', href: '/StudentAthleteDashboard' },
    { label: 'My Schedule', href: '/MySchedule' },
    { label: 'My Team', href: '/MyTeam' },
    { label: 'Join Team', href: '/join-team' },
    { label: 'Academics', href: '/AcademicSubmissions' },
    { label: 'My Documents', href: '/documents/my' },
    { label: 'Announcements', href: '/announcements' },
    { label: 'Profile', href: '/account/profile' },
    { label: 'Settings', href: '/account/settings' },
]

const activeLabel = computed(() => {
    const all = [...primaryItems, ...secondaryItems]
    const found = all.find((item) => isActive(item.route))
    return found?.label ?? 'Athlete Workspace'
})
const currentPageName = computed(() => {
    if (currentPath.value === '/account/profile' || currentPath.value.startsWith('/account/profile/')) {
        return 'Profile'
    }
    if (currentPath.value === '/account/settings' || currentPath.value.startsWith('/account/settings/')) {
        return 'Settings'
    }
    if (currentPath.value === '/account/account-settings' || currentPath.value.startsWith('/account/account-settings/')) {
        return 'Account Settings'
    }
    if (currentPath.value === '/account/notifications' || currentPath.value.startsWith('/account/notifications/')) {
        return 'Notifications'
    }
    if (currentPath.value === '/account/preferences' || currentPath.value.startsWith('/account/preferences/')) {
        return 'Preferences'
    }
    if (currentPath.value === '/account/help' || currentPath.value.startsWith('/account/help/')) {
        return 'Support'
    }

    return activeLabel.value
})

function isActive(route: string): boolean {
    return currentPath.value === route || currentPath.value.startsWith(`${route}/`)
}

function go(route: string) {
    mobileMenuOpen.value = false
    router.get(route)
}

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem(SIDEBAR_PREF_KEY, sidebarCollapsed.value ? '1' : '0')
}

function logout() {
    mobileMenuOpen.value = false
    router.post('/logout')
}

function openNotifications() {
    if (notificationsCloseTimer.value) {
        window.clearTimeout(notificationsCloseTimer.value)
        notificationsCloseTimer.value = null
    }
    notificationsOpen.value = true
}

function handleNotificationsBellClick() {
    if (window.matchMedia('(max-width: 767px)').matches) {
        notificationsOpen.value = false
        go('/account/notifications')
    }
}

function scheduleNotificationsClose() {
    if (notificationsCloseTimer.value) {
        window.clearTimeout(notificationsCloseTimer.value)
    }
    notificationsCloseTimer.value = window.setTimeout(() => {
        notificationsOpen.value = false
        notificationsCloseTimer.value = null
    }, 180)
}

function isBellProcessing(id: number | string) {
    return typeof id === 'number' && bellProcessingIds.value.includes(id)
}

function markBellRead(item: { id: number | string; is_read: boolean; kind?: string | null }) {
    if (item.kind === 'verification' || typeof item.id !== 'number') return
    if (item.is_read || isBellProcessing(item.id)) return
    const previous = item.is_read
    item.is_read = true
    bellProcessingIds.value = [...bellProcessingIds.value, item.id]

    router.put(`/announcements/${item.id}/read`, {}, {
        preserveScroll: true,
        onError: () => {
            item.is_read = previous
        },
        onFinish: () => {
            bellProcessingIds.value = bellProcessingIds.value.filter((id) => id !== item.id)
        },
    })
}

function sendVerificationEmail(route?: string | null) {
    if (verificationSending.value) return
    verificationSending.value = true

    router.post(route || '/email/verification-notification', {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            verificationSending.value = false
        },
    })
}

function iconPath(icon: string) {
    if (icon === 'layout-grid') return 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z'
    if (icon === 'users') return 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6M23 11h-6'
    if (icon === 'calendar') return 'M8 2v3M16 2v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'
    if (icon === 'heart-pulse') return 'M22 12h-4l-3 8-4-16-3 8H2'
    if (icon === 'graduation-cap') return 'M2 9l10-5 10 5-10 5-10-5zM6 11v5c0 1.6 2.7 3 6 3s6-1.4 6-3v-5'
    if (icon === 'bell') return 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0'
    if (icon === 'user') return 'M20 21a8 8 0 0 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'
    if (icon === 'help') return 'M9.09 9a3 3 0 1 1 5.82 1c0 2-3 3-3 3M12 17h.01'
    if (icon === 'settings') return 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 0 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.2a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.2a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'
    return 'M12 2v20M2 12h20'
}

function cardMotion(order: number) {
    return { '--card-order': String(order) }
}

const upcomingChartOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        fontFamily: 'inherit',
        background: 'transparent',
        foreColor: isDarkMode.value ? '#cbd5e1' : '#475569',
    },
    colors: ['#034485'],
    dataLabels: { enabled: false },
    plotOptions: {
        bar: {
            borderRadius: 8,
            columnWidth: '48%',
        },
    },
    xaxis: {
        categories: upcomingLabels.value,
        axisBorder: { color: 'rgba(148, 163, 184, 0.24)' },
        axisTicks: { color: 'rgba(148, 163, 184, 0.24)' },
        labels: {
            style: {
                colors: Array(upcomingLabels.value.length).fill(isDarkMode.value ? '#94a3b8' : '#64748b'),
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
        borderColor: isDarkMode.value ? 'rgba(148, 163, 184, 0.12)' : 'rgba(148, 163, 184, 0.14)',
        strokeDashArray: 4,
    },
    tooltip: { theme: isDarkMode.value ? 'dark' : 'light' },
}))

const attendanceBreakdownOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'donut',
        toolbar: { show: false },
        fontFamily: 'inherit',
        background: 'transparent',
    },
    labels: ['Present', 'Absent', 'Excused', 'No Response'],
    colors: ['#034485', '#2563eb', '#60a5fa', '#93c5fd'],
    legend: {
        position: 'bottom',
        labels: { colors: isDarkMode.value ? '#cbd5e1' : '#475569' },
    },
    stroke: {
        colors: [isDarkMode.value ? '#171717' : '#ffffff'],
        width: 4,
    },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: {
            donut: {
                size: '68%',
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
                        fontSize: '26px',
                        fontWeight: 700,
                        offsetY: -10,
                        formatter: (value: string) => String(Math.round(Number(value))),
                    },
                    total: {
                        show: true,
                        label: 'Total',
                        color: isDarkMode.value ? '#94a3b8' : '#64748b',
                        formatter: () => String(attendanceTotal.value),
                    },
                },
            },
        },
    },
    tooltip: { theme: isDarkMode.value ? 'dark' : 'light' },
}))

const academicProgressOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'radialBar',
        toolbar: { show: false },
        fontFamily: 'inherit',
        background: 'transparent',
    },
    colors: ['#034485'],
    plotOptions: {
        radialBar: {
            hollow: {
                size: '68%',
            },
            track: {
                background: isDarkMode.value ? '#232b36' : '#dbeafe',
            },
            dataLabels: {
                name: {
                    show: true,
                    color: isDarkMode.value ? '#94a3b8' : '#64748b',
                    fontSize: '12px',
                    offsetY: 16,
                },
                value: {
                    show: true,
                    color: isDarkMode.value ? '#f8fafc' : '#0f172a',
                    fontSize: '28px',
                    fontWeight: 700,
                    offsetY: -16,
                    formatter: (value: number) => `${Math.round(value)}%`,
                },
            },
        },
    },
    labels: ['Completed'],
}))

function onEsc(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        mobileMenuOpen.value = false
    }
}

onMounted(() => {
    sidebarCollapsed.value = localStorage.getItem(SIDEBAR_PREF_KEY) === '1'
    window.addEventListener('keydown', onEsc)
})

onUnmounted(() => {
    window.removeEventListener('keydown', onEsc)
    document.body.style.overflow = ''
})

watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
})
</script>

<template>
    <div class="student-shell min-h-screen" :class="isDarkMode ? 'bg-[#111111] text-slate-100' : 'bg-[#f5f7fb] text-slate-900'">
        <div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,rgba(3,68,133,0.10),transparent_42%)]" />

        <div v-if="mobileMenuOpen" class="fixed inset-0 z-40 bg-slate-900/45 md:hidden" @click="mobileMenuOpen = false" />

        <aside
            class="student-shell__sidebar hidden border-r-0 backdrop-blur md:fixed md:inset-y-0 md:left-0 md:z-30 md:flex md:flex-col md:border-r"
            :class="[
                'md:top-[71px] md:h-[calc(100vh-71px)]',
                sidebarCollapsed ? 'md:w-20' : 'md:w-64',
                isDarkMode ? 'border-[#2a2f3a] bg-[#111111]' : 'border-[#bfd4eb] bg-[#eaf3ff]',
            ]"
        >
            <div class="flex h-full flex-col">
                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    <button
                        v-for="item in primaryItems"
                        :key="item.key"
                        type="button"
                        @click="go(item.route)"
                        class="group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color] duration-200"
                        :class="[
                            isActive(item.route)
                                ? 'border-[#034485] bg-[#034485] text-white'
                                : isDarkMode
                                    ? 'border-transparent text-slate-200 hover:border-[#2a2f3a] hover:bg-[#1f2937] hover:text-white'
                                    : 'border-transparent text-slate-700 hover:border-[#bfd4eb] hover:bg-white/75',
                            sidebarCollapsed ? 'justify-center px-2' : '',
                        ]"
                        :title="sidebarCollapsed ? item.label : ''"
                    >
                        <svg
                            class="h-4.5 w-4.5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path :d="iconPath(item.icon)" />
                        </svg>
                        <span
                            class="origin-left whitespace-nowrap transition-[max-width,opacity,transform,margin] duration-200 ease-out"
                            :class="sidebarCollapsed ? 'ml-0 max-w-45 scale-100 opacity-100 md:max-w-0 md:scale-95 md:overflow-hidden md:opacity-0' : 'ml-2 max-w-45 scale-100 opacity-100'"
                        >
                            {{ item.label }}
                        </span>
                    </button>
                </nav>

                <div class="border-t px-3 py-3" :class="isDarkMode ? 'border-[#2a2f3a]' : 'border-[#d6e4f4]'">
                    <div v-if="hasSecondaryItems">
                        <button
                            type="button"
                            class="group mb-2 flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                            :class="[
                                isActive('/account/settings')
                                    ? 'border-[#034485] bg-[#034485] text-white'
                                    : isDarkMode
                                        ? 'border-transparent text-slate-200 hover:border-[#2a2f3a] hover:bg-[#1f2937] hover:text-white'
                                        : 'border-transparent text-slate-700 hover:border-[#bfd4eb] hover:bg-white/75',
                                sidebarCollapsed ? 'justify-center' : '',
                            ]"
                            @click="go('/account/settings')"
                            :title="sidebarCollapsed ? 'Settings' : ''"
                        >
                            <svg
                                class="h-4.5 w-4.5 shrink-0"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path :d="iconPath('settings')" />
                            </svg>
                            <span
                                class="origin-left whitespace-nowrap transition-[max-width,opacity,transform,margin] duration-200 ease-out"
                                :class="sidebarCollapsed ? 'ml-0 max-w-45 scale-100 opacity-100 md:max-w-0 md:scale-95 md:overflow-hidden md:opacity-0' : 'ml-2 max-w-45 scale-100 opacity-100'"
                            >
                                Settings
                            </span>
                        </button>

                        <button
                            type="button"
                            class="group mb-2 flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                            :class="[
                                isActive('/account/help')
                                    ? 'border-[#034485] bg-[#034485] text-white'
                                    : isDarkMode
                                        ? 'border-transparent text-slate-200 hover:border-[#2a2f3a] hover:bg-[#1f2937] hover:text-white'
                                        : 'border-transparent text-slate-700 hover:border-[#bfd4eb] hover:bg-white/75',
                                sidebarCollapsed ? 'justify-center' : '',
                            ]"
                            @click="go('/account/help')"
                            :title="sidebarCollapsed ? 'Help & Support' : ''"
                        >
                            <svg
                                class="h-4.5 w-4.5 shrink-0"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path :d="iconPath('help')" />
                            </svg>
                            <span
                                class="origin-left whitespace-nowrap transition-[max-width,opacity,transform,margin] duration-200 ease-out"
                                :class="sidebarCollapsed ? 'ml-0 max-w-45 scale-100 opacity-100 md:max-w-0 md:scale-95 md:overflow-hidden md:opacity-0' : 'ml-2 max-w-45 scale-100 opacity-100'"
                            >
                                Help &amp; Support
                            </span>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                        :class="[
                            isDarkMode
                                ? 'border-transparent text-rose-300 hover:bg-rose-950/30'
                                : 'text-rose-600 hover:border-rose-200 hover:bg-rose-50',
                            sidebarCollapsed ? 'justify-center' : '',
                        ]"
                        @click="logout"
                    >
                        <svg
                            class="h-4.5 w-4.5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <path d="M16 17l5-5-5-5" />
                            <path d="M21 12H9" />
                        </svg>
                        <span
                            class="origin-left whitespace-nowrap transition-[max-width,opacity,transform,margin] duration-200 ease-out"
                            :class="sidebarCollapsed ? 'ml-0 max-w-45 scale-100 opacity-100 md:max-w-0 md:scale-95 md:overflow-hidden md:opacity-0' : 'ml-2 max-w-45 scale-100 opacity-100'"
                        >
                            Logout
                        </span>
                    </button>
                </div>
            </div>
        </aside>

        <aside
            class="student-shell__mobile-sidebar fixed inset-y-0 left-0 z-50 w-[82vw] max-w-xs border-r p-4 transition md:hidden"
            :class="[
                isDarkMode ? 'border-[#2a2f3a] bg-[#111111]' : 'border-[#bfd4eb] bg-[#eef5ff]',
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm font-bold" :class="isDarkMode ? 'text-slate-100' : 'text-[#1f2937]'">Student Menu</p>
                <button
                    type="button"
                    class="rounded border px-2 py-1 text-xs"
                    :class="isDarkMode ? 'border-slate-700 bg-[#111111] text-slate-200' : 'border-slate-300 text-slate-700'"
                    @click="mobileMenuOpen = false"
                >
                    Close
                </button>
            </div>

            <div class="space-y-2">
                <button
                    v-for="item in [...primaryItems, ...secondaryItems]"
                    :key="`mobile-${item.key}`"
                    type="button"
                    @click="go(item.route)"
                    class="group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color] duration-200"
                    :class="[
                        isActive(item.route)
                            ? 'border-[#034485] bg-[#034485] text-white'
                            : isDarkMode
                                ? 'border-transparent text-slate-200 hover:border-[#2a2f3a] hover:bg-[#1f2937] hover:text-white'
                                : 'border-transparent text-slate-700 hover:border-[#bfd4eb] hover:bg-white/75',
                    ]"
                >
                    <svg
                        class="h-4.5 w-4.5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path :d="iconPath(item.icon)" />
                    </svg>
                    <span class="ml-2">{{ item.label }}</span>
                </button>

                <button
                    type="button"
                    class="group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                    :class="[
                        isDarkMode
                            ? 'border-transparent text-rose-300 hover:bg-rose-950/30'
                            : 'border-transparent text-rose-600 hover:border-rose-200 hover:bg-rose-50',
                    ]"
                    @click="logout"
                >
                    <svg
                        class="h-4.5 w-4.5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <path d="M16 17l5-5-5-5" />
                        <path d="M21 12H9" />
                    </svg>
                    <span class="ml-2">Logout</span>
                </button>
            </div>
        </aside>

        <header class="fixed inset-x-0 top-0 z-40 border-b border-[#02315f] bg-[#034485] shadow-none lg:shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]">
            <div class="flex w-full items-center justify-between gap-3 py-3 pl-0 pr-2 sm:pr-3 lg:pr-4">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-white/20 text-white md:hidden"
                        @click="mobileMenuOpen = true"
                        aria-label="Open menu"
                    >
                        <span class="space-y-1">
                            <span class="block h-0.5 w-4 bg-current" />
                            <span class="block h-0.5 w-4 bg-current" />
                            <span class="block h-0.5 w-4 bg-current" />
                        </span>
                    </button>

                    <div class="min-w-0 flex items-center gap-2">
                        <div class="min-w-0 ml-1 px-1 py-1 sm:ml-2">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white">AC VMIS Student</p>
                            <div class="flex min-w-0 items-center gap-2">
                                <h2 class="text-sm font-semibold leading-tight text-white sm:text-base">{{ currentPageName }}</h2>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="hidden h-10 w-10 items-center justify-center rounded-lg border border-white/20 text-white md:inline-flex"
                            @click="toggleSidebar"
                            :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                            aria-label="Toggle sidebar"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path v-if="sidebarCollapsed" d="M8 6l6 6-6 6" />
                                <path v-else d="M16 6l-6 6 6 6" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div
                        class="relative"
                        @mouseenter="openNotifications"
                        @mouseleave="scheduleNotificationsClose"
                        @focusin="openNotifications"
                        @focusout="scheduleNotificationsClose"
                    >
                        <button
                            type="button"
                            class="announcement-bell relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-white bg-white text-[#034485] transition hover:bg-slate-100"
                            aria-label="Open announcements"
                            @click="handleNotificationsBellClick"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5" />
                                <path d="M9 17a3 3 0 0 0 6 0" />
                            </svg>
                            <span
                                v-if="notificationsCount > 0"
                                class="absolute -top-1 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                            >
                                {{ notificationsCount }}
                            </span>
                        </button>

                        <div
                            v-if="notificationsOpen"
                            class="student-shell__announcement-menu absolute right-0 mt-2 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Announcements
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ notificationsCount }}</span>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                <button
                                    v-for="item in studentNotifications"
                                    :key="item.id ?? item.title"
                                    type="button"
                                    class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm transition"
                                    :class="item.kind === 'verification' ? 'border-b border-amber-200 bg-amber-50 text-amber-950' : item.is_read ? 'border-b border-slate-100 text-slate-700 hover:bg-slate-50' : 'border-b border-white/10 bg-[#034485] text-white hover:bg-[#033a70]'"
                                    @click="item.kind === 'verification' ? void 0 : (markBellRead(item), go('/announcements'))"
                                >
                                    <span
                                        v-if="item.kind !== 'verification'"
                                        class="mt-1 inline-flex h-2 w-2 shrink-0 rounded-full"
                                        :class="item.is_read ? 'bg-[#034485]' : 'bg-white'"
                                    />
                                    <span v-else class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M12 9v4" />
                                            <path d="M12 17h.01" />
                                            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                        </svg>
                                    </span>
                                    <span class="flex-1">
                                        <span class="block font-semibold" :class="item.kind === 'verification' ? 'text-amber-950' : item.is_read ? 'text-slate-800' : 'text-white'">{{ item.title }}</span>
                                        <span class="block text-xs" :class="item.kind === 'verification' ? 'text-amber-900' : item.is_read ? 'text-slate-500' : 'text-white/80'">{{ item.message }}</span>
                                        <span v-if="item.kind === 'verification'" class="mt-2 flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="rounded-full bg-amber-500 px-3 py-1 text-[11px] font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60"
                                                :disabled="verificationSending"
                                                @click.stop="sendVerificationEmail(item.send_verification_route)"
                                            >
                                                {{ verificationSending ? 'Sending...' : item.send_verification_label || 'Send Verification Email' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full border border-amber-200 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 transition hover:bg-amber-100"
                                                @click.stop="go(item.settings_href || '/account/account-settings')"
                                            >
                                                {{ item.secondary_action_label || 'Go to Account Settings' }}
                                            </button>
                                        </span>
                                    </span>
                                    <span
                                        v-if="item.kind !== 'verification'"
                                        class="ml-auto text-[10px] font-semibold"
                                        :class="item.is_read ? 'text-slate-400' : 'text-white/70'"
                                    >
                                        {{ item.published_at ?? '' }}
                                    </span>
                                </button>
                                <div v-if="studentNotifications.length === 0" class="px-3 py-4 text-xs text-slate-500">
                                    No announcements are available at this time.
                                </div>
                            </div>
                            <div class="border-t border-slate-200 px-3 py-2">
                                <button
                                    type="button"
                                    class="w-full rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                                    @click="go('/announcements')"
                                >
                                    View All Announcements
                                </button>
                            </div>
                        </div>
                    </div>
                    <UserAccountMenu :inverse="true" menu-placement="bottom" compact />
                </div>
            </div>
        </header>

        <div class="pt-18 transition-[padding] duration-300 ease-out will-change-[padding]" :class="sidebarCollapsed ? 'md:pl-20' : 'md:pl-64'">
            <main class="mx-auto w-full max-w-400 px-4 py-4 pb-[calc(env(safe-area-inset-bottom,0px)+5.5rem)] sm:px-6 md:px-6 md:pb-6 lg:px-8">
                <Transition name="student-content-fade" mode="out-in">
                    <div :key="currentPath" class="student-content-stage">
                        <template v-if="hasDefaultSlot">
                            <slot />
                        </template>
                        <template v-else>
                            <div class="student-dashboard-view space-y-6">
                        <section class="dashboard-card rounded-xl border border-[#034485]/35 bg-[#034485] p-5 text-white" :style="cardMotion(2)">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-white/80">Overview</p>
                                    <h1 class="mt-1 text-2xl font-bold text-white">Student-Athlete Dashboard</h1>
                                    <p class="mt-1 text-sm text-white/85">{{ heroSummary }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="hero-chip px-3 py-1 text-xs font-medium text-white">
                                        {{ upcomingSessionsCount }} sessions this week
                                    </span>
                                    <span
                                        class="hero-chip px-3 py-1 text-xs font-medium"
                                        :class="hasActionItems ? 'bg-white/14 text-white border-white/22' : 'text-white'"
                                    >
                                        {{ hasActionItems ? 'Action needed' : 'On track' }}
                                    </span>
                                </div>
                            </div>
                        </section>

                        <section class="grid grid-cols-1 gap-3 md:grid-cols-4">
                            <article class="dashboard-card metric-tile ui-surface ui-surface-hover p-4" :style="cardMotion(3)">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Attendance Snapshot</p>
                                <p class="mt-2 text-2xl font-bold text-[#034485]">
                                    {{ attendanceTotal > 0 ? attendanceKpiValue : 'N/A' }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">Present and excused sessions over the last 30 days.</p>
                            </article>
                            <article class="dashboard-card metric-tile ui-tint ui-surface-hover p-4" :style="cardMotion(4)">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Coach Attendance Queue</p>
                                <p class="mt-2 text-2xl font-bold text-[#034485]">{{ kpis.pending_responses ?? 0 }}</p>
                                <p class="mt-1 text-xs text-slate-500">Sessions still waiting for coach-posted attendance.</p>
                            </article>
                            <article class="dashboard-card metric-tile ui-surface ui-surface-hover p-4" :style="cardMotion(5)">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Academic Standing</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ academicStatusLabel }}</p>
                                <p class="mt-1 text-xs text-slate-500">Latest eligibility evaluation on file.</p>
                            </article>
                            <article class="dashboard-card metric-tile ui-surface ui-surface-hover p-4" :style="cardMotion(6)">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Announcements</p>
                                <p class="mt-2 text-2xl font-bold text-[#034485]">{{ notificationsCount }}</p>
                                <p class="mt-1 text-xs text-slate-500">Unread notices from admin and system updates.</p>
                            </article>
                        </section>

                        <section class="dashboard-card surface-card ui-surface p-5" :style="cardMotion(7)">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600">Primary Actions</h2>
                                <span class="text-xs text-slate-500">Primary student workflows</span>
                            </div>

                            <div v-if="!hasTeamAssignment" class="empty-state mt-4 px-4 py-5 text-sm text-slate-600">
                                You are not assigned to a team yet.
                            </div>

                            <div v-else class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                <button
                                    type="button"
                                    @click="go('/MySchedule')"
                                    class="action-tile group flex h-full min-h-[11rem] flex-col justify-between p-4 text-left ui-focus-ring"
                                    :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : ''"
                                    :style="cardMotion(8)"
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-xl text-[#034485]" :class="isDarkMode ? 'bg-slate-800' : 'bg-[#034485]/10'">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M8 2v3M16 2v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">My Schedule</p>
                                            <p class="text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">View activities, times, venues, and posted attendance results.</p>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-[11px] font-semibold" :class="Number(kpis.pending_responses || 0) > 0 ? (isDarkMode ? 'text-amber-300' : 'text-amber-700') : (isDarkMode ? 'text-slate-300' : 'text-slate-500')">
                                        {{ Number(kpis.pending_responses || 0) > 0 ? `${kpis.pending_responses} sessions still waiting for coach attendance` : 'Coach-posted attendance is up to date' }}
                                    </p>
                                </button>

                                <button
                                    type="button"
                                    @click="go('/AcademicSubmissions')"
                                    class="action-tile group flex h-full min-h-[11rem] flex-col justify-between p-4 text-left ui-focus-ring"
                                    :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : ''"
                                    :style="cardMotion(9)"
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-xl text-[#034485]" :class="isDarkMode ? 'bg-slate-800' : 'bg-[#034485]/10'">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M2 9l10-5 10 5-10 5-10-5zM6 11v5c0 1.6 2.7 3 6 3s6-1.4 6-3v-5" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Academics</p>
                                            <p class="text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">Submit academic requirements and review your current standing.</p>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-[11px] font-semibold" :class="Number(academicSubmissions.pending || 0) > 0 ? (isDarkMode ? 'text-amber-300' : 'text-amber-700') : (isDarkMode ? 'text-emerald-300' : 'text-emerald-700')">
                                        {{ Number(academicSubmissions.pending || 0) > 0
                                            ? `${academicSubmissions.pending} pending submissions`
                                            : 'All open requirements completed' }}
                                    </p>
                                </button>

                                <button
                                    type="button"
                                    @click="go('/MyTeam')"
                                    class="action-tile group flex h-full min-h-[11rem] flex-col justify-between p-4 text-left ui-focus-ring"
                                    :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : ''"
                                    :style="cardMotion(11)"
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-xl text-[#034485]" :class="isDarkMode ? 'bg-slate-800' : 'bg-[#034485]/10'">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                                <path d="M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8" />
                                                <path d="M20 8v6" />
                                                <path d="M23 11h-6" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">My Team</p>
                                            <p class="text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">Check roster details, jersey status, and team information.</p>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-[11px] font-semibold" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">Open your team workspace</p>
                                </button>
                            </div>
                        </section>

                        <section>
                            <div class="dashboard-card surface-card ui-surface p-5" :style="cardMotion(12)">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold uppercase tracking-wide text-[#034485]">Upcoming Sessions</h3>
                                        <p class="mt-1 text-sm text-slate-500">Your scheduled team activities for the next seven days.</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="section-chip px-3 py-1.5 text-xs font-semibold text-[#034485] ui-focus-ring"
                                        @click="go('/MySchedule')"
                                    >
                                        View Schedule
                                    </button>
                                </div>

                                <div class="mt-4">
                                    <VueApexCharts
                                        height="300"
                                        type="bar"
                                        :options="upcomingChartOptions"
                                        :series="[{ name: 'Sessions', data: upcomingCountsSeries }]"
                                    />
                                </div>
                            </div>
                        </section>

                        <section class="grid gap-4 lg:grid-cols-2">
                            <div class="dashboard-card surface-card ui-surface p-5" :style="cardMotion(17)">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <h3 class="text-sm font-bold uppercase tracking-wide text-[#034485]">Action Needed</h3>
                                    <span
                                        v-if="!hasActionItems"
                                        class="section-chip bg-[color:var(--color-brand-light)] px-2.5 py-1 text-[11px] font-semibold text-[#034485]"
                                    >
                                        Up to Date
                                    </span>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div class="surface-inset p-3" :class="isDarkMode ? 'border border-slate-700 bg-slate-900' : ''">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Attendance Posting</p>
                                                <p class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">
                                                    {{ Number(kpis.pending_responses || 0) > 0
                                                        ? 'Some schedules are still waiting for the coach or assistant coach to post attendance.'
                                                        : 'Attendance has been posted for all current coach-managed sessions.' }}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                class="primary-inline-button w-full px-2.5 py-1 text-xs font-semibold text-white sm:w-auto ui-focus-ring"
                                                @click="go('/MySchedule')"
                                            >
                                                Open
                                            </button>
                                        </div>
                                    </div>

                                    <div class="surface-inset p-3" :class="isDarkMode ? 'border border-slate-700 bg-slate-900' : ''">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Academic Submission Status</p>
                                                <p class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">
                                                    {{ Number(academicSubmissions.pending || 0) > 0
                                                        ? `${academicSubmissions.pending} submission${Number(academicSubmissions.pending) === 1 ? ' is' : 's are'} still pending in the current period.`
                                                        : 'All currently open academic submission requirements are complete.' }}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                class="primary-inline-button w-full px-2.5 py-1 text-xs font-semibold text-white sm:w-auto ui-focus-ring"
                                                @click="go('/AcademicSubmissions')"
                                            >
                                                Open
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="dashboard-card surface-card ui-surface p-5" :style="cardMotion(21)">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <h3 class="text-sm font-bold uppercase tracking-wide text-[#034485]">Attendance Breakdown</h3>
                                    <span class="text-xs text-slate-500">{{ attendanceTotal }} total</span>
                                </div>
                                <div class="mt-4">
                                    <div v-if="hasAttendanceData">
                                        <VueApexCharts height="320" type="donut" :options="attendanceBreakdownOptions" :series="attendanceSeries" />
                                    </div>
                                    <div
                                        v-else
                                        class="empty-state px-4 py-10 text-center text-sm text-slate-500"
                                    >
                                        No attendance records are available yet.
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
                            <div class="dashboard-card surface-card ui-surface p-5" :style="cardMotion(22)">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold uppercase tracking-wide text-[#034485]">Academic Submission Progress</h3>
                                        <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">Track how many active academic requirements you have already completed.</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="section-chip px-3 py-1.5 text-xs font-semibold text-[#034485] ui-focus-ring"
                                        @click="go('/AcademicSubmissions')"
                                    >
                                        Open Academics
                                    </button>
                                </div>

                                <div class="surface-inset mt-4 p-4" :class="isDarkMode ? 'border border-slate-700 bg-slate-900' : ''">
                                    <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-center">
                                        <div>
                                            <VueApexCharts
                                                height="220"
                                                type="radialBar"
                                                :options="academicProgressOptions"
                                                :series="[submissionProgress]"
                                            />
                                        </div>
                                        <div>
                                            <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">
                                                You have completed {{ academicSubmissions.submitted }} of {{ submissionTotal || 0 }} required submissions.
                                            </p>
                                            <div class="mt-4 grid grid-cols-2 gap-3 text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                                                <div class="data-chip px-3 py-3" :class="isDarkMode ? 'border border-slate-700 bg-slate-950' : ''">
                                                    <span class="font-semibold text-[#034485]">Submitted:</span> {{ academicSubmissions.submitted }}
                                                </div>
                                                <div class="data-chip px-3 py-3" :class="isDarkMode ? 'border border-slate-700 bg-slate-950' : ''">
                                                    <span class="font-semibold text-[#034485]">Pending:</span> {{ academicSubmissions.pending }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                            </div>
                        </template>
                    </div>
                </Transition>

            </main>

            <RoleFooter
                title="Student-Athlete Workspace"
                description="Review schedules, attendance, and academic submissions."
                :links="footerLinks"
            />
        </div>

        <StudentBottomNav :items="primaryItems" :is-active="isActive" />
    </div>
</template>

<style scoped>
.student-dashboard-view .dashboard-card,
.dashboard-card {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
    animation: student-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: calc(var(--card-order, 0) * 60ms);
    will-change: transform, opacity;
}

@keyframes student-card-rise {
    0% {
        opacity: 0;
        transform: translateY(18px) scale(0.985);
    }

    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .student-dashboard-view .dashboard-card,
    .dashboard-card {
        animation: none;
        opacity: 1;
        transform: none;
    }
}

.surface-card {
    border-radius: calc(var(--radius-md) + 8px);
}

.metric-tile {
    border-radius: calc(var(--radius-md) + 4px);
}

.action-tile {
    border-radius: calc(var(--radius-lg) + 6px);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    box-shadow: var(--shadow-sm);
    transition:
        border-color var(--transition-base),
        box-shadow var(--transition-base),
        transform var(--transition-base),
        background-color var(--transition-base);
}

.action-tile:hover {
    transform: translateY(-2px);
    border-color: var(--color-border-strong);
    box-shadow: var(--shadow-md);
    background: color-mix(in srgb, var(--color-brand-light) 55%, white);
}

.surface-inset {
    border-radius: calc(var(--radius-sm) + 6px);
    background: var(--color-brand-light);
    border: 1px solid color-mix(in srgb, var(--color-brand) 20%, white);
}

.empty-state {
    border-radius: calc(var(--radius-md) + 6px);
    border: 1px dashed var(--color-border-strong);
    background: color-mix(in srgb, var(--color-brand-light) 55%, white);
}

.hero-chip,
.section-chip {
    border-radius: var(--radius-full);
    border: 1px solid color-mix(in srgb, var(--color-brand) 24%, white);
}

.hero-chip {
    background: rgba(255, 255, 255, 0.12);
}

.section-chip {
    background: var(--color-surface);
}

.primary-inline-button {
    border-radius: var(--radius-sm);
    background: var(--color-brand);
}

.primary-inline-button:hover {
    background: var(--color-brand-dark);
}

.data-chip {
    border-radius: calc(var(--radius-sm) + 4px);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    box-shadow: var(--shadow-xs);
}
</style>
