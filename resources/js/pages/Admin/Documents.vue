<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import Select from 'primevue/select'
import { computed, reactive, watch } from 'vue'

import { useTheme } from '@/composables/useTheme'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({ layout: AdminDashboard })

type FilterState = {
    search: string
    type: string
    review_status: string
    period_id: number
    upload_date: string
}

type DocumentRow = {
    id: number
    student: {
        id: number | null
        name: string
        email: string | null
        student_id_number: string | null
        course_or_strand: string | null
    }
    document_type: string
    document_label: string
    document_context: string | null
    academic_period: { id: number; label: string } | null
    file_url: string
    download_url: string
    uploaded_at: string | null
    notes: string | null
    review_status: string
    reviewed_at: string | null
    reviewer_name: string | null
    file_name: string
}

type Pagination<T> = {
    data: T[]
    current_page: number
    last_page: number
    per_page?: number
    from: number | null
    to: number | null
    total: number
    links: Array<{ url: string | null; label: string; active: boolean }>
}

const props = defineProps<{
    filters: FilterState
    periods: Array<{ id: number; label: string }>
    documentTypes: Array<{ context: string; code: string; label: string }>
    documents: Pagination<DocumentRow>
}>()

const { isDarkMode } = useTheme()
const filters = reactive({ ...props.filters })
let searchDebounce: ReturnType<typeof setTimeout> | null = null

function parseFilterDate(value: string): Date | null {
    if (!value) return null

    const date = new Date(`${value}T00:00:00`)
    return Number.isNaN(date.getTime()) ? null : date
}

