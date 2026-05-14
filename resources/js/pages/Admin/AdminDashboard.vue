<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import type { ApexOptions } from 'apexcharts';
import { computed, onMounted, onUnmounted, ref, useSlots, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

import AdminBottomNav from '@/components/admin/AdminBottomNav.vue';
import RoleFooter from '@/components/ui/RoleFooter.vue';
import UserAccountMenu from '@/components/UserAccountMenu.vue';
import { useTheme } from '@/composables/useTheme';

const SIDEBAR_PREF_KEY = 'ac-vmis-admin-sidebar-collapsed';

const slots = useSlots();
const page = usePage();
const { isDarkMode } = useTheme();

type DashboardPayload = {
    filters: {
        period: 'today' | 'week' | 'month';
        start_date: string;
        end_date: string;
    };
    kpis: {
        attendance_rate: number;
        attendance_present: number;
        attendance_total: number;
        no_response: number;
        active_teams: number;
        pending_academic_review: number;
    };
    pending_items: {
        pending_academic_reviews: number;
        teams_without_recent_attendance: number;
    };
    academic_status: {
        eligible: number;
        pending_review: number;
        ineligible: number;
    };
    trends: {
        labels: string[];
        attendance: {
            present: number[];
            late: number[];
            absent: number[];
            no_response: number[];
        };
        attendance_by_team: {
            labels: string[];
            rates: number[];
        };
    };
    recent_activity: Array<{
        id: string;
        type: 'approval' | 'academic' | 'roster' | 'attendance';
        title: string;
        description: string;
        happened_at: string | null;
    }>;
};

const hasDefaultSlot = computed(() => Boolean(slots.default));
const currentPath = computed(() => {
    const raw = String(page.url || '');
    const base = raw.split('#')[0].split('?')[0];
    return base || '/';
});
const dashboard = computed(() => (page.props.dashboard as DashboardPayload | undefined) ?? null);
const mobileNavOpen = ref(false);
const sidebarCollapsed = ref(false);
const notificationsOpen = ref(false);
const notificationsCloseTimer = ref<number | null>(null);
const reportsHoverCloseTimer = ref<number | null>(null);
const reportsTriggerRef = ref<HTMLElement | null>(null);
const reportsHoverStyle = ref<Record<string, string>>({});

const adminNotifications = ref<
    Array<{
        id: number | string;
        kind?: string | null;
        title: string;
        message: string;
        type: string;
        is_read: boolean;
        published_at: string | null;
        settings_href?: string | null;
        send_verification_route?: string | null;
        send_verification_label?: string | null;
        secondary_action_label?: string | null;
    }>
>([]);
const bellProcessingIds = ref<number[]>([]);
const verificationSending = ref(false);
const notificationsReady = ref(false);
const notificationsLoading = ref(true);
const notificationsFailed = ref(false);
const notificationRouterStops: Array<() => void> = [];

watch(
    () => (page.props.auth as any)?.admin_notifications?.recent,
    (items) => {
        adminNotifications.value = Array.isArray(items) ? items.map((item: any) => ({ ...item })) : [];
        notificationsReady.value = true;
        notificationsLoading.value = false;
    },
    { immediate: true },
);

const bellUnreadCount = computed(() => {
    if (adminNotifications.value.length) {
        return adminNotifications.value.filter((item) => !item.is_read).length;
    }
    return notificationsCount.value;
});

const notificationsCount = computed(() => {
    const unread = (page.props.auth as any)?.announcements?.unread_count;
    if (typeof unread === 'number') return unread;
    const fallback = (page.props.auth as any)?.admin_notifications?.items;
    if (Array.isArray(fallback)) {
        return fallback.reduce((sum, item) => sum + Number(item.count || 0), 0);
    }
    return 0;
});

function logout() {
    router.post('/logout');
}

type NavEntry = {
    name: string;
    iconPaths: string[];
    route?: string;
    children?: Array<{
        name: string;
        route: string;
    }>;
};

const pages: NavEntry[] = [
    { name: 'Dashboard', route: '/AdminDashboard', iconPaths: ['M3 13h8V3H3z', 'M13 21h8v-6h-8z', 'M13 11h8V3h-8z', 'M3 21h8v-6H3z'] },
    {
        name: 'User Records',
        route: '/people',
        iconPaths: ['M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M20 8v6', 'M23 11h-6'],
    },
    {
        name: 'Team Monitoring',
        route: '/teams',
        iconPaths: [
            'M17 21v-2a4 4 0 0 0-3-3.87',
            'M7 21v-2a4 4 0 0 1 3-3.87',
            'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
            'M5 8H4a2 2 0 0 0-2 2v2',
            'M19 8h1a2 2 0 0 1 2 2v2',
        ],
    },
    { name: 'Operations', route: '/operations', iconPaths: ['M3 3v18h18', 'M7 13l3-3 3 2 5-6'] },
    {
        name: 'Academics',
        route: '/academics',
        iconPaths: ['M4 19.5V6a2 2 0 0 1 2-2h9l5 5v10.5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z', 'M14 4v5h5', 'M8 13h8', 'M8 17h5'],
    },
    {
        name: 'Documents',
        route: '/documents',
        iconPaths: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6', 'M8 13h8', 'M8 17h5'],
    },
    {
        name: 'Audit Trail',
        route: '/audit-trail',
        iconPaths: ['M12 8v5l3 2', 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0', 'M12 3v2', 'M12 19v2'],
    },
    {
        name: 'Reports',
        iconPaths: ['M4 19h16', 'M7 16V8', 'M12 16V5', 'M17 16v-3'],
        children: [
            { name: 'Attendance', route: '/reports/attendance' },
            { name: 'Roster', route: '/reports/roster' },
            { name: 'Academics', route: '/reports/academics' },
        ],
    },
];

const footerLinks = [
    { label: 'Dashboard', href: '/AdminDashboard' },
    { label: 'User Records', href: '/people' },
    { label: 'Team Monitoring', href: '/teams' },
    { label: 'Operations', href: '/operations' },
    { label: 'Academics', href: '/academics' },
    { label: 'Documents', href: '/documents' },
    { label: 'Audit Trail', href: '/audit-trail' },
    { label: 'Reports', href: '/reports/attendance' },
    { label: 'Announcements', href: '/announcements' },
    { label: 'Profile', href: '/account/profile' },
    { label: 'Settings', href: '/account/settings' },
];

const adminBottomNavItems: Array<{
    key: string;
    label: string;
    mobileLabel?: string;
    route: string;
    icon: 'users' | 'shield-users' | 'bar-chart-3' | 'graduation-cap';
}> = [
    { key: 'records', label: 'User Records', mobileLabel: 'Records', route: '/people', icon: 'users' },
    { key: 'teams', label: 'Team Monitoring', route: '/teams', icon: 'shield-users' },
    { key: 'operations', label: 'Operations', route: '/operations', icon: 'bar-chart-3' },
    { key: 'academics', label: 'Academics', route: '/academics', icon: 'graduation-cap' },
];

const currentPageName = computed(() => {
    if (currentPath.value === '/operations/attendance' || currentPath.value.startsWith('/operations/attendance/')) {
        return 'Operations';
    }
    if (currentPath.value === '/account/profile' || currentPath.value.startsWith('/account/profile/')) {
        return 'Profile';
    }
    if (currentPath.value === '/account/settings' || currentPath.value.startsWith('/account/settings/')) {
        return 'Settings';
    }
    if (currentPath.value === '/account/account-settings' || currentPath.value.startsWith('/account/account-settings/')) {
        return 'Account Settings';
    }
    if (currentPath.value === '/account/notifications' || currentPath.value.startsWith('/account/notifications/')) {
        return 'Notifications';
    }
    if (currentPath.value === '/account/preferences' || currentPath.value.startsWith('/account/preferences/')) {
        return 'Preferences';
    }
    if (currentPath.value === '/account/help' || currentPath.value.startsWith('/account/help/')) {
        return 'Help & Support';
    }
    if (currentPath.value === '/announcements' || currentPath.value.startsWith('/announcements/')) {
        return 'Announcements';
    }

    const match = pages.find((item) => {
        if (item.route) {
            return currentPath.value === item.route || currentPath.value.startsWith(`${item.route}/`);
        }

        return item.children?.some((child) => currentPath.value === child.route || currentPath.value.startsWith(`${child.route}/`)) ?? false;
    });
    return match?.name ?? 'Dashboard';
});

const isSettingsRoute = computed(() => {
    return (
        currentPath.value === '/account/settings' ||
        currentPath.value.startsWith('/account/settings/') ||
        currentPath.value === '/account/account-settings' ||
        currentPath.value.startsWith('/account/account-settings/') ||
        currentPath.value === '/account/notifications' ||
        currentPath.value.startsWith('/account/notifications/') ||
        currentPath.value === '/account/preferences' ||
        currentPath.value.startsWith('/account/preferences/')
    );
});

const isHelpRoute = computed(() => {
    return (
        currentPath.value === '/account/help' ||
        currentPath.value.startsWith('/account/help/') ||
        currentPath.value === '/contact' ||
        currentPath.value.startsWith('/contact/')
    );
});

const selectedPeriod = computed(() => dashboard.value?.filters.period ?? 'week');
const attendanceKpiLabel = computed(() => {
    if (selectedPeriod.value === 'today') return 'Overall Attendance Today';
    if (selectedPeriod.value === 'month') return 'Overall Attendance This Month';
    return 'Overall Attendance This Week';
});
const attendanceKpiValue = computed(() => {
    const present = Number(dashboard.value?.kpis.attendance_present ?? 0);
    const total = Number(dashboard.value?.kpis.attendance_total ?? 0);
    return `${present} / ${total}`;
});
const attendanceKpiHint = computed(() => {
    if (selectedPeriod.value === 'today') return 'Present attendance records posted today.';
    if (selectedPeriod.value === 'month') return 'Present attendance records in the selected month.';
    return 'Present attendance records in the selected week.';
});
const reportsExpanded = ref(false);
const reportsHoverOpen = ref(false);
const attendanceChartMode = ref<'stacked' | 'line'>('stacked');

const attendanceTrendSeries = computed(() => {
    const attendance = dashboard.value?.trends.attendance;
    return [
        { name: 'Present', data: attendance?.present ?? [] },
        { name: 'Late', data: attendance?.late ?? [] },
        { name: 'Absent', data: attendance?.absent ?? [] },
        { name: 'No Response', data: attendance?.no_response ?? [] },
    ];
});

const attendanceChartOptions = computed<ApexOptions>(() => ({
    chart: {
        type: attendanceChartMode.value === 'stacked' ? 'bar' : 'line',
        stacked: attendanceChartMode.value === 'stacked',
        toolbar: { show: false },
        fontFamily: 'inherit',
        foreColor: '#475569',
        background: 'transparent',
    },
    colors: ['#034485', '#3b82f6', '#60a5fa', '#93c5fd'],
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
        labels: { colors: '#475569' },
    },
    plotOptions: {
        bar: {
            borderRadius: 6,
            columnWidth: '48%',
        },
    },
    xaxis: {
        categories: dashboard.value?.trends.labels ?? [],
        axisBorder: { color: 'rgba(148, 163, 184, 0.32)' },
        axisTicks: { color: 'rgba(148, 163, 184, 0.32)' },
        labels: {
            style: {
                colors: Array((dashboard.value?.trends.labels ?? []).length).fill('#64748b'),
                fontSize: '11px',
            },
        },
    },
    yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: {
            style: {
                colors: ['#64748b'],
                fontSize: '11px',
            },
        },
    },
    grid: {
        borderColor: 'rgba(148, 163, 184, 0.18)',
        strokeDashArray: 4,
    },
    tooltip: {
        theme: 'light',
    },
    responsive: [
        {
            breakpoint: 768,
            options: {
                plotOptions: {
                    bar: {
                        columnWidth: '64%',
                    },
                },
                legend: {
                    position: 'bottom',
                },
            },
        },
    ],
}));

