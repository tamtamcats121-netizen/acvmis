<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

import EmptyResultsState from '@/components/ui/EmptyResultsState.vue';
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue';
import { resolveUserAvatarUrl } from '@/utils/media';

defineOptions({
    layout: AdminDashboard,
});

type RoleFilter = 'all' | 'student-athlete' | 'coach';
type UserStatusFilter = 'active' | 'deactivated';
type SortField = 'name' | 'email' | 'created_at';
type SortDirection = 'asc' | 'desc';
type SortOption = `${SortField}:${SortDirection}`;

type StudentInfo = {
    student_id_number: string | null;
    course_or_strand: string | null;
    current_grade_level: string | null;
    academic_level_label: string | null;
    student_status: string | null;
    approval_status: string | null;
    phone_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_relationship: string | null;
    emergency_contact_phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
    height: string | null;
    weight: string | null;
};

type CoachInfo = {
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    coach_status: string | null;
    phone_number: string | null;
    date_of_birth: string | null;
    gender: string | null;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: 'student-athlete' | 'coach';
    status: UserStatusFilter;
    avatar: string | null;
    created_at: string | null;
    student?: StudentInfo | null;
    coach?: CoachInfo | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedUsers = {
    data: UserRow[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    links: PaginationLink[];
};

type Filters = {
    search?: string;
    role?: RoleFilter;
    status?: UserStatusFilter;
    sort?: SortField;
    direction?: SortDirection;
};

type Totals = {
    all: number;
    students: number;
    coaches: number;
    deactivated: number;
    filtered: number;
};

type SportOption = {
    id: number;
    name: string;
};

type AssignableTeam = {
    id: number;
    team_name: string;
    year: string | null;
    sport_id: number;
    sport_name: string | null;
    head_coach?: { id: number; name: string | null } | null;
    assistant_coach?: { id: number; name: string | null } | null;
    coach_name: string | null;
    assistant_coach_name: string | null;
};

type CoachOnboardingFlash = {
    email: string;
    temporary_password: string;
    email_sent: boolean;
    activation_url: string;
};

const props = defineProps<{
    users: PaginatedUsers;
    filters?: Filters;
    totals?: Totals;
    pendingCount?: number;
    sports?: SportOption[];
    assignableTeams?: AssignableTeam[];
}>();

const defaultSort: SortOption = 'created_at:desc';
const selectedUserId = ref<number | null>(props.users.data[0]?.id ?? null);
const copiedUserId = ref<number | null>(null);
const deactivateTarget = ref<UserRow | null>(null);
const reactivateTarget = ref<UserRow | null>(null);
const regenerateTarget = ref<UserRow | null>(null);
const actionLoadingId = ref<number | null>(null);
const search = ref(props.filters?.search ?? '');
const roleFilter = ref<RoleFilter>(props.filters?.role ?? 'all');
const statusFilter = ref<UserStatusFilter>(props.filters?.status ?? 'active');
const sortOption = ref<SortOption>(`${props.filters?.sort ?? 'created_at'}:${props.filters?.direction ?? 'desc'}` as SortOption);
const topTab = ref<'active' | 'queue'>('active');
const createCoachOpen = ref(false);
const coachOnboardingOpen = ref(false);
const createCoachFeedback = ref<string | null>(null);
const adminInviteOpen = ref(false);
const adminInviteFeedback = ref<string | null>(null);
const selectedSportIds = ref<number[]>([]);
const copiedOnboardingPassword = ref(false);
const copiedActivationLink = ref(false);
const statusSwitching = ref(false);
const statusSwitchNotice = ref<string | null>(null);
const onboardingFlash = ref<CoachOnboardingFlash | null>(null);
const onboardingMode = ref<'created' | 'regenerated'>('created');
const createCoachPanel = ref<HTMLElement | null>(null);
const onboardingPanel = ref<HTMLElement | null>(null);
const page = usePage();
const createCoachForm = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    phone_number: '',
    date_of_birth: '',
    gender: '',
    home_address: '',
    notes: '',
    assignment_role: 'assistant',
    team_ids: [] as number[],
});
const adminInviteForm = useForm({
    email: '',
});

