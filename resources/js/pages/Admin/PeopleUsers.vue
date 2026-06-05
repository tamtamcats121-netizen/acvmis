<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputMask from 'primevue/inputmask'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import Paginator from 'primevue/paginator'
import Select from 'primevue/select'
import { computed, ref, watch } from 'vue'

import AppAvatar from '@/components/common/AppAvatar.vue'
import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { showAppToast } from '@/composables/useAppToast'
import { useTheme } from '@/composables/useTheme'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type RoleFilter = 'all' | 'student-athlete' | 'coach'
type UserStatusFilter = 'active' | 'deactivated'
type SortField = 'name' | 'email' | 'created_at'
type SortDirection = 'asc' | 'desc'
type SortOption = `${SortField}:${SortDirection}`

type TeamAssignment = {
    id: number
    name: string
    sport_name: string
    year: string | null
    role_label: string
    position: string | null
    status: string | null
    archived: boolean
}

type UserRow = {
    id: number
    name: string
    email: string
    role: 'student-athlete' | 'student' | 'coach' | 'admin'
    status: UserStatusFilter
    avatar: string | null
    avatar_url?: string | null
    created_at: string | null
    assignment: {
        sport_label: string | null
        status_label: string
        current_teams: TeamAssignment[]
        history_teams: TeamAssignment[]
        needs_action: boolean
        needs_action_label: string | null
    }
    actions: {
        can_send_activation_link: boolean
        can_deactivate: boolean
        can_reactivate: boolean
        can_delete: boolean
        delete_blockers: Array<{ label: string; count: number }>
    }
}

type PaginationLink = {
    url: string | null
    label: string
    active: boolean
}

type PaginatedUsers = {
    data: UserRow[]
    current_page: number
    last_page: number
    per_page: number
    from: number | null
    to: number | null
    total: number
    links: PaginationLink[]
}

type Filters = {
    search?: string
    role?: RoleFilter
    status?: UserStatusFilter
    sort?: SortField
    direction?: SortDirection
}

type Totals = {
    all: number
    students: number
    coaches: number
    deactivated: number
    filtered: number
}

type SportOption = {
    id: number
    name: string
}

type CoachOnboardingFlash = {
    email: string
    email_sent: boolean
    activation_url: string
}

const props = defineProps<{
    users: PaginatedUsers
    filters?: Filters
    totals?: Totals
    sports?: SportOption[]
}>()

const { isDarkMode } = useTheme()

const defaultSort: SortOption = 'created_at:desc'
const search = ref(props.filters?.search ?? '')
const roleFilter = ref<RoleFilter>(props.filters?.role ?? 'all')
const statusFilter = ref<UserStatusFilter>(props.filters?.status ?? 'active')
const sortOption = ref<SortOption>(`${props.filters?.sort ?? 'created_at'}:${props.filters?.direction ?? 'desc'}` as SortOption)
const createCoachOpen = ref(false)
const createCoachFeedback = ref<string | null>(null)
const adminInviteOpen = ref(false)
const adminInviteFeedback = ref<string | null>(null)
const actionLoadingId = ref<number | null>(null)
const rowActionMenu = ref()
const actionMenuUser = ref<UserRow | null>(null)
const deactivateTarget = ref<UserRow | null>(null)
const reactivateTarget = ref<UserRow | null>(null)
const regenerateTarget = ref<UserRow | null>(null)
const deleteTarget = ref<UserRow | null>(null)
let searchDebounce: ReturnType<typeof setTimeout> | null = null

const createCoachForm = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    sport_id: '',
    phone_number: '',
    date_of_birth: '',
    gender: '',
    home_address: '',
    notes: '',
})

const adminInviteForm = useForm({
    email: '',
})

