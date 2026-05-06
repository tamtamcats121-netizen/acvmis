<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref } from 'vue'

import BackLinkButton from '@/components/ui/BackLinkButton.vue'
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

type OcrPayload = {
    id: number
    engine: string
    engine_version: string | null
    run_status: string
    mean_confidence: number | null
    processed_at: string | null
    error_message: string | null
    raw_text_preview: string | null
    validation: {
        status: string | null
        summary: string | null
        flags: Array<{ code: string; message: string }>
        checked_at: string | null
    }
    parsed_summary: {
        gwa: number | null
        total_units: number | null
        parser_status: string
        parser_confidence: number | null
    } | null
    interpretation: {
        scale: string
        value_label: string
        status: string | null
        label: string
    }
} | null

type SubmissionRow = {
    document_id: number
    student_id: number
    student_name: string
    student_id_number: string | null
    team_id: number | null
    team_name: string | null
    document_type: string
    uploaded_at: string | null
    notes: string | null
    file_url: string | null
    ocr: OcrPayload
    period: { id: number; school_year: string; term: string } | null
    evaluation: {
        id: number
        gpa: number | null
        status: string | null
        remarks: string | null
        evaluated_at: string | null
        evaluator_name: string | null
        evaluation_source: string | null
        review_required: boolean
    } | null
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
    selectedDocumentId: number | null
}>()

const selectedPeriodId = ref<number | null>(props.selectedPeriodId)
const activeDocumentId = ref<number | null>(props.selectedDocumentId)
const isLoading = ref(false)
const isSaving = ref(false)
const perPage = ref(12)

const filters = reactive({
    search: '',
    status: '',
})

const rowsState = ref<PaginatedPayload<SubmissionRow>>({
    data: [],
    meta: {
        current_page: 1,
        last_page: 1,
        per_page: perPage.value,
        total: 0,
        from: null,
        to: null,
    },
})

const evaluationForm = reactive({
    gpa: '',
    status: '',
    remarks: '',
})

const selectedRow = computed(() =>
    rowsState.value.data.find((row) => row.document_id === activeDocumentId.value) || null,
)

function termLabel(termCode: string) {
    if (termCode === '1st_sem') return '1st Sem'
    if (termCode === '2nd_sem') return '2nd Sem'
    if (termCode === 'summer') return 'Summer'
    return termCode
}