if (!['name:asc', 'name:desc', 'email:asc', 'email:desc', 'created_at:asc', 'created_at:desc'].includes(sortOption.value)) {
    sortOption.value = defaultSort;
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
let topTabTimeout: ReturnType<typeof setTimeout> | null = null;

const totalUsers = computed(() => props.totals?.all ?? 0);
const totalStudents = computed(() => props.totals?.students ?? 0);
const totalCoaches = computed(() => props.totals?.coaches ?? 0);
const totalDeactivated = computed(() => props.totals?.deactivated ?? 0);
const filteredTotal = computed(() => props.totals?.filtered ?? props.users.total);
const totalActive = computed(() => Math.max(totalUsers.value - totalDeactivated.value, 0));
const hasActiveFilters = computed(
    () => search.value.trim() !== '' || roleFilter.value !== 'all' || statusFilter.value !== 'active' || sortOption.value !== defaultSort,
);
const isDeactivatedView = computed(() => statusFilter.value === 'deactivated');
const selectedUser = computed(() => props.users.data.find((user) => user.id === selectedUserId.value) ?? props.users.data[0] ?? null);
const hasBlockingModal = computed(() => Boolean(deactivateTarget.value || reactivateTarget.value || regenerateTarget.value || createCoachOpen.value || coachOnboardingOpen.value || adminInviteOpen.value));
const sportOptions = computed(() => props.sports ?? []);
const assignableTeams = computed(() => props.assignableTeams ?? []);
const filteredAssignableTeams = computed(() => {
    if (selectedSportIds.value.length === 0) {
        return assignableTeams.value;
    }
    const selected = new Set(selectedSportIds.value);
    return assignableTeams.value.filter((team) => selected.has(team.sport_id));
});

function buildQuery(resetPage = true) {
    const [sort, direction] = sortOption.value.split(':') as [SortField, SortDirection];

    return {
        search: search.value.trim() || undefined,
        role: roleFilter.value === 'all' ? undefined : roleFilter.value,
        status: statusFilter.value,
        sort,
        direction,
        page: resetPage ? 1 : props.users.current_page,
    };
}

function applyFilters({ resetPage = true, debounce = false }: { resetPage?: boolean; debounce?: boolean } = {}) {
    const visit = () => {
        router.get('/people', buildQuery(resetPage), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters', 'totals', 'pendingCount'],
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

watch(roleFilter, () => {
    applyFilters({ resetPage: true });
});

watch(sortOption, () => {
    applyFilters({ resetPage: true });
});

watch(
    () => props.users.data,
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

function openInfo(user: UserRow) {
    selectedUserId.value = user.id;
}

function openCreateCoach() {
    createCoachOpen.value = true;
    resetCreateCoachViewport();
}

function openAdminInvite() {
    adminInviteOpen.value = true;
}

function closeCreateCoach() {
    createCoachOpen.value = false;
    createCoachForm.reset();
    createCoachForm.clearErrors();
    createCoachForm.assignment_role = 'assistant';
    createCoachForm.team_ids = [];
    selectedSportIds.value = [];
    createCoachFeedback.value = null;
    onboardingFlash.value = null;
    onboardingMode.value = 'created';
    copiedOnboardingPassword.value = false;
    copiedActivationLink.value = false;
}

function openCoachOnboardingModal(mode: 'created' | 'regenerated') {
    onboardingMode.value = mode;
    coachOnboardingOpen.value = true;
    createCoachOpen.value = false;
    resetCreateCoachViewport();
    focusOnboardingPanel();
}

function closeCoachOnboardingModal() {
    coachOnboardingOpen.value = false;
    onboardingFlash.value = null;
    onboardingMode.value = 'created';
    copiedOnboardingPassword.value = false;
    copiedActivationLink.value = false;
}

function resetCreateCoachViewport() {
    nextTick(() => {
        if (!createCoachPanel.value) return;
        createCoachPanel.value.scrollTop = 0;
    });
}

function focusOnboardingPanel() {
    nextTick(() => {
        onboardingPanel.value?.scrollIntoView({ block: 'start', behavior: 'smooth' });
        onboardingPanel.value?.focus({ preventScroll: true });
    });
}

function closeAdminInvite() {
    adminInviteOpen.value = false;
    adminInviteForm.reset();
    adminInviteForm.clearErrors();
}

function submitAdminInvite() {
    adminInviteFeedback.value = null;
    adminInviteForm.post('/admin/invites', {
        preserveScroll: true,
        onSuccess: (visit) => {
            adminInviteFeedback.value = String((visit.props as any)?.flash?.success ?? 'Admin invitation sent.');
            closeAdminInvite();
        },
        onError: () => {
            adminInviteOpen.value = true;
        },
    });
}

function teamSlotTaken(team: AssignableTeam) {
    return createCoachForm.assignment_role === 'head'
        ? Boolean(team.head_coach?.id)
        : Boolean(team.assistant_coach?.id);
}

function teamSlotLabel(team: AssignableTeam) {
    if (createCoachForm.assignment_role === 'head') {
        return team.coach_name ? `Head coach: ${team.coach_name}` : 'Head coach slot open';
    }
    return team.assistant_coach_name ? `Assistant coach: ${team.assistant_coach_name}` : 'Assistant slot open';
}

function submitCreateCoach() {
    createCoachFeedback.value = null;
    createCoachForm.post('/admin/coaches', {
        preserveScroll: true,
        onSuccess: (visit) => {
            const flash = (visit.props as any)?.flash?.coach_onboarding as CoachOnboardingFlash | undefined;
            onboardingFlash.value = flash ?? null;
            onboardingMode.value = 'created';
            createCoachFeedback.value = flash
                ? 'The coach account has been created. Please record the temporary password now, as it will only be shown once.'
                : 'The coach account has been created successfully.';
            createCoachForm.reset();
            createCoachForm.assignment_role = 'assistant';
            createCoachForm.team_ids = [];
            selectedSportIds.value = [];
            copiedOnboardingPassword.value = false;
            copiedActivationLink.value = false;
            if (flash) {
                createCoachForm.reset();
                createCoachForm.assignment_role = 'assistant';
                createCoachForm.team_ids = [];
                selectedSportIds.value = [];
                openCoachOnboardingModal('created');
                return;
            }
            createCoachOpen.value = false;
        },
        onError: () => {
            createCoachOpen.value = true;
            resetCreateCoachViewport();
        },
    });
}

async function copyOnboardingPassword() {
    if (!onboardingFlash.value?.temporary_password) return;
    try {
        await navigator.clipboard.writeText(onboardingFlash.value.temporary_password);
        copiedOnboardingPassword.value = true;
        setTimeout(() => {
            copiedOnboardingPassword.value = false;
        }, 1400);
    } catch {
        copiedOnboardingPassword.value = false;
    }
}

async function copyActivationLink() {
    if (!onboardingFlash.value?.activation_url) return;
    try {
        await navigator.clipboard.writeText(onboardingFlash.value.activation_url);
        copiedActivationLink.value = true;
        setTimeout(() => {
            copiedActivationLink.value = false;
        }, 1400);
    } catch {
        copiedActivationLink.value = false;
    }
}

function regenerateCoachCredentials(user: UserRow) {
    if (user.role !== 'coach') return;
    regenerateTarget.value = user;
}

function closeRegenerateDialog() {
    regenerateTarget.value = null;
}

function confirmRegenerateCoachCredentials() {
    if (!regenerateTarget.value || regenerateTarget.value.role !== 'coach') return;

    router.post(
        `/admin/coaches/${regenerateTarget.value.id}/regenerate-onboarding`,
        {},
        {
            preserveScroll: true,
            onSuccess: (visit) => {
                const flash = (visit.props as any)?.flash?.coach_onboarding as CoachOnboardingFlash | undefined;
                onboardingFlash.value = flash ?? null;
                onboardingMode.value = 'regenerated';
                copiedOnboardingPassword.value = false;
                copiedActivationLink.value = false;
                createCoachFeedback.value = flash
                    ? 'Coach access credentials have been regenerated. Please record the new temporary password now.'
                    : 'Coach access credentials have been regenerated.';
                if (flash) {
                    openCoachOnboardingModal('regenerated');
                }
                closeRegenerateDialog();
            },
        },
    );
}

function goToApprovalRequests() {
    if (topTab.value === 'queue') return;
    topTab.value = 'queue';
    if (topTabTimeout) clearTimeout(topTabTimeout);
    topTabTimeout = setTimeout(() => {
        router.get('/people/queue');
    }, 180);
}

function visitPage(url: string | null) {
    if (!url) return;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'filters', 'totals', 'pendingCount'],
    });
}

function resetAllFilters() {
    search.value = '';
    roleFilter.value = 'all';
    statusFilter.value = 'active';
    sortOption.value = defaultSort;
    applyFilters({ resetPage: true });
}

function setStatusView(next: UserStatusFilter) {
    if (statusFilter.value === next) return;
    const previous = statusFilter.value;
    statusFilter.value = next;
    statusSwitching.value = true;
    statusSwitchNotice.value = next === 'active' ? 'Showing active accounts.' : 'Showing deactivated accounts.';

    if (searchDebounce) {
        clearTimeout(searchDebounce);
        searchDebounce = null;
    }

    router.get('/people', buildQuery(true), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['users', 'filters', 'totals', 'pendingCount'],
        onError: () => {
            statusFilter.value = previous;
            statusSwitchNotice.value = 'The account view could not be updated right now.';
        },
        onFinish: () => {
            statusSwitching.value = false;
        },
    });
}

function formatRole(role: UserRow['role']) {
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

function formatSimple(value?: string | null) {
    return value?.trim() ? value : '-';
}

function userInitials(user: UserRow) {
    return String(user.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'U';
}

function accountTone(user: UserRow) {
    return user.status === 'active'
        ? 'border border-[#034485]/30 bg-[#e9f2ff] text-[#034485]'
        : 'border border-amber-200 bg-amber-100 text-amber-700';
}

function roleTone(user: UserRow) {
    return user.role === 'coach'
        ? 'border border-[#1f3f73]/30 bg-[#edf4ff] text-[#1f3f73]'
        : 'border border-[#034485]/25 bg-[#f3f8ff] text-[#034485]';
}

function getPrimaryPhone(user: UserRow) {
    if (user.student?.phone_number) return user.student.phone_number;
    if (user.coach?.phone_number) return user.coach.phone_number;
    return null;
}

function getAge(value?: string | null) {
    if (!value) return null;
    const birthDate = new Date(value);
    if (Number.isNaN(birthDate.getTime())) return null;
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1;
    }
    return age >= 0 ? age : null;
}

function coachDisplayName(coach: CoachInfo | null | undefined) {
    if (!coach) return '-';
    const parts = [coach.first_name, coach.middle_name, coach.last_name].filter((part) => part && part.trim());
    return parts.length ? parts.join(' ') : '-';
}

function profileCompleteness(user: UserRow) {
    if (user.student) {
        const fields = [
            user.student.student_id_number,
            user.student.course_or_strand,
            user.student.academic_level_label ?? user.student.current_grade_level,
            user.student.phone_number,
            user.student.date_of_birth,
            user.student.gender,
        ];
        const filled = fields.filter((field) => field && field.toString().trim()).length;
        return Math.round((filled / fields.length) * 100);
    }

    if (user.coach) {
        const fields = [
            user.coach.first_name,
            user.coach.last_name,
            user.coach.phone_number,
            user.coach.date_of_birth,
            user.coach.gender,
            user.coach.coach_status,
        ];
        const filled = fields.filter((field) => field && field.toString().trim()).length;
        return Math.round((filled / fields.length) * 100);
    }

    return 0;
}

async function copyEmail(user: UserRow) {
    try {
        await navigator.clipboard.writeText(user.email);
        copiedUserId.value = user.id;
        setTimeout(() => {
            if (copiedUserId.value === user.id) copiedUserId.value = null;
        }, 1300);
    } catch {
        copiedUserId.value = null;
    }
}

function openDeactivateDialog(user: UserRow) {
    deactivateTarget.value = user;
}

function closeDeactivateDialog() {
    deactivateTarget.value = null;
}

function openReactivateDialog(user: UserRow) {
    reactivateTarget.value = user;
}

function closeReactivateDialog() {
    reactivateTarget.value = null;
}

function deactivateUser() {
    if (!deactivateTarget.value) return;

    actionLoadingId.value = deactivateTarget.value.id;

    router.post(
        `/admin/users/${deactivateTarget.value.id}/deactivate`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDeactivateDialog();
                setStatusView('deactivated');
            },
            onFinish: () => {
                actionLoadingId.value = null;
            },
        },
    );
}

