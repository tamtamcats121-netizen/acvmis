<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import CoachBottomNav from '@/components/coach/CoachBottomNav.vue';
import RoleFooter from '@/components/ui/RoleFooter.vue';
import UserAccountMenu from '@/components/UserAccountMenu.vue';
import { coachPrimaryNav, coachSecondaryNav } from '@/config/coachNav';


const props = defineProps<{
    title?: string;
}>();

const page = usePage();
const currentPath = computed(() => String(page.url || ''));
const mobileMenuOpen = ref(false);
const isNavCollapsed = ref(false);
const notificationsOpen = ref(false);
const notificationsCloseTimer = ref<number | null>(null);

const coachNotifications = ref<
    Array<{
        id: number;
        title: string;
        message: string;
        type: string;
        is_read: boolean;
        published_at: string | null;
    }>
>([]);
const bellProcessingIds = ref<number[]>([]);

watch(
    () => (page.props.auth as any)?.coach_notifications?.recent,
    (items) => {
        coachNotifications.value = Array.isArray(items) ? items.map((item: any) => ({ ...item })) : [];
    },
    { immediate: true },
);

const notificationsCount = computed(() => {
    if (coachNotifications.value.length) {
        return coachNotifications.value.filter((item) => !item.is_read).length;
    }
    const unread = (page.props.auth as any)?.announcements?.unread_count;
    if (typeof unread === 'number') return unread;
    const fallback = (page.props.auth as any)?.coach_notifications?.items;
    if (Array.isArray(fallback)) {
        return fallback.reduce((sum, item) => sum + Number(item.count || 0), 0);
    }
    return 0;
});

const primaryItems = coachPrimaryNav;
const secondaryItems = coachSecondaryNav;
const hasSecondaryItems = secondaryItems.length > 0;
const navToggleLabel = computed(() => (isNavCollapsed.value ? 'Expand sidebar' : 'Collapse sidebar'));

const footerLinks = [
    { label: 'Dashboard', href: '/coach/dashboard' },
    { label: 'Schedule & Attendance', href: '/coach/schedule' },
    { label: 'Wellness', href: '/coach/wellness' },
    { label: 'Team', href: '/coach/team' },
    { label: 'Academics', href: '/coach/academics' },
    { label: 'Announcements', href: '/announcements' },
    { label: 'Profile', href: '/account/profile' },
    { label: 'Settings', href: '/account/settings' },
];

const activeLabel = computed(() => {
    const all = [...primaryItems, ...secondaryItems];
    const found = all.find((item) => isActive(item.route));
    return found?.label ?? props.title ?? 'Dashboard';
});

function isActive(route: string): boolean {
    return currentPath.value === route || currentPath.value.startsWith(`${route}/`);
}

function go(route: string) {
    mobileMenuOpen.value = false;
    router.get(route);
}

