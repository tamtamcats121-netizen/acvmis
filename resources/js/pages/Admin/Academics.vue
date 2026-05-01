<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref, watch } from 'vue'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { showAppToast } from '@/composables/useAppToast'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type Period = {
    id: number
    school_year: string
    term: string
    starts_on: string
    ends_on: string
    status: 'draft' | 'open' | 'closed'
}

type EvaluationsRow = {
    evaluation_id: number
    student_id: number
    student_name: string
    student_id_number: string | null
    period: { id: number; school_year: string; term: string } | null
    document_id: number | null
    document_type: string | null
    gpa: number | null
    status: string | null
    remarks: string | null
    evaluated_at: string | null
    evaluator_name: string | null
}

type PaginatedPayload<T> = {
    data: T[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number | null
        to: number | null
    }
}

const props = defineProps<{
    periods: Period[]
    selectedPeriodId: number | null
}>()

const selectedPeriodId = ref<number | null>(props.selectedPeriodId)
const isLoading = ref(false)
const showFilters = ref(false)
const periodSaving = ref(false)
const deletePeriodDialogOpen = ref(false)
const auditDialogOpen = ref(false)
const auditNote = ref('')
const pendingEvaluation = ref<EvaluationsRow | null>(null)
const periodErrors = ref<Record<string, string>>({})
const noticeDialog = ref<{ open: boolean; title: string; description: string }>({
    open: false,
    title: '',
    description: '',
})
const schoolYear = ref('')
const term = ref<'1st_sem' | '2nd_sem' | 'summer'>('1st_sem')
const startsOn = ref('')
const endsOn = ref('')

const filterForm = reactive({
    search: '',
    status: '',
    start_date: '',
    end_date: '',
    per_page: '15',
})

const evaluationsState = ref<PaginatedPayload<EvaluationsRow>>({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null },
})

const selectedPeriod = computed(() =>
    (props.periods || []).find((p) => p.id === selectedPeriodId.value) || null,
)

const activeFilterCount = computed(() => {
    let count = 0
    if (filterForm.search.trim()) count++
    if (filterForm.status) count++
    if (filterForm.start_date || filterForm.end_date) count++
    if (filterForm.per_page !== '15') count++
    return count
})

const activeQuickRange = computed<'all' | 'today' | 'week' | 'month'>(() => {
    const start = filterForm.start_date
    const end = filterForm.end_date

    if (!start && !end) return 'all'

    const now = new Date()
    const toIsoDate = (value: Date) => {
        const yyyy = value.getFullYear()
        const mm = String(value.getMonth() + 1).padStart(2, '0')
        const dd = String(value.getDate()).padStart(2, '0')
        return `${yyyy}-${mm}-${dd}`
    }

    const today = toIsoDate(now)
    if (start === today && end === today) return 'today'

    const weekStart = new Date(now)
    const day = weekStart.getDay()
    const deltaToMonday = day === 0 ? 6 : day - 1
    weekStart.setDate(weekStart.getDate() - deltaToMonday)
    const weekEnd = new Date(weekStart)
    weekEnd.setDate(weekStart.getDate() + 6)
    if (start === toIsoDate(weekStart) && end === toIsoDate(weekEnd)) return 'week'

    const monthStart = new Date(now)
    monthStart.setDate(1)
    const monthEnd = new Date(now)
    monthEnd.setMonth(monthEnd.getMonth() + 1, 0)
    if (start === toIsoDate(monthStart) && end === toIsoDate(monthEnd)) return 'month'

    return 'all'
})

watch(
    () => props.selectedPeriodId,
    (value) => {
        selectedPeriodId.value = value ?? null
    },
)

watch(
    () => props.periods,
    (periods) => {
        if (!selectedPeriodId.value && periods.length) {
            selectedPeriodId.value = periods[0].id
        }
    },
    { deep: true },
)


function termLabel(termCode: string) {
    if (termCode === '1st_sem') return '1st Sem'
    if (termCode === '2nd_sem') return '2nd Sem'
    if (termCode === 'summer') return 'Summer'
    return termCode
}

