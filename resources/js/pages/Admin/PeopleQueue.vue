<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue';
import { resolveUserAvatarUrl } from '@/utils/media';

defineOptions({
    layout: AdminDashboard,
});

type ReadinessFilter = 'all' | 'ready' | 'incomplete';
type QueueSort = 'newest' | 'oldest' | 'name_asc' | 'name_desc';
type QueueStatus = 'pending' | 'rejected';

type QueueUser = {
    id: number;
    name: string;
    email: string;
    role: 'student-athlete' | 'student';
    status: 'pending' | 'rejected';
    avatar?: string | null;
    created_at: string | null;
    student?: {
        id: number;
        student_id_number: string | null;
        first_name: string | null;
        middle_name?: string | null;
        last_name: string | null;
        home_address?: string | null;
        course_or_strand: string | null;
        current_grade_level: string | null;
        academic_level_label?: string | null;
        approval_status?: string | null;
        phone_number?: string | null;
        date_of_birth?: string | null;
        gender?: string | null;
        height?: string | null;
        weight?: string | null;
        emergency_contact_name?: string | null;
        emergency_contact_relationship?: string | null;
        emergency_contact_phone?: string | null;
        latest_health_clearance?: {
            id: number;
            clearance_date?: string | null;
            clearance_status: string | null;
            valid_until: string | null;
            physician_name: string | null;
            conditions?: string | null;
            allergies?: string | null;
            restrictions?: string | null;
        } | null;
        latest_academic_document?: {
            id: number;
            document_type: string | null;
            uploaded_at: string | null;
        } | null;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedQueue = {
    data: QueueUser[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    links: PaginationLink[];
};

type Filters = {
    search?: string;
    status?: QueueStatus;
    readiness?: ReadinessFilter;
    sort?: QueueSort;
};

type Stats = {
    pending_total: number;
    ready_total: number;
    incomplete_total: number;
    rejected_total: number;
};

const props = defineProps<{
    queue: PaginatedQueue;
    filters?: Filters;
    stats?: Stats;
    pendingCount?: number;
}>();

const search = ref(props.filters?.search ?? '');
const status = ref<QueueStatus>(props.filters?.status ?? 'pending');
const readiness = ref<ReadinessFilter>(props.filters?.readiness ?? 'all');
const sort = ref<QueueSort>(props.filters?.sort ?? 'newest');

const approvingId = ref<number | null>(null);
const rejectingId = ref<number | null>(null);
const approveTarget = ref<QueueUser | null>(null);
const rejectTarget = ref<QueueUser | null>(null);
const rejectRemarks = ref('');
const selectedUserId = ref<number | null>(props.queue.data[0]?.id ?? null);
const previewDocument = ref<{
    title: string;
    url: string;
    subtitle?: string | null;
} | null>(null);
const topTab = ref<'active' | 'queue'>('queue');

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
let topTabTimeout: ReturnType<typeof setTimeout> | null = null;

const stats = computed(() => ({
    pending_total: props.stats?.pending_total ?? 0,
    ready_total: props.stats?.ready_total ?? 0,
    incomplete_total: props.stats?.incomplete_total ?? 0,
    rejected_total: props.stats?.rejected_total ?? 0,
}));
const queue = computed(() => props.queue);
const isRejectedView = computed(() => status.value === 'rejected');
const selectedUser = computed(() => queue.value.data.find((user) => user.id === selectedUserId.value) ?? queue.value.data[0] ?? null);

watch(
    () => props.queue.data,
    (users) => {
        if (!users.length) {
            selectedUserId.value = null;
            return;
        }

        const stillVisible = users.some((user) => user.id === selectedUserId.value);
        if (!stillVisible) {
            selectedUserId.value = users[0].id;
        }
    },
    { immediate: true },
);

function buildQuery(resetPage = true) {
    return {
        search: search.value.trim() || undefined,
        status: status.value,
        readiness: readiness.value === 'all' ? undefined : readiness.value,
        sort: sort.value,
        page: resetPage ? 1 : props.queue.current_page,
    };
}

function applyFilters({ resetPage = true, debounce = false }: { resetPage?: boolean; debounce?: boolean } = {}) {
    const visit = () => {
        router.get('/people/queue', buildQuery(resetPage), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['queue', 'filters', 'stats', 'pendingCount'],
        });
    };

    if (!debounce) {
        visit();
        return;
    }

    if (searchDebounce) {
        clearTimeout(searchDebounce);
    }

    searchDebounce = setTimeout(visit, 250);
}

watch(search, () => {
    applyFilters({ resetPage: true, debounce: true });
});

watch(status, () => {
    if (status.value === 'rejected') {
        readiness.value = 'all';
    }
    applyFilters({ resetPage: true });
});

watch(readiness, () => {
    applyFilters({ resetPage: true });
});

watch(sort, () => {
    applyFilters({ resetPage: true });
});

function goToUserManagement() {
    if (topTab.value === 'active') return;
    topTab.value = 'active';
    if (topTabTimeout) clearTimeout(topTabTimeout);
    topTabTimeout = setTimeout(() => {
        router.get('/people');
    }, 180);
}

function visitPage(url: string | null) {
    if (!url) return;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['queue', 'filters', 'stats', 'pendingCount'],
    });
}

