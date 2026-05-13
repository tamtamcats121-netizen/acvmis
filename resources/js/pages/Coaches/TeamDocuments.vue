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
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'

defineOptions({ layout: CoachDashboard })

type FilterState = {
    search: string
    type: string
    review_status: string
    upload_date: string
}

type DocumentRow = {
    id: number
    student_name: string
    student_id_number: string | null
    team_name: string | null
    document_label: string
    review_status: string
    uploaded_at: string | null
    file_url: string
    download_url: string
    notes: string | null
    file_name: string
}

const props = defineProps<{
    team: { id: number; name: string; sport: string | null } | null
    filters: FilterState
    documentTypes: Array<{ code: string; label: string }>
    documents: {
        data: DocumentRow[]
        meta: {
            current_page: number
            last_page: number
            per_page: number
            total: number
        }
    }
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

function applyFilters(page = 1) {
    router.get('/coach/documents', { ...filters, page }, {
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
    () => [filters.type, filters.review_status, filters.upload_date],
    () => {
        applyFilters(1)
    },
)

const firstRecordIndex = computed(() => (props.documents.meta.current_page - 1) * props.documents.meta.per_page)
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

function handlePageChange(event: { page: number }) {
    applyFilters(event.page + 1)
}

function statusTone(status: string | null | undefined) {
    const normalized = String(status ?? '').toLowerCase()
    if (normalized === 'reviewed') return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    if (normalized === 'needs_review') return 'border-amber-200 bg-amber-50 text-amber-700'
    if (normalized === 'auto_processed') return 'border-sky-200 bg-sky-50 text-sky-700'
    return 'border-slate-200 bg-slate-100 text-slate-700'
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
    <Head title="Team Documents" />

    <div class="space-y-5">
        <section class="rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">Team Documents</p>
            <h1 class="mt-2 text-2xl font-bold">{{ team ? team.name : 'Team Documents' }}</h1>
            <p class="mt-2 text-sm text-white/85">
                Review your assigned student-athletes’ submitted documents in a searchable table with review status and file access in one place.
            </p>
        </section>

        <div
            v-if="!team"
            class="rounded-3xl border p-6 text-sm"
            :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-400' : 'border-[#c8dcef] bg-white text-slate-500'"
        >
            You are not assigned to a team yet.
        </div>

        <template v-else>
            <section
                class="rounded-3xl border p-4"
                :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-[#c8dcef] bg-white text-slate-900'"
            >
                <div class="grid gap-3 md:grid-cols-4">
                    <InputText
                        v-model="filters.search"
                        class="w-full"
                        placeholder="Search student name, ID, or document"
                    />
                    <Select v-model="filters.type" :options="documentTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <Select v-model="filters.review_status" :options="reviewStatusOptions" optionLabel="label" optionValue="value" class="w-full" />
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

            <section
                class="rounded-3xl border p-4"
                :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-[#c8dcef] bg-white text-slate-900'"
            >
                <DataTable
                    :value="documents.data"
                    dataKey="id"
                    responsiveLayout="scroll"
                    class="team-documents-table"
                    :pt="{
                        table: { class: 'min-w-[980px]' },
                        thead: { class: 'bg-[#034485] text-white' },
                        tbody: { class: isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-white text-slate-900' },
                    }"
                >
                    <template #empty>
                        <div class="py-8 text-center text-sm text-slate-500">
                            No team documents matched the current filters.
                        </div>
                    </template>

                    <Column field="student_name" header="Student">
                        <template #body="{ data }">
                            <div>
                                <p class="font-semibold">{{ data.student_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ data.student_id_number || '-' }}</p>
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

                    <Column field="team_name" header="Team">
                        <template #body="{ data }">
                            {{ data.team_name || team.name }}
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

                    <Column field="notes" header="Notes">
                        <template #body="{ data }">
                            <span class="line-clamp-2 text-sm text-slate-600 dark:text-slate-300">
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
                        Page {{ documents.meta.current_page }} of {{ documents.meta.last_page }} • {{ documents.meta.total }} documents
                    </p>

                    <Paginator
                        :first="firstRecordIndex"
                        :rows="documents.meta.per_page"
                        :totalRecords="documents.meta.total"
                        template="PrevPageLink PageLinks NextPageLink"
                        @page="handlePageChange"
                    />
                </div>
            </section>
        </template>
    </div>
</template>
