<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'

defineOptions({
    layout: StudentAthleteDashboard,
})

type Announcement = {
    id: number
    title: string
    message: string
    type: string
    is_read: boolean
    published_at: string | null
    read_at: string | null
    created_by: number | null
    created_by_name: string | null
    created_by_role: string | null
    source_label: string | null
    type_label?: string | null
}

type PaginationLink = {
    url: string | null
    label: string
    active: boolean
}

type PaginatedAnnouncements = {
    data: Announcement[]
    links: PaginationLink[]
    from: number | null
    to: number | null
    total: number
    current_page: number
    last_page: number
    prev_page_url: string | null
    next_page_url: string | null
}

const props = defineProps<{
    announcements: PaginatedAnnouncements
    filters?: {
        filter?: 'all' | 'unread'
    }
}>()

const localAnnouncements = ref<Announcement[]>(props.announcements.data.map((item) => ({ ...item })))
const activeFilter = ref<'all' | 'unread'>(props.filters?.filter ?? 'all')
const actionMessage = ref('')
const actionTone = ref<'success' | 'error'>('success')
const processingIds = ref<number[]>([])
const processingAll = ref(false)

const unreadCount = computed(() => localAnnouncements.value.filter((item) => !item.is_read).length)
const pageSummary = computed(() => {
    const from = props.announcements.from ?? 0
    const to = props.announcements.to ?? 0
    return `Showing ${from} to ${to} of ${props.announcements.total} announcements`
})

watch(
    () => props.announcements.data,
    (items) => {
        localAnnouncements.value = items.map((item) => ({ ...item }))
    },
)

watch(
    () => props.filters?.filter,
    (value) => {
        activeFilter.value = value ?? 'all'
    },
)

function isProcessing(id: number) {
    return processingIds.value.includes(id)
}

function setMessage(message: string, tone: 'success' | 'error' = 'success') {
    actionMessage.value = message
    actionTone.value = tone
    window.setTimeout(() => {
        actionMessage.value = ''
    }, 2200)
}