function formatDateTime(dt: string | null) {
    if (!dt) return '-'
    return new Date(dt).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function periodStatusLabel(status: string | null) {
    const normalized = String(status ?? '').toLowerCase()
    if (normalized === 'open') return 'Open'
    if (normalized === 'draft') return 'Draft'
    if (normalized === 'closed') return 'Closed'
    return 'Closed'
}

function periodStatusTone(status: string | null) {
    const normalized = String(status ?? '').toLowerCase()
    if (normalized === 'open') return 'bg-emerald-100 text-emerald-700'
    if (normalized === 'draft') return 'bg-slate-100 text-slate-600'
    return 'bg-rose-100 text-rose-700'
}

function evaluationLabel(status: string | null) {
    const normalized = String(status ?? '').toLowerCase()
    if (normalized === 'eligible') return 'Eligible'
    if (normalized === 'pending_review') return 'Pending Review'
    if (normalized === 'ineligible') return 'Ineligible'
    return 'Pending'
}

function evaluationTone(status: string | null) {
    const normalized = String(status ?? '').toLowerCase()
    if (normalized === 'eligible') return 'bg-emerald-100 text-emerald-700'
    if (normalized === 'pending_review') return 'bg-amber-100 text-amber-700'
    if (normalized === 'ineligible') return 'bg-rose-100 text-rose-700'
    return 'bg-slate-100 text-slate-600'
}

function buildQuery(page = 1) {
    return {
        period_id: selectedPeriodId.value || undefined,
        search: filterForm.search.trim() || undefined,
        status: filterForm.status || undefined,
        start_date: filterForm.start_date || undefined,
        end_date: filterForm.end_date || undefined,
        per_page: filterForm.per_page,
        page,
    }
}

function applyQuickPeriod(period: 'today' | 'week' | 'month') {
    const now = new Date()
    const start = new Date(now)
    const end = new Date(now)

    if (period === 'week') {
        const day = start.getDay()
        const deltaToMonday = day === 0 ? 6 : day - 1
        start.setDate(start.getDate() - deltaToMonday)
        end.setDate(start.getDate() + 6)
    }

    if (period === 'month') {
        start.setDate(1)
        end.setMonth(end.getMonth() + 1, 0)
    }

    const toIsoDate = (value: Date) => {
        const yyyy = value.getFullYear()
        const mm = String(value.getMonth() + 1).padStart(2, '0')
        const dd = String(value.getDate()).padStart(2, '0')
        return `${yyyy}-${mm}-${dd}`
    }

    filterForm.start_date = toIsoDate(start)
    filterForm.end_date = toIsoDate(end)
    fetchEvaluations(1)
}

function clearQuickDates() {
    filterForm.start_date = ''
    filterForm.end_date = ''
    fetchEvaluations(1)
}

function createPeriod() {
    periodSaving.value = true
    periodErrors.value = {}

    router.post('/academics/periods', {
        school_year: schoolYear.value,
        term: term.value,
        starts_on: startsOn.value,
        ends_on: endsOn.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            schoolYear.value = ''
            term.value = '1st_sem'
            startsOn.value = ''
            endsOn.value = ''
            periodErrors.value = {}
            selectedPeriodId.value = props.periods?.[0]?.id ?? selectedPeriodId.value
            showAppToast('The new period is now available in the active period panel.', 'success', {
                summary: 'Academic Period Created',
            })
        },
        onError: (errors) => {
            periodErrors.value = Object.fromEntries(
                Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '')]),
            )

            const firstError = Object.values(periodErrors.value).find((value) => value.trim() !== '')
            showAppToast(firstError || 'Please review the academic period details and try again.', 'error', {
                summary: 'Unable to Create Period',
            })
        },
        onFinish: () => {
            periodSaving.value = false
        },
    })
}

function openDeletePeriod() {
    if (!selectedPeriod.value) return
    deletePeriodDialogOpen.value = true
}

function confirmDeletePeriod() {
    if (!selectedPeriod.value) return
    const periodId = selectedPeriod.value.id
    deletePeriodDialogOpen.value = false

    router.delete(`/academics/periods/${periodId}`, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast('Academic period deleted successfully.', 'success', {
                summary: 'Period Removed',
            })
            noticeDialog.value = {
                open: true,
                title: 'Period removed',
                description: 'Academic period deleted successfully.',
            }
            router.get('/academics', {}, { preserveScroll: true })
        },
        onError: () => {
            showAppToast('This period cannot be deleted once it has submissions.', 'error', {
                summary: 'Delete Blocked',
            })
            noticeDialog.value = {
                open: true,
                title: 'Delete blocked',
                description: 'This period cannot be deleted once it has submissions.',
            }
        },
    })
}