const totalUsers = computed(() => props.totals?.all ?? 0)
const totalStudents = computed(() => props.totals?.students ?? 0)
const totalCoaches = computed(() => props.totals?.coaches ?? 0)
const totalDeactivated = computed(() => props.totals?.deactivated ?? 0)
const filteredTotal = computed(() => props.totals?.filtered ?? props.users.total)
const totalActive = computed(() => Math.max(totalUsers.value - totalDeactivated.value, 0))
const hasActiveFilters = computed(() => search.value.trim() !== '' || roleFilter.value !== 'all' || sortOption.value !== defaultSort)
const firstRecordIndex = computed(() => (props.users.current_page - 1) * props.users.per_page)
const roleFilterOptions = [
    { label: 'All roles', value: 'all' },
    { label: 'Student Athlete', value: 'student-athlete' },
    { label: 'Coach', value: 'coach' },
]
const sortOptions = [
    { label: 'Newest First', value: 'created_at:desc' },
    { label: 'Oldest First', value: 'created_at:asc' },
    { label: 'Name A-Z', value: 'name:asc' },
    { label: 'Name Z-A', value: 'name:desc' },
    { label: 'Email A-Z', value: 'email:asc' },
    { label: 'Email Z-A', value: 'email:desc' },
]
const sportOptions = computed(() => [
    { label: 'Assigned sport', value: '' },
    ...((props.sports ?? []).map((sport) => ({
        label: sport.name,
        value: String(sport.id),
    }))),
])
const actionMenuItems = computed(() => {
    const user = actionMenuUser.value
    if (!user) return []

    const items: Array<Record<string, unknown>> = [
        {
            label: 'View Profile',
            icon: 'pi pi-user',
            command: () => router.get(`/people/${user.id}`),
        },
    ]

    if (user.actions.can_send_activation_link) {
        items.push({
            label: 'Send Activation Link',
            icon: 'pi pi-send',
            command: () => regenerateCoachCredentials(user),
        })
    }

    if (user.actions.can_deactivate) {
        items.push({
            label: 'Deactivate',
            icon: 'pi pi-ban',
            command: () => {
                deactivateTarget.value = user
            },
        })
    }

    if (user.actions.can_reactivate) {
        items.push({
            label: 'Reactivate',
            icon: 'pi pi-check-circle',
            command: () => {
                reactivateTarget.value = user
            },
        })
    }

    items.push({
        separator: true,
    })

    items.push({
        label: 'Delete',
        icon: 'pi pi-trash',
        class: 'admin-user-directory-delete-item',
        command: () => {
            if (user.actions.can_delete) {
                deleteTarget.value = user
                return
            }

            showAppToast('This user has linked records. Deactivate the account instead.', 'error')
        },
    })

    return items
})

function buildQuery(page = 1) {
    const [sort, direction] = sortOption.value.split(':') as [SortField, SortDirection]

    return {
        search: search.value.trim() || undefined,
        role: roleFilter.value === 'all' ? undefined : roleFilter.value,
        status: statusFilter.value,
        sort,
        direction,
        page,
    }
}

function applyFilters(page = 1) {
    router.get('/people', buildQuery(page), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['users', 'filters', 'totals'],
    })
}

watch(search, () => {
    if (searchDebounce) clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => applyFilters(1), 250)
})

watch(roleFilter, () => applyFilters(1))
watch(sortOption, () => applyFilters(1))

function setStatusView(next: UserStatusFilter) {
    if (statusFilter.value === next) return
    statusFilter.value = next
    applyFilters(1)
}

function resetAllFilters() {
    search.value = ''
    roleFilter.value = 'all'
    sortOption.value = defaultSort
    applyFilters(1)
}

function handlePageChange(event: { page: number }) {
    applyFilters(event.page + 1)
}

function handleSort(event: { sortField?: string | ((item: unknown) => string); sortOrder?: number | null }) {
    const nextField = typeof event.sortField === 'string' ? event.sortField as SortField : 'created_at'
    const nextDirection: SortDirection = event.sortOrder === 1 ? 'asc' : 'desc'
    sortOption.value = `${nextField}:${nextDirection}` as SortOption
}

function toggleActionMenu(event: Event, user: UserRow) {
    actionMenuUser.value = user
    rowActionMenu.value?.toggle(event)
}

function formatRole(role: UserRow['role']) {
    if (role === 'coach') return 'Coach'
    if (role === 'admin') return 'Admin'
    return 'Student Athlete'
}