const attendanceByTeamSeries = computed(() => [
    {
        name: 'Attendance Rate',
        data: dashboard.value?.trends.attendance_by_team.rates ?? [],
    },
]);

const attendanceByTeamOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        fontFamily: 'inherit',
        foreColor: '#475569',
    },
    colors: ['#034485'],
    plotOptions: {
        bar: {
            horizontal: true,
            borderRadius: 6,
            barHeight: '52%',
        },
    },
    dataLabels: {
        enabled: true,
        formatter: (value) => `${Number(value).toFixed(0)}%`,
        style: {
            colors: ['#ffffff'],
            fontWeight: 600,
        },
    },
    xaxis: {
        categories: dashboard.value?.trends.attendance_by_team.labels ?? [],
        max: 100,
        labels: {
            formatter: (value) => `${value}%`,
            style: { colors: ['#64748b'], fontSize: '11px' },
        },
    },
    yaxis: {
        labels: {
            style: { colors: ['#475569'], fontSize: '11px' },
        },
    },
    grid: {
        borderColor: 'rgba(148, 163, 184, 0.18)',
        strokeDashArray: 4,
    },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (value) => `${Number(value).toFixed(2)}%`,
        },
    },
}));

const academicStatusSeries = computed(() => [
    dashboard.value?.academic_status.eligible ?? 0,
    dashboard.value?.academic_status.pending_review ?? 0,
    dashboard.value?.academic_status.ineligible ?? 0,
]);

const academicStatusOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'donut',
        toolbar: { show: false },
        fontFamily: 'inherit',
        foreColor: '#475569',
    },
    labels: ['Eligible', 'Pending Review', 'Ineligible'],
    colors: ['#034485', '#3b82f6', '#93c5fd'],
    stroke: {
        colors: ['#ffffff'],
    },
    dataLabels: {
        enabled: true,
        formatter: (value) => `${Number(value).toFixed(0)}%`,
    },
    legend: {
        position: 'bottom',
        fontSize: '12px',
        labels: {
            colors: '#475569',
        },
    },
    plotOptions: {
        pie: {
            donut: {
                size: '66%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        color: '#64748b',
                        formatter: () => String(academicStatusSeries.value.reduce((sum, value) => sum + Number(value || 0), 0)),
                    },
                    value: {
                        color: '#0f172a',
                        fontSize: '22px',
                        fontWeight: 700,
                    },
                },
            },
        },
    },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (value) => `${Number(value)} record(s)`,
        },
    },
    responsive: [
        {
            breakpoint: 768,
            options: {
                chart: {
                    height: 300,
                },
            },
        },
    ],
}));

