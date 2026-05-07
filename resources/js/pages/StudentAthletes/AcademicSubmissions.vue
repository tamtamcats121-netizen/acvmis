<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { showAppToast } from '@/composables/useAppToast'
import Spinner from '@/components/ui/spinner/Spinner.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'

defineOptions({
    layout: StudentAthleteDashboard,
})

type Period = {
    id: number
    school_year: string
    term: string
    starts_on: string
    ends_on: string
    eligibility_status?: string | null
    is_eligible?: boolean
    can_submit?: boolean
}

type Submission = {
    id: number
    period_id: number | null
    period_label: string | null
    document_type: string
    file_url: string | null
    uploaded_at: string | null
    notes: string | null
    ocr: {
        id: number
        run_status: string
        processed_at: string | null
        error_message: string | null
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
        validation: {
            status: string | null
            summary: string | null
            flags: Array<{ code: string; message: string }>
            checked_at: string | null
        }
    } | null
    evaluation: {
        gpa: number | null
        status: string
        remarks: string | null
        review_required?: boolean
        scale_mismatch?: boolean
        mismatch_message?: string | null
        evaluated_at: string | null
    } | null
}

const props = defineProps<{
    student: {
        id: number
        name: string
        student_id_number: string | null
        course_or_strand?: string | null
        current_grade_level?: string | null
        academic_level_label?: string | null
    } | null
    openPeriods: Period[]
    submissions: Submission[]
    hasActiveWindow?: boolean
    hasSubmittedAll?: boolean
    hasEligibleForActivePeriod?: boolean
    selectedPeriodId?: number
    resultSubmissionId?: number
}>()

const page = usePage()
const academicAccess = computed(() => (page.props.auth as any)?.academic_access ?? null)
const isAcademicallyRestricted = computed(() => Boolean(academicAccess.value?.is_restricted))
const restrictionEvaluation = computed(() => academicAccess.value?.evaluation ?? null)
function isSeniorHighStudent() {
    const academicLevelLabel = String(props.student?.academic_level_label ?? '').toLowerCase()
    if (academicLevelLabel.includes('senior high')) {
        return true
    }

    const gradeLevel = String(props.student?.current_grade_level ?? '').trim()
    return /(^|[^0-9])(11|12)([^0-9]|$)/.test(gradeLevel)
}

const studentMetricLabel = computed(() => (isSeniorHighStudent() ? 'GWA' : 'GPA'))

const isSubmissionModalOpen = ref(false)
const activePeriodId = ref<number | null>(null)
const notes = ref('')
const file = ref<File | null>(null)
const previewUrl = ref('')
const submitError = ref('')
const isSubmitting = ref(false)
const uploadProgress = ref(0)
const isScanning = ref(false)
const processingSubmission = ref<{
    periodLabel: string
    fileName: string
    startedAt: string
} | null>(null)

function parseTime(value: string | null | undefined) {
    if (!value) return 0
    const t = Date.parse(value)
    return Number.isNaN(t) ? 0 : t
}

const sortedSubmissions = computed(() =>
    [...(props.submissions || [])].sort((a, b) => parseTime(b.uploaded_at) - parseTime(a.uploaded_at))
)

const latestSubmission = computed(() => sortedSubmissions.value[0] || null)
const latestEvaluated = computed(() => sortedSubmissions.value.find((row) => row.evaluation) || null)
const displayedSubmissions = computed(() => sortedSubmissions.value)
const selectedPeriod = computed(() => props.openPeriods.find((period) => period.id === activePeriodId.value) ?? null)
const resultSubmission = computed(() =>
    props.resultSubmissionId ? sortedSubmissions.value.find((row) => row.id === props.resultSubmissionId) ?? null : null
)

watch(
    () => file.value,
    (next, prev) => {
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value)
            previewUrl.value = ''
        }

        if (prev && prev !== next && previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value)
        }

        if (next && next.type.startsWith('image/')) {
            previewUrl.value = URL.createObjectURL(next)
        }
    }
)

watch(
    () => props.selectedPeriodId,
    (periodId) => {
        if (!periodId || props.resultSubmissionId) return
        openSubmissionModal(periodId)
    },
    { immediate: true }
)

watch(
    () => props.resultSubmissionId,
    (submissionId, previousId) => {
        if (!submissionId || submissionId === previousId) return

        const row = sortedSubmissions.value.find((item) => item.id === submissionId)
        if (!row) return

        const isProcessed = row.ocr?.validation?.status === 'valid'
        showAppToast(
            isProcessed
                ? 'Academic submission processed successfully.'
                : 'Unable to fully process document. Please review or resubmit.',
            isProcessed ? 'success' : 'error'
        )
    }
)