function hasRequirements(user: QueueUser) {
    return Boolean(user.student?.latest_health_clearance) && Boolean(user.student?.latest_academic_document);
}

function requirementIssues(user: QueueUser) {
    const issues: string[] = [];

    if (!user.student?.latest_health_clearance) {
        issues.push('Health clearance not submitted');
    }
    if (!user.student?.latest_academic_document) {
        issues.push('Academic document not submitted');
    }

    return issues;
}

function queuePosition(index: number) {
    return (props.queue.current_page - 1) * props.queue.per_page + index + 1;
}

function formatRole(role: QueueUser['role']) {
    return role.replace('-', ' ');
}

function formatDate(value?: string | null) {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';
    return parsed.toLocaleDateString();
}

function formatDateTime(value?: string | null) {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';
    return parsed.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatClearanceStatus(value?: string | null) {
    if (!value) return 'No clearance';
    return value.replaceAll('_', ' ');
}

function formatDocumentType(value?: string | null) {
    if (!value) return 'No document';
    return value.replaceAll('_', ' ').toUpperCase();
}

function readinessTone(user: QueueUser) {
    if (user.status === 'rejected') return 'bg-rose-100 text-rose-700 border border-rose-200';
    return hasRequirements(user)
        ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
        : 'bg-amber-100 text-amber-700 border border-amber-200';
}

function readinessLabel(user: QueueUser) {
    if (user.status === 'rejected') return 'Rejected';
    return hasRequirements(user) ? 'Ready for approval' : 'Requirements incomplete';
}

function userInitials(user: QueueUser) {
    return String(user.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'SA';
}

function infoValue(value?: string | null) {
    return value && String(value).trim() ? String(value) : '-';
}

function clearanceFileUrl(id?: number | null) {
    if (!id) return null;
    return `/files/clearance/${id}`;
}

function academicFileUrl(id?: number | null) {
    if (!id) return null;
    return `/files/academic/${id}`;
}

function openDocumentPreview(title: string, url: string, subtitle?: string | null) {
    previewDocument.value = { title, url, subtitle };
}

function closeDocumentPreview() {
    previewDocument.value = null;
}

function openApproveDialog(user: QueueUser) {
    approveTarget.value = user;
}

function closeApproveDialog() {
    approveTarget.value = null;
}

function openRejectDialog(user: QueueUser) {
    rejectTarget.value = user;
    rejectRemarks.value = '';
}

function closeRejectDialog() {
    rejectTarget.value = null;
    rejectRemarks.value = '';
}

function approveUser() {
    if (!approveTarget.value) return;

    approvingId.value = approveTarget.value.id;

    router.post(
        `/admin/users/${approveTarget.value.id}/approve`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                closeApproveDialog();
                router.reload({
                    only: ['queue', 'filters', 'stats', 'pendingCount'],
                });
            },
            onFinish: () => {
                approvingId.value = null;
            },
        },
    );
}