function formatDateTime(value?: string | null) {
    if (!value) return '-'
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return '-'
    return parsed.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function accountTone(user: UserRow) {
    return user.status === 'active'
        ? 'text-[#034485]'
        : 'text-slate-600 dark:text-slate-200'
}

function roleTone(user: UserRow) {
    if (user.role === 'coach') {
        return 'border border-sky-200 bg-sky-50/95 text-sky-700'
    }

    if (user.role === 'admin') {
        return 'border border-[#4a90e2]/30 bg-[#eef5ff] text-[#034485]'
    }

    return 'border border-[#4a90e2]/30 bg-[#eef5ff] text-[#034485]'
}

function assignmentTone(user: UserRow) {
    return user.assignment.needs_action
        ? 'border border-slate-200 bg-slate-50 text-slate-700'
        : 'border border-[#4a90e2]/25 bg-[#f1f7ff] text-[#034485]'
}

function assignmentPrimaryLabel(user: UserRow) {
    if (user.assignment.current_teams.length > 0) {
        return user.assignment.sport_label || user.assignment.status_label
    }

    if (user.assignment.sport_label) {
        return user.assignment.sport_label
    }

    return 'Unassigned'
}

function assignmentSecondaryLabel(user: UserRow) {
    if (user.assignment.needs_action) {
        return null
    }

    if (user.assignment.status_label === 'Unassigned') {
        return null
    }

    if (user.assignment.status_label === user.assignment.sport_label) {
        return null
    }

    return user.assignment.status_label
}

function openCreateCoach() {
    createCoachOpen.value = true
}

function closeCreateCoach() {
    createCoachOpen.value = false
    createCoachForm.reset()
    createCoachForm.clearErrors()
}

function openAdminInvite() {
    adminInviteOpen.value = true
}

function closeAdminInvite() {
    adminInviteOpen.value = false
    adminInviteForm.reset()
    adminInviteForm.clearErrors()
}

function submitAdminInvite() {
    adminInviteFeedback.value = null
    adminInviteForm.post('/admin/invites', {
        preserveScroll: true,
        onSuccess: (visit) => {
            adminInviteFeedback.value = String((visit.props as any)?.flash?.success ?? 'Admin invitation sent.')
            showAppToast(adminInviteFeedback.value, 'success')
            closeAdminInvite()
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            showAppToast(Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Unable to send the invitation right now.'), 'error')
            adminInviteOpen.value = true
        },
    })
}

function submitCreateCoach() {
    createCoachFeedback.value = null
    createCoachForm.post('/admin/coaches', {
        preserveScroll: true,
        onSuccess: (visit) => {
            const flash = (visit.props as any)?.flash?.coach_onboarding as CoachOnboardingFlash | undefined
            createCoachFeedback.value = flash
                ? (flash.email_sent
                    ? `Coach account created. Activation link was sent to ${flash.email}.`
                    : `Coach account created, but the activation email could not be sent to ${flash.email}.`)
                : 'The coach account has been created successfully.'
            showAppToast(createCoachFeedback.value, 'success')
            closeCreateCoach()
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            showAppToast(Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Unable to create the coach account right now.'), 'error')
            createCoachOpen.value = true
        },
    })
}

function regenerateCoachCredentials(user: UserRow) {
    if (!user.actions.can_send_activation_link) return
    regenerateTarget.value = user
}

function confirmRegenerateCoachCredentials() {
    if (!regenerateTarget.value) return

    actionLoadingId.value = regenerateTarget.value.id
    router.post(`/admin/coaches/${regenerateTarget.value.id}/activation-link`, {}, {
        preserveScroll: true,
        onSuccess: (visit) => {
            const flash = (visit.props as any)?.flash?.coach_onboarding as CoachOnboardingFlash | undefined
            createCoachFeedback.value = flash
                ? (flash.email_sent
                    ? `Coach activation link was sent to ${flash.email}.`
                    : `Coach activation link was created, but the email could not be sent to ${flash.email}.`)
                : 'Coach activation link has been sent.'
            showAppToast(createCoachFeedback.value, 'success')
            regenerateTarget.value = null
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            showAppToast(Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Unable to send the coach activation link.'), 'error')
        },
        onFinish: () => {
            actionLoadingId.value = null
        },
    })
}

function confirmDeactivateUser() {
    if (!deactivateTarget.value) return

    actionLoadingId.value = deactivateTarget.value.id
    router.post(`/admin/users/${deactivateTarget.value.id}/deactivate`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast('The account has been deactivated.', 'success')
            deactivateTarget.value = null
            applyFilters(props.users.current_page)
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            showAppToast(Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Unable to deactivate this account right now.'), 'error')
        },
        onFinish: () => {
            actionLoadingId.value = null
        },
    })
}

function confirmReactivateUser() {
    if (!reactivateTarget.value) return

    actionLoadingId.value = reactivateTarget.value.id
    router.post(`/admin/users/${reactivateTarget.value.id}/reactivate`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast('The account has been reactivated.', 'success')
            reactivateTarget.value = null
            applyFilters(props.users.current_page)
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            showAppToast(Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Unable to reactivate this account right now.'), 'error')
        },
        onFinish: () => {
            actionLoadingId.value = null
        },
    })
}

function confirmDeleteUser() {
    if (!deleteTarget.value) return

    actionLoadingId.value = deleteTarget.value.id
    router.delete(`/admin/users/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast('The account was deleted permanently.', 'success')
            deleteTarget.value = null
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            showAppToast(Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'This user has linked records. Deactivate the account instead.'), 'error')
        },
        onFinish: () => {
            actionLoadingId.value = null
        },
    })
}
</script>

<template>
    <Head title="User Records" />

    <div class="space-y-5">
        <section class="overflow-hidden rounded-[2rem] border border-[#02376b] bg-[#034485] p-6 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">Admin Workspace</p>
            <h1 class="mt-2 text-2xl font-bold">User Directory</h1>
            <p class="mt-2 max-w-3xl text-sm text-white/85">
                Monitor user accounts, assignment visibility, and access status in one clean directory without mixing in approval workflows.
            </p>
            <div class="mt-4 grid grid-cols-1 gap-2 sm:max-w-xl sm:grid-cols-2">
                <button
                    type="button"
                    class="rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/15"
                    @click="openAdminInvite"
                >
                    Invite Administrator
                </button>
                <button
                    type="button"
                    class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-[#034485] transition hover:bg-[#eef5ff]"
                    @click="openCreateCoach"
                >
                    Create Coach Account
                </button>
            </div>
        </section>

        <p v-if="adminInviteFeedback" class="rounded-2xl border border-[#034485]/25 bg-[#edf5ff] px-4 py-3 text-sm font-medium text-[#034485]">
            {{ adminInviteFeedback }}
        </p>
        <p v-if="createCoachFeedback" class="rounded-2xl border border-[#034485]/25 bg-[#edf5ff] px-4 py-3 text-sm font-medium text-[#034485]">
            {{ createCoachFeedback }}
        </p>

        <section class="grid gap-3 md:grid-cols-4">
            <article class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">Active Users</p>
                <p class="mt-2 text-2xl font-bold">{{ totalUsers }}</p>
            </article>
            <article class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">Student Athletes</p>
                <p class="mt-2 text-2xl font-bold">{{ totalStudents }}</p>
            </article>
            <article class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/20 bg-white text-slate-900'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">Coaches</p>
                <p class="mt-2 text-2xl font-bold">{{ totalCoaches }}</p>
            </article>
            <article class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-slate-200 bg-slate-50 text-slate-900'">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-200' : 'text-slate-600'">Deactivated</p>
                <p class="mt-2 text-2xl font-bold">{{ totalDeactivated }}</p>
            </article>
        </section>

        <section class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/25 bg-white text-slate-900'">
            <div class="mb-4 w-full sm:max-w-md">
                <div class="relative grid grid-cols-2 rounded-2xl border p-1 sm:rounded-full" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/20 bg-[#f4f8ff]'">
                    <div
                        class="absolute bottom-1 top-1 w-[calc(50%-0.25rem)] rounded-xl bg-[#034485] transition-transform duration-300 ease-out sm:rounded-full"
                        :class="statusFilter === 'active' ? 'translate-x-0' : 'translate-x-full'"
                    />
                    <button
                        type="button"
                        class="relative z-10 rounded-xl px-4 py-2 text-sm font-semibold transition-colors duration-300 sm:rounded-full"
                        :class="statusFilter === 'active' ? 'text-white' : (isDarkMode ? 'text-slate-100' : 'text-[#034485]')"
                        @click="setStatusView('active')"
                    >
                        Active
                        <span class="ml-2 rounded-full px-2 py-0.5 text-xs" :class="statusFilter === 'active' ? 'bg-white/20 text-white' : (isDarkMode ? 'bg-slate-800 text-slate-100' : 'bg-[#dcecff] text-[#034485]')">
                            {{ totalActive }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="relative z-10 rounded-xl px-4 py-2 text-sm font-semibold transition-colors duration-300 sm:rounded-full"
                        :class="statusFilter === 'deactivated' ? 'text-white' : (isDarkMode ? 'text-slate-100' : 'text-slate-700')"
                        @click="setStatusView('deactivated')"
                    >
                        Deactivated
                        <span class="ml-2 rounded-full px-2 py-0.5 text-xs" :class="statusFilter === 'deactivated' ? 'bg-white/20 text-white' : (isDarkMode ? 'bg-slate-800 text-slate-100' : 'bg-slate-200 text-slate-700')">
                            {{ totalDeactivated }}
                        </span>
                    </button>
                </div>
            </div>

            <div class="grid gap-3 lg:grid-cols-12">
                <InputText
                    v-model="search"
                    placeholder="Search name, email, sport, team, student ID, or course"
                    class="lg:col-span-6"
                />
                <Select
                    v-model="roleFilter"
                    :options="roleFilterOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="lg:col-span-3"
                />
                <Select
                    v-model="sortOption"
                    :options="sortOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="lg:col-span-3"
                />
            </div>

            <div class="mt-3 flex items-center justify-between gap-3">
                <p class="text-xs font-medium" :class="isDarkMode ? 'text-slate-200' : 'text-slate-500'">
                    Showing {{ filteredTotal }} record{{ filteredTotal === 1 ? '' : 's' }} in the {{ statusFilter }} view.
                </p>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                    :class="isDarkMode ? 'border-[#4a90e2]/35 bg-slate-900 text-white hover:border-sky-300/45 hover:bg-[#0a2f57]' : 'border-[#034485]/25 bg-white text-[#034485] hover:bg-[#eef5ff]'"
                    @click="resetAllFilters"
                >
                    Clear Filters
                </button>
            </div>
        </section>

        <section class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white' : 'border-[#034485]/25 bg-white text-slate-900'">
            <div>
                <DataTable
                    :value="users.data"
                    dataKey="id"
                    responsiveLayout="scroll"
                    :class="['admin-user-directory-table', { 'admin-user-directory-table--dark': isDarkMode }]"
                    :sortField="sortOption.split(':')[0]"
                    :sortOrder="sortOption.endsWith(':asc') ? 1 : -1"
                    @sort="handleSort"
                    :pt="{
                        table: { class: 'min-w-[1180px]' },
                        tbody: { class: isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-white text-slate-900' },
                    }"
                >
                    <template #empty>
                        <div class="py-10 text-center text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">
                            No users matched the current directory filters.
                        </div>
                    </template>

                    <Column field="name" header="User" sortable>
                        <template #body="{ data }">
                            <div class="flex items-start gap-3">
                                <AppAvatar
                                    :src="data.avatar"
                                    :src-url="data.avatar_url"
                                    :name="data.name"
                                    :alt="`${data.name} avatar`"
                                    size-class="h-12 w-12"
                                    rounded-class="rounded-2xl"
                                    class="text-sm"
                                />
                                <div class="min-w-0">
                                    <p class="font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-950'">{{ data.name }}</p>
                                    <p class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">ID {{ data.id }}</p>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column field="email" header="Email" sortable>
                        <template #body="{ data }">
                            <div class="min-w-0">
                                <p class="break-all text-sm">{{ data.email }}</p>
                            </div>
                        </template>
                    </Column>

                    <Column field="role" header="Role">
                        <template #body="{ data }">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="roleTone(data)">
                                {{ formatRole(data.role) }}
                            </span>
                        </template>
                    </Column>

                    <Column field="status" header="Account Status">
                        <template #body="{ data }">
                            <span class="text-sm font-semibold capitalize" :class="accountTone(data)">
                                {{ data.status }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Sport / Team Assignment">
                        <template #body="{ data }">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="assignmentTone(data)">
                                        {{ assignmentPrimaryLabel(data) }}
                                    </span>
                                </div>
                                <div class="text-sm">
                                    <p v-if="assignmentSecondaryLabel(data)" class="font-medium" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ assignmentSecondaryLabel(data) }}</p>
                                    <p v-if="data.assignment.history_teams.length" class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                                        {{ data.assignment.history_teams.length }} archived/history team link{{ data.assignment.history_teams.length === 1 ? '' : 's' }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column field="created_at" header="Created" sortable>
                        <template #body="{ data }">
                            <span class="text-sm">{{ formatDateTime(data.created_at) }}</span>
                        </template>
                    </Column>

                    <Column header="Actions">
                        <template #body="{ data }">
                            <div class="flex justify-center">
                                <Button
                                    icon="pi pi-ellipsis-v"
                                    text
                                    rounded
                                    severity="contrast"
                                    class="admin-user-directory-menu-trigger"
                                    aria-label="Open user actions"
                                    @click="toggleActionMenu($event, data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm" :class="isDarkMode ? 'text-slate-200' : 'text-slate-600'">
                    Page {{ users.current_page }} of {{ users.last_page }} • {{ users.total }} users
                </p>

                <Paginator
                    :first="firstRecordIndex"
                    :rows="users.per_page"
                    :totalRecords="users.total"
                    template="PrevPageLink PageLinks NextPageLink"
                    @page="handlePageChange"
                />
            </div>
        </section>

        <Menu ref="rowActionMenu" :model="actionMenuItems" popup class="admin-user-directory-menu" />

        <ConfirmDialog
            :open="Boolean(regenerateTarget)"
            title="Send Coach Activation Link"
            confirmText="Send Link"
            :loading="actionLoadingId === regenerateTarget?.id"
            @update:open="regenerateTarget = null"
            @confirm="confirmRegenerateCoachCredentials"
        >
            <div class="space-y-2 text-sm text-slate-700">
                <p>This will email a secure activation link to the selected coach account.</p>
                <p>The coach will set their own password through the link. No temporary password will be generated or shared.</p>
            </div>
        </ConfirmDialog>

        <ConfirmDialog
            :open="Boolean(deactivateTarget)"
            title="Deactivate Account"
            description="Deactivation removes sign-in access while keeping the user’s records, assignments, and historical data intact."
            confirmText="Deactivate"
            confirmVariant="destructive"
            :loading="actionLoadingId === deactivateTarget?.id"
            @update:open="deactivateTarget = null"
            @confirm="confirmDeactivateUser"
        />

        <ConfirmDialog
            :open="Boolean(reactivateTarget)"
            title="Reactivate Account"
            description="This restores sign-in access and leaves all existing records and relationships untouched."
            confirmText="Reactivate"
            :loading="actionLoadingId === reactivateTarget?.id"
            @update:open="reactivateTarget = null"
            @confirm="confirmReactivateUser"
        />

        <ConfirmDialog
            :open="Boolean(deleteTarget)"
            title="Delete Account Permanently"
            description="Use permanent deletion only for mistaken or test accounts that do not need to preserve linked records."
            confirmText="Delete Permanently"
            confirmVariant="destructive"
            :loading="actionLoadingId === deleteTarget?.id"
            @update:open="deleteTarget = null"
            @confirm="confirmDeleteUser"
        />

        <ConfirmDialog
            :open="createCoachOpen"
            title="Create Coach Account"
            confirmText="Create & Send Activation Link"
            :loading="createCoachForm.processing"
            @update:open="(value) => { createCoachOpen = value; if (!value) closeCreateCoach() }"
            @confirm="submitCreateCoach"
        >
            <div class="space-y-4">
                <div class="space-y-1 text-sm text-slate-700">
                    <p>Create a coach account directly from the admin directory.</p>
                    <p>Enter the coach’s profile details and assigned sport. The coach will receive a secure activation link by email and set their own password.</p>
                </div>
                <div class="grid gap-3">
                    <div>
                        <InputText v-model="createCoachForm.first_name" placeholder="First name" class="w-full" :invalid="Boolean(createCoachForm.errors.first_name)" />
                        <Message v-if="createCoachForm.errors.first_name" severity="error" size="small" variant="simple" class="mt-1">{{ createCoachForm.errors.first_name }}</Message>
                    </div>
                    <div>
                        <InputText v-model="createCoachForm.middle_name" placeholder="Middle name" class="w-full" :invalid="Boolean(createCoachForm.errors.middle_name)" />
                        <Message v-if="createCoachForm.errors.middle_name" severity="error" size="small" variant="simple" class="mt-1">{{ createCoachForm.errors.middle_name }}</Message>
                    </div>
                    <div>
                        <InputText v-model="createCoachForm.last_name" placeholder="Last name" class="w-full" :invalid="Boolean(createCoachForm.errors.last_name)" />
                        <Message v-if="createCoachForm.errors.last_name" severity="error" size="small" variant="simple" class="mt-1">{{ createCoachForm.errors.last_name }}</Message>
                    </div>
                    <div>
                        <InputText v-model="createCoachForm.email" type="email" placeholder="Email address" class="w-full" :invalid="Boolean(createCoachForm.errors.email)" />
                        <Message v-if="createCoachForm.errors.email" severity="error" size="small" variant="simple" class="mt-1">{{ createCoachForm.errors.email }}</Message>
                    </div>
                    <div>
                        <Select v-model="createCoachForm.sport_id" :options="sportOptions" optionLabel="label" optionValue="value" class="w-full" :invalid="Boolean(createCoachForm.errors.sport_id)" />
                        <Message v-if="createCoachForm.errors.sport_id" severity="error" size="small" variant="simple" class="mt-1">{{ createCoachForm.errors.sport_id }}</Message>
                    </div>
                    <div>
                        <InputMask v-model="createCoachForm.phone_number" mask="0999-999-9999" placeholder="Phone number" class="w-full" :invalid="Boolean(createCoachForm.errors.phone_number)" />
                        <Message v-if="createCoachForm.errors.phone_number" severity="error" size="small" variant="simple" class="mt-1">{{ createCoachForm.errors.phone_number }}</Message>
                    </div>
                </div>
            </div>
        </ConfirmDialog>

        <ConfirmDialog
            :open="adminInviteOpen"
            title="Invite Administrator"
            confirmText="Send Invite"
            :loading="adminInviteForm.processing"
            @update:open="(value) => { adminInviteOpen = value; if (!value) closeAdminInvite() }"
            @confirm="submitAdminInvite"
        >
            <div class="space-y-4">
                <div class="space-y-1 text-sm text-slate-700">
                    <p>Send an administrator invitation to a new email address.</p>
                    <p>The recipient will receive an email with a secure link to complete setup and join the admin workspace.</p>
                </div>
                <div>
                    <InputText v-model="adminInviteForm.email" type="email" placeholder="Administrator email address" class="w-full" :invalid="Boolean(adminInviteForm.errors.email)" />
                    <Message v-if="adminInviteForm.errors.email" severity="error" size="small" variant="simple" class="mt-1">{{ adminInviteForm.errors.email }}</Message>
                </div>
            </div>
        </ConfirmDialog>
    </div>
</template>

<style scoped>
:deep(.admin-user-directory-table .p-datatable-thead > tr > th) {
    border-color: rgba(147, 197, 253, 0.22) !important;
    background: #034485 !important;
    background-image: none !important;
    color: #ffffff !important;
    box-shadow: none !important;
}

:deep(.admin-user-directory-table .p-datatable-table-container) {
    overflow: hidden;
    border-radius: 1.25rem;
    border: 1px solid rgba(3, 68, 133, 0.14);
}

:deep(.admin-user-directory-table--dark .p-datatable-table-container) {
    border-color: rgba(74, 144, 226, 0.18);
    background: #020617;
}

:deep(.admin-user-directory-table .p-datatable-thead > tr > th.p-sortable-column),
:deep(.admin-user-directory-table .p-datatable-thead > tr > th.p-sortable-column.p-datatable-column-sorted),
:deep(.admin-user-directory-table .p-datatable-thead > tr > th.p-sortable-column:hover),
:deep(.admin-user-directory-table .p-datatable-thead > tr > th.p-sortable-column:focus-within) {
    background: #034485 !important;
    background-image: none !important;
    color: #ffffff !important;
}

:deep(.admin-user-directory-table .p-sortable-column .p-column-header-content),
:deep(.admin-user-directory-table .p-sortable-column .p-column-title) {
    color: #ffffff !important;
}

:deep(.admin-user-directory-table .p-sortable-column .p-sortable-column-icon),
:deep(.admin-user-directory-table .p-sortable-column.p-highlight .p-sortable-column-icon),
:deep(.admin-user-directory-table .p-datatable-thead .p-sortable-column-icon),
:deep(.admin-user-directory-table .p-datatable-thead .p-sortable-column-icon svg),
:deep(.admin-user-directory-table .p-datatable-thead .p-sortable-column-icon path) {
    color: rgba(255, 255, 255, 0.84) !important;
    fill: rgba(255, 255, 255, 0.84) !important;
    stroke: rgba(255, 255, 255, 0.84) !important;
}

:deep(.admin-user-directory-table .p-datatable-thead > tr > th:last-child) {
    text-align: center;
}

:deep(.admin-user-directory-table .p-datatable-thead > tr > th:first-child) {
    border-top-left-radius: 1.25rem;
}

:deep(.admin-user-directory-table .p-datatable-thead > tr > th:last-child) {
    border-top-right-radius: 1.25rem;
}

:deep(.admin-user-directory-table .p-datatable-tbody > tr > td) {
    border-color: rgba(226, 232, 240, 0.9);
    background: #ffffff;
    color: #0f172a;
}

:deep(.admin-user-directory-table--dark .p-datatable-tbody > tr > td) {
    border-color: rgba(51, 65, 85, 0.8);
    background: #020617;
    color: #f8fafc;
}

:deep(.admin-user-directory-table .p-datatable-tbody > tr > td *:not(.pi)) {
    color: inherit;
}

:deep(.admin-user-directory-table .p-datatable-tbody > tr:hover) {
    background: rgba(3, 68, 133, 0.08);
}

:deep(.admin-user-directory-menu-trigger.p-button) {
    color: #034485;
    border: 1px solid rgba(74, 144, 226, 0.22);
    background: #f8fbff;
}

:deep(.admin-user-directory-menu-trigger.p-button:hover) {
    background: #eef5ff;
    border-color: rgba(3, 68, 133, 0.28);
    color: #034485;
}

:deep(.admin-user-directory-menu.p-menu) {
    min-width: 15rem;
    border: 1px solid rgba(74, 144, 226, 0.22);
    background: #ffffff;
    color: #0f172a;
}

:deep(.admin-user-directory-menu .p-menuitem-content) {
    color: #0f172a;
    border-radius: 0.85rem;
}

:deep(.admin-user-directory-menu .p-menuitem-link) {
    color: #0f172a;
}

:deep(.admin-user-directory-menu .p-menuitem-content:hover) {
    background: #eef5ff;
}

:deep(.admin-user-directory-menu .admin-user-directory-delete-item .p-menuitem-link),
:deep(.admin-user-directory-menu .admin-user-directory-delete-item .p-menuitem-icon) {
    color: #f87171;
}

:deep(.p-paginator) {
    background: transparent;
    border: 0;
    color: #475569;
}

:deep(.p-paginator .p-paginator-page),
:deep(.p-paginator .p-paginator-prev),
:deep(.p-paginator .p-paginator-next) {
    color: #475569;
    border-radius: 9999px;
}

:deep(.p-paginator .p-paginator-page.p-highlight) {
    background: #dcecff !important;
    border-color: #bfd8ff !important;
    color: #034485 !important;
    box-shadow: none !important;
}

:deep(.p-paginator .p-paginator-page.p-highlight:hover) {
    background: #c9e1ff !important;
    border-color: #a8cbff !important;
    color: #02376b !important;
}
</style>