function formatDashboardActivityTime(value: string | null) {
    if (!value) return 'Just now';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Recently';
    }

    return date.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function activityTypeClasses(type: DashboardPayload['recent_activity'][number]['type']) {
    switch (type) {
        case 'approval':
            return 'border-[#034485]/25 bg-[#034485]/10 text-[#034485]';
        case 'academic':
            return 'border-sky-200 bg-sky-50 text-sky-700';
        case 'roster':
            return 'border-blue-200 bg-blue-50 text-blue-700';
        case 'attendance':
            return 'border-indigo-200 bg-indigo-50 text-indigo-700';
        default:
            return 'border-slate-200 bg-slate-50 text-slate-700';
    }
}

function activityTypeLabel(type: DashboardPayload['recent_activity'][number]['type']) {
    switch (type) {
        case 'approval':
            return 'Approval';
        case 'academic':
            return 'Academic';
        case 'roster':
            return 'Roster';
        case 'attendance':
            return 'Attendance';
        default:
            return 'Activity';
    }
}

function goTo(route: string) {
    mobileNavOpen.value = false;
    router.get(route);
}

function goToNavTarget(route: string) {
    mobileNavOpen.value = false;
    reportsHoverOpen.value = false;
    router.get(route);
}

function isActive(route: string): boolean {
    return currentPath.value === route || currentPath.value.startsWith(`${route}/`);
}

function isChildActive(route: string): boolean {
    return isActive(route);
}

function isEntryActive(entry: NavEntry): boolean {
    if (entry.route) {
        return isActive(entry.route);
    }

    if (entry.children?.some((child) => isActive(child.route))) {
        return true;
    }

    return entry.children?.some((child) => isChildActive(child.route)) ?? false;
}

function toggleReportsNav() {
    reportsExpanded.value = !reportsExpanded.value;
}

function setReportsTriggerRef(el: Element | { $el?: Element | null } | null) {
    reportsTriggerRef.value = el instanceof HTMLElement
        ? el
        : el && '$el' in el && el.$el instanceof HTMLElement
            ? el.$el
            : null;
}

function updateReportsHoverPosition() {
    if (!reportsTriggerRef.value) return;

    const rect = reportsTriggerRef.value.getBoundingClientRect();
    const menuWidth = 224;
    const menuHeight = 220;
    const viewportPadding = 12;
    const idealTop = rect.top + rect.height / 2 - menuHeight / 2;
    const maxTop = window.innerHeight - menuHeight - viewportPadding;
    const clampedTop = Math.min(Math.max(viewportPadding, idealTop), Math.max(viewportPadding, maxTop));
    const maxLeft = window.innerWidth - menuWidth - viewportPadding;
    const clampedLeft = Math.min(rect.right + 8, Math.max(viewportPadding, maxLeft));

    reportsHoverStyle.value = {
        position: 'fixed',
        top: `${clampedTop}px`,
        left: `${clampedLeft}px`,
    };
}

function clearReportsHoverCloseTimer() {
    if (reportsHoverCloseTimer.value) {
        window.clearTimeout(reportsHoverCloseTimer.value);
        reportsHoverCloseTimer.value = null;
    }
}

function openReportsHoverMenu() {
    if (sidebarCollapsed.value && !mobileNavOpen.value) {
        clearReportsHoverCloseTimer();
        updateReportsHoverPosition();
        reportsHoverOpen.value = true;
    }
}

function closeReportsHoverMenu() {
    clearReportsHoverCloseTimer();
    reportsHoverOpen.value = false;
}

function scheduleReportsHoverClose() {
    if (!sidebarCollapsed.value || mobileNavOpen.value) {
        closeReportsHoverMenu();
        return;
    }

    clearReportsHoverCloseTimer();
    reportsHoverCloseTimer.value = window.setTimeout(() => {
        reportsHoverOpen.value = false;
        reportsHoverCloseTimer.value = null;
    }, 140);
}

function handleReportsEntryClick() {
    if (sidebarCollapsed.value && !mobileNavOpen.value) {
        updateReportsHoverPosition();
        reportsHoverOpen.value = !reportsHoverOpen.value;
        return;
    }

    toggleReportsNav();
}

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    localStorage.setItem(SIDEBAR_PREF_KEY, sidebarCollapsed.value ? '1' : '0');

    if (!sidebarCollapsed.value) {
        closeReportsHoverMenu();
        return;
    }

    updateReportsHoverPosition();
}

function closeMobileNav() {
    mobileNavOpen.value = false;
    closeReportsHoverMenu();
}

function openNotifications() {
    if (notificationsCloseTimer.value) {
        window.clearTimeout(notificationsCloseTimer.value);
        notificationsCloseTimer.value = null;
    }
    notificationsOpen.value = true;
}

function scheduleNotificationsClose() {
    if (notificationsCloseTimer.value) {
        window.clearTimeout(notificationsCloseTimer.value);
    }
    notificationsCloseTimer.value = window.setTimeout(() => {
        notificationsOpen.value = false;
        notificationsCloseTimer.value = null;
    }, 180);
}

function isBellProcessing(id: number | string) {
    return typeof id === 'number' && bellProcessingIds.value.includes(id);
}

function markBellRead(item: { id: number | string; is_read: boolean; kind?: string | null }) {
    if (item.kind === 'verification' || typeof item.id !== 'number') return;
    if (item.is_read || isBellProcessing(item.id)) return;
    const previous = item.is_read;
    item.is_read = true;
    bellProcessingIds.value = [...bellProcessingIds.value, item.id];

    router.put(
        `/announcements/${item.id}/read`,
        {},
        {
            preserveScroll: true,
            onError: () => {
                item.is_read = previous;
            },
            onFinish: () => {
                bellProcessingIds.value = bellProcessingIds.value.filter((id) => id !== item.id);
            },
        },
    );
}

function sendVerificationEmail(route?: string | null) {
    if (verificationSending.value) return;
    verificationSending.value = true;

    router.post(route || '/email/verification-notification', {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            verificationSending.value = false;
        },
    });
}

function onEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeMobileNav();
        closeReportsHoverMenu();
    }
}