function markRead(item: Announcement) {
    if (item.is_read || isProcessing(item.id) || processingAll.value) return
    const previousReadAt = item.read_at
    const previousIsRead = item.is_read
    item.is_read = true
    item.read_at = item.read_at ?? new Date().toISOString()
    processingIds.value = [...processingIds.value, item.id]

    router.put(`/announcements/${item.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => setMessage('The announcement has been marked as read.'),
        onError: () => {
            item.is_read = previousIsRead
            item.read_at = previousReadAt
            setMessage('The announcement could not be marked as read.', 'error')
        },
        onFinish: () => {
            processingIds.value = processingIds.value.filter((id) => id !== item.id)
        },
    })
}

function markAllRead() {
    if (processingAll.value || unreadCount.value === 0) return
    const snapshot = localAnnouncements.value.map((item) => ({ ...item }))
    const nowIso = new Date().toISOString()
    localAnnouncements.value = localAnnouncements.value.map((item) => ({
        ...item,
        is_read: true,
        read_at: item.read_at ?? nowIso,
    }))
    processingAll.value = true

    router.put('/announcements/read-all', {}, {
        preserveScroll: true,
        onSuccess: () => setMessage('All announcements have been marked as read.'),
        onError: () => {
            localAnnouncements.value = snapshot
            setMessage('The announcements could not be marked as read.', 'error')
        },
        onFinish: () => {
            processingAll.value = false
        },
    })
}

function setFilter(filter: 'all' | 'unread') {
    if (filter === activeFilter.value) return
    activeFilter.value = filter
    router.get('/announcements', { filter }, { preserveScroll: true, preserveState: true })
}

function visitPage(url: string | null) {
    if (!url) return
    router.get(url, {}, { preserveScroll: true, preserveState: true })
}

function formatDateTime(value: string | null) {
    if (!value) return '-'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value
    const now = new Date()
    const isToday = date.toDateString() === now.toDateString()
    const datePart = isToday
        ? 'Today'
        : date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: date.getFullYear() === now.getFullYear() ? undefined : 'numeric',
        })
    const timePart = date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
    return `${datePart} ${timePart}`
}

function formatRelative(value: string | null) {
    if (!value) return ''
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''
    const diffMs = date.getTime() - Date.now()
    const diffMinutes = Math.round(diffMs / 60000)
    const absMinutes = Math.abs(diffMinutes)
    if (absMinutes < 1) return 'moments ago'
    if (absMinutes < 60) {
        return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(diffMinutes, 'minute')
    }
    const diffHours = Math.round(diffMinutes / 60)
    if (Math.abs(diffHours) < 24) {
        return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(diffHours, 'hour')
    }
    const diffDays = Math.round(diffHours / 24)
    if (Math.abs(diffDays) < 7) {
        return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(diffDays, 'day')
    }
    return ''
}
</script>

<template>
    <Head title="Announcements" />
    <div class="space-y-4">
        <div class="flex flex-col gap-4">
            <div>
                <Link
                    href="/StudentAthleteDashboard"
                    class="inline-flex items-center rounded-full bg-[#034485] px-4 py-1 text-xs font-semibold text-white hover:bg-[#033a70]"
                >
                    Return to Dashboard
                </Link>
            </div>

            <section class="page-card rounded-3xl border border-[#034485]/35 bg-[#034485] p-5 text-white">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Student notices</p>
                <h1 class="mt-2 text-2xl font-bold text-white">Announcements</h1>
                <p class="mt-2 text-sm text-white/85">Review official notices from administrators and the system.</p>
            </section>

            <div class="flex flex-wrap items-center gap-2 sm:justify-between">
                <p class="text-sm text-slate-500">{{ pageSummary }}</p>
                <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white p-1 text-xs font-semibold">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 transition"
                        :class="activeFilter === 'all' ? 'bg-[#1f2937] text-white' : 'text-slate-600 hover:bg-slate-100'"
                        @click="setFilter('all')"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 transition"
                        :class="activeFilter === 'unread' ? 'bg-[#1f2937] text-white' : 'text-slate-600 hover:bg-slate-100'"
                        @click="setFilter('unread')"
                    >
                        Unread
                    </button>
                </div>
                <button
                    type="button"
                    class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="processingAll || unreadCount === 0"
                    @click="markAllRead"
                >
                    {{ processingAll ? 'Updating...' : 'Mark All as Read' }}
                </button>
            </div>
            </div>
        </div>

        <p v-if="actionMessage" class="text-sm" :class="actionTone === 'error' ? 'text-rose-600' : 'text-emerald-600'">
            {{ actionMessage }}
        </p>

        <div v-if="localAnnouncements.length === 0" class="page-card rounded-xl border border-slate-200 bg-white p-6 text-slate-500">
            No announcements are available at this time.
        </div>

        <div
            v-for="item in localAnnouncements"
            :key="item.id"
            class="page-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition"
            :class="!item.is_read ? 'border-l-4 border-[#1f2937]' : ''"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span v-if="!item.is_read" class="h-2 w-2 rounded-full bg-[#1f2937]" />
                    <h2 class="font-semibold text-slate-900">{{ item.title }}</h2>
                </div>
                <button
                    v-if="!item.is_read"
                    type="button"
                    class="rounded-full bg-[#1f2937] px-3 py-1 text-xs font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isProcessing(item.id) || processingAll"
                    @click="markRead(item)"
                >
                    {{ isProcessing(item.id) ? 'Updating...' : 'Mark as Read' }}
                </button>
            </div>
            <p class="mt-2 text-sm text-slate-600">{{ item.message }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span>{{ formatDateTime(item.published_at) }}</span>
                <span v-if="formatRelative(item.published_at)">• {{ formatRelative(item.published_at) }}</span>
                <span>• {{ item.source_label || 'System' }}</span>
                <span
                    v-if="item.type_label"
                    class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500"
                >
                    {{ item.type_label }}
                </span>
            </div>
        </div>

        <div
            v-if="props.announcements.links.length > 1"
            class="flex flex-col gap-3 border-t border-slate-200 pt-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
        >
            <p>{{ pageSummary }}</p>
            <nav class="flex flex-wrap items-center gap-1" aria-label="Announcement pagination">
                <button
                    v-for="(link, index) in props.announcements.links"
                    :key="`${index}-${link.label}`"
                    type="button"
                    :disabled="!link.url"
                    @click="visitPage(link.url)"
                    class="min-w-9 rounded-md border px-2 py-1 text-xs transition"
                    :class="link.active
                        ? 'border-[#1f2937] bg-[#1f2937] text-white'
                        : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40'"
                    v-html="link.label"
                />
            </nav>
        </div>
    </div>
</template>

<style scoped>
.page-card {
    opacity: 0;
    animation: student-announcement-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

@keyframes student-announcement-card-rise {
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
    .page-card {
        animation: none;
        opacity: 1;
    }
}
</style>