function formatFilterDate(value: Date | null): string {
    if (!value) return ''

    const year = value.getFullYear()
    const month = String(value.getMonth() + 1).padStart(2, '0')
    const day = String(value.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

const uploadDateModel = computed<Date | null>({
    get: () => parseFilterDate(filters.upload_date),
    set: (value) => {
        filters.upload_date = formatFilterDate(value)
    },
})
const documentTypeOptions = computed(() => [
    { label: 'All document types', value: 'all' },
    ...props.documentTypes.map((type) => ({ label: type.label, value: type.code })),
])
const reviewStatusOptions = [
    { label: 'All review statuses', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Auto Processed', value: 'auto_processed' },
    { label: 'Needs Review', value: 'needs_review' },
    { label: 'Reviewed', value: 'reviewed' },
]
const periodOptions = computed(() => [
    { label: 'All periods', value: 0 },
    ...props.periods.map((period) => ({ label: period.label, value: period.id })),
])

function applyFilters(page = 1) {
    router.get('/documents', {
        search: filters.search || undefined,
        type: filters.type === 'all' ? undefined : filters.type,
        review_status: filters.review_status === 'all' ? undefined : filters.review_status,
        period_id: filters.period_id > 0 ? filters.period_id : undefined,
        upload_date: filters.upload_date || undefined,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch(
    () => filters.search,
    () => {
        if (searchDebounce) clearTimeout(searchDebounce)
        searchDebounce = setTimeout(() => applyFilters(1), 250)
    },
)

watch(
    () => [filters.type, filters.review_status, filters.period_id, filters.upload_date],
    () => {
        applyFilters(1)
    },
)

const firstRecordIndex = computed(() => {
    const perPage = props.documents.per_page ?? Math.max(props.documents.to ?? 0, props.documents.data.length || 12)
    return (props.documents.current_page - 1) * perPage
})

function handlePageChange(event: { page: number }) {
    applyFilters(event.page + 1)
}

function statusTone(status: string | null | undefined) {
    const normalized = String(status ?? '').toLowerCase()
    if (normalized === 'reviewed') return 'border-[#034485]/35 bg-[#dcecff] text-[#034485]'
    if (normalized === 'needs_review') return 'border-[#034485]/30 bg-[#edf5ff] text-[#034485]'
    if (normalized === 'auto_processed') return 'border-[#034485]/25 bg-[#f4f8ff] text-[#034485]'
    return 'border-[#034485]/20 bg-white text-[#034485]'
}

function statusLabel(status: string | null | undefined) {
    const normalized = String(status ?? '').replace(/_/g, ' ').trim()
    if (!normalized) return 'Pending'
    return normalized.replace(/\b\w/g, (char) => char.toUpperCase())
}

function formatDateTime(value: string | null) {
    if (!value) return '-'
    return new Date(value).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}
</script>

<template>
    <Head title="Documents" />

    <div class="space-y-5">
        <section class="rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">Documents</p>
            <h1 class="mt-2 text-2xl font-bold">Document Center</h1>
            <p class="mt-2 text-sm text-white/85">
                View uploaded student-athlete documents in one organized place without extra approval or evaluation actions.
            </p>
        </section>

        <section class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-[#c8dcef] bg-white text-slate-900'">
            <div class="grid gap-3 md:grid-cols-5">
                <InputText v-model="filters.search" class="w-full" placeholder="Search student name, ID, or document" />
                <Select v-model="filters.type" :options="documentTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
                <Select v-model="filters.review_status" :options="reviewStatusOptions" optionLabel="label" optionValue="value" class="w-full" />
                <Select v-model="filters.period_id" :options="periodOptions" optionLabel="label" optionValue="value" class="w-full" />
                <DatePicker
                    v-model="uploadDateModel"
                    showIcon
                    iconDisplay="input"
                    inputClass="w-full rounded-2xl border px-4 py-3 text-sm"
                    :pt="{
                        pcInputText: {
                            root: {
                                class: isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-200 bg-white text-slate-900',
                            },
                        },
                    }"
                    panelClass="text-sm"
                    placeholder="Upload date"
                    dateFormat="yy-mm-dd"
                    :manualInput="false"
                />
            </div>
        </section>

        <section class="rounded-3xl border p-4" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-[#c8dcef] bg-white text-slate-900'">
            <DataTable
                :value="documents.data"
                dataKey="id"
                responsiveLayout="scroll"
                class="admin-documents-table"
                :pt="{
                    table: { class: 'min-w-[1280px]' },
                    thead: { class: 'bg-[#034485] text-white shadow-[inset_0_-1px_0_rgba(255,255,255,0.08)]' },
                    headerCell: { class: 'bg-[#034485] text-white border-b border-white/10 first:rounded-tl-2xl last:rounded-tr-2xl' },
                    columnHeaderContent: { class: 'text-xs font-semibold uppercase tracking-[0.14em]' },
                    tbody: { class: isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-white text-slate-900' },
                }"
            >
                <template #empty>
                    <div class="py-8 text-center text-sm text-slate-500">
                        No documents matched the current filters.
                    </div>
                </template>

                <Column field="student.name" header="Student">
                    <template #body="{ data }">
                        <div>
                            <p class="font-semibold">{{ data.student.name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ data.student.student_id_number || '-' }}</p>
                        </div>
                    </template>
                </Column>

                <Column field="document_label" header="Document">
                    <template #body="{ data }">
                        <div>
                            <p class="font-medium">{{ data.document_label }}</p>
                            <p class="mt-1 text-xs text-slate-500 break-all">{{ data.file_name }}</p>
                        </div>
                    </template>
                </Column>

                <Column field="academic_period.label" header="Period">
                    <template #body="{ data }">
                        {{ data.academic_period?.label || 'Registration / General' }}
                    </template>
                </Column>

                <Column field="review_status" header="Review">
                    <template #body="{ data }">
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold" :class="statusTone(data.review_status)">
                            {{ statusLabel(data.review_status) }}
                        </span>
                    </template>
                </Column>

                <Column field="uploaded_at" header="Uploaded">
                    <template #body="{ data }">
                        {{ formatDateTime(data.uploaded_at) }}
                    </template>
                </Column>

                <Column field="reviewer_name" header="Reviewer">
                    <template #body="{ data }">
                        {{ data.reviewer_name || 'Not reviewed yet' }}
                    </template>
                </Column>

                <Column field="notes" header="Notes">
                    <template #body="{ data }">
                        <span
                            class="line-clamp-2 text-sm font-medium"
                            :class="isDarkMode ? 'text-slate-100' : 'text-slate-800'"
                        >
                            {{ data.notes || '-' }}
                        </span>
                    </template>
                </Column>

                <Column header="Actions">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <a :href="data.file_url" target="_blank" class="rounded-2xl border border-[#034485]/25 bg-white px-3 py-2 text-xs font-semibold text-[#034485]">
                                Preview
                            </a>
                            <a
                                :href="data.download_url"
                                class="rounded-2xl border px-3 py-2 text-xs font-semibold"
                                :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-200 bg-slate-50 text-slate-700'"
                            >
                                Download
                            </a>
                        </div>
                    </template>
                </Column>
            </DataTable>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                    Page {{ documents.current_page }} of {{ documents.last_page }} • {{ documents.total }} documents
                </p>

                <Paginator
                    :first="firstRecordIndex"
                    :rows="documents.per_page ?? Math.max(documents.to ?? 0, documents.data.length || 12)"
                    :totalRecords="documents.total"
                    template="PrevPageLink PageLinks NextPageLink"
                    @page="handlePageChange"
                />
            </div>
        </section>
    </div>
</template>