function logout() {
    mobileMenuOpen.value = false;
    router.post('/logout');
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

function isBellProcessing(id: number) {
    return bellProcessingIds.value.includes(id);
}

function markBellRead(item: { id: number; is_read: boolean }) {
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

function toggleNav() {
    isNavCollapsed.value = !isNavCollapsed.value;
}

function onEsc(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        mobileMenuOpen.value = false;
    }
}

function iconPath(icon: string) {
    if (icon === 'layout-grid') return 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z';
    if (icon === 'users') return 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6M23 11h-6';
    if (icon === 'calendar') return 'M8 2v3M16 2v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z';
    if (icon === 'heart-pulse') return 'M22 12h-4l-3 8-4-16-3 8H2';
    if (icon === 'graduation-cap') return 'M2 9l10-5 10 5-10 5-10-5zM6 11v5c0 1.6 2.7 3 6 3s6-1.4 6-3v-5';
    if (icon === 'clipboard-check')
        return 'M9 3h6M9 1h6a2 2 0 0 1 2 2v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a2 2 0 0 1 2-2zm-1 11l2 2 4-4';
    if (icon === 'bell') return 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0';
    if (icon === 'user') return 'M20 21a8 8 0 0 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8';
    if (icon === 'settings')
        return 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 0 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.2a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.2a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z';
    return 'M12 2v20M2 12h20';
}

onMounted(() => {
    const saved = window.localStorage.getItem('coach_nav_collapsed');
    if (saved !== null) {
        isNavCollapsed.value = saved === '1';
    }
    window.addEventListener('keydown', onEsc);
});
onUnmounted(() => {
    window.removeEventListener('keydown', onEsc);
    document.body.style.overflow = '';
});

watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

watch(isNavCollapsed, (collapsed) => {
    window.localStorage.setItem('coach_nav_collapsed', collapsed ? '1' : '0');
});
</script>

<template>
    <div class="coach-shell min-h-screen bg-[#f5f7fb] text-slate-900" :class="isNavCollapsed ? 'coach-shell--collapsed' : ''">
        <div
            class="coach-shell__glow bg-[radial-gradient(circle_at_top_right,rgba(3,68,133,0.10),transparent_40%)] pointer-events-none fixed inset-0 -z-10"
        />

        <div v-if="mobileMenuOpen" class="fixed inset-0 z-40 bg-slate-900/45 md:hidden" @click="mobileMenuOpen = false" />

        <header class="coach-shell__topbar fixed inset-x-0 top-0 z-40 border-b border-slate-200/80 bg-white/88 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)] backdrop-blur">
            <div class="flex w-full items-center gap-3 pl-0 pr-2 py-3 sm:pl-0 sm:pr-3 lg:pl-0 lg:pr-4">
                <button
                    type="button"
                    class="coach-shell__nav-toggle inline-flex h-10 w-10 items-center justify-center rounded-lg border md:hidden"
                    @click="mobileMenuOpen = true"
                    aria-label="Open menu"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M3 6h18M3 12h18M3 18h18" />
                    </svg>
                </button>

                <div class="min-w-0 flex flex-1 items-center gap-2">
                    <div class="coach-shell__brand min-w-0 rounded-2xl bg-[#034485] px-3 py-2 shadow-[0_14px_28px_-20px_rgba(3,68,133,0.55)]">
                        <p class="coach-shell__brand-title truncate text-[11px] font-semibold tracking-[0.18em] text-white uppercase">AC VMIS Coach</p>
                        <p class="coach-shell__brand-subtitle truncate text-sm font-semibold text-white sm:text-base">{{ activeLabel }}</p>
                    </div>

                    <button
                        type="button"
                        class="coach-shell__nav-toggle hidden h-10 w-10 items-center justify-center rounded-lg border md:inline-flex"
                        :aria-label="navToggleLabel"
                        :title="navToggleLabel"
                        @click="toggleNav"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path v-if="isNavCollapsed" d="M9 6l6 6-6 6" />
                            <path v-else d="M15 6l-6 6 6 6" />
                        </svg>
                    </button>
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
                            class="announcement-bell relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-900"
                            aria-label="Open announcements"
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
                            class="coach-shell__announcement-menu absolute right-0 mt-2 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-200 px-3 py-2 text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >
                                Announcements
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ notificationsCount }}</span>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                <button
                                    v-for="item in coachNotifications"
                                    :key="item.id ?? item.title"
                                    type="button"
                                    class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm transition"
                                    :class="
                                        item.is_read
                                            ? 'border-b border-slate-100 text-slate-700 hover:bg-slate-50'
                                            : 'border-b border-white/10 bg-[#034485] text-white hover:bg-[#033a70]'
                                    "
                                    @click="
                                        markBellRead(item);
                                        go('/announcements');
                                    "
                                >
                                    <span class="mt-1 inline-flex h-2 w-2 shrink-0 rounded-full" :class="item.is_read ? 'bg-[#034485]' : 'bg-white'" />
                                    <span class="flex-1">
                                        <span class="block font-semibold" :class="item.is_read ? 'text-slate-800' : 'text-white'">{{ item.title }}</span>
                                        <span class="block text-xs" :class="item.is_read ? 'text-slate-500' : 'text-white/80'">{{ item.message }}</span>
                                    </span>
                                    <span class="ml-auto text-[10px] font-semibold" :class="item.is_read ? 'text-slate-400' : 'text-white/70'">{{ item.published_at ?? '' }}</span>
                                </button>
                                <div v-if="coachNotifications.length === 0" class="px-3 py-4 text-xs text-slate-500">No announcements right now.</div>
                            </div>
                            <div class="border-t border-slate-200 px-3 py-2">
                                <button
                                    type="button"
                                    class="w-full rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                                    @click="go('/announcements')"
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

        <div class="transition-all duration-300 ease-out pt-18" :class="isNavCollapsed ? 'md:pl-20' : 'md:pl-64'">
            <aside
                class="hidden border-r border-[#bfd4eb]/90 bg-[#eaf3ff]/95 backdrop-blur md:fixed md:inset-y-0 md:left-0 md:z-30 md:flex md:flex-col"
                :class="[isNavCollapsed ? 'md:w-20' : 'md:w-64', 'md:top-18 md:h-[calc(100vh-72px)]']"
            >
                <nav class="flex-1 space-y-1 px-3 py-4" aria-label="Primary">
                    <button
                        v-for="item in primaryItems"
                        :key="item.key"
                        type="button"
                        class="coach-side-link"
                        :class="[isActive(item.route) ? 'coach-side-link--active' : '', isNavCollapsed ? 'coach-side-link--collapsed' : '']"
                        :title="item.label"
                        :aria-label="item.label"
                        @click="go(item.route)"
                    >
                        <svg
                            class="h-4 w-4"
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
                        <span v-if="!isNavCollapsed">{{ item.label }}</span>
                    </button>
                </nav>

                <div class="border-t border-[#d6e4f4] px-3 py-3">
                    <div v-if="hasSecondaryItems">
                        <p class="mb-2 px-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">More</p>
                        <button
                            v-for="item in secondaryItems"
                            :key="item.key"
                            type="button"
                            class="coach-side-link"
                            :class="[isActive(item.route) ? 'coach-side-link--active' : '', isNavCollapsed ? 'coach-side-link--collapsed' : '']"
                            :title="item.label"
                            :aria-label="item.label"
                            @click="go(item.route)"
                        >
                            <svg
                                class="h-4 w-4"
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
                            <span v-if="!isNavCollapsed">{{ item.label }}</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="coach-side-link"
                        :class="isNavCollapsed ? 'coach-side-link--collapsed' : ''"
                        @click="go('/account/settings')"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 0 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.2a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.2a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"
                            />
                        </svg>
                        <span v-if="!isNavCollapsed">Settings</span>
                    </button>

                    <button
                        type="button"
                        class="coach-side-link"
                        :class="isNavCollapsed ? 'coach-side-link--collapsed' : ''"
                        @click="go('/account/help')"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 17v.01" />
                            <path d="M12 13a2 2 0 1 0-2-2" />
                        </svg>
                        <span v-if="!isNavCollapsed">Help &amp; Support</span>
                    </button>

                    <button
                        type="button"
                        class="coach-side-link coach-side-link--logout"
                        :class="isNavCollapsed ? 'coach-side-link--collapsed' : ''"
                        @click="logout"
                    >
                        <svg
                            class="h-4 w-4"
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
                        <span v-if="!isNavCollapsed">Logout</span>
                    </button>
                </div>
            </aside>

            <aside
                class="coach-shell__mobile-sidebar fixed inset-y-0 left-0 z-50 w-[82vw] max-w-xs border-r border-[#bfd4eb] bg-[#eef5ff] p-4 transition md:hidden"
                :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="mb-4 flex items-center justify-between">
                    <p class="coach-shell__mobile-title text-sm font-bold text-[#1f2937]">Coach Menu</p>
                    <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs" @click="mobileMenuOpen = false">Close</button>
                </div>

                <div class="space-y-2">
                    <button
                        v-for="item in [...primaryItems, ...secondaryItems]"
                        :key="item.key"
                        type="button"
                        class="coach-side-link w-full"
                        :class="isActive(item.route) ? 'coach-side-link--active' : ''"
                        @click="go(item.route)"
                    >
                        <svg
                            class="h-4 w-4"
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
                        <span>{{ item.label }}</span>
                    </button>

                    <button type="button" class="coach-side-link w-full" @click="go('/account/settings')">
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 0 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.2a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.2a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"
                            />
                        </svg>
                        <span>Settings</span>
                    </button>

                    <button type="button" class="coach-side-link w-full" @click="go('/account/help')">
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 17v.01" />
                            <path d="M12 13a2 2 0 1 0-2-2" />
                        </svg>
                        <span>Help &amp; Support</span>
                    </button>

                    <button type="button" class="coach-side-link coach-side-link--logout w-full" @click="logout">
                        <svg
                            class="h-4 w-4"
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
                        <span>Logout</span>
                    </button>
                </div>
            </aside>

            <main class="mx-auto w-full max-w-400 px-4 py-4 pb-[calc(env(safe-area-inset-bottom,0px)+5.5rem)] sm:px-6 md:px-6 md:pb-6 lg:px-8">
                <slot />
            </main>

            <RoleFooter
                title="Coach"
                description="Manage schedules, attendance checks, wellness tracking, and academic visibility in one place."
                :links="footerLinks"
                :bottom-nav="true"
            />
        </div>

        <CoachBottomNav :items="primaryItems" :is-active="isActive" />
    </div>
</template>

<style scoped>
.coach-side-link {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.55rem;
    border-radius: 0.65rem;
    padding: 0.55rem 0.65rem;
    font-size: 0.83rem;
    font-weight: 600;
    color: #334155;
    transition:
        background-color 180ms ease,
        color 180ms ease,
        transform 180ms ease;
}

.coach-side-link:hover {
    background: rgba(255, 255, 255, 0.78);
    color: #034485;
}

.coach-side-link--active {
    background: #034485;
    color: #ffffff;
    transform: translateX(2px);
}

.coach-side-link--active:hover {
    background: #033a70;
    color: #ffffff;
}

.coach-side-link--collapsed {
    justify-content: center;
    padding: 0.6rem;
}

.coach-side-link--logout {
    border: 1px solid transparent;
    color: #e11d48;
}

.coach-side-link--logout:hover {
    border-color: #fecdd3;
    background: #fff1f2;
    color: #e11d48;
    transform: none;
}
</style>