async function updateEvaluation(row: EvaluationsRow) {
    if (!row.period?.id || !row.document_id) {
        noticeDialog.value = {
            open: true,
            title: 'Update Blocked',
            description: 'Cannot update evaluation without linked period and document.',
        }
        return
    }
    pendingEvaluation.value = row
    auditNote.value = ''
    auditDialogOpen.value = true
}

async function confirmEvaluationUpdate() {
    const row = pendingEvaluation.value
    if (!row?.period?.id || !row.document_id) return

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    const response = await fetch(`/academics/evaluations/${row.student_id}/${row.period.id}`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken ?? '',
        },
        body: JSON.stringify({
            document_id: row.document_id,
            gpa: row.gpa,
            remarks: row.remarks,
            audit_note: auditNote.value.trim() || null,
        }),
    })

    if (!response.ok) {
        noticeDialog.value = {
            open: true,
            title: 'Save Failed',
            description: 'Unable to update evaluation.',
        }
        return
    }

    auditDialogOpen.value = false
    pendingEvaluation.value = null
    fetchEvaluations(evaluationsState.value.meta.current_page)
}

async function fetchEvaluations(page = 1) {
    isLoading.value = true

    const params = new URLSearchParams()
    Object.entries(buildQuery(page)).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return
        params.set(key, String(value))
    })

    const response = await fetch(`/academics/evaluations/records?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })

    isLoading.value = false

    if (!response.ok) return

    const payload = await response.json() as PaginatedPayload<EvaluationsRow>
    evaluationsState.value = payload
}

function deadlineCountdown(endsOn: string | null) {
    if (!endsOn) return 'No deadline'
    const now = new Date()
    const end = new Date(endsOn + 'T23:59:59')
    const diffMs = end.getTime() - now.getTime()
    if (diffMs < 0) return 'Deadline passed'
    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24))
    if (days === 0) return 'Deadline today'
    if (days === 1) return '1 day left'
    return `${days} days left`
}

function resetFilters() {
    filterForm.search = ''
    filterForm.status = ''
    filterForm.start_date = ''
    filterForm.end_date = ''
    filterForm.per_page = '15'
    fetchEvaluations(1)
}

function changePeriod() {
    router.get('/academics', { period_id: selectedPeriodId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            fetchEvaluations(1)
        },
    })
}

function quickRangeClass(range: 'all' | 'today' | 'week' | 'month') {
    return activeQuickRange.value === range
        ? 'border border-white/40 bg-white text-[#034485]'
        : 'border border-[#034485]/20 bg-white/10 text-white/85 hover:bg-white/20'
}

onMounted(() => {
    fetchEvaluations(1)
})

</script>

<template>
    <Head title="Academics Workspace" />

    <div class="space-y-5">
        <section class="page-card rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Academic Oversight</p>
                    <h1 class="mt-2 text-2xl font-bold">Academics Workspace</h1>
                    <p class="mt-2 text-sm text-white/85">Period settings and evaluations queue for academic compliance.</p>
                </div>
            </div>
        </section>

        <section class="page-card rounded-3xl border border-[#034485]/35 bg-white p-5">
            <section class="page-card space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-800">Period Management</h2>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        @click="router.get('/academics/past-periods')"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 12a9 9 0 1 0 9-9" />
                            <path d="M3 4v6h6" />
                            <path d="M12 7v5l3 3" />
                        </svg>
                        Past Periods
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="rounded-2xl border border-[#034485]/35 bg-white p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-800">Active Period</h2>
                        </div>
                        <select
                            v-if="periods.length"
                            v-model="selectedPeriodId"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm sm:w-[240px]"
                            @change="changePeriod"
                        >
                            <option v-for="p in periods" :key="p.id" :value="p.id">
                                {{ p.school_year }} - {{ termLabel(p.term) }}
                            </option>
                        </select>
                        <button
                            v-if="selectedPeriod"
                            type="button"
                            class="rounded-full border border-rose-200 bg-rose-500 px-2 py-0.5 text-xs font-semibold text-white transition hover:bg-rose-600"
                            title="Delete period"
                            @click="openDeletePeriod"
                        >
                            X
                        </button>
                    </div>
                    <div v-if="selectedPeriod" class="mt-3 space-y-3 rounded-2xl border border-[#034485]/20 bg-[#034485]/5 p-3 text-sm text-slate-700">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-[#034485] px-2 py-0.5 text-xs font-semibold text-white">Active Period</span>
                            <span class="font-semibold text-slate-900">{{ selectedPeriod.school_year }} - {{ termLabel(selectedPeriod.term) }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="periodStatusTone(selectedPeriod.status)">
                                {{ periodStatusLabel(selectedPeriod.status).toUpperCase() }}
                            </span>
                            <span class="ml-auto text-xs text-slate-500">Deadline: {{ deadlineCountdown(selectedPeriod.ends_on) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs text-slate-500">Status is auto-calculated from the date window.</span>
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                @click="router.get('/academics/submissions', { period_id: selectedPeriod.id })"
                            >
                                Academic Submissions
                            </button>
                        </div>
                    </div>
                    <div v-else class="mt-3 rounded-2xl border border-dashed border-[#034485]/25 bg-[#034485]/5 p-4 text-sm text-slate-500">
                        Select a period to view its status and controls.
                    </div>
                </div>

                <div class="rounded-2xl border border-[#034485]/35 bg-white p-4">
                    <h2 class="mb-3 text-sm font-semibold text-slate-800">Create Academic Period</h2>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-5">
                        <div>
                            <input v-model="schoolYear" placeholder="2026-2027" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm" />
                            <p v-if="periodErrors.school_year" class="mt-1 text-xs text-rose-600">{{ periodErrors.school_year }}</p>
                        </div>
                        <div>
                        <select v-model="term" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                            <option value="1st_sem">1st Sem</option>
                            <option value="2nd_sem">2nd Sem</option>
                            <option value="summer">Summer</option>
                        </select>
                            <p v-if="periodErrors.term" class="mt-1 text-xs text-rose-600">{{ periodErrors.term }}</p>
                        </div>
                        <div>
                            <input v-model="startsOn" type="date" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm" />
                            <p v-if="periodErrors.starts_on" class="mt-1 text-xs text-rose-600">{{ periodErrors.starts_on }}</p>
                        </div>
                        <div>
                            <input v-model="endsOn" type="date" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm" />
                            <p v-if="periodErrors.ends_on" class="mt-1 text-xs text-rose-600">{{ periodErrors.ends_on }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-md bg-[#1f2937] px-3 py-2 text-sm font-semibold text-white hover:bg-[#334155] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="periodSaving"
                            @click="createPeriod"
                        >
                            {{ periodSaving ? 'Creating...' : 'Create' }}
                        </button>
                    </div>
                </div>
                </div>

            </section>

            <section class="page-card mt-6 space-y-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">Evaluations</h2>
                    <p class="mt-1 text-sm text-slate-500">Search and review the evaluation records for the selected academic period.</p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="Search student, ID, document"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm sm:flex-1"
                        @keyup.enter="fetchEvaluations(1)"
                    />
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="fetchEvaluations(1)">
                        Search
                    </button>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="showFilters = !showFilters">
                        Filters <span v-if="activeFilterCount" class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs">{{ activeFilterCount }}</span>
                    </button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="quick-range-btn rounded-full px-3 py-1 text-xs font-medium" :class="quickRangeClass('all')" @click="clearQuickDates">All Time</button>
                    <button type="button" class="quick-range-btn rounded-full px-3 py-1 text-xs font-medium" :class="quickRangeClass('today')" @click="applyQuickPeriod('today')">Today</button>
                    <button type="button" class="quick-range-btn rounded-full px-3 py-1 text-xs font-medium" :class="quickRangeClass('week')" @click="applyQuickPeriod('week')">This Week</button>
                    <button type="button" class="quick-range-btn rounded-full px-3 py-1 text-xs font-medium" :class="quickRangeClass('month')" @click="applyQuickPeriod('month')">This Month</button>
                </div>

                <div v-if="showFilters" class="grid grid-cols-1 gap-3 border-t border-slate-200 pt-3 md:grid-cols-2 lg:grid-cols-4">
                    <select v-model="filterForm.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Statuses</option>
                        <option value="eligible">Eligible</option>
                        <option value="pending_review">Pending Review</option>
                        <option value="ineligible">Ineligible</option>
                        <option value="pending">Pending Evaluation</option>
                    </select>

                    <input v-model="filterForm.start_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="filterForm.end_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />

                    <div class="grid grid-cols-3 gap-2">
                        <select v-model="filterForm.per_page" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100" @click="fetchEvaluations(1)">Apply</button>
                        <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100" @click="resetFilters">Reset</button>
                    </div>
                </div>

                <section class="page-card overflow-hidden rounded-2xl border border-[#034485]/35 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-[#034485] text-white">
                            <tr>
                                <th class="px-4 py-3 text-left">Athlete</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">GPA</th>
                                <th class="px-4 py-3 text-left">Remarks</th>
                                <th class="px-4 py-3 text-left">Updated</th>
                                <th class="px-4 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <transition-group name="table-fade" tag="tbody">
                            <tr v-for="row in evaluationsState.data" :key="row.evaluation_id" class="border-t border-slate-200 text-slate-700 align-top">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ row.student_name }}</div>
                                    <div class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="evaluationTone(row.status)">
                                        {{ evaluationLabel(row.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <input v-model.number="row.gpa" type="number" step="0.01" min="0" max="100" class="w-24 rounded-md border border-slate-300 px-2 py-1.5" />
                                </td>
                                <td class="px-4 py-3">
                                    <input v-model="row.remarks" type="text" class="w-full rounded-md border border-slate-300 px-2 py-1.5" />
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ formatDateTime(row.evaluated_at) }}</div>
                                    <div class="text-xs text-slate-500">{{ row.evaluator_name || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-100" @click="updateEvaluation(row)">Update</button>
                                </td>
                            </tr>
                            <tr v-if="evaluationsState.data.length === 0" key="empty">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No evaluation records found.</td>
                            </tr>
                        </transition-group>
                    </table>
                </div>
                </section>

            <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
                <p>
                    Showing
                    {{ evaluationsState.meta.from || 0 }}-
                    {{ evaluationsState.meta.to || 0 }}
                    of
                    {{ evaluationsState.meta.total }}
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                        :disabled="evaluationsState.meta.current_page <= 1 || isLoading"
                        @click="fetchEvaluations(evaluationsState.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                        :disabled="evaluationsState.meta.current_page >= evaluationsState.meta.last_page || isLoading"
                        @click="fetchEvaluations(evaluationsState.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
            </section>
        </section>

        <ConfirmDialog
            :open="auditDialogOpen"
            title="Update Evaluation"
            description="Add an optional audit note for this admin update."
            confirm-text="Save Update"
            @update:open="auditDialogOpen = $event"
            @confirm="confirmEvaluationUpdate"
        >
            <label class="mb-1 block text-xs font-medium text-slate-600">Audit Note (Optional)</label>
            <textarea
                v-model="auditNote"
                rows="3"
                class="w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm"
                placeholder="Reason or context for this change"
            />
        </ConfirmDialog>

        <ConfirmDialog
            :open="noticeDialog.open"
            :title="noticeDialog.title"
            :description="noticeDialog.description"
            confirm-text="OK"
            :show-cancel="false"
            @update:open="noticeDialog.open = $event"
            @confirm="noticeDialog.open = false"
        />

        <ConfirmDialog
            :open="deletePeriodDialogOpen"
            title="Delete Academic Period"
            description="Delete this period? This is only allowed if there are no submissions."
            confirm-text="Delete Period"
            confirm-variant="destructive"
            @update:open="deletePeriodDialogOpen = $event"
            @confirm="confirmDeletePeriod"
        />

    </div>
</template>

<style scoped>
.table-fade-enter-active,
.table-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.table-fade-enter-from,
.table-fade-leave-to {
    opacity: 0;
    transform: translateY(6px);
}

.table-fade-move {
    transition: transform 0.2s ease;
}

</style>
