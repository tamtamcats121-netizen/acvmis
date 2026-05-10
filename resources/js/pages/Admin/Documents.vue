<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import DatePicker from 'primevue/datepicker'

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
const selectedPreview = computed(() => props.documents.data[0] ?? null)

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

function applyFilters() {
    router.get('/documents', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function statusTone(status: string | null | undefined) {
    const normalized = String(status ?? '').toLowerCase()
    if (normalized === 'reviewed') return 'border-emerald-500/35 bg-emerald-500/10 text-emerald-300'
    if (normalized === 'needs_review') return 'border-amber-500/35 bg-amber-500/10 text-amber-300'
    if (normalized === 'auto_processed') return 'border-sky-500/35 bg-sky-500/10 text-sky-300'
    return 'border-slate-500/35 bg-slate-500/10 text-slate-200'
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

        <section class="rounded-3xl border p-4 shadow-sm" :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-100' : 'border-[#d6e4f4] bg-white text-slate-900'">
            <div class="grid gap-3 md:grid-cols-5">
                <input v-model="filters.search" class="rounded-2xl border px-4 py-3 text-sm" :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100 placeholder:text-slate-500' : 'border-slate-200 bg-white text-slate-900'" placeholder="Search student name, ID, or document" />
                <select v-model="filters.type" class="rounded-2xl border px-4 py-3 text-sm" :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-200 bg-white text-slate-900'">
                    <option value="all">All document types</option>
                    <option v-for="type in documentTypes" :key="`${type.context}-${type.code}`" :value="type.code">{{ type.label }}</option>
                </select>
                <select v-model="filters.review_status" class="rounded-2xl border px-4 py-3 text-sm" :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-200 bg-white text-slate-900'">
                    <option value="all">All review statuses</option>
                    <option value="pending">Pending</option>
                    <option value="auto_processed">Auto Processed</option>
                    <option value="needs_review">Needs Review</option>
                    <option value="reviewed">Reviewed</option>
                </select>
                <select v-model="filters.period_id" class="rounded-2xl border px-4 py-3 text-sm" :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-200 bg-white text-slate-900'">
                    <option :value="0">All periods</option>
                    <option v-for="period in periods" :key="period.id" :value="period.id">{{ period.label }}</option>
                </select>
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
            <div class="mt-3 flex justify-end">
                <button class="rounded-2xl bg-[#034485] px-4 py-2 text-sm font-semibold text-white" @click="applyFilters">Apply Filters</button>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,0.85fr)]">
            <section class="space-y-3">
                <article v-for="row in documents.data" :key="row.id" class="rounded-3xl border p-5 shadow-sm" :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-100' : 'border-[#d6e4f4] bg-white text-slate-900'">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em]" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">{{ row.document_label }}</p>
                            <h2 class="mt-2 text-lg font-semibold">{{ row.student.name }}</h2>
                            <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">{{ row.student.student_id_number || '-' }} • {{ row.student.course_or_strand || '-' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="statusTone(row.review_status)">
                                {{ statusLabel(row.review_status) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-slate-50'">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">Student ID</p>
                            <p class="mt-2 text-sm font-medium">{{ row.student.student_id_number || '-' }}</p>
                        </div>
                        <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-slate-50'">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">File Name</p>
                            <p class="mt-2 text-sm font-medium break-all">{{ row.file_name }}</p>
                        </div>
                        <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-slate-50'">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">Period</p>
                            <p class="mt-2 text-sm font-medium">{{ row.academic_period?.label || 'Registration / General' }}</p>
                        </div>
                        <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-slate-50'">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">Uploaded</p>
                            <p class="mt-2 text-sm font-medium">{{ formatDateTime(row.uploaded_at) }}</p>
                        </div>
                        <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-slate-50'">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">Reviewer</p>
                            <p class="mt-2 text-sm font-medium">{{ row.reviewer_name || 'Not reviewed yet' }}</p>
                        </div>
                    </div>

                    <p v-if="row.notes" class="mt-4 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">{{ row.notes }}</p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a :href="row.file_url" target="_blank" class="rounded-2xl border border-[#034485]/25 bg-white px-4 py-2 text-sm font-semibold text-[#034485]">Preview</a>
                        <a :href="row.download_url" class="rounded-2xl border px-4 py-2 text-sm font-semibold" :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-200 bg-slate-50 text-slate-700'">Download</a>
                    </div>
                </article>
                <div v-if="documents.data.length === 0" class="rounded-3xl border p-8 text-center text-sm shadow-sm" :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-400' : 'border-[#d6e4f4] bg-white text-slate-500'">
                    No documents matched the current filters.
                </div>
            </section>

            <aside class="rounded-3xl border p-5 shadow-sm" :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-100' : 'border-[#d6e4f4] bg-white text-slate-900'">
                <p class="text-xs font-semibold uppercase tracking-[0.16em]" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">Preview Summary</p>
                <template v-if="selectedPreview">
                    <h2 class="mt-3 text-xl font-semibold">{{ selectedPreview.document_label }}</h2>
                    <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">{{ selectedPreview.student.name }} • {{ selectedPreview.student.email || '-' }}</p>
                    <div class="mt-4 space-y-3 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">
                        <p><span class="font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Student ID:</span> {{ selectedPreview.student.student_id_number || '-' }}</p>
                        <p><span class="font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">File name:</span> {{ selectedPreview.file_name }}</p>
                        <p><span class="font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Review status:</span> {{ statusLabel(selectedPreview.review_status) }}</p>
                        <p><span class="font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Period:</span> {{ selectedPreview.academic_period?.label || 'Registration / General' }}</p>
                        <p><span class="font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Uploaded:</span> {{ formatDateTime(selectedPreview.uploaded_at) }}</p>
                        <p><span class="font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Reviewer:</span> {{ selectedPreview.reviewer_name || 'Not reviewed yet' }}</p>
                        <p v-if="selectedPreview.notes"><span class="font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Notes:</span> {{ selectedPreview.notes }}</p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a :href="selectedPreview.file_url" target="_blank" class="rounded-2xl border border-[#034485]/25 bg-white px-4 py-2 text-sm font-semibold text-[#034485]">View / Preview</a>
                        <a :href="selectedPreview.download_url" class="rounded-2xl border px-4 py-2 text-sm font-semibold" :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-200 bg-slate-50 text-slate-700'">Download</a>
                    </div>
                </template>
                <p v-else class="mt-3 text-sm" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">No documents found for the current filters.</p>
            </aside>
        </div>
    </div>
</template>