function formatDateTime(value: string | null) {
    if (!value) return '-'
    return new Date(value).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function statusTone(status: string | null | undefined) {
    if (status === 'eligible') return 'bg-emerald-100 text-emerald-700 border border-emerald-200'
    if (status === 'pending_review') return 'bg-amber-100 text-amber-700 border border-amber-200'
    if (status === 'ineligible') return 'bg-rose-100 text-rose-700 border border-rose-200'
    return 'bg-slate-100 text-slate-700 border border-slate-200'
}

function statusLabel(status: string | null | undefined) {
    if (status === 'eligible') return 'Eligible'
    if (status === 'pending_review') return 'Pending Review'
    if (status === 'ineligible') return 'Ineligible'
    return 'Pending'
}

function validationTone(status: string | null | undefined) {
    if (status === 'valid') return 'bg-emerald-100 text-emerald-700 border border-emerald-200'
    if (status === 'manual_review') return 'bg-amber-100 text-amber-700 border border-amber-200'
    if (status === 'pending') return 'bg-sky-100 text-sky-700 border border-sky-200'
    return 'bg-slate-100 text-slate-600 border border-slate-200'
}

function validationLabel(status: string | null | undefined) {
    if (status === 'valid') return 'Ready for evaluation'
    if (status === 'manual_review') return 'Needs manual review'
    if (status === 'pending') return 'Scanning'
    return 'Scan unavailable'
}

function scaleLabel(scale: string | null | undefined) {
    if (scale === 'basic_education') return 'Basic Education'
    if (scale === 'higher_education') return 'Higher Education'
    return 'Unknown scale'
}

function previewFinalStatus(value: string) {
    if (value === '') return 'Pending'

    const numericValue = Number(value)
    if (Number.isNaN(numericValue)) return 'Pending'
    if (numericValue >= 75 && numericValue <= 100) return 'Eligible'
    if (numericValue >= 1 && numericValue <= 3) return 'Eligible'
    if (numericValue >= 5 || (numericValue >= 0 && numericValue < 75)) return 'Ineligible'
    return 'Pending Review'
}

function syncForm(row: SubmissionRow | null) {
    evaluationForm.gpa = row?.evaluation?.gpa != null
        ? String(row.evaluation.gpa)
        : (row?.ocr?.parsed_summary?.gwa != null ? String(row.ocr.parsed_summary.gwa) : '')
    evaluationForm.status = row?.evaluation?.status || row?.ocr?.interpretation?.status || ''
    evaluationForm.remarks = row?.evaluation?.remarks ?? ''
}

function selectRow(row: SubmissionRow) {
    activeDocumentId.value = row.document_id
    syncForm(row)

    router.get('/academics/evaluations', {
        period_id: selectedPeriodId.value,
        document_id: row.document_id,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function buildQuery(page = 1) {
    return {
        period_id: selectedPeriodId.value || undefined,
        search: filters.search.trim() || undefined,
        status: filters.status || undefined,
        per_page: perPage.value,
        page,
    }
}

async function fetchRows(page = 1) {
    isLoading.value = true

    const params = new URLSearchParams()
    Object.entries(buildQuery(page)).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return
        params.set(key, String(value))
    })

    const response = await fetch(`/academics/submissions/records?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })

    isLoading.value = false

    if (!response.ok) return

    const payload = await response.json() as PaginatedPayload<SubmissionRow>
    rowsState.value = payload

    const matchingRow = payload.data.find((row) => row.document_id === activeDocumentId.value)
    const nextSelected = matchingRow || payload.data[0] || null

    activeDocumentId.value = nextSelected?.document_id ?? null
    syncForm(nextSelected)
}

function changePeriod() {
    activeDocumentId.value = null
    router.get('/academics/evaluations', { period_id: selectedPeriodId.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => fetchRows(1),
    })
}

function applyFilters() {
    fetchRows(1)
}

function saveEvaluation() {
    if (!selectedRow.value || !selectedPeriodId.value) return

    isSaving.value = true

    router.post('/academics/evaluate', {
        period_id: selectedPeriodId.value,
        student_id: selectedRow.value.student_id,
        document_id: selectedRow.value.document_id,
        gpa: evaluationForm.gpa ? Number(evaluationForm.gpa) : null,
        status: evaluationForm.status || null,
        remarks: evaluationForm.remarks,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false
        },
        onSuccess: () => {
            fetchRows(rowsState.value.meta.current_page)
        },
    })
}

onMounted(() => {
    fetchRows(1)
})
</script>

<template>
    <Head title="Academic Evaluations" />

    <div class="space-y-5">
        <section class="page-card rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-3">
                    <BackLinkButton href="/academics" label="Back to Academics" />
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Academic Evaluations</p>
                        <h1 class="mt-2 text-2xl font-bold">Academic Evaluations</h1>
                        <p class="mt-2 text-sm text-white/85">
                            Review uploaded grade reports, confirm the OCR result, and save the final eligibility decision here.
                        </p>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-2 sm:w-auto sm:min-w-[260px]">
                    <label class="text-xs font-semibold uppercase tracking-[0.16em] text-white/75">Academic Period</label>
                    <select
                        v-model="selectedPeriodId"
                        class="rounded-2xl border border-white/25 bg-white/10 px-4 py-3 text-sm text-white"
                        @change="changePeriod"
                    >
                        <option :value="null" disabled>Select period</option>
                        <option v-for="period in periods" :key="period.id" :value="period.id" class="text-slate-900">
                            {{ period.school_year }} - {{ termLabel(period.term) }}
                        </option>
                    </select>
                </div>
            </div>
        </section>

        <section class="page-card rounded-3xl border border-[#034485]/25 bg-white p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Evaluation Queue</h2>
                    <p class="mt-1 text-sm text-slate-500">Select a submitted document to review and evaluate.</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_180px_auto]">
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Search student or ID"
                        class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-700"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="filters.status"
                        class="rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-700"
                    >
                        <option value="">All statuses</option>
                        <option value="eligible">Eligible</option>
                        <option value="pending_review">Pending Review</option>
                        <option value="ineligible">Ineligible</option>
                        <option value="pending">Pending Evaluation</option>
                    </select>
                    <button
                        type="button"
                        class="rounded-2xl bg-[#034485] px-4 py-3 text-sm font-semibold text-white hover:bg-[#02386e]"
                        @click="applyFilters"
                    >
                        Search
                    </button>
                </div>
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(320px,0.95fr)_minmax(0,1.35fr)]">
                <section class="space-y-3">
                    <div
                        v-for="row in rowsState.data"
                        :key="row.document_id"
                        class="rounded-3xl border p-4 transition"
                        :class="row.document_id === activeDocumentId
                            ? 'border-[#034485] bg-[#EAF4FF] shadow-sm'
                            : 'border-slate-200 bg-white hover:border-[#034485]/35 hover:shadow-sm'"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-900">{{ row.student_name }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusTone(row.evaluation?.status)">
                                        {{ statusLabel(row.evaluation?.status) }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500">
                                    {{ row.student_id_number || 'No student ID' }}
                                    <span v-if="row.team_name">· {{ row.team_name }}</span>
                                </p>
                                <div class="grid gap-1 text-xs text-slate-600">
                                    <div>Uploaded: <span class="font-semibold text-slate-800">{{ formatDateTime(row.uploaded_at) }}</span></div>
                                    <div>Document: <span class="font-semibold text-slate-800">{{ row.document_type }}</span></div>
                                    <div>Detected GPA / Average: <span class="font-semibold text-slate-800">{{ row.ocr?.parsed_summary?.gwa ?? '-' }}</span></div>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="rounded-2xl border border-[#034485]/20 bg-white px-4 py-2 text-xs font-semibold text-[#034485] hover:bg-[#034485]/5"
                                @click="selectRow(row)"
                            >
                                {{ row.document_id === activeDocumentId ? 'Selected' : 'Review Submission' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="!rowsState.data.length" class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                        No academic submissions found for this period.
                    </div>

                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <p>
                            Showing {{ rowsState.meta.from || 0 }}-{{ rowsState.meta.to || 0 }} of {{ rowsState.meta.total }}
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50 disabled:opacity-40"
                                :disabled="rowsState.meta.current_page <= 1 || isLoading"
                                @click="fetchRows(rowsState.meta.current_page - 1)"
                            >
                                Previous
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50 disabled:opacity-40"
                                :disabled="rowsState.meta.current_page >= rowsState.meta.last_page || isLoading"
                                @click="fetchRows(rowsState.meta.current_page + 1)"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </section>

                <section v-if="selectedRow" class="space-y-4 rounded-3xl border border-[#034485]/25 bg-slate-50/70 p-4 sm:p-5">
                    <div class="rounded-3xl border border-[#034485]/20 bg-white p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Student Details</p>
                                <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ selectedRow.student_name }}</h2>
                                <div class="mt-2 grid gap-1 text-sm text-slate-600">
                                    <div>Student ID: <span class="font-medium text-slate-800">{{ selectedRow.student_id_number || '-' }}</span></div>
                                    <div>Team: <span class="font-medium text-slate-800">{{ selectedRow.team_name || '-' }}</span></div>
                                    <div>Period: <span class="font-medium text-slate-800">{{ selectedRow.period ? `${selectedRow.period.school_year} - ${termLabel(selectedRow.period.term)}` : '-' }}</span></div>
                                </div>
                            </div>
                            <a
                                v-if="selectedRow.file_url"
                                :href="selectedRow.file_url"
                                target="_blank"
                                class="inline-flex items-center rounded-2xl border border-[#034485]/20 bg-[#034485]/5 px-4 py-2 text-xs font-semibold text-[#034485] hover:bg-[#034485]/10"
                            >
                                View File
                            </a>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-3xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Submission</p>
                            <div class="mt-3 grid gap-2 text-sm text-slate-600">
                                <div>Document type: <span class="font-medium text-slate-800">{{ selectedRow.document_type }}</span></div>
                                <div>Uploaded at: <span class="font-medium text-slate-800">{{ formatDateTime(selectedRow.uploaded_at) }}</span></div>
                                <div>Notes: <span class="font-medium text-slate-800">{{ selectedRow.notes || 'No notes provided.' }}</span></div>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Current Evaluation</p>
                            <div class="mt-3 space-y-2 text-sm text-slate-600">
                                <div class="flex items-center justify-between gap-3">
                                    <span>Status</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusTone(selectedRow.evaluation?.status)">
                                        {{ statusLabel(selectedRow.evaluation?.status) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span>GPA / Average</span>
                                    <span class="font-medium text-slate-800">{{ selectedRow.evaluation?.gpa ?? selectedRow.ocr?.parsed_summary?.gwa ?? '-' }}</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span>Evaluated by</span>
                                    <span class="max-w-[14rem] text-right font-medium text-slate-800">{{ selectedRow.evaluation?.evaluator_name || '-' }}</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span>Updated</span>
                                    <span class="max-w-[14rem] text-right font-medium text-slate-800">{{ formatDateTime(selectedRow.evaluation?.evaluated_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">OCR Result Summary</p>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="validationTone(selectedRow.ocr?.validation?.status)">
                                {{ validationLabel(selectedRow.ocr?.validation?.status) }}
                            </span>
                        </div>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 text-sm text-slate-600">
                                <div>Extracted GPA / Average: <span class="font-semibold text-slate-800">{{ selectedRow.ocr?.parsed_summary?.gwa ?? '-' }}</span></div>
                                <div class="mt-1">Detected scale: <span class="font-semibold text-slate-800">{{ scaleLabel(selectedRow.ocr?.interpretation?.scale) }}</span></div>
                                <div class="mt-1">Interpretation: <span class="font-semibold text-slate-800">{{ selectedRow.ocr?.interpretation?.label || '-' }}</span></div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 text-sm text-slate-600">
                                <div>OCR confidence: <span class="font-semibold text-slate-800">{{ selectedRow.ocr?.mean_confidence ?? '-' }}</span></div>
                                <div class="mt-1">Parser confidence: <span class="font-semibold text-slate-800">{{ selectedRow.ocr?.parsed_summary?.parser_confidence ?? '-' }}</span></div>
                                <div class="mt-1">Processed at: <span class="font-semibold text-slate-800">{{ formatDateTime(selectedRow.ocr?.processed_at) }}</span></div>
                            </div>
                        </div>
                        <div v-if="selectedRow.ocr?.validation?.summary" class="mt-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-3 text-sm text-slate-600">
                            {{ selectedRow.ocr.validation.summary }}
                        </div>
                        <div v-if="selectedRow.ocr?.validation?.flags?.length" class="mt-3 space-y-2 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                            <div v-for="flag in selectedRow.ocr.validation.flags" :key="`${selectedRow.document_id}-${flag.code}`">
                                {{ flag.message }}
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-[#034485]/25 bg-white p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Final Decision</p>
                                <p class="mt-1 text-sm text-slate-500">Save the official eligibility result for this submission.</p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusTone(selectedRow.evaluation?.status)">
                                {{ statusLabel(selectedRow.evaluation?.status) }}
                            </span>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="text-sm font-medium text-slate-700">GPA / General Average</span>
                                <input
                                    v-model="evaluationForm.gpa"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-700"
                                />
                            </label>
                            <label class="space-y-2">
                                <span class="text-sm font-medium text-slate-700">Final Status</span>
                                <select
                                    v-model="evaluationForm.status"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-700"
                                >
                                    <option value="">Use computed status</option>
                                    <option value="eligible">Eligible</option>
                                    <option value="pending_review">Pending Review</option>
                                    <option value="ineligible">Ineligible</option>
                                </select>
                                <p class="text-xs text-slate-500">
                                    {{ evaluationForm.status ? `Selected: ${statusLabel(evaluationForm.status)}` : `Computed: ${previewFinalStatus(evaluationForm.gpa)}` }}
                                </p>
                            </label>
                        </div>

                        <label class="mt-4 block space-y-2">
                            <span class="text-sm font-medium text-slate-700">Remarks</span>
                            <textarea
                                v-model="evaluationForm.remarks"
                                rows="4"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-700"
                                placeholder="Enter remarks for the student record."
                            />
                        </label>

                        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-slate-500">
                                Saving this decision updates <code>academic_eligibility_evaluations</code> and the student's academic result.
                            </p>
                            <button
                                type="button"
                                class="rounded-2xl bg-[#034485] px-5 py-3 text-sm font-semibold text-white hover:bg-[#02386e] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="isSaving"
                                @click="saveEvaluation"
                            >
                                {{ isSaving ? 'Saving...' : 'Save Evaluation' }}
                            </button>
                        </div>
                    </div>
                </section>

                <section v-else class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500">
                    Select a submission from the queue to review its OCR summary and save the evaluation.
                </section>
            </div>
        </section>
    </div>
</template>