function rejectUser() {
    if (!rejectTarget.value) return;

    rejectingId.value = rejectTarget.value.id;

    router.post(
        `/admin/users/${rejectTarget.value.id}/reject`,
        {
            remarks: rejectRemarks.value.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeRejectDialog();
                router.reload({
                    only: ['queue', 'filters', 'stats', 'pendingCount'],
                });
            },
            onFinish: () => {
                rejectingId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="People Queue" />

    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative inline-grid w-full grid-cols-2 items-center rounded-2xl border border-[#034485]/45 bg-white p-1 sm:w-auto sm:rounded-full">
                <span
                    class="pointer-events-none absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-full bg-[#1f2937] transition-transform duration-200 ease-out"
                    :class="topTab === 'active' ? 'translate-x-0' : 'translate-x-full'"
                    aria-hidden="true"
                />
                <button
                    type="button"
                    @click="goToUserManagement"
                    class="relative z-10 flex w-full min-w-0 items-center justify-center rounded-xl px-3 py-2 text-center text-xs font-semibold leading-tight transition sm:rounded-full sm:px-4 sm:py-1.5"
                    :class="topTab === 'active' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                >
                    Active Users
                </button>
                <button
                    type="button"
                    class="relative z-10 inline-flex w-full min-w-0 items-center justify-center gap-2 rounded-xl px-3 py-2 text-center text-xs font-semibold leading-tight transition sm:rounded-full sm:px-4 sm:py-1.5"
                    :class="topTab === 'queue' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                    aria-current="page"
                >
                    Approval Queue
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                        :class="topTab === 'queue' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-700'"
                    >
                        {{ props.pendingCount ?? stats.pending_total }}
                    </span>
                </button>
            </div>
        </div>

        <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-[11px] leading-relaxed tracking-wide text-slate-500 uppercase">Pending Accounts</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ stats.pending_total }}</p>
            </article>
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-[11px] leading-relaxed tracking-wide text-slate-500 uppercase">Ready To Approve</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ stats.ready_total }}</p>
            </article>
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-[11px] leading-relaxed tracking-wide text-slate-500 uppercase">Needs Requirements</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ stats.incomplete_total }}</p>
            </article>
            <article class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-[11px] leading-relaxed tracking-wide text-slate-500 uppercase">Rejected Accounts</p>
                <p class="mt-1 text-2xl font-bold text-rose-600">{{ stats.rejected_total }}</p>
            </article>
        </section>

        <section class="rounded-xl border border-[#034485]/45 bg-white p-4">
            <div class="relative mb-3 inline-grid w-full grid-cols-2 items-center rounded-2xl border border-[#034485]/45 bg-white p-1 sm:inline-flex sm:w-auto sm:rounded-full">
                <span
                    class="pointer-events-none absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-full transition-transform duration-200 ease-out"
                    :class="status === 'pending' ? 'translate-x-0 bg-[#1f2937]' : 'translate-x-full bg-rose-600'"
                    aria-hidden="true"
                />
                <button
                    type="button"
                    @click="status = 'pending'"
                    class="relative z-10 rounded-xl px-3 py-2 text-center text-xs font-semibold leading-tight transition sm:rounded-full sm:px-4 sm:py-1.5"
                    :class="status === 'pending' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                >
                    Pending Applications
                </button>
                <button
                    type="button"
                    @click="status = 'rejected'"
                    class="relative z-10 rounded-xl px-3 py-2 text-center text-xs font-semibold leading-tight transition sm:rounded-full sm:px-4 sm:py-1.5"
                    :class="status === 'rejected' ? 'text-white' : 'text-slate-700 hover:text-slate-900'"
                >
                    Rejected Applications
                </button>
            </div>
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-6"
                />

                <select
                    v-model="readiness"
                    :disabled="isRejectedView"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-3"
                >
                    <option value="all">All Readiness</option>
                    <option value="ready">Ready to Approve</option>
                    <option value="incomplete">Incomplete Requirements</option>
                </select>

                <select
                    v-model="sort"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20 lg:col-span-3"
                >
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="name_asc">Name A-Z</option>
                    <option value="name_desc">Name Z-A</option>
                </select>
            </div>
        </section>

        <Transition
            mode="out-in"
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <section :key="status" class="overflow-hidden rounded-xl border border-[#034485]/45 bg-white">
                <div v-if="queue.data.length" class="grid gap-0 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                    <div class="border-b border-slate-200 xl:border-r xl:border-b-0">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Applicants</p>
                            <p class="mt-1 text-sm text-slate-600">Review applications efficiently while keeping all supporting details in view.</p>
                        </div>
                        <div class="max-h-[calc(100vh-24rem)] overflow-y-auto">
                            <button
                                v-for="(user, index) in queue.data"
                                :key="user.id"
                                type="button"
                                class="w-full border-b border-slate-200 px-4 py-4 text-left transition-colors duration-200 ease-out last:border-b-0"
                                :class="selectedUser?.id === user.id ? 'bg-[#034485]' : 'hover:bg-slate-50'"
                                @click="selectedUserId = user.id"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl border text-sm font-bold transition-colors duration-200 ease-out"
                                        :class="selectedUser?.id === user.id ? 'border-white/25 bg-white/15 text-white' : 'border-[#034485]/20 bg-[#e9f2ff] text-[#034485]'"
                                    >
                                        <img v-if="user.avatar" :src="resolveUserAvatarUrl(user.avatar)" :alt="user.name" class="h-full w-full object-cover">
                                        <span v-else>{{ userInitials(user) }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold leading-tight break-words transition-colors duration-200 ease-out" :class="selectedUser?.id === user.id ? 'text-white' : 'text-slate-900'">{{ user.name }}</p>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors duration-200 ease-out" :class="readinessTone(user)">
                                                {{ readinessLabel(user) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs transition-colors duration-200 ease-out" :class="selectedUser?.id === user.id ? 'text-blue-100' : 'text-slate-500'">
                                            {{ infoValue(user.student?.student_id_number) }} • {{ infoValue(user.student?.course_or_strand) }}
                                        </p>
                                        <p class="mt-1 text-xs transition-colors duration-200 ease-out" :class="selectedUser?.id === user.id ? 'text-blue-100' : 'text-slate-500'">
                                            {{ infoValue(user.student?.academic_level_label || user.student?.current_grade_level) }} • {{ user.email }}
                                        </p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors duration-200 ease-out"
                                                :class="user.student?.latest_health_clearance ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                            >
                                                {{ user.student?.latest_health_clearance ? 'Health clearance submitted' : 'Health clearance missing' }}
                                            </span>
                                            <span
                                                class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors duration-200 ease-out"
                                                :class="user.student?.latest_academic_document ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                            >
                                                {{ user.student?.latest_academic_document ? 'Academic document submitted' : 'Academic document missing' }}
                                            </span>
                                            <span
                                                class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors duration-200 ease-out"
                                                :class="selectedUser?.id === user.id ? 'border-white/20 bg-white/10 text-blue-50' : 'border-slate-200 bg-white text-slate-600'"
                                            >
                                                #{{ queuePosition(index) }} • {{ formatDate(user.created_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="bg-white">
                        <div v-if="selectedUser" class="space-y-5 p-4 sm:p-5">
                            <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-[#034485]/20 bg-[#e9f2ff] text-base font-bold text-[#034485]">
                                        <img v-if="selectedUser.avatar" :src="resolveUserAvatarUrl(selectedUser.avatar)" :alt="selectedUser.name" class="h-full w-full object-cover">
                                        <span v-else>{{ userInitials(selectedUser) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-lg font-bold leading-tight break-words text-slate-900">{{ selectedUser.name }}</h2>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="readinessTone(selectedUser)">
                                                {{ readinessLabel(selectedUser) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ infoValue(selectedUser.student?.student_id_number) }} • {{ formatRole(selectedUser.role) }}
                                        </p>
                                        <p class="text-sm text-slate-500">Submitted {{ formatDateTime(selectedUser.created_at) }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                    <button
                                        v-if="!isRejectedView"
                                        type="button"
                                        @click="openApproveDialog(selectedUser)"
                                        :disabled="!hasRequirements(selectedUser) || approvingId === selectedUser.id || rejectingId === selectedUser.id"
                                        class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        v-if="!isRejectedView"
                                        type="button"
                                        @click="openRejectDialog(selectedUser)"
                                        :disabled="approvingId === selectedUser.id || rejectingId === selectedUser.id"
                                        class="w-full rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto"
                                    >
                                        Reject
                                    </button>
                                </div>
                            </div>

                            <div v-if="!isRejectedView && requirementIssues(selectedUser).length" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                {{ requirementIssues(selectedUser).join(' | ') }}
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <section class="rounded-2xl border border-[#034485]/18 bg-[#f9fbff] p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Identity</p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs text-slate-500">Full Name</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ selectedUser.name }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Student ID</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.student_id_number) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Birth Date</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatDate(selectedUser.student?.date_of_birth) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Gender</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.gender) }}</p>
                                        </div>
                                    </div>
                                </section>

                                <section class="rounded-2xl border border-[#034485]/18 bg-[#f9fbff] p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Academic</p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs text-slate-500">Course / Strand</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.course_or_strand) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Year Level</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.academic_level_label || selectedUser.student?.current_grade_level) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Height</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.height) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Weight</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.weight) }}</p>
                                        </div>
                                    </div>
                                </section>

                                <section class="rounded-2xl border border-[#034485]/18 bg-[#f9fbff] p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Contact</p>
                                    <div class="mt-3 grid gap-3">
                                        <div>
                                            <p class="text-xs text-slate-500">Email</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ selectedUser.email }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Phone Number</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.phone_number) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Home Address</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.home_address) }}</p>
                                        </div>
                                    </div>
                                </section>

                                <section class="rounded-2xl border border-[#034485]/18 bg-[#f9fbff] p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Emergency</p>
                                    <div class="mt-3 grid gap-3">
                                        <div>
                                            <p class="text-xs text-slate-500">Contact Name</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.emergency_contact_name) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Relationship</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.emergency_contact_relationship) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Emergency Phone</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ infoValue(selectedUser.student?.emergency_contact_phone) }}</p>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <section class="rounded-2xl border border-[#034485]/20 bg-white p-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Documents</p>
                                    <p class="mt-1 text-sm text-slate-600">Preview supporting documents within the page to maintain review context.</p>
                                </div>
                                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Medical or Health Certificate</p>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{
                                                        selectedUser.student?.latest_health_clearance
                                                            ? `Status: ${formatClearanceStatus(selectedUser.student.latest_health_clearance.clearance_status)}`
                                                            : 'No health document has been submitted.'
                                                    }}
                                                </p>
                                            </div>
                                            <span
                                                class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                                :class="selectedUser.student?.latest_health_clearance ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                            >
                                                {{ selectedUser.student?.latest_health_clearance ? 'Submitted' : 'Missing' }}
                                            </span>
                                        </div>
                                        <div class="mt-3 space-y-2 text-sm text-slate-700">
                                            <p>Issued: {{ formatDate(selectedUser.student?.latest_health_clearance?.clearance_date) }}</p>
                                            <p>Valid Until: {{ formatDate(selectedUser.student?.latest_health_clearance?.valid_until) }}</p>
                                            <p>Physician: {{ infoValue(selectedUser.student?.latest_health_clearance?.physician_name) }}</p>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <button
                                                v-if="clearanceFileUrl(selectedUser.student?.latest_health_clearance?.id)"
                                                type="button"
                                                class="w-full rounded-lg border border-[#034485]/30 bg-white px-3 py-2 text-sm font-semibold text-[#034485] transition hover:bg-[#eef5ff] sm:w-auto"
                                                @click="openDocumentPreview('Medical / Health Certificate', clearanceFileUrl(selectedUser.student?.latest_health_clearance?.id)!, selectedUser.name)"
                                            >
                                                Preview Document
                                            </button>
                                        </div>
                                        <div
                                            v-if="selectedUser.student?.latest_health_clearance?.conditions || selectedUser.student?.latest_health_clearance?.allergies || selectedUser.student?.latest_health_clearance?.restrictions"
                                            class="mt-4 space-y-2 rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-600"
                                        >
                                            <p><span class="font-semibold text-slate-800">Conditions:</span> {{ infoValue(selectedUser.student?.latest_health_clearance?.conditions) }}</p>
                                            <p><span class="font-semibold text-slate-800">Allergies:</span> {{ infoValue(selectedUser.student?.latest_health_clearance?.allergies) }}</p>
                                            <p><span class="font-semibold text-slate-800">Restrictions:</span> {{ infoValue(selectedUser.student?.latest_health_clearance?.restrictions) }}</p>
                                        </div>
                                    </article>

                                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Academic Supporting Document</p>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{
                                                        selectedUser.student?.latest_academic_document
                                                            ? `Type: ${formatDocumentType(selectedUser.student.latest_academic_document.document_type)}`
                                                            : 'No academic document has been submitted.'
                                                    }}
                                                </p>
                                            </div>
                                            <span
                                                class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                                :class="selectedUser.student?.latest_academic_document ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                            >
                                                {{ selectedUser.student?.latest_academic_document ? 'Submitted' : 'Missing' }}
                                            </span>
                                        </div>
                                        <div class="mt-3 space-y-2 text-sm text-slate-700">
                                            <p>Uploaded: {{ formatDateTime(selectedUser.student?.latest_academic_document?.uploaded_at) }}</p>
                                            <p>Document Type: {{ selectedUser.student?.latest_academic_document ? formatDocumentType(selectedUser.student.latest_academic_document.document_type) : '-' }}</p>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <button
                                                v-if="academicFileUrl(selectedUser.student?.latest_academic_document?.id)"
                                                type="button"
                                                class="w-full rounded-lg border border-[#034485]/30 bg-white px-3 py-2 text-sm font-semibold text-[#034485] transition hover:bg-[#eef5ff] sm:w-auto"
                                                @click="openDocumentPreview('Academic Document', academicFileUrl(selectedUser.student?.latest_academic_document?.id)!, selectedUser.name)"
                                            >
                                                Preview Document
                                            </button>
                                        </div>
                                    </article>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <div v-else class="px-4 py-10 text-center text-sm text-slate-500">
                    {{
                        isRejectedView
                            ? 'No rejected applications match the selected filters.'
                            : 'No pending applications match the selected filters.'
                    }}
                </div>

                <div
                    class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>
                        Showing {{ queue.from ?? 0 }} to {{ queue.to ?? 0 }} of {{ queue.total }}
                        {{ isRejectedView ? 'rejected applications' : 'pending applications' }}
                    </p>
                    <nav class="flex flex-wrap items-center gap-1" aria-label="Approval queue pagination">
                        <button
                            v-for="(link, index) in queue.links"
                            :key="`${index}-${link.label}`"
                            type="button"
                            :disabled="!link.url"
                            @click="visitPage(link.url)"
                            class="min-w-9 rounded-md border px-2 py-1 text-xs transition"
                            :class="
                                link.active
                                    ? 'border-[#1f2937] bg-[#1f2937] text-white'
                                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40'
                            "
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </section>
        </Transition>
    </div>

    <Transition name="modal-fade">
        <div v-if="approveTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeApproveDialog">
            <div class="modal-panel w-full max-w-lg rounded-xl border border-[#034485]/45 bg-white p-5">
                <h2 class="text-lg font-bold text-slate-900">Confirm Approval</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Approve <span class="font-semibold text-slate-900">{{ approveTarget.name }}</span> and grant access to the system?
                </p>

                <div
                    v-if="requirementIssues(approveTarget).length"
                    class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-700"
                >
                    {{ requirementIssues(approveTarget).join(' | ') }}
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeApproveDialog"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="approveUser"
                        :disabled="approvingId === approveTarget.id || !hasRequirements(approveTarget)"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Confirm Approval
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeRejectDialog">
            <div class="modal-panel w-full max-w-lg rounded-xl border border-[#034485]/45 bg-white p-5">
                <h2 class="text-lg font-bold text-slate-900">Reject Application</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Provide remarks, if needed, for <span class="font-semibold text-slate-900">{{ rejectTarget.name }}</span
                    >.
                </p>

                <textarea
                    v-model="rejectRemarks"
                    rows="4"
                    placeholder="Enter remarks for the application review, if applicable"
                    class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                />

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeRejectDialog"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="rejectUser"
                        :disabled="rejectingId === rejectTarget.id"
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Confirm Rejection
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div v-if="previewDocument" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/70 p-3 sm:p-6" @click.self="closeDocumentPreview">
            <div class="flex h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-[#034485]/30 bg-white shadow-2xl">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold leading-tight break-words text-slate-900">{{ previewDocument.title }}</h2>
                        <p v-if="previewDocument.subtitle" class="text-xs break-words text-slate-500">{{ previewDocument.subtitle }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                            @click="closeDocumentPreview"
                        >
                            Close
                        </button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 bg-slate-100">
                    <iframe :src="previewDocument.url" class="h-full w-full border-0" title="Document preview" />
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-fade-enter-active .modal-panel,
.modal-fade-leave-active .modal-panel {
    transition:
        transform 0.2s ease,
        opacity 0.2s ease;
}

.modal-fade-enter-from .modal-panel,
.modal-fade-leave-to .modal-panel {
    transform: translateY(8px) scale(0.98);
    opacity: 0;
}
</style>