function setDashboardPeriod(period: 'today' | 'week' | 'month') {
    if (hasDefaultSlot.value) return;
    router.get(
        '/AdminDashboard',
        { period },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

onMounted(() => {
    sidebarCollapsed.value = localStorage.getItem(SIDEBAR_PREF_KEY) === '1';
    reportsExpanded.value = currentPath.value.startsWith('/reports');
    window.addEventListener('keydown', onEscape);
    window.addEventListener('resize', updateReportsHoverPosition);
    window.addEventListener('scroll', updateReportsHoverPosition, true);
    notificationRouterStops.push(
        router.on('start', () => {
            notificationsLoading.value = true;
            notificationsFailed.value = false;
        }),
        router.on('finish', () => {
            notificationsLoading.value = false;
            notificationsReady.value = true;
        }),
        router.on('error', () => {
            notificationsFailed.value = true;
        }),
    );
});

onUnmounted(() => {
    window.removeEventListener('keydown', onEscape);
    window.removeEventListener('resize', updateReportsHoverPosition);
    window.removeEventListener('scroll', updateReportsHoverPosition, true);
    clearReportsHoverCloseTimer();
    document.body.style.overflow = '';
    notificationRouterStops.splice(0).forEach((stop) => stop());
});

watch(mobileNavOpen, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

watch(
    () => currentPath.value,
    (value) => {
        if (value.startsWith('/reports')) {
            reportsExpanded.value = true;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="admin-shell min-h-screen bg-[#f5f7fb] text-slate-900">
        <div class="bg-[radial-gradient(circle_at_top_right,rgba(3,68,133,0.10),transparent_36%)] pointer-events-none fixed inset-0 -z-10" />

        <div v-if="mobileNavOpen" class="admin-shell__mobile-overlay fixed inset-0 z-40 bg-slate-900/45 md:hidden" @click="closeMobileNav" />

        <aside
            class="admin-shell__sidebar hidden border-r backdrop-blur md:fixed md:inset-y-0 md:left-0 md:z-30 md:flex md:flex-col"
            :class="[
                'md:top-18 md:h-[calc(100vh-72px)]',
                sidebarCollapsed ? 'md:w-20' : 'md:w-64',
                isDarkMode
                    ? 'border-slate-800 bg-[#0b1220]/95'
                    : 'border-[#bfd4eb]/90 bg-[#eaf3ff]/95',
            ]"
        >
            <div class="flex h-full flex-col">
                <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-visible px-3 py-4">
                    <div
                        v-for="entry in pages"
                        :key="entry.name"
                        class="relative space-y-1"
                        @mouseenter="entry.children ? openReportsHoverMenu() : undefined"
                        @mouseleave="entry.children ? scheduleReportsHoverClose() : undefined"
                        @focusin="entry.children ? openReportsHoverMenu() : undefined"
                        @focusout="entry.children ? scheduleReportsHoverClose() : undefined"
                    >
                        <button
                            type="button"
                            :ref="entry.children ? setReportsTriggerRef : undefined"
                            @click="entry.children ? handleReportsEntryClick() : goTo(entry.route!)"
                            class="admin-shell__nav-item group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color,transform] duration-200 ease-out"
                            :class="[
                                isEntryActive(entry)
                                    ? isDarkMode
                                        ? 'admin-shell__nav-item--active border-[#034485] bg-[#034485] text-white shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                        : 'admin-shell__nav-item--active border-[#034485]/18 bg-white text-[#034485] shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                    : isDarkMode
                                        ? 'admin-shell__nav-item--inactive border-transparent text-slate-200 hover:border-slate-700 hover:bg-slate-900 hover:text-white'
                                        : 'admin-shell__nav-item--inactive border-transparent text-slate-700 hover:border-[#034485]/18 hover:bg-white/70 hover:text-[#034485]',
                                sidebarCollapsed && !mobileNavOpen ? 'justify-center px-2' : '',
                            ]"
                            :title="sidebarCollapsed ? entry.name : ''"
                            :aria-expanded="entry.children ? (sidebarCollapsed ? reportsHoverOpen : reportsExpanded) : undefined"
                            :aria-haspopup="entry.children ? 'menu' : undefined"
                        >
                            <svg
                                class="h-4.5 w-4.5 shrink-0"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    v-for="(path, idx) in entry.iconPaths"
                                    :key="`${entry.name}-icon-${idx}`"
                                    :d="path"
                                />
                            </svg>
                            <span
                                class="origin-left whitespace-nowrap transition-[max-width,opacity,transform,margin] duration-200 ease-out"
                                :class="
                                    sidebarCollapsed
                                        ? 'ml-0 max-w-45 scale-100 opacity-100 lg:max-w-0 lg:scale-95 lg:overflow-hidden lg:opacity-0'
                                        : 'ml-2 max-w-45 scale-100 opacity-100'
                                "
                            >
                                {{ entry.name }}
                            </span>
                            <svg
                                v-if="entry.children && !sidebarCollapsed"
                                class="ml-auto h-4 w-4 shrink-0 transition-transform duration-200"
                                :class="reportsExpanded ? 'rotate-180' : ''"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </button>

                        <div
                            v-if="entry.children && reportsExpanded && !sidebarCollapsed"
                            class="space-y-1 pl-4"
                        >
                            <button
                                v-for="child in entry.children"
                                :key="`${entry.name}-${child.name}`"
                                type="button"
                                @click="goToNavTarget(child.route)"
                                class="admin-shell__subnav-item flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color] duration-200"
                                :class="[
                                    isChildActive(child.route)
                                        ? isDarkMode
                                            ? 'admin-shell__subnav-item--active border-[#034485] bg-[#034485] text-white'
                                            : 'admin-shell__subnav-item--active border-[#034485]/18 bg-white text-[#034485]'
                                        : isDarkMode
                                            ? 'admin-shell__subnav-item--inactive border-transparent text-slate-300 hover:border-slate-700 hover:bg-slate-900 hover:text-white'
                                            : 'admin-shell__subnav-item--inactive border-transparent text-slate-600 hover:border-[#034485]/18 hover:bg-white hover:text-[#034485]',
                                ]"
                            >
                                <span class="truncate">{{ child.name }}</span>
                            </button>
                        </div>

                        <div
                            v-if="entry.children && reportsHoverOpen && sidebarCollapsed"
                            :style="reportsHoverStyle"
                            class="admin-shell__submenu z-[60] w-56 overflow-hidden rounded-xl border shadow-[0_24px_60px_-24px_rgba(15,23,42,0.45)]"
                            :class="isDarkMode ? 'border-slate-700 bg-[#0f172a]' : 'border-[#bfd4eb] bg-[#f7fbff]'"
                            @mouseenter="openReportsHoverMenu"
                            @mouseleave="scheduleReportsHoverClose"
                        >
                            <div class="border-b px-4 py-3" :class="isDarkMode ? 'border-slate-700' : 'border-[#d6e4f4]'">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em]" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">Reports</p>
                            </div>
                            <div class="space-y-1 p-2">
                                <button
                                    v-for="child in entry.children"
                                    :key="`${entry.name}-hover-${child.name}`"
                                    type="button"
                                    @click="goToNavTarget(child.route)"
                                    role="menuitem"
                                    class="admin-shell__subnav-item flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color] duration-200"
                                    :class="[
                                        isChildActive(child.route)
                                            ? isDarkMode
                                                ? 'admin-shell__subnav-item--active border-[#034485] bg-[#034485] text-white'
                                                : 'admin-shell__subnav-item--active border-[#034485]/18 bg-white text-[#034485]'
                                            : isDarkMode
                                                ? 'admin-shell__subnav-item--inactive border-transparent text-slate-200 hover:border-slate-700 hover:bg-slate-900 hover:text-white'
                                                : 'admin-shell__subnav-item--inactive border-transparent text-slate-700 hover:border-[#034485]/18 hover:bg-white hover:text-[#034485]',
                                    ]"
                                >
                                    <span class="truncate">{{ child.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="admin-shell__sidebar-footer border-t px-3 py-3" :class="isDarkMode ? 'border-slate-800' : 'border-[#d6e4f4]'">
                    <button
                        type="button"
                        class="admin-shell__utility-item group mb-2 flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                        :class="[
                            isSettingsRoute
                                ? isDarkMode
                                    ? 'admin-shell__utility-item--active border-[#034485] bg-[#034485] text-white shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                    : 'admin-shell__utility-item--active border-[#034485]/18 bg-white text-[#034485] shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                : isDarkMode
                                    ? 'admin-shell__utility-item--inactive border-transparent text-slate-200 hover:border-slate-700 hover:bg-slate-900 hover:text-white'
                                    : 'admin-shell__utility-item--inactive border-transparent text-slate-700 hover:border-[#034485]/18 hover:bg-white hover:text-[#034485]',
                            sidebarCollapsed ? 'justify-center' : '',
                        ]"
                        @click="goTo('/account/settings')"
                    >
                        <svg
                            class="h-4.5 w-4.5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="3" />
                            <path
                                d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.05.05a2 2 0 1 1-2.83 2.83l-.05-.05A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1v.2a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-.4-1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.87.34l-.05.05a2 2 0 1 1-2.83-2.83l.05-.05A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1-.4H2.8a2 2 0 1 1 0-4H3a1.7 1.7 0 0 0 1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.87l-.05-.05A2 2 0 1 1 7.04 3.8l.05.05A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1V2.8a2 2 0 1 1 4 0V3a1.7 1.7 0 0 0 .4 1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.05-.05A2 2 0 1 1 20.2 7.04l-.05.05A1.7 1.7 0 0 0 19.4 9c0 .4.2.77.6 1 .3.2.64.35 1 .4h.2a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1 .4 1.7 1.7 0 0 0-.6 1z"
                            />
                        </svg>
                        <span v-if="!sidebarCollapsed" class="ml-2">Settings</span>
                    </button>
                    <button
                        type="button"
                        class="admin-shell__utility-item group mb-2 flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                        :class="[
                            isHelpRoute
                                ? isDarkMode
                                    ? 'admin-shell__utility-item--active border-[#034485] bg-[#034485] text-white shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                    : 'admin-shell__utility-item--active border-[#034485]/18 bg-white text-[#034485] shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                : isDarkMode
                                    ? 'admin-shell__utility-item--inactive border-transparent text-slate-200 hover:border-slate-700 hover:bg-slate-900 hover:text-white'
                                    : 'admin-shell__utility-item--inactive border-transparent text-slate-700 hover:border-[#034485]/18 hover:bg-white hover:text-[#034485]',
                            sidebarCollapsed ? 'justify-center' : '',
                        ]"
                        @click="goTo('/account/help')"
                    >
                        <svg
                            class="h-4.5 w-4.5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 17v.01" />
                            <path d="M12 13a2 2 0 1 0-2-2" />
                        </svg>
                        <span v-if="!sidebarCollapsed" class="ml-2">Help &amp; Support</span>
                    </button>
                    <button
                        type="button"
                        class="admin-shell__logout-item group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                        :class="[
                            isDarkMode
                                ? 'border-transparent text-rose-300 hover:border-rose-900/60 hover:bg-rose-950/30'
                                : 'border-transparent text-rose-600 hover:border-rose-200 hover:bg-rose-50',
                            sidebarCollapsed ? 'justify-center' : '',
                        ]"
                        @click="logout"
                    >
                        <svg
                            class="h-4.5 w-4.5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <path d="M16 17l5-5-5-5" />
                            <path d="M21 12H9" />
                        </svg>
                        <span v-if="!sidebarCollapsed" class="ml-2">Logout</span>
                    </button>
                </div>
            </div>
        </aside>

        <aside
            class="admin-shell__mobile-sidebar fixed inset-y-0 left-0 z-50 w-[82vw] max-w-xs border-r p-4 transition md:hidden"
            :class="[
                isDarkMode ? 'border-slate-800 bg-[#0b1220]' : 'border-[#bfd4eb] bg-[#eef5ff]',
                mobileNavOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm font-bold" :class="isDarkMode ? 'text-slate-100' : 'text-[#1f2937]'">Admin Menu</p>
                <button
                    type="button"
                    class="rounded border px-2 py-1 text-xs"
                    :class="isDarkMode ? 'border-slate-700 bg-[#111111] text-slate-200' : 'border-slate-300 text-slate-700'"
                    @click="closeMobileNav"
                >
                    Close
                </button>
            </div>

            <div class="space-y-2">
                <template v-for="entry in pages" :key="`mobile-${entry.name}`">
                    <button
                        type="button"
                        class="admin-shell__nav-item group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color,transform] duration-200 ease-out"
                        :class="[
                            isEntryActive(entry)
                                ? isDarkMode
                                    ? 'admin-shell__nav-item--active border-[#034485] bg-[#034485] text-white shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                    : 'admin-shell__nav-item--active border-[#034485]/18 bg-white text-[#034485] shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                : isDarkMode
                                    ? 'admin-shell__nav-item--inactive border-transparent text-slate-200 hover:border-slate-700 hover:bg-slate-900 hover:text-white'
                                    : 'admin-shell__nav-item--inactive border-transparent text-slate-700 hover:border-[#034485]/18 hover:bg-white/70 hover:text-[#034485]',
                        ]"
                        @click="entry.children ? handleReportsEntryClick() : goTo(entry.route!)"
                    >
                        <svg
                            class="h-4.5 w-4.5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path
                                v-for="(path, idx) in entry.iconPaths"
                                :key="`mobile-${entry.name}-icon-${idx}`"
                                :d="path"
                            />
                        </svg>
                        <span class="ml-2">{{ entry.name }}</span>
                        <svg
                            v-if="entry.children"
                            class="ml-auto h-4 w-4 shrink-0 transition-transform duration-200"
                            :class="reportsExpanded ? 'rotate-180' : ''"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </button>

                    <div v-if="entry.children && reportsExpanded" class="space-y-1 pl-4">
                        <button
                            v-for="child in entry.children"
                            :key="`mobile-${entry.name}-${child.name}`"
                            type="button"
                            @click="goToNavTarget(child.route)"
                            class="admin-shell__subnav-item flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-[background-color,color,border-color] duration-200"
                            :class="[
                                isChildActive(child.route)
                                    ? isDarkMode
                                        ? 'admin-shell__subnav-item--active border-[#034485] bg-[#034485] text-white'
                                        : 'admin-shell__subnav-item--active border-[#034485]/18 bg-white text-[#034485]'
                                    : isDarkMode
                                        ? 'admin-shell__subnav-item--inactive border-transparent text-slate-300 hover:border-slate-700 hover:bg-slate-900 hover:text-white'
                                        : 'admin-shell__subnav-item--inactive border-transparent text-slate-600 hover:border-[#034485]/18 hover:bg-white hover:text-[#034485]',
                            ]"
                        >
                            <span class="truncate">{{ child.name }}</span>
                        </button>
                    </div>
                </template>

                <button
                    type="button"
                    class="admin-shell__utility-item group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                    :class="[
                        isSettingsRoute
                            ? isDarkMode
                                ? 'admin-shell__utility-item--active border-[#034485] bg-[#034485] text-white shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                : 'admin-shell__utility-item--active border-[#034485]/18 bg-white text-[#034485] shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                            : isDarkMode
                                ? 'admin-shell__utility-item--inactive border-transparent text-slate-200 hover:border-slate-700 hover:bg-slate-900 hover:text-white'
                                : 'admin-shell__utility-item--inactive border-transparent text-slate-700 hover:border-[#034485]/18 hover:bg-white hover:text-[#034485]',
                    ]"
                    @click="goTo('/account/settings')"
                >
                    <svg
                        class="h-4.5 w-4.5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="3" />
                        <path
                            d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.05.05a2 2 0 1 1-2.83 2.83l-.05-.05A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1v.2a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-.4-1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.87.34l-.05.05a2 2 0 1 1-2.83-2.83l.05-.05A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1-.4H2.8a2 2 0 1 1 0-4H3a1.7 1.7 0 0 0 1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.87l-.05-.05A2 2 0 1 1 7.04 3.8l.05.05A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1V2.8a2 2 0 1 1 4 0V3a1.7 1.7 0 0 0 .4 1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.05-.05A2 2 0 1 1 20.2 7.04l-.05.05A1.7 1.7 0 0 0 19.4 9c0 .4.2.77.6 1 .3.2.64.35 1 .4h.2a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1 .4 1.7 1.7 0 0 0-.6 1z"
                        />
                    </svg>
                    <span class="ml-2">Settings</span>
                </button>

                <button
                    type="button"
                    class="admin-shell__utility-item group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                    :class="[
                        isHelpRoute
                            ? isDarkMode
                                ? 'admin-shell__utility-item--active border-[#034485] bg-[#034485] text-white shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                                : 'admin-shell__utility-item--active border-[#034485]/18 bg-white text-[#034485] shadow-[0_18px_36px_-28px_rgba(3,68,133,0.55)]'
                            : isDarkMode
                                ? 'admin-shell__utility-item--inactive border-transparent text-slate-200 hover:border-slate-700 hover:bg-slate-900 hover:text-white'
                                : 'admin-shell__utility-item--inactive border-transparent text-slate-700 hover:border-[#034485]/18 hover:bg-white hover:text-[#034485]',
                    ]"
                    @click="goTo('/account/help')"
                >
                    <svg
                        class="h-4.5 w-4.5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 17v.01" />
                        <path d="M12 13a2 2 0 1 0-2-2" />
                    </svg>
                    <span class="ml-2">Help &amp; Support</span>
                </button>

                <button
                    type="button"
                    class="admin-shell__logout-item group flex w-full items-center rounded-lg border px-3 py-2 text-left text-sm font-medium transition-all duration-200"
                    :class="isDarkMode ? 'border-transparent text-rose-300 hover:border-rose-900/60 hover:bg-rose-950/30' : 'border-transparent text-rose-600 hover:border-rose-200 hover:bg-rose-50'"
                    @click="logout"
                >
                    <svg
                        class="h-4.5 w-4.5 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
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

        <header class="admin-shell__topbar fixed inset-x-0 top-0 z-40 border-b border-slate-200/80 bg-white/88 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)] backdrop-blur">
            <div class="flex w-full items-center justify-between gap-3 pl-0 pr-2 py-3 sm:pl-0 sm:pr-3 lg:pl-0 lg:pr-4">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="admin-shell__nav-toggle inline-flex h-10 w-10 items-center justify-center rounded-lg border md:hidden"
                        @click="mobileNavOpen = true"
                        aria-label="Open menu"
                    >
                        <span class="space-y-1">
                            <span class="block h-0.5 w-4 bg-current" />
                            <span class="block h-0.5 w-4 bg-current" />
                            <span class="block h-0.5 w-4 bg-current" />
                        </span>
                    </button>

                    <div class="min-w-0 flex items-center gap-2">
                        <div class="admin-shell__brand-chip inline-flex max-w-full flex-col px-1 py-1">
                            <p class="admin-shell__kicker truncate text-[11px] font-semibold tracking-[0.18em] text-white/80 uppercase">AC VMIS Admin</p>
                            <div class="flex min-w-0 items-center gap-2">
                                <h2 class="admin-shell__title truncate text-sm font-semibold text-white sm:text-base">{{ currentPageName }}</h2>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="admin-shell__nav-toggle hidden h-10 w-10 items-center justify-center rounded-lg border md:inline-flex"
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
                            class="announcement-bell relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-[#034485]/30 hover:text-[#034485]"
                            aria-label="Open announcements"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5" />
                                <path d="M9 17a3 3 0 0 0 6 0" />
                            </svg>
                            <span
                                v-if="notificationsLoading"
                                class="absolute -right-1 -top-1 h-4 w-4 rounded-full border-2 border-[#034485]/25 border-t-[#034485] bg-white animate-spin"
                                aria-label="Loading notifications"
                            />
                            <span
                                v-else-if="notificationsFailed"
                                class="absolute -right-1 -top-1 h-3.5 w-3.5 rounded-full border border-white bg-amber-400"
                                title="Notifications could not refresh"
                            />
                            <span
                                v-else-if="notificationsReady && bellUnreadCount > 0"
                                class="absolute -top-1 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                            >
                                {{ bellUnreadCount }}
                            </span>
                        </button>

                        <div
                            v-if="notificationsOpen"
                            class="absolute right-0 mt-2 w-72 overflow-hidden rounded-xl border border-[#034485]/15 bg-white shadow-[0_18px_46px_rgba(15,23,42,0.18)]"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-200 px-3 py-2 text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >
                                Announcements
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                                    {{ notificationsLoading ? '...' : notificationsFailed ? '!' : bellUnreadCount }}
                                </span>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                <div v-if="notificationsLoading" class="flex items-center gap-2 px-3 py-4 text-xs font-semibold text-slate-500">
                                    <span class="h-3.5 w-3.5 rounded-full border-2 border-[#034485]/25 border-t-[#034485] animate-spin"></span>
                                    Loading notifications...
                                </div>
                                <div v-else-if="notificationsFailed" class="px-3 py-4 text-xs font-semibold text-amber-700">
                                    Notifications could not refresh. You can still open Announcements.
                                </div>
                                <button
                                    v-for="item in adminNotifications"
                                    :key="item.id ?? item.title"
                                    type="button"
                                    class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm transition"
                                    :class="
                                        item.kind === 'verification'
                                            ? 'border-b border-amber-200 bg-amber-50 text-amber-950'
                                            : item.is_read
                                                ? 'border-b border-slate-200 text-slate-700 hover:bg-slate-50'
                                                : 'border-b border-[#034485]/15 bg-[#034485] text-white hover:bg-[#033a70]'
                                    "
                                    @click="item.kind === 'verification' ? void 0 : (markBellRead(item), goTo('/announcements'))"
                                >
                                    <span
                                        v-if="item.kind !== 'verification'"
                                        class="mt-1 inline-flex h-2 w-2 shrink-0 rounded-full"
                                        :class="item.is_read ? 'bg-[#60a5fa]' : 'bg-white'"
                                    />
                                    <span v-else class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M12 9v4" />
                                            <path d="M12 17h.01" />
                                            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                        </svg>
                                    </span>
                                    <span class="flex-1">
                                        <span class="block font-semibold" :class="item.kind === 'verification' ? 'text-amber-950' : item.is_read ? 'text-slate-700' : 'text-white'">{{ item.title }}</span>
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
                                                @click.stop="goTo(item.settings_href || '/account/account-settings')"
                                            >
                                                {{ item.secondary_action_label || 'Go to Account Settings' }}
                                            </button>
                                        </span>
                                    </span>
                                    <span
                                        v-if="item.kind !== 'verification'"
                                        class="ml-auto text-[10px] font-semibold"
                                        :class="item.is_read ? 'text-slate-500' : 'text-white/70'"
                                    >
                                        {{ item.published_at ?? '' }}
                                    </span>
                                </button>
                                <div v-if="!notificationsLoading && !notificationsFailed && adminNotifications.length === 0" class="px-3 py-4 text-xs text-slate-500">No announcements right now.</div>
                            </div>
                            <div class="border-t border-slate-200 px-3 py-2">
                                <button
                                    type="button"
                                    class="w-full rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-[#034485]/30 hover:bg-slate-50"
                                    @click="goTo('/announcements')"
                                >
                                    View all
                                </button>
                            </div>
                        </div>
                    </div>
                    <UserAccountMenu :inverse="false" menu-placement="bottom" compact />
                </div>
            </div>
        </header>

        <div class="admin-shell__content pt-18 transition-[padding] duration-300 ease-out will-change-[padding]" :class="sidebarCollapsed ? 'md:pl-20' : 'md:pl-64'">
            <main class="mx-auto w-full max-w-400 px-4 py-4 sm:px-6 md:px-6 lg:px-8">
                <slot v-if="hasDefaultSlot" />

                <div v-else-if="dashboard" class="space-y-5">
                    <section class="admin-dashboard-title-card page-card rounded-3xl border border-[#034485]/35 bg-[#034485] p-6 text-white">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Asian College</p>
                            <h3 class="text-2xl font-bold">Administrative Dashboard</h3>
                            <p class="max-w-3xl text-sm text-white/85">Centralized oversight for varsity operations, student-athlete compliance, and team activity monitoring.</p>
                        </div>
                    </section>

                    <section class="page-card rounded-2xl border border-[#034485]/18 bg-white p-4">
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="selectedPeriod === 'today' ? 'bg-[#034485] text-white' : 'bg-slate-100 text-slate-700'"
                                @click="setDashboardPeriod('today')"
                            >
                                Today
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="selectedPeriod === 'week' ? 'bg-[#034485] text-white' : 'bg-slate-100 text-slate-700'"
                                @click="setDashboardPeriod('week')"
                            >
                                This Week
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="selectedPeriod === 'month' ? 'bg-[#034485] text-white' : 'bg-slate-100 text-slate-700'"
                                @click="setDashboardPeriod('month')"
                            >
                                This Month
                            </button>
                        </div>
                    </section>

                    <section class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">{{ attendanceKpiLabel }}</p>
                            <p class="mt-2 text-2xl font-bold text-[#034485]">{{ attendanceKpiValue }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ attendanceKpiHint }}</p>
                        </article>
                        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">No Response</p>
                            <p class="mt-2 text-2xl font-bold text-[#034485]">{{ dashboard.kpis.no_response }}</p>
                        </article>
                        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Active Teams</p>
                            <p class="mt-2 text-2xl font-bold text-[#034485]">{{ dashboard.kpis.active_teams }}</p>
                        </article>
                        <article class="page-card rounded-2xl border border-[#034485]/22 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Pending Academic Review</p>
                            <p class="mt-2 text-2xl font-bold text-[#034485]">{{ dashboard.kpis.pending_academic_review }}</p>
                        </article>
                    </section>

                    <section class="page-card rounded-2xl border border-[#034485]/18 bg-white p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Quick Actions</p>
                                <h4 class="mt-1 text-base font-semibold text-slate-900">Open Key Admin Workspaces</h4>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <button
                                type="button"
                                class="rounded-2xl border border-[#034485]/18 bg-[#034485]/5 px-4 py-4 text-left transition hover:bg-[#034485]/10"
                                @click="goTo('/people')"
                            >
                                <p class="text-sm font-semibold text-[#034485]">User Records</p>
                                <p class="mt-1 text-xs text-slate-600">Review user information, account status, and history.</p>
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-[#034485]/18 bg-[#034485]/5 px-4 py-4 text-left transition hover:bg-[#034485]/10"
                                @click="goTo('/teams')"
                            >
                                <p class="text-sm font-semibold text-[#034485]">Team Monitoring</p>
                                <p class="mt-1 text-xs text-slate-600">Monitor created teams, rosters, and status by sport.</p>
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-[#034485]/18 bg-[#034485]/5 px-4 py-4 text-left transition hover:bg-[#034485]/10"
                                @click="goTo('/academics')"
                            >
                                <p class="text-sm font-semibold text-[#034485]">Academics</p>
                                <p class="mt-1 text-xs text-slate-600">Open period management and evaluation review.</p>
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-[#034485]/18 bg-[#034485]/5 px-4 py-4 text-left transition hover:bg-[#034485]/10"
                                @click="goTo('/operations')"
                            >
                                <p class="text-sm font-semibold text-[#034485]">Operations</p>
                                <p class="mt-1 text-xs text-slate-600">Monitor schedules and attendance activity.</p>
                            </button>
                        </div>
                    </section>

                    <section class="page-card rounded-2xl border border-[#034485]/18 bg-white p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Attendance Trend</p>
                                <h4 class="mt-1 text-base font-semibold text-slate-900">Attendance Response Overview</h4>
                                <p class="mt-1 text-xs text-slate-600">View attendance behavior as a stacked comparison or a trend line across the selected dashboard period.</p>
                            </div>
                            <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                                <button
                                    type="button"
                                    class="w-full rounded-full px-3 py-2 text-center text-xs font-medium sm:w-auto sm:py-1"
                                    :class="attendanceChartMode === 'stacked' ? 'bg-[#034485] text-white' : 'bg-slate-100 text-slate-700'"
                                    @click="attendanceChartMode = 'stacked'"
                                >
                                    Stacked Bar
                                </button>
                                <button
                                    type="button"
                                    class="w-full rounded-full px-3 py-2 text-center text-xs font-medium sm:w-auto sm:py-1"
                                    :class="attendanceChartMode === 'line' ? 'bg-[#034485] text-white' : 'bg-slate-100 text-slate-700'"
                                    @click="attendanceChartMode = 'line'"
                                >
                                    Trend Line
                                </button>
                            </div>
                        </div>

                        <div v-if="dashboard.trends.labels.length === 0" class="mt-4 rounded-2xl border border-dashed border-[#034485]/25 bg-[#034485]/5 px-4 py-10 text-center text-sm text-slate-500">
                            No attendance trend data is available for this period.
                        </div>

                        <VueApexCharts
                            v-else
                            class="mt-4"
                            height="360"
                            :type="attendanceChartMode === 'stacked' ? 'bar' : 'line'"
                            :options="attendanceChartOptions"
                            :series="attendanceTrendSeries"
                        />
                    </section>

                    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <article class="page-card rounded-2xl border border-[#034485]/18 bg-white p-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Attendance by Team</p>
                                <h4 class="mt-1 text-base font-semibold text-slate-900">Team Attendance Comparison</h4>
                                <p class="mt-1 text-xs text-slate-600">Compare which teams are performing best and worst in attendance for the selected period.</p>
                            </div>

                            <div
                                v-if="dashboard.trends.attendance_by_team.labels.length === 0"
                                class="mt-4 rounded-2xl border border-dashed border-[#034485]/25 bg-[#034485]/5 px-4 py-10 text-center text-sm text-slate-500"
                            >
                                No team attendance data is available for this period.
                            </div>

                            <VueApexCharts
                                v-else
                                class="mt-4"
                                height="320"
                                type="bar"
                                :options="attendanceByTeamOptions"
                                :series="attendanceByTeamSeries"
                            />
                        </article>

                    </section>

                    <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.45fr)]">
                        <div class="space-y-4">
                            <article class="page-card rounded-2xl border border-[#034485]/18 bg-white p-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Pending Items</p>
                                    <h4 class="mt-1 text-base font-semibold text-slate-900">Immediate Review Counts</h4>
                                    <p class="mt-1 text-xs text-slate-600">Keep track of academic reviews and teams that still need recent attendance posting.</p>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div class="flex items-center justify-between rounded-2xl border border-[#034485]/18 bg-[#034485]/5 px-4 py-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Pending Academic Reviews</p>
                                            <p class="mt-1 text-xs text-slate-600">Evaluations still awaiting final review handling.</p>
                                        </div>
                                        <span class="rounded-full bg-[#034485] px-3 py-1 text-xs font-semibold text-white">
                                            {{ dashboard.pending_items.pending_academic_reviews }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between rounded-2xl border border-[#034485]/18 bg-[#034485]/5 px-4 py-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Teams Without Recent Attendance</p>
                                            <p class="mt-1 text-xs text-slate-600">Active teams with schedules in range but no attendance posting yet.</p>
                                        </div>
                                        <span class="rounded-full bg-[#034485] px-3 py-1 text-xs font-semibold text-white">
                                            {{ dashboard.pending_items.teams_without_recent_attendance }}
                                        </span>
                                    </div>
                                </div>
                            </article>

                            <article class="page-card rounded-2xl border border-[#034485]/18 bg-white p-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Academic Status</p>
                                    <h4 class="mt-1 text-base font-semibold text-slate-900">Eligibility Distribution</h4>
                                    <p class="mt-1 text-xs text-slate-600">View the current mix of eligible, pending review, and ineligible student-athlete evaluations.</p>
                                </div>

                                <div
                                    v-if="academicStatusSeries.every((value) => Number(value) === 0)"
                                    class="mt-4 rounded-2xl border border-dashed border-[#034485]/25 bg-[#034485]/5 px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    No academic evaluation status data is available right now.
                                </div>

                                <template v-else>
                                    <VueApexCharts class="mt-4" height="320" type="donut" :options="academicStatusOptions" :series="academicStatusSeries" />

                                    <div class="mt-3 grid grid-cols-3 gap-2">
                                        <div class="rounded-2xl border border-[#034485]/18 bg-[#034485]/5 px-3 py-3 text-center">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#034485]/80">Eligible</p>
                                            <p class="mt-1 text-lg font-bold text-[#034485]">{{ dashboard.academic_status.eligible }}</p>
                                        </div>
                                        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-3 py-3 text-center">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-sky-700">Pending</p>
                                            <p class="mt-1 text-lg font-bold text-sky-700">{{ dashboard.academic_status.pending_review }}</p>
                                        </div>
                                        <div class="rounded-2xl border border-blue-200 bg-blue-50 px-3 py-3 text-center">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-blue-700">Ineligible</p>
                                            <p class="mt-1 text-lg font-bold text-blue-700">{{ dashboard.academic_status.ineligible }}</p>
                                        </div>
                                    </div>
                                </template>
                            </article>
                        </div>

                        <article class="page-card rounded-2xl border border-[#034485]/18 bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#034485]">Recent Activity</p>
                                    <h4 class="mt-1 text-base font-semibold text-slate-900">Latest Operational Updates</h4>
                                    <p class="mt-1 text-xs text-slate-600">Follow recent approvals, academic reviews, roster updates, and attendance postings from the latest system activity.</p>
                                </div>
                            </div>

                            <div v-if="dashboard.recent_activity.length === 0" class="mt-4 rounded-2xl border border-dashed border-[#034485]/25 bg-[#034485]/5 px-4 py-10 text-center text-sm text-slate-500">
                                No recent activity is available right now.
                            </div>

                            <div v-else class="mt-4 space-y-3">
                                <article
                                    v-for="item in dashboard.recent_activity"
                                    :key="item.id"
                                    class="page-card rounded-2xl border border-[#034485]/18 bg-[#034485]/[0.03] px-4 py-3"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span
                                                    class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em]"
                                                    :class="activityTypeClasses(item.type)"
                                                >
                                                    {{ activityTypeLabel(item.type) }}
                                                </span>
                                                <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                                            </div>
                                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ item.description }}</p>
                                        </div>
                                        <p class="shrink-0 text-xs font-medium text-slate-500">
                                            {{ formatDashboardActivityTime(item.happened_at) }}
                                        </p>
                                    </div>
                                </article>
                            </div>
                        </article>
                    </section>

                </div>

                <div v-else class="space-y-5">
                    <section class="page-card rounded-xl border border-[#034485]/22 bg-white p-5">
                        <h3 class="text-xl font-bold text-slate-900">Dashboard</h3>
                        <p class="mt-1 text-sm text-slate-600">No dashboard data available.</p>
                    </section>
                </div>
            </main>

            <RoleFooter
                title="AC VMIS"
                description="Manage varsity operations, attendance, and academic workflows from one dashboard."
                :links="footerLinks"
            />
        </div>

        <AdminBottomNav :items="adminBottomNavItems" :is-active="isActive" />
    </div>
</template>

<style scoped>
.admin-shell__content {
    padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));
}

@media (min-width: 768px) {
    .admin-shell__content {
        padding-bottom: 0;
    }
}

:deep(.page-card) {
    animation: adminCardRise 0.5s ease-out both;
}

:deep(.page-card:nth-child(2)) {
    animation-delay: 0.05s;
}

:deep(.page-card:nth-child(3)) {
    animation-delay: 0.1s;
}

:deep(.page-card:nth-child(4)) {
    animation-delay: 0.15s;
}

@keyframes adminCardRise {
    from {
        opacity: 0;
        transform: translateY(16px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    :deep(.page-card) {
        animation: none !important;
    }
}
</style>