function reactivateUser() {
    if (!reactivateTarget.value) return;

    actionLoadingId.value = reactivateTarget.value.id;

    router.post(
        `/admin/users/${reactivateTarget.value.id}/reactivate`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                closeReactivateDialog();
                setStatusView('active');
            },
            onFinish: () => {
                actionLoadingId.value = null;
            },
        },
    );
}

function onModalEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeDeactivateDialog();
        closeReactivateDialog();
        closeRegenerateDialog();
        closeCreateCoach();
        closeAdminInvite();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onModalEscape);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onModalEscape);
    document.body.style.overflow = '';
    if (searchDebounce) clearTimeout(searchDebounce);
});

watch(hasBlockingModal, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

watch(
    () => createCoachForm.assignment_role,
    () => {
        createCoachForm.team_ids = createCoachForm.team_ids.filter((teamId) => {
            const team = assignableTeams.value.find((entry) => entry.id === teamId);
            return team ? !teamSlotTaken(team) : false;
        });
    },
);

watch(
    () => (page.props as any)?.flash?.coach_onboarding,
    (value) => {
        onboardingFlash.value = (value as CoachOnboardingFlash | null) ?? null;
        copiedOnboardingPassword.value = false;
        copiedActivationLink.value = false;
        if (onboardingFlash.value) {
            openCoachOnboardingModal(onboardingMode.value);
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="People" />

    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative inline-grid w-full grid-cols-2 items-center rounded-2xl border border-[#034485]/45 bg-[#f4f8ff] p-1 sm:w-auto sm:rounded-full">
                <span
                    class="pointer-events-none absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-full bg-[#034485] transition-transform duration-200 ease-out"
                    :class="topTab === 'active' ? 'translate-x-0' : 'translate-x-full'"
                    aria-hidden="true"
                />
                <button
                    type="button"
                    class="relative z-10 flex w-full min-w-0 items-center justify-center rounded-xl px-3 py-2 text-center text-xs font-semibold leading-tight transition sm:rounded-full sm:px-4 sm:py-1.5"
                    :class="topTab === 'active' ? 'text-white' : 'text-[#034485] hover:text-[#02315f]'"
                    aria-current="page"
                >
                    Active Users
                </button>
                <button
                    type="button"
                    @click="goToApprovalRequests"
                    class="relative z-10 inline-flex w-full min-w-0 items-center justify-center gap-2 rounded-xl px-3 py-2 text-center text-xs font-semibold leading-tight transition sm:rounded-full sm:px-4 sm:py-1.5"
                    :class="topTab === 'queue' ? 'text-white' : 'text-[#034485] hover:text-[#02315f]'"
                >
                    Approval Queue
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                        :class="topTab === 'queue' ? 'bg-white/20 text-white' : 'bg-[#dcecff] text-[#034485]'"
                    >
                        {{ props.pendingCount ?? 0 }}
                    </span>
                </button>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <button
                    type="button"
                    @click="openAdminInvite"
                    class="inline-flex w-full items-center justify-center rounded-full border border-[#034485]/45 bg-white px-4 py-2 text-center text-xs font-semibold text-[#034485] transition hover:border-[#034485] hover:bg-[#eef5ff] sm:w-auto"
                >
                    Invite Administrator
                </button>
                <button
                    type="button"
                    @click="openCreateCoach"
                    class="inline-flex w-full items-center justify-center rounded-full bg-[#034485] px-4 py-2 text-center text-xs font-semibold text-white transition hover:bg-[#02315f] sm:w-auto"
                >
                    Create Coach Account
                </button>
            </div>
        </div>
        <p v-if="adminInviteFeedback" class="rounded-lg border border-[#034485]/25 bg-[#edf5ff] px-3 py-2 text-sm font-medium text-[#034485]">
            {{ adminInviteFeedback }}
        </p>
        <p v-if="createCoachFeedback" class="rounded-lg border border-[#034485]/25 bg-[#edf5ff] px-3 py-2 text-sm font-medium text-[#034485]">
            {{ createCoachFeedback }}
        </p>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-[11px] leading-relaxed tracking-wide text-slate-500 uppercase">Users</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ totalUsers }}</p>
            </article>
            <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-[11px] leading-relaxed tracking-wide text-slate-500 uppercase">Student-Athletes</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ totalStudents }}</p>
            </article>
            <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-[11px] leading-relaxed tracking-wide text-slate-500 uppercase">Coaches</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ totalCoaches }}</p>
            </article>
            <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                <p class="text-[11px] leading-relaxed tracking-wide text-slate-500 uppercase">Deactivated</p>
                <p class="mt-1 text-2xl font-bold text-[#1f3f73]">{{ totalDeactivated }}</p>
            </article>
        </div>

        <div class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
            <div class="mb-3 inline-grid w-full max-w-md grid-cols-2 items-center rounded-2xl border border-[#034485]/45 bg-[#f4f8ff] p-1 sm:inline-flex sm:rounded-full">
                <button
                    type="button"
                    @click="setStatusView('active')"
                    class="relative inline-flex min-w-0 flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2 text-center text-xs font-semibold leading-tight transition sm:rounded-full sm:px-4"
                    :class="statusFilter === 'active' ? 'bg-[#034485] text-white shadow-sm' : 'text-[#034485] hover:bg-white hover:text-[#02315f]'"
                    :aria-pressed="statusFilter === 'active'"
                    :disabled="statusSwitching"
                >
                    <span>Active</span>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                        :class="statusFilter === 'active' ? 'bg-white/20 text-white' : 'bg-[#dcecff] text-[#034485]'"
                    >
                        {{ totalActive }}
                    </span>
                </button>
                <span
                    class="mx-1 h-6 w-px bg-[#034485]/15"
                    aria-hidden="true"
                />
                <button
                    type="button"
                    @click="setStatusView('deactivated')"
                    class="relative inline-flex min-w-0 flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2 text-center text-xs font-semibold leading-tight transition sm:rounded-full sm:px-4"
                    :class="statusFilter === 'deactivated' ? 'bg-amber-600 text-white shadow-sm' : 'text-amber-700 hover:bg-white hover:text-amber-800'"
                    :aria-pressed="statusFilter === 'deactivated'"
                    :disabled="statusSwitching"
                >
                    <span>Deactivated</span>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                        :class="statusFilter === 'deactivated' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-700'"
                    >
                        {{ totalDeactivated }}
                    </span>
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name, email, student ID, course, or status"
                    class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20 lg:col-span-6"
                />

                <select
                    v-model="roleFilter"
                    class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20 lg:col-span-3"
                >
                    <option value="all">All Roles</option>
                    <option value="student-athlete">Student Athlete</option>
                    <option value="coach">Coach</option>
                </select>

                <select
                    v-model="sortOption"
                    class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20 lg:col-span-3"
                >
                    <option value="created_at:desc">Newest First</option>
                    <option value="created_at:asc">Oldest First</option>
                    <option value="name:asc">Name A-Z</option>
                    <option value="name:desc">Name Z-A</option>
                    <option value="email:asc">Email A-Z</option>
                    <option value="email:desc">Email Z-A</option>
                </select>
            </div>

            <div class="mt-3 flex justify-end" v-if="hasActiveFilters">
                <button
                    type="button"
                    @click="resetAllFilters"
                    class="rounded-md border border-[#034485]/35 bg-white px-3 py-1.5 text-xs font-semibold text-[#034485] transition hover:bg-[#eef5ff]"
                >
                    Clear Filters
                </button>
            </div>
            <p class="mt-2 text-xs font-medium text-slate-500">
                {{
                    statusSwitching
                        ? 'Updating the account list...'
                        : statusSwitchNotice
                            ? statusSwitchNotice
                        : isDeactivatedView
                            ? 'Viewing deactivated accounts. Select Reactivate to restore account access.'
                            : `Matching records: ${filteredTotal}`
                }}
            </p>
        </div>

        <Transition
            mode="out-in"
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div :key="statusFilter" class="page-card overflow-hidden rounded-xl border border-[#034485]/45 bg-white" :class="statusSwitching ? 'opacity-75' : ''">
                <div v-if="users.data.length" class="grid gap-0 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                            <div class="flex h-full min-h-full flex-col border-b border-[#034485]/15 bg-[#f8fbff] xl:border-r xl:border-b-0">
                        <div class="border-b border-[#034485]/15 bg-[#eef5ff] px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">User Directory</p>
                            <p v-if="isDeactivatedView" class="mt-1 text-sm text-slate-600">
                                Review deactivated accounts and restore access without leaving the page.
                            </p>
                        </div>
                        <div class="flex-1 bg-white">
                            <div class="max-h-[calc(100vh-24rem)] min-h-[24rem] overflow-y-auto">
                            <button
                                v-for="user in users.data"
                                :key="user.id"
                                type="button"
                                class="user-directory-row w-full border-b border-[#034485]/12 px-4 py-4 text-left transition-colors duration-200 ease-out last:border-b-0"
                                :class="selectedUser?.id === user.id ? 'border-l-4 border-l-[#02315f] bg-[#034485]' : ''"
                                @click="openInfo(user)"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl border text-sm font-bold transition-colors duration-200 ease-out"
                                        :class="selectedUser?.id === user.id ? 'border-white/25 bg-white/15 text-white' : 'border-[#034485]/20 bg-[#e9f2ff] text-[#034485]'"
                                    >
                                        <img
                                            v-if="user.avatar"
                                            :src="resolveUserAvatarUrl(user.avatar)"
                                            :alt="`${user.name} avatar`"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else>{{ userInitials(user) }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold leading-tight break-words transition-colors duration-200 ease-out" :class="selectedUser?.id === user.id ? 'text-white' : 'text-slate-900'">{{ user.name }}</p>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize transition-colors duration-200 ease-out" :class="accountTone(user)">
                                                {{ user.status }}
                                            </span>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize transition-colors duration-200 ease-out" :class="roleTone(user)">
                                                {{ formatRole(user.role) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs transition-colors duration-200 ease-out" :class="selectedUser?.id === user.id ? 'text-blue-100' : 'text-slate-500'">{{ user.email }}</p>
                                        <p class="mt-1 text-xs transition-colors duration-200 ease-out" :class="selectedUser?.id === user.id ? 'text-blue-100' : 'text-slate-500'">
                                            {{
                                                user.student
                                                    ? `${formatSimple(user.student.student_id_number)} • ${formatSimple(user.student.course_or_strand)}`
                                                    : `${formatSimple(coachDisplayName(user.coach))} • ${formatSimple(user.coach?.coach_status)}`
                                            }}
                                        </p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors duration-200 ease-out"
                                                :class="selectedUser?.id === user.id ? 'border-white/20 bg-white/10 text-blue-50' : 'border-[#034485]/15 bg-white text-[#034485]'"
                                            >
                                                Registered {{ formatDate(user.created_at) }}
                                            </span>
                                            <span
                                                class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors duration-200 ease-out"
                                                :class="selectedUser?.id === user.id ? 'border-white/20 bg-white/10 text-blue-50' : 'border-[#034485]/15 bg-white text-[#034485]'"
                                            >
                                                Profile {{ profileCompleteness(user) }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white">
                        <div v-if="selectedUser" class="space-y-5 p-4 sm:p-5">
                            <div class="flex flex-col gap-4 border-b border-[#034485]/15 pb-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-[#034485]/20 bg-[#e9f2ff] text-base font-bold text-[#034485]">
                                        <img
                                            v-if="selectedUser.avatar"
                                            :src="resolveUserAvatarUrl(selectedUser.avatar)"
                                            :alt="`${selectedUser.name} avatar`"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else>{{ userInitials(selectedUser) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-lg font-bold leading-tight break-words text-slate-900">{{ selectedUser.name }}</h2>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize" :class="accountTone(selectedUser)">
                                                {{ selectedUser.status }}
                                            </span>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize" :class="roleTone(selectedUser)">
                                                {{ formatRole(selectedUser.role) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-600">{{ selectedUser.email }}</p>
                                        <p class="text-sm text-slate-500">Joined {{ formatDateTime(selectedUser.created_at) }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                    <button
                                        type="button"
                                        @click="copyEmail(selectedUser)"
                                        class="inline-flex w-full items-center justify-center gap-1 rounded-lg border border-[#034485]/35 bg-white px-3 py-2 text-sm font-semibold text-[#034485] transition hover:bg-[#eef5ff] sm:w-auto"
                                    >
                                        <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor">
                                            <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                        </svg>
                                        {{ copiedUserId === selectedUser.id ? 'Copied' : 'Copy Email Address' }}
                                    </button>
                                    <button
                                        v-if="selectedUser.role === 'coach'"
                                        type="button"
                                        @click="regenerateCoachCredentials(selectedUser)"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#034485]/35 bg-[#eef5ff] px-3 py-2 text-sm font-semibold text-[#034485] transition hover:border-[#034485] hover:bg-[#e1eeff] sm:w-auto"
                                    >
                                        <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                                            <path d="M21 3v6h-6" />
                                        </svg>
                                        Regenerate Credentials
                                    </button>
                                    <button
                                        v-if="selectedUser.status === 'active'"
                                        type="button"
                                        @click="openDeactivateDialog(selectedUser)"
                                        :disabled="actionLoadingId === selectedUser.id"
                                        class="w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto"
                                    >
                                        Deactivate
                                    </button>
                                    <button
                                        v-if="selectedUser.status === 'deactivated'"
                                        type="button"
                                        @click="openReactivateDialog(selectedUser)"
                                        :disabled="actionLoadingId === selectedUser.id"
                                        class="w-full rounded-lg bg-[#034485] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#02315f] disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto"
                                    >
                                        Reactivate
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <section class="page-card rounded-2xl border border-[#034485]/18 bg-[#f9fbff] p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Account Overview</p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs text-slate-500">User ID</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ selectedUser.id }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Profile Completeness</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ profileCompleteness(selectedUser) }}%</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Account Status</p>
                                            <p class="mt-1 text-sm font-medium capitalize text-slate-900">{{ selectedUser.status }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Registered</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatDate(selectedUser.created_at) }}</p>
                                        </div>
                                    </div>
                                </section>

                                <section class="page-card rounded-2xl border border-[#034485]/18 bg-[#f9fbff] p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Contact</p>
                                    <div class="mt-3 grid gap-3">
                                        <div>
                                            <p class="text-xs text-slate-500">Email</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ selectedUser.email }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Primary Phone</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(getPrimaryPhone(selectedUser)) }}</p>
                                        </div>
                                    </div>
                                </section>

                                <section v-if="selectedUser.student" class="page-card rounded-2xl border border-[#034485]/18 bg-[#f9fbff] p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Student Information</p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs text-slate-500">Student ID</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.student_id_number) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Course / Strand</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.course_or_strand) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Academic Level</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.academic_level_label || selectedUser.student.current_grade_level) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Enrollment Status</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.student_status) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Approval Status</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.approval_status) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Gender</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.gender) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Birth Date</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatDate(selectedUser.student.date_of_birth) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Age</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ getAge(selectedUser.student.date_of_birth) ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Height</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.height) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Weight</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.weight) }}</p>
                                        </div>
                                    </div>
                                </section>

                                <section v-if="selectedUser.student" class="page-card rounded-2xl border border-[#034485]/18 bg-[#f9fbff] p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Emergency Contact</p>
                                    <div class="mt-3 grid gap-3">
                                        <div>
                                            <p class="text-xs text-slate-500">Name</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.emergency_contact_name) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Relationship</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.emergency_contact_relationship) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Phone</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.student.emergency_contact_phone) }}</p>
                                        </div>
                                    </div>
                                </section>

                                <section v-if="selectedUser.coach" class="page-card rounded-2xl border border-[#034485]/18 bg-[#f9fbff] p-4 lg:col-span-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Coach Information</p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs text-slate-500">Coach Name</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ coachDisplayName(selectedUser.coach) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Coach Status</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.coach.coach_status) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Phone</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.coach.phone_number) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Gender</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatSimple(selectedUser.coach.gender) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Birth Date</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ formatDate(selectedUser.coach.date_of_birth) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Age</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ getAge(selectedUser.coach.date_of_birth) ?? '-' }}</p>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-4">
                    <EmptyResultsState
                        title="No user records matched your filters"
                        description="Try adjusting the account status, role, or search terms to find the user you need."
                    />
                </div>

                <div
                    class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>Showing {{ users.from ?? 0 }} to {{ users.to ?? 0 }} of {{ users.total }} users</p>
                    <nav class="flex flex-wrap items-center gap-1" aria-label="User pagination">
                        <button
                            v-for="(link, index) in users.links"
                            :key="`${index}-${link.label}`"
                            type="button"
                            :disabled="!link.url"
                            @click="visitPage(link.url)"
                            class="min-w-9 rounded-md border px-2 py-1 text-xs transition"
                            :class="
                                link.active
                                    ? 'border-[#034485] bg-[#034485] text-white'
                                    : 'border-[#034485]/25 bg-white text-[#034485] hover:bg-[#eef5ff] disabled:cursor-not-allowed disabled:opacity-40'
                            "
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </Transition>
    </div>

    <Transition name="modal-fade">
        <div
            v-if="adminInviteOpen"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:items-center"
            @click.self="closeAdminInvite"
        >
            <div class="modal-panel my-6 w-full max-w-xl rounded-2xl border border-[#034485]/45 bg-white p-6 sm:my-0 sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Invite Administrator</h2>
                        <p class="mt-1 text-sm text-slate-600">Send a one-time account setup link to a future AC-VMIS administrator.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeAdminInvite"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#034485]/30 bg-white text-[#034485] hover:bg-[#eef5ff]"
                        aria-label="Close admin invite form"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submitAdminInvite">
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Email</label>
                        <input
                            v-model="adminInviteForm.email"
                            type="email"
                            class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20"
                            placeholder="futureadmin@example.com"
                        />
                        <p v-if="adminInviteForm.errors.email" class="mt-1 text-xs text-rose-600">{{ adminInviteForm.errors.email }}</p>
                    </div>

                    <div class="rounded-xl border border-[#034485]/15 bg-[#f6faff] p-3 text-xs text-slate-600">
                        This invitation link may be used only once and will expire after three days. The administrator account will be created after setup is completed.
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="closeAdminInvite"
                            class="rounded-md border border-[#034485]/35 bg-white px-4 py-2 text-sm font-semibold text-[#034485] transition hover:bg-[#eef5ff]"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="adminInviteForm.processing"
                            class="rounded-md bg-[#034485] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#02315f] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ adminInviteForm.processing ? 'Sending...' : 'Send Invitation' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div
            v-if="createCoachOpen"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:items-center"
            @click.self="closeCreateCoach"
        >
            <div
                ref="createCoachPanel"
                class="modal-panel my-6 max-h-[calc(100vh-3rem)] w-full max-w-4xl overflow-y-auto rounded-2xl border border-[#034485]/45 bg-white p-6 sm:my-0 sm:p-7"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Create Coach Account</h2>
                        <p class="mt-1 text-sm text-slate-600">Create a coach account and optionally assign the coach to a team during setup.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeCreateCoach"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#034485]/30 bg-white text-[#034485] hover:bg-[#eef5ff]"
                        aria-label="Close create coach form"
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
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <form class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2" @submit.prevent="submitCreateCoach">
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">First Name</label>
                        <input
                            v-model="createCoachForm.first_name"
                            type="text"
                            class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20"
                        />
                        <p v-if="createCoachForm.errors.first_name" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.first_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Last Name</label>
                        <input
                            v-model="createCoachForm.last_name"
                            type="text"
                            class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20"
                        />
                        <p v-if="createCoachForm.errors.last_name" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.last_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Middle Name (Optional)</label>
                        <input
                            v-model="createCoachForm.middle_name"
                            type="text"
                            class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Email</label>
                        <input
                            v-model="createCoachForm.email"
                            type="email"
                            class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20"
                        />
                        <p v-if="createCoachForm.errors.email" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Phone Number</label>
                        <input
                            v-model="createCoachForm.phone_number"
                            type="text"
                            class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Gender</label>
                        <select
                            v-model="createCoachForm.gender"
                            class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20"
                        >
                            <option value="">Not set</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <p v-if="createCoachForm.errors.gender" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.gender }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Date Of Birth</label>
                        <input
                            v-model="createCoachForm.date_of_birth"
                            type="date"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                        <p v-if="createCoachForm.errors.date_of_birth" class="mt-1 text-xs text-rose-600">
                            {{ createCoachForm.errors.date_of_birth }}
                        </p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Team Role Assignment</label>
                        <select
                            v-model="createCoachForm.assignment_role"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        >
                            <option value="assistant">Assistant Coach</option>
                            <option value="head">Head Coach</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Home Address</label>
                        <textarea
                            v-model="createCoachForm.home_address"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#1f2937] focus:ring-2 focus:ring-[#1f2937]/20"
                        />
                        <p v-if="createCoachForm.errors.home_address" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.home_address }}</p>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Sports Filter (Optional)</label>
                        <div class="flex flex-wrap gap-2 rounded-lg border border-[#034485]/15 bg-[#f6faff] p-2">
                            <label
                                v-for="sport in sportOptions"
                                :key="sport.id"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-[#034485]/20 bg-white px-3 py-1 text-xs font-semibold text-[#034485]"
                            >
                                <input v-model="selectedSportIds" type="checkbox" :value="sport.id" class="h-3.5 w-3.5" />
                                {{ sport.name }}
                            </label>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Assign Team(s)</label>
                        <div class="max-h-56 space-y-2 overflow-y-auto rounded-lg border border-[#034485]/15 bg-[#f6faff] p-2">
                            <label
                                v-for="team in filteredAssignableTeams"
                                :key="team.id"
                                class="flex cursor-pointer items-start justify-between gap-3 rounded-lg border border-[#034485]/15 bg-white px-3 py-2 text-sm"
                                :class="teamSlotTaken(team) ? 'opacity-60' : ''"
                            >
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">
                                        {{ team.team_name }} <span class="text-slate-500">({{ team.year || 'N/A' }})</span>
                                    </p>
                                    <p class="text-xs text-slate-500">{{ team.sport_name || 'No sport assigned' }}</p>
                                    <p class="mt-1 text-xs" :class="teamSlotTaken(team) ? 'text-[#1f3f73]' : 'text-[#034485]'">
                                        {{ teamSlotLabel(team) }}
                                    </p>
                                </div>
                                <input
                                    v-model="createCoachForm.team_ids"
                                    type="checkbox"
                                    :value="team.id"
                                    :disabled="teamSlotTaken(team)"
                                    class="mt-1 h-4 w-4"
                                />
                            </label>
                            <p v-if="filteredAssignableTeams.length === 0" class="px-1 py-2 text-xs text-slate-500">
                                No teams found for the selected sport filter.
                            </p>
                        </div>
                        <p v-if="createCoachForm.errors.team_ids" class="mt-1 text-xs whitespace-pre-line text-rose-600">
                            {{ createCoachForm.errors.team_ids }}
                        </p>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase">Notes (Optional)</label>
                        <textarea
                            v-model="createCoachForm.notes"
                            rows="2"
                            class="w-full rounded-lg border border-[#034485]/20 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20"
                        />
                        <p v-if="createCoachForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ createCoachForm.errors.notes }}</p>
                    </div>

                    <div class="flex justify-end gap-2 lg:col-span-2">
                        <button
                            type="button"
                            @click="closeCreateCoach"
                            class="rounded-md border border-[#034485]/35 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#eef5ff]"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="createCoachForm.processing"
                            class="rounded-md bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#02315f] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ createCoachForm.processing ? 'Creating...' : 'Create Coach Account' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div
            v-if="coachOnboardingOpen && onboardingFlash"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeCoachOnboardingModal"
        >
            <div class="modal-panel w-full max-w-xl rounded-2xl border border-[#034485]/35 bg-white p-6 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-[#034485]">
                            {{ onboardingMode === 'regenerated' ? 'Coach access credentials regenerated' : 'New coach account credentials' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">Please record these details now. The temporary password will not be displayed again.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeCoachOnboardingModal"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#034485]/30 bg-white text-[#034485] hover:bg-[#eef5ff]"
                        aria-label="Close coach onboarding details"
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
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div
                    ref="onboardingPanel"
                    tabindex="-1"
                    class="mt-5 rounded-xl border border-[#034485]/20 bg-[#f4f8ff] p-4 outline-none ring-0"
                >
                    <p class="text-xs font-semibold tracking-wide text-[#034485] uppercase">Coach Email</p>
                    <p class="mt-1 text-sm font-semibold text-[#02315f]">{{ onboardingFlash.email }}</p>

                    <div class="mt-4">
                        <p class="text-xs font-semibold tracking-wide text-[#034485] uppercase">Temporary Password</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-md border border-[#034485]/30 bg-white px-3 py-1.5 font-mono text-sm text-[#02315f]">
                                {{ onboardingFlash.temporary_password }}
                            </span>
                            <button
                                type="button"
                                @click="copyOnboardingPassword"
                                class="rounded-md border border-[#034485]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#034485] hover:bg-[#eef5ff]"
                            >
                                {{ copiedOnboardingPassword ? 'Copied' : 'Copy Temporary Password' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="text-xs font-semibold tracking-wide text-[#034485] uppercase">Activation Link</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                @click="copyActivationLink"
                                class="rounded-md border border-[#034485]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#034485] hover:bg-[#eef5ff]"
                            >
                                {{ copiedActivationLink ? 'Copied Link' : 'Copy Activation Link' }}
                            </button>
                        </div>
                    </div>

                    <p class="mt-4 text-xs" :class="onboardingFlash.email_sent ? 'text-[#034485]' : 'text-[#1f3f73]'">
                        {{
                            onboardingFlash.email_sent
                                ? 'The onboarding email was sent successfully.'
                                : 'The onboarding email could not be sent. Please provide the temporary password manually.'
                        }}
                    </p>
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        @click="closeCoachOnboardingModal"
                        class="rounded-md bg-[#034485] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#02315f]"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div
            v-if="regenerateTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeRegenerateDialog"
        >
            <div class="modal-panel w-full max-w-lg rounded-xl border border-[#034485]/45 bg-white p-5">
                <h2 class="text-lg font-bold text-slate-900">Regenerate Credentials</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Regenerate onboarding credentials for <span class="font-semibold text-slate-900">{{ regenerateTarget.name }}</span
                    >? The previous temporary password will no longer be valid.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeRegenerateDialog"
                        class="rounded-md border border-[#034485]/35 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#eef5ff]"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="confirmRegenerateCoachCredentials"
                        class="rounded-md bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#02315f]"
                    >
                        Confirm Regenerate
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div
            v-if="deactivateTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeDeactivateDialog"
        >
            <div class="modal-panel w-full max-w-lg rounded-xl border border-[#034485]/45 bg-white p-5">
                <h2 class="text-lg font-bold text-slate-900">Deactivate Account</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Deactivate <span class="font-semibold text-slate-900">{{ deactivateTarget.name }}</span
                    >? This blocks login but keeps records for audit and recovery.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeDeactivateDialog"
                        class="rounded-md border border-[#034485]/35 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#eef5ff]"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="deactivateUser"
                        :disabled="actionLoadingId === deactivateTarget.id"
                        class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Confirm Deactivate
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <Transition name="modal-fade">
        <div
            v-if="reactivateTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeReactivateDialog"
        >
            <div class="modal-panel w-full max-w-lg rounded-xl border border-[#034485]/45 bg-white p-5">
                <h2 class="text-lg font-bold text-slate-900">Reactivate Account</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Reactivate <span class="font-semibold text-slate-900">{{ reactivateTarget.name }}</span
                    >? This restores full sign-in access immediately.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeReactivateDialog"
                        class="rounded-md border border-[#034485]/35 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#eef5ff]"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="reactivateUser"
                        :disabled="actionLoadingId === reactivateTarget.id"
                        class="rounded-md bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#02315f] disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Confirm Reactivate
                    </button>
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

.user-directory-row:hover {
    background: #f5f9ff;
}

:global(html.theme-dark) .user-directory-row:hover {
    background: rgba(3, 68, 133, 0.16) !important;
}
</style>