onBeforeUnmount(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
    }
})

function openSubmissionModal(periodId: number) {
    const period = props.openPeriods.find((item) => item.id === periodId)
    if (!period || period.can_submit === false || period.is_eligible) return

    activePeriodId.value = periodId
    submitError.value = ''
    isSubmissionModalOpen.value = true
}

function closeSubmissionModal() {
    if (isSubmitting.value) return

    resetSubmissionModal()
}

function resetSubmissionModal() {
    isSubmissionModalOpen.value = false
    activePeriodId.value = null
    notes.value = ''
    file.value = null
    submitError.value = ''
    uploadProgress.value = 0
    isScanning.value = false
}

function handleFileChange(event: Event) {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

function removeFile() {
    file.value = null
    showAppToast('Selected file removed.', 'success')
}

function submit() {
    if (isSubmitting.value) return

    submitError.value = ''

    if (!selectedPeriod.value) {
        submitError.value = 'Please choose an active academic period.'
        return
    }

    if (selectedPeriod.value.can_submit === false || selectedPeriod.value.is_eligible) {
        submitError.value = 'You are already eligible for this period. Further submissions are locked.'
        return
    }

    if (!file.value) {
        submitError.value = 'Please attach your academic document before submitting.'
        return
    }

    const submissionPeriodLabel = `${selectedPeriod.value.school_year} - ${termLabel(selectedPeriod.value.term)}`
    const submissionFileName = file.value.name
    const submissionPeriodId = selectedPeriod.value.id

    const fd = new FormData()
    fd.append('academic_period_id', String(selectedPeriod.value.id))
    fd.append('document_type', 'grade_report')
    fd.append('notes', notes.value)
    fd.append('document_file', file.value)

    router.post('/AcademicSubmissions', fd, {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => {
            processingSubmission.value = {
                periodLabel: submissionPeriodLabel,
                fileName: submissionFileName,
                startedAt: new Date().toISOString(),
            }
            resetSubmissionModal()
            isSubmitting.value = true
            isScanning.value = true
            uploadProgress.value = 0
            showAppToast('Uploading academic document...', 'success', {
                summary: 'Upload Started',
            })
        },
        onProgress: (event) => {
            uploadProgress.value = Math.round(event?.percentage ?? 0)
        },
        onError: (errors) => {
            processingSubmission.value = null
            const firstError = Object.values(errors || {})[0]
            submitError.value = Array.isArray(firstError)
                ? String(firstError[0])
                : String(firstError || 'Unable to submit your academic document.')
            activePeriodId.value = submissionPeriodId
            isSubmissionModalOpen.value = true
            showAppToast('Unable to submit your document.', 'error')
        },
        onFinish: () => {
            isSubmitting.value = false
            isScanning.value = false
            uploadProgress.value = 0
        },
    })
}

function docLabel(type: string) {
    if (type === 'grade_report') return 'Grade Report'
    return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function evaluationPill(status: string | null | undefined) {
    const value = String(status ?? '').toLowerCase()
    if (!value) return 'bg-slate-100 text-slate-600 border border-slate-200'
    if (value.includes('eligible')) return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
    if (value.includes('ineligible')) return 'bg-rose-50 text-rose-700 border border-rose-200'
    if (value.includes('pending_review') || value.includes('pending')) return 'bg-amber-50 text-amber-700 border border-amber-200'
    return 'bg-[#034485]/5 text-slate-600 border border-[#034485]/20'
}

function statusPill(row: Submission) {
    const status = row.evaluation?.status ?? row.ocr?.interpretation?.status ?? 'pending_review'
    const normalized = String(status).toLowerCase()

    if (normalized.includes('eligible')) {
        return { label: 'Eligible', class: evaluationPill(status) }
    }

    if (normalized.includes('ineligible')) {
        return { label: 'Ineligible', class: evaluationPill(status) }
    }

    return { label: 'Pending Review', class: evaluationPill(status) }
}

function validationPill(status: string | null | undefined) {
    const value = String(status ?? '').toLowerCase()
    if (value === 'valid') return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
    if (value === 'manual_review') return 'bg-amber-50 text-amber-700 border border-amber-200'
    if (value === 'pending') return 'bg-sky-50 text-sky-700 border border-sky-200'
    return 'bg-slate-100 text-slate-600 border border-slate-200'
}

function validationLabel(status: string | null | undefined) {
    const value = String(status ?? '').toLowerCase()
    if (value === 'valid') return 'Processed'
    if (value === 'manual_review') return 'Needs review'
    if (value === 'pending') return 'Scanning'
    return status || 'Not yet processed'
}

function actionPillStatus(row: Submission | null) {
    if (!row) return 'pending'
    if (row.evaluation?.scale_mismatch || row.evaluation?.review_required) return 'manual_review'
    return row.ocr?.validation?.status ?? 'pending'
}

function termLabel(termCode: string) {
    if (termCode === '1st_sem') return '1st Semester'
    if (termCode === '2nd_sem') return '2nd Semester'
    if (termCode === 'summer') return 'Summer'
    return termCode
}

function formatDate(value: string | null | undefined, options?: Intl.DateTimeFormatOptions) {
    if (!value) return '-'
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return value
    return parsed.toLocaleDateString(undefined, options)
}

function formatDateTime(value: string | null | undefined) {
    if (!value) return '-'
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return value
    return parsed.toLocaleString()
}

function selectedResultValue(row: Submission | null) {
    if (!row) return '-'
    return row.evaluation?.gpa ?? row.ocr?.parsed_summary?.gwa ?? '-'
}

function metricWithFallback() {
    return studentMetricLabel.value || 'Academic Result'
}

function selectedResultLabel(row: Submission | null) {
    if (!row) return 'Pending Review'
    return row.evaluation?.status
        ? row.evaluation.status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
        : row.ocr?.interpretation?.label || 'Pending Review'
}

function nextActionLabel(row: Submission | null) {
    if (!row) return 'Submit your current grade report during an active academic period.'

    if (row.evaluation?.scale_mismatch) {
        return row.evaluation.mismatch_message || 'This academic result does not match the expected scale for your academic level. Please request manual review.'
    }

    const evaluationStatus = String(row.evaluation?.status ?? '').toLowerCase()
    const validationStatus = String(row.ocr?.validation?.status ?? '').toLowerCase()

    if (evaluationStatus.includes('eligible')) {
        return 'No further action is required for this period.'
    }

    if (evaluationStatus.includes('ineligible')) {
        return 'Review the evaluation remarks and resubmit an updated academic file if needed.'
    }

    if (validationStatus === 'manual_review') {
        return 'Wait for the academic evaluation. Your submission needs manual review.'
    }

    if (validationStatus === 'pending') {
        return 'Please wait while the system completes scanning and evaluation.'
    }

    if (validationStatus && validationStatus !== 'valid') {
        return 'Review the file quality and resubmit if the result stays incomplete.'
    }

    return 'Wait for the final academic evaluation to confirm your eligibility.'
}

function summaryStatusLabel(row: Submission | null) {
    if (!row) return 'No submission yet'
    if (row.evaluation?.status) {
        return row.evaluation.status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
    }

    const validationStatus = String(row.ocr?.validation?.status ?? '').toLowerCase()
    if (validationStatus === 'valid') return 'Awaiting final evaluation'
    if (validationStatus === 'manual_review') return 'Needs manual review'
    if (validationStatus === 'pending') return 'Scanning in progress'
    return 'Pending review'
}

function evaluationSummaryText(row: Submission | null) {
    if (!row) return '-'

    if (row.evaluation?.mismatch_message) {
        return row.evaluation.mismatch_message
    }

    return row.evaluation?.remarks || row.ocr?.validation?.summary || 'Your submission is waiting for the final evaluation result.'
}

function cardMotion(order: number) {
    return { '--card-order': String(order) }
}

</script>

<template>
    <Head title="Academic Submissions" />

    <div class="academics-page-view space-y-5">
        <section class="page-card rounded-3xl border border-[#034485]/35 bg-[#034485] p-5 text-white" :style="cardMotion(1)">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Academic Submissions</h1>
                <p class="text-sm text-white/80">
                    {{ isAcademicallyRestricted
                        ? 'Review your current academic standing, upload your latest grade report, and wait for the official eligibility result.'
                        : 'Submit your latest grade report through the guided scan flow.' }}
                </p>
            </div>
        </div>
        </section>

        <div v-if="!student" class="page-card rounded-lg border border-[#034485]/35 bg-white p-4 text-slate-600" :style="cardMotion(2)">
            Student profile not found.
        </div>

        <template v-else>
            <section
                v-if="isAcademicallyRestricted"
                class="page-card overflow-hidden rounded-[28px] border border-amber-200 bg-[linear-gradient(135deg,rgba(255,251,235,0.98),rgba(255,255,255,0.96))] shadow-sm"
                :style="cardMotion(2)"
            >
                <div class="grid gap-4 px-4 py-5 sm:px-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(260px,0.9fr)] lg:items-center">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M12 9v4" />
                                <path d="M12 17h.01" />
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                            </svg>
                        </span>
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-semibold text-slate-900">Varsity access is currently limited</p>
                                <span class="rounded-full border border-amber-200 bg-white/90 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-700">
                                    Academic restriction
                                </span>
                            </div>
                            <p class="max-w-3xl text-sm leading-6 text-slate-600">{{ academicAccess?.message }}</p>
                            <p class="text-sm text-slate-600">
                                Upload your current academic document here. Access to teams and schedules returns only after the active period is submitted and marked eligible.
                            </p>
                        </div>
                    </div>

                    <div class="page-card rounded-3xl border border-amber-200/80 bg-white/90 p-4" :style="cardMotion(3)">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Current evaluation</p>
                        <div class="mt-3 space-y-2 text-sm text-slate-600">
                            <div class="flex items-center justify-between gap-3">
                                <span>Status</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="evaluationPill(restrictionEvaluation?.status)">
                                    {{ restrictionEvaluation?.status ?? 'Ineligible' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Period</span>
                                <span class="text-right font-medium text-slate-800">{{ restrictionEvaluation?.period_label ?? latestEvaluated?.period_label ?? '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ metricWithFallback() }}</span>
                                <span class="font-medium text-slate-800">{{ restrictionEvaluation?.gpa ?? latestEvaluated?.evaluation?.gpa ?? '-' }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <span>Remarks</span>
                                <span class="max-w-[14rem] text-right text-slate-700">{{ restrictionEvaluation?.remarks ?? latestEvaluated?.evaluation?.remarks ?? 'Check the latest scanned result below for more detail.' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                <div class="page-card rounded-3xl border border-[#034485]/35 bg-white p-4" :style="cardMotion(4)">
                    <p class="text-xs text-slate-500">Student</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ student.name }}</p>
                    <div class="mt-2 grid gap-1 text-xs text-slate-500">
                        <div><span class="font-semibold text-slate-700">ID:</span> {{ student.student_id_number || '-' }}</div>
                        <div><span class="font-semibold text-slate-700">Course/Strand:</span> {{ student.course_or_strand || '-' }}</div>
                        <div><span class="font-semibold text-slate-700">Academic Level:</span> {{ student.academic_level_label || student.current_grade_level || '-' }}</div>
                    </div>
                </div>

                <div class="page-card rounded-3xl border border-[#034485]/35 bg-white p-4 lg:col-span-2" :style="cardMotion(5)">
                    <template v-if="latestSubmission">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-slate-500">Latest Academic Record</span>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="statusPill(latestSubmission).class">
                                        {{ statusPill(latestSubmission).label }}
                                    </span>
                                </div>
                                <h2 class="text-lg font-semibold text-slate-900">{{ latestSubmission.period_label || 'Submitted period' }}</h2>
                                <p class="text-sm text-slate-600">
                                    Your uploaded file is saved under this academic period. Review the evaluation result below to confirm whether your submission is already eligible or still needs action.
                                </p>
                            </div>
                            <a
                                v-if="latestSubmission.file_url"
                                :href="latestSubmission.file_url"
                                target="_blank"
                                class="inline-flex items-center rounded-full border border-[#034485]/30 bg-white px-4 py-2 text-xs font-semibold text-[#034485] hover:bg-[#034485]/5"
                            >
                                View submitted file
                            </a>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <div class="page-card rounded-3xl border border-slate-200 bg-slate-50/60 p-4" :style="cardMotion(6)">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Submitted File</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ docLabel(latestSubmission.document_type) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Uploaded {{ formatDateTime(latestSubmission.uploaded_at) }}</p>
                                <p class="mt-3 text-xs text-slate-600">
                                    {{ latestSubmission.notes || 'No additional notes were attached to this submission.' }}
                                </p>
                            </div>
                            <div class="page-card rounded-3xl border border-slate-200 bg-slate-50/60 p-4" :style="cardMotion(7)">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Evaluation Result</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ summaryStatusLabel(latestSubmission) }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ metricWithFallback() }}: <span class="font-semibold text-slate-700">{{ selectedResultValue(latestSubmission) }}</span>
                                </p>
                        <p class="mt-3 text-xs text-slate-600">
                            {{ evaluationSummaryText(latestSubmission) }}
                        </p>
                    </div>
                    <div class="page-card rounded-3xl border border-slate-200 bg-slate-50/60 p-4" :style="cardMotion(8)">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Next Action</p>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="validationPill(actionPillStatus(latestSubmission))">
                            {{ validationLabel(actionPillStatus(latestSubmission)) }}
                        </span>
                        <p class="mt-3 text-xs leading-5 text-slate-600">{{ nextActionLabel(latestSubmission) }}</p>
                                <p class="mt-2 text-xs text-slate-500">Last update: {{ formatDateTime(latestSubmission.evaluation?.evaluated_at || latestSubmission.ocr?.processed_at || latestSubmission.uploaded_at) }}</p>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-xs text-slate-500">Latest Academic Record</p>
                        <p class="mt-2 text-sm text-slate-600">No academic submission is on file yet. Upload your first document during an active period to start evaluation.</p>
                    </template>
                </div>
            </section>

            <section
                v-if="processingSubmission && !resultSubmission"
                class="page-card rounded-[28px] border border-[#034485]/20 bg-[linear-gradient(135deg,rgba(3,68,133,0.08),rgba(255,255,255,0.98))] p-5 shadow-sm"
                :style="cardMotion(9)"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-[#034485] px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-white">
                                Processing Submission
                            </span>
                            <span class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[10px] font-semibold text-sky-700">
                                Scanning
                            </span>
                        </div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ processingSubmission.periodLabel }}</h2>
                        <p class="text-sm text-slate-600">
                            Your upload has been received. Please wait while the system scans the document and interprets the grade result.
                        </p>
                    </div>
                    <div class="page-card rounded-3xl border border-white/70 bg-white/90 px-4 py-3 text-right" :style="cardMotion(10)">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">File</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ processingSubmission.fileName }}</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="page-card rounded-3xl border border-white/70 bg-white/90 p-4" :style="cardMotion(11)">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Stage</p>
                        <div class="mt-3 flex items-center gap-3">
                            <Spinner class="h-5 w-5 text-sky-700" />
                            <p class="text-sm font-semibold text-slate-900">Scanning document...</p>
                        </div>
                    </div>
                    <div class="page-card rounded-3xl border border-white/70 bg-white/90 p-4" :style="cardMotion(12)">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Upload progress</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900">{{ uploadProgress }}%</p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-sky-100">
                            <div class="h-full rounded-full bg-sky-600 transition-all duration-200" :style="{ width: `${uploadProgress}%` }" />
                        </div>
                    </div>
                    <div class="page-card rounded-3xl border border-white/70 bg-white/90 p-4" :style="cardMotion(13)">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Started</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ formatDateTime(processingSubmission.startedAt) }}</p>
                        <p class="mt-2 text-xs text-slate-500">This panel will be replaced automatically once processing is complete.</p>
                    </div>
                </div>
            </section>

            <section
                v-else-if="resultSubmission"
                class="page-card rounded-[28px] border border-[#034485]/20 bg-[linear-gradient(135deg,rgba(3,68,133,0.08),rgba(255,255,255,0.98))] p-5 shadow-sm"
                :style="cardMotion(14)"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-[#034485] px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-white">
                                Latest Academic Record
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="statusPill(resultSubmission).class">
                                {{ statusPill(resultSubmission).label }}
                            </span>
                        </div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ resultSubmission.period_label || 'Submitted period' }}</h2>
                        <p class="text-sm text-slate-600">
                            Your submission was received for this period. Review the evaluation result and next action below.
                        </p>
                    </div>
                    <a
                        v-if="resultSubmission.file_url"
                        :href="resultSubmission.file_url"
                        target="_blank"
                        class="inline-flex items-center rounded-full border border-[#034485]/30 bg-white px-4 py-2 text-xs font-semibold text-[#034485] hover:bg-[#034485]/5"
                    >
                        View submitted file
                    </a>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="page-card rounded-3xl border border-white/70 bg-white/90 p-4" :style="cardMotion(15)">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Submitted File</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ docLabel(resultSubmission.document_type) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Uploaded {{ formatDateTime(resultSubmission.uploaded_at) }}</p>
                        <p class="mt-3 text-xs text-slate-600">
                            {{ resultSubmission.notes || 'No additional notes were attached to this submission.' }}
                        </p>
                    </div>
                    <div class="page-card rounded-3xl border border-white/70 bg-white/90 p-4" :style="cardMotion(16)">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Evaluation Result</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ summaryStatusLabel(resultSubmission) }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ metricWithFallback() }}: <span class="font-semibold text-slate-700">{{ selectedResultValue(resultSubmission) }}</span>
                        </p>
                        <p class="mt-3 text-xs text-slate-600">
                            {{ evaluationSummaryText(resultSubmission) }}
                        </p>
                    </div>
                    <div class="page-card rounded-3xl border border-white/70 bg-white/90 p-4" :style="cardMotion(17)">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Next Action</p>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="validationPill(actionPillStatus(resultSubmission))">
                            {{ validationLabel(actionPillStatus(resultSubmission)) }}
                        </span>
                        <p class="mt-3 text-xs leading-5 text-slate-600">{{ nextActionLabel(resultSubmission) }}</p>
                        <p class="mt-2 text-xs text-slate-500">Last update: {{ formatDateTime(resultSubmission.evaluation?.evaluated_at || resultSubmission.ocr?.processed_at || resultSubmission.uploaded_at) }}</p>
                    </div>
                </div>
            </section>

            <section class="page-card rounded-[28px] border border-[#034485]/25 bg-white p-5 shadow-sm" :style="cardMotion(18)">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Submission Window</h2>
                        <p class="text-xs text-slate-500">Choose an active period to start the guided academic upload flow.</p>
                    </div>
                    <span class="text-xs text-slate-500">{{ openPeriods.length }} open</span>
                </div>

                <div v-if="openPeriods.length === 0" class="mt-4 text-sm text-slate-500">
                    No active submission period is available at this time. Please wait for an administrator announcement.
                </div>

                <div v-else class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <button
                        v-for="(period, index) in openPeriods"
                        :key="period.id"
                        type="button"
                        :disabled="period.can_submit === false || Boolean(period.is_eligible)"
                        @click="openSubmissionModal(period.id)"
                        class="page-card group rounded-[24px] border p-4 text-left transition"
                        :style="cardMotion(19 + index)"
                        :class="period.can_submit === false || period.is_eligible
                            ? 'cursor-not-allowed border-emerald-200 bg-emerald-50/80 opacity-85'
                            : 'border-[#034485]/20 bg-[linear-gradient(135deg,#034485,#0b5cab)] text-white hover:-translate-y-0.5 hover:shadow-lg'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold" :class="period.can_submit === false || period.is_eligible ? 'text-emerald-900' : 'text-white'">
                                    {{ period.school_year }} - {{ termLabel(period.term) }}
                                </p>
                                <p class="mt-1 text-xs" :class="period.can_submit === false || period.is_eligible ? 'text-emerald-700' : 'text-white/80'">
                                    Window: {{ formatDate(period.starts_on, { month: 'short', day: 'numeric' }) }} - {{ formatDate(period.ends_on, { month: 'short', day: 'numeric' }) }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-[10px] font-semibold"
                                :class="period.can_submit === false || period.is_eligible
                                    ? 'bg-white text-emerald-700'
                                    : 'bg-white/15 text-white'"
                            >
                                {{ period.is_eligible ? 'Eligible' : 'Open' }}
                            </span>
                        </div>
                        <p class="mt-4 text-xs font-semibold" :class="period.can_submit === false || period.is_eligible ? 'text-emerald-800' : 'text-white'">
                            {{ period.can_submit === false || period.is_eligible ? 'Eligibility already confirmed for this period' : 'Click to upload and scan your grade report' }}
                        </p>
                    </button>
                </div>

                <div
                    v-if="hasActiveWindow"
                    class="page-card mt-4 rounded-2xl border border-[#034485]/15 bg-[#034485]/5 px-4 py-3 text-xs text-slate-600"
                    :style="cardMotion(30)"
                >
                    <span class="font-semibold text-slate-700">Current status:</span>
                    <span class="ml-2 rounded-full bg-[#034485] px-2 py-0.5 text-[10px] font-semibold text-white">
                        {{ !isAcademicallyRestricted ? 'Eligible' : (restrictionEvaluation?.status || academicAccess?.status || 'Limited') }}
                    </span>
                    <span class="ml-2">
                        {{
                            !isAcademicallyRestricted
                                ? 'Access to varsity modules is currently restored.'
                                : hasSubmittedAll
                                    ? 'Your file is already on record. Varsity access stays paused until the active period is marked eligible.'
                                    : 'Teams and schedules stay paused until you submit and receive an eligible result.'
                        }}
                    </span>
                </div>
            </section>

            <section v-if="displayedSubmissions.length > 0" class="grid grid-cols-1 gap-3 md:hidden">
                <div v-for="(row, index) in displayedSubmissions" :key="row.id" class="page-card rounded-2xl border border-[#034485]/20 bg-white p-4 shadow-sm" :style="cardMotion(31 + index)">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ row.period_label || 'Unknown period' }}</p>
                            <p class="text-xs text-slate-500">{{ formatDateTime(row.uploaded_at) }}</p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusPill(row).class">
                            {{ statusPill(row).label }}
                        </span>
                    </div>
                    <div class="mt-3 grid gap-3 text-xs text-slate-500">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">Submitted File</p>
                            <div class="mt-2">Document: <span class="font-semibold text-slate-700">{{ docLabel(row.document_type) }}</span></div>
                            <div class="mt-1">{{ row.notes || 'No notes attached.' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">Evaluation Result</p>
                            <div class="mt-2">Status: <span class="font-semibold text-slate-700">{{ summaryStatusLabel(row) }}</span></div>
                            <div class="mt-1">{{ metricWithFallback() }}: <span class="font-semibold text-slate-700">{{ selectedResultValue(row) }}</span></div>
                            <div class="mt-1">{{ evaluationSummaryText(row) }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">Next Action</p>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="validationPill(actionPillStatus(row))">
                                {{ validationLabel(actionPillStatus(row)) }}
                            </span>
                            <div class="mt-2 leading-5">{{ nextActionLabel(row) }}</div>
                        </div>
                        <a v-if="row.file_url" :href="row.file_url" target="_blank" class="inline-flex pt-1 font-semibold text-[#034485] hover:underline">
                            View submitted file
                        </a>
                    </div>
                </div>
            </section>

            <div v-else class="page-card rounded-xl border border-[#034485]/35 bg-white p-6 text-center text-slate-500" :style="cardMotion(31)">
                No submissions are available at this time.
            </div>

            <section class="overflow-x-auto rounded-[28px] border border-[#034485]/20 bg-white shadow-sm">
                <div class="flex items-center justify-between px-4 pt-4">
                    <h2 class="text-sm font-semibold text-[#034485]">Submission History</h2>
                    <span class="text-xs text-slate-500">{{ displayedSubmissions.length }} total</span>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-[#034485] text-white">
                        <tr>
                            <th class="px-3 py-2 text-left">Period</th>
                            <th class="px-3 py-2 text-left">Uploaded</th>
                            <th class="px-3 py-2 text-left">Submitted File</th>
                            <th class="px-3 py-2 text-left">Evaluation</th>
                            <th class="px-3 py-2 text-left">Next Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in displayedSubmissions" :key="row.id" class="border-t border-[#034485]/10 text-slate-700">
                            <td class="px-3 py-3">{{ row.period_label || '-' }}</td>
                            <td class="px-3 py-3">
                                <div>{{ formatDateTime(row.uploaded_at) }}</div>
                                <div class="text-xs text-slate-500">{{ row.notes || '-' }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="text-xs font-semibold uppercase text-slate-700">{{ docLabel(row.document_type) }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ row.notes || 'No notes attached.' }}</div>
                                <a v-if="row.file_url" :href="row.file_url" target="_blank" class="text-xs font-semibold text-[#034485] hover:underline">View file</a>
                            </td>
                            <td class="px-3 py-3">
                                <div class="space-y-1 text-xs text-slate-500">
                                    <div>Status: <span class="font-semibold text-slate-700">{{ summaryStatusLabel(row) }}</span></div>
                                    <div>{{ metricWithFallback() }}: <span class="font-semibold text-slate-700">{{ selectedResultValue(row) }}</span></div>
                                    <div>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="validationPill(actionPillStatus(row))">
                                            {{ validationLabel(actionPillStatus(row)) }}
                                        </span>
                                    </div>
                                    <div>{{ evaluationSummaryText(row) }}</div>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="space-y-2 text-xs text-slate-500">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusPill(row).class">
                                        {{ statusPill(row).label }}
                                    </span>
                                    <div>{{ nextActionLabel(row) }}</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>

        <transition name="modal-fade">
            <div
                v-if="isSubmissionModalOpen"
                class="fixed inset-0 z-[60] overflow-y-auto bg-slate-950/50 px-4 py-6 backdrop-blur-sm"
            >
                <div class="mx-auto flex min-h-full w-full items-center justify-center">
                    <div class="relative flex w-full max-w-3xl max-h-[calc(100vh-3rem)] flex-col overflow-hidden rounded-[32px] bg-white shadow-2xl">
                    <div class="shrink-0 border-b border-slate-200 px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#034485]">Selected Period</p>
                                <h2 class="mt-1 text-xl font-semibold text-slate-900">
                                    {{ selectedPeriod ? `${selectedPeriod.school_year} - ${termLabel(selectedPeriod.term)}` : 'Academic Submission' }}
                                </h2>
                                <p class="mt-2 text-sm text-slate-500">
                                    Window: {{ selectedPeriod ? `${formatDate(selectedPeriod.starts_on, { month: 'short', day: 'numeric' })} - ${formatDate(selectedPeriod.ends_on, { month: 'short', day: 'numeric' })}` : '-' }}
                                </p>
                                <p class="text-sm text-slate-500">Accepted Formats: PDF, PNG, JPG</p>
                            </div>
                            <button
                                type="button"
                                @click="closeSubmissionModal"
                                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-50"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="flex min-h-0 flex-1 flex-col">
                        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5">
                        <div class="rounded-3xl border border-[#034485]/15 bg-[#034485]/5 p-4 text-sm text-slate-600">
                            <p class="font-semibold text-slate-800">Instructions</p>
                            <p class="mt-1">
                                Upload your latest grade report for this active academic period. The system will scan the document automatically and return the extracted {{ metricWithFallback() }} after processing.
                            </p>
                        </div>

                        <div v-if="submitError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ submitError }}
                        </div>

                        <div class="space-y-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Upload Document</p>
                                <p class="text-xs text-slate-500">Choose one PDF or image file to scan.</p>
                            </div>
                            <label
                                class="flex min-h-[180px] cursor-pointer flex-col items-center justify-center rounded-[28px] border border-dashed border-[#034485]/25 bg-[linear-gradient(135deg,rgba(3,68,133,0.03),rgba(3,68,133,0.08))] px-6 py-8 text-center transition hover:border-[#034485]/45 hover:bg-[linear-gradient(135deg,rgba(3,68,133,0.05),rgba(3,68,133,0.1))]"
                            >
                                <input type="file" accept=".pdf,image/png,image/jpeg,image/jpg" class="hidden" @change="handleFileChange" />
                                <div class="space-y-2">
                                    <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#034485] text-white">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 16V4" />
                                            <path d="m7 9 5-5 5 5" />
                                            <path d="M20 16.5a3.5 3.5 0 0 1-3.5 3.5h-9A3.5 3.5 0 0 1 4 16.5" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-900">{{ file ? 'Replace selected document' : 'Click to upload a PDF, PNG, or JPG' }}</p>
                                    <p class="text-xs text-slate-500">Clear image scans work best for automatic {{ metricWithFallback() }} extraction.</p>
                                </div>
                            </label>
                        </div>

                        <div v-if="file" class="rounded-[28px] border border-slate-200 bg-slate-50/80 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Selected File Preview</p>
                                    <p class="text-xs text-slate-500">{{ file.name }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="removeFile"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                                >
                                    Remove
                                </button>
                            </div>

                            <div class="mt-4">
                                <div v-if="previewUrl" class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-2">
                                    <img :src="previewUrl" alt="Document preview" class="max-h-72 w-full rounded-xl object-contain" />
                                </div>
                                <div v-else class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
                                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100 text-rose-700">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <path d="M14 2v6h6" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ file.name }}</p>
                                        <p class="text-xs text-slate-500">PDF document ready for scanning</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="academic-notes" class="text-sm font-semibold text-slate-900">Notes</label>
                            <textarea
                                id="academic-notes"
                                v-model="notes"
                                rows="3"
                                placeholder="Add optional context for the administrator."
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#034485]/40 focus:ring-2 focus:ring-[#034485]/10"
                            />
                        </div>

                        <div v-if="isScanning" class="rounded-[28px] border border-sky-200 bg-sky-50/90 p-4">
                            <div class="flex items-center gap-3">
                                <Spinner class="h-5 w-5 text-sky-700" />
                                <div>
                                    <p class="text-sm font-semibold text-sky-900">Scanning document...</p>
                                    <p class="text-xs text-sky-700">
                                        {{ uploadProgress > 0 && uploadProgress < 100
                                            ? `Uploading file: ${uploadProgress}%`
                                            : 'OCR and eligibility interpretation are running. Please wait.' }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-sky-100">
                                <div class="h-full rounded-full bg-sky-600 transition-all duration-200" :style="{ width: `${uploadProgress > 0 ? uploadProgress : 100}%` }" />
                            </div>
                        </div>
                        </div>

                        <div class="shrink-0 border-t border-slate-200 bg-white px-5 py-4">
                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <button
                                type="button"
                                @click="closeSubmissionModal"
                                :disabled="isSubmitting"
                                class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmitting || !file"
                                class="inline-flex items-center justify-center gap-2 rounded-full bg-[#034485] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#033a70] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <Spinner v-if="isSubmitting" class="h-4 w-4 text-white" />
                                {{ isSubmitting ? 'Scanning document...' : 'Submit for Scan' }}
                            </button>
                        </div>
                        </div>
                    </form>
                </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

.academics-page-view .page-card {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
    animation: student-page-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: calc(var(--card-order, 0) * 55ms);
    will-change: transform, opacity;
}

@keyframes student-page-card-rise {
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
    .academics-page-view .page-card {
        animation: none;
        opacity: 1;
        transform: none;
    }
}
</style>
