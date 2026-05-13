<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import DatePicker from 'primevue/datepicker'
import { computed, reactive } from 'vue'

import { useTheme } from '@/composables/useTheme'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type TeamOption = {
    id: number
    team_name: string
    sport_name: string
}

const reportTabs = [
    { label: 'Attendance', href: '/reports/attendance' },
    { label: 'Roster', href: '/reports/roster' },
    { label: 'Academics', href: '/reports/academics' },
]

const props = defineProps<{
    filters: {
        selected: {
            period_id: number | null
            team_id: number | null
            academic_status: string | null
            start_date: string | null
            end_date: string | null
        }
        options: {
            periods: { id: number; label: string }[]
            teams: TeamOption[]
            academic_statuses: { value: string; label: string }[]
        }
    }
    academicReport: {
        summary: {
            total_submissions: number
            eligible: number
            pending_review: number
            ineligible: number
            pending: number
        }
        rows: Array<{
            document_id: number
            student_id: number
            student_name: string
            student_id_number: string | null
            team_name: string
            document_type: string
            uploaded_at: string | null
            period_label: string
            academic_status: string
            gpa: string
        }>
    }
}>()

const { isDarkMode } = useTheme()
const form = reactive({
    period_id: props.filters.selected.period_id ? String(props.filters.selected.period_id) : '',
    team_id: props.filters.selected.team_id ? String(props.filters.selected.team_id) : '',
    academic_status: props.filters.selected.academic_status ?? '',
    start_date: props.filters.selected.start_date ?? '',
    end_date: props.filters.selected.end_date ?? '',
})

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

const startDateModel = computed<Date | null>({
    get: () => parseFilterDate(form.start_date),
    set: (value) => {
        form.start_date = formatFilterDate(value)
    },
})

const endDateModel = computed<Date | null>({
    get: () => parseFilterDate(form.end_date),
    set: (value) => {
        form.end_date = formatFilterDate(value)
    },
})

const queryString = computed(() => {
    const params = new URLSearchParams()

    if (form.period_id) params.set('period_id', form.period_id)
    if (form.team_id) params.set('team_id', form.team_id)
    if (form.academic_status) params.set('academic_status', form.academic_status)
    if (form.start_date) params.set('start_date', form.start_date)
    if (form.end_date) params.set('end_date', form.end_date)

    const query = params.toString()
    return query ? `?${query}` : ''
})

function applyFilters() {
    router.get('/reports/academics', {
        period_id: form.period_id || undefined,
        team_id: form.team_id || undefined,
        academic_status: form.academic_status || undefined,
        start_date: form.start_date || undefined,
        end_date: form.end_date || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    form.period_id = ''
    form.team_id = ''
    form.academic_status = ''
    form.start_date = ''
    form.end_date = ''
    applyFilters()
}
</script>

<template>
    <Head title="Academic Report" />

    <div class="space-y-5">
        <section class="page-card rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Academic Submission Report</h1>
                    <p class="text-sm text-slate-600">Track document submissions, academic evaluation status, and academic result context for varsity monitoring.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        v-for="tab in reportTabs"
                        :key="tab.href"
                        :href="tab.href"
                        class="rounded-md border px-3 py-2 text-sm font-semibold transition"
                        :class="tab.href === '/reports/academics' ? 'border-[#1f2937] bg-[#1f2937] text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100'"
                    >
                        {{ tab.label }}
                    </a>
                </div>
            </div>
        </section>

        <section class="page-card rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Period</label>
                    <select v-model="form.period_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Periods</option>
                        <option v-for="period in props.filters.options.periods" :key="period.id" :value="String(period.id)">
                            {{ period.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Team</label>
                    <select v-model="form.team_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Teams</option>
                        <option v-for="team in props.filters.options.teams" :key="team.id" :value="String(team.id)">
                            {{ team.team_name }} ({{ team.sport_name }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Academic Status</label>
                    <select v-model="form.academic_status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Academic Statuses</option>
                        <option v-for="status in props.filters.options.academic_statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Start Date</label>
                    <DatePicker
                        v-model="startDateModel"
                        showIcon
                        iconDisplay="input"
                        inputClass="w-full rounded-md border px-3 py-2 text-sm"
                        :pt="{
                            pcInputText: {
                                root: {
                                    class: isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-900',
                                },
                            },
                        }"
                        panelClass="text-sm"
                        placeholder="Start date"
                        dateFormat="yy-mm-dd"
                        :manualInput="false"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">End Date</label>
                    <DatePicker
                        v-model="endDateModel"
                        showIcon
                        iconDisplay="input"
                        inputClass="w-full rounded-md border px-3 py-2 text-sm"
                        :pt="{
                            pcInputText: {
                                root: {
                                    class: isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-900',
                                },
                            },
                        }"
                        panelClass="text-sm"
                        placeholder="End date"
                        dateFormat="yy-mm-dd"
                        :manualInput="false"
                    />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="applyFilters">
                    Apply
                </button>
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="resetFilters">
                    Reset
                </button>
                <a :href="`/reports/academics/export.csv${queryString}`" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Export CSV
                </a>
                <a :href="`/reports/academics/print${queryString}`" target="_blank" rel="noreferrer" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Open PDF View
                </a>
            </div>
        </section>

        <section class="page-card space-y-4 rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Total Submissions</p>
                    <p class="mt-1 text-2xl font-bold text-[#034485]">{{ props.academicReport.summary.total_submissions }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Eligible</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ props.academicReport.summary.eligible }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Probation</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ props.academicReport.summary.pending_review }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Ineligible</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ props.academicReport.summary.ineligible }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Pending</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.academicReport.summary.pending }}</p>
                </article>
            </div>

            <div v-if="props.academicReport.rows.length === 0" class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-sm text-slate-500">
                No academic submission data found for the selected filters.
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-[#034485]/45">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <th class="px-5 py-4">Student</th>
                            <th class="px-5 py-4">Team</th>
                            <th class="px-5 py-4">Period</th>
                            <th class="px-5 py-4">Document</th>
                            <th class="px-5 py-4">Uploaded</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Academic Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.academicReport.rows" :key="row.document_id" class="align-top">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ row.student_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.student_id_number || 'No student ID' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ row.team_name }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.period_label }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.document_type }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.uploaded_at || 'N/A' }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ row.academic_status }}</span>
                            </td>
                            <td class="px-5 py-4 font-semibold text-[#034485]">{{ row.gpa }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
