<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { useTheme } from '@/composables/useTheme'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'

defineOptions({ layout: CoachDashboard })

type ApplicationRow = {
    id: number
    name: string
    email: string
    created_at: string | null
    requirements_complete: boolean
    student: {
        id: number | null
        student_id_number: string | null
        course_or_strand: string | null
        current_grade_level: string | null
        academic_level_label: string | null
        approval_status: string | null
        applied_sport: string | null
        phone_number: string | null
        date_of_birth: string | null
        gender: string | null
        height: string | null
        weight: string | null
        emergency_contact_name: string | null
        emergency_contact_relationship: string | null
        emergency_contact_phone: string | null
        registration_documents: Array<{
            id: number
            document_type: string | null
            uploaded_at: string | null
        }>
    }
}

const props = defineProps<{
    applications: ApplicationRow[]
    filters: {
        status: 'pending' | 'approved' | 'rejected'
        search: string
    }
    sport: { id: number | null; name: string | null } | null
    stats: {
        pending: number
        approved: number
        rejected: number
    }
}>()

const { isDarkMode } = useTheme()
const search = ref(props.filters.search ?? '')
const selectedStatus = ref(props.filters.status ?? 'pending')
const rejectTarget = ref<ApplicationRow | null>(null)
const rejectRemarks = ref('')
const processingId = ref<number | null>(null)
let searchDebounce: ReturnType<typeof setTimeout> | null = null

const filteredRows = computed(() => props.applications)

function applyFilters() {
    router.get('/coach/applications', {
        status: selectedStatus.value,
        search: search.value.trim() || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch(search, () => {
    if (searchDebounce) clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => {
        applyFilters()
    }, 300)
})

watch(selectedStatus, () => {
    applyFilters()
})

onBeforeUnmount(() => {
    if (searchDebounce) clearTimeout(searchDebounce)
})

function approve(row: ApplicationRow) {
    processingId.value = row.id
    router.post(`/coach/applications/${row.id}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null
        },
    })
}

function openReject(row: ApplicationRow) {
    rejectTarget.value = row
    rejectRemarks.value = ''
}

function submitReject() {
    if (!rejectTarget.value) return
    processingId.value = rejectTarget.value.id
    router.post(`/coach/applications/${rejectTarget.value.id}/reject`, {
        remarks: rejectRemarks.value.trim() || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null
            rejectTarget.value = null
        },
    })
}

function formatDate(value: string | null) {
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

function docLabel(value: string | null) {
    return String(value ?? 'document').replaceAll('_', ' ').toUpperCase()
}
</script>

<template>
    <Head title="Coach Student Applications" />

    <div class="space-y-6">
        <section
            class="rounded-3xl border p-6"
            :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-100' : 'border-[#034485] bg-[#034485] text-white shadow-[0_24px_60px_rgba(3,68,133,0.28)]'"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em]" :class="isDarkMode ? 'text-[#034485]' : 'text-white/70'">Coach Workflow</p>
                    <h1 class="mt-2 text-2xl font-bold">Student Applications</h1>
                    <p class="mt-2 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-white/85'">
                        Review student-athlete applications for {{ sport?.name || 'your assigned sport' }} and decide who passed tryouts or belongs to the program.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div
                        class="rounded-2xl border px-4 py-3 backdrop-blur-xl"
                        :class="isDarkMode ? 'border-slate-800/80 bg-slate-900/80' : 'border-white/25 bg-white/14 shadow-[inset_0_1px_0_rgba(255,255,255,0.28),0_18px_40px_rgba(2,49,95,0.18)]'"
                    >
                        <p class="text-xs uppercase tracking-wide" :class="isDarkMode ? 'text-slate-500' : 'text-white/70'">Pending</p>
                        <p class="mt-1 text-xl font-bold">{{ props.stats.pending }}</p>
                    </div>
                    <div
                        class="rounded-2xl border px-4 py-3 backdrop-blur-xl"
                        :class="isDarkMode ? 'border-slate-800/80 bg-slate-900/80' : 'border-white/25 bg-white/14 shadow-[inset_0_1px_0_rgba(255,255,255,0.28),0_18px_40px_rgba(2,49,95,0.18)]'"
                    >
                        <p class="text-xs uppercase tracking-wide" :class="isDarkMode ? 'text-slate-500' : 'text-white/70'">Approved</p>
                        <p class="mt-1 text-xl font-bold">{{ props.stats.approved }}</p>
                    </div>
                    <div
                        class="rounded-2xl border px-4 py-3 backdrop-blur-xl"
                        :class="isDarkMode ? 'border-slate-800/80 bg-slate-900/80' : 'border-white/25 bg-white/14 shadow-[inset_0_1px_0_rgba(255,255,255,0.28),0_18px_40px_rgba(2,49,95,0.18)]'"
                    >
                        <p class="text-xs uppercase tracking-wide" :class="isDarkMode ? 'text-slate-500' : 'text-white/70'">Rejected</p>
                        <p class="mt-1 text-xl font-bold">{{ props.stats.rejected }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-800 bg-slate-950' : 'border-[#034485]/20 bg-white'">
            <div class="grid gap-3 md:grid-cols-[1fr_220px]">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search name, email, student ID, or course"
                    class="rounded-xl border px-3 py-2 text-sm"
                    :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-900'"
                />
                <select
                    v-model="selectedStatus"
                    class="rounded-xl border px-3 py-2 text-sm"
                    :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-900'"
                >
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </section>

        <section class="space-y-4">
            <article
                v-for="row in filteredRows"
                :key="row.id"
                class="rounded-3xl border p-5"
                :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-100' : 'border-[#034485]/20 bg-white text-slate-900'"
            >
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="space-y-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold">{{ row.name }}</h2>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="row.requirements_complete ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                    {{ row.requirements_complete ? 'Requirements Complete' : 'Requirements Incomplete' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                                {{ row.email }} • {{ row.student.student_id_number || 'No ID' }} • {{ row.student.applied_sport || 'No sport' }}
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Grade Level</p>
                                <p class="mt-1 text-sm font-medium">{{ row.student.academic_level_label || row.student.current_grade_level || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Course / Strand</p>
                                <p class="mt-1 text-sm font-medium">{{ row.student.course_or_strand || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Phone</p>
                                <p class="mt-1 text-sm font-medium">{{ row.student.phone_number || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Submitted</p>
                                <p class="mt-1 text-sm font-medium">{{ formatDate(row.created_at) }}</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Registration Documents</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="document in row.student.registration_documents"
                                    :key="document.id"
                                    class="rounded-full border px-3 py-1 text-xs font-semibold"
                                    :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-200' : 'border-[#034485]/20 bg-[#f6faff] text-[#034485]'"
                                >
                                    {{ docLabel(document.document_type) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedStatus === 'pending'" class="flex gap-2">
                        <button
                            class="rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50"
                            :disabled="processingId === row.id"
                            @click="openReject(row)"
                        >
                            Reject
                        </button>
                        <button
                            class="rounded-xl bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#02315f] disabled:opacity-60"
                            :disabled="processingId === row.id || !row.requirements_complete"
                            @click="approve(row)"
                        >
                            {{ processingId === row.id ? 'Saving...' : 'Approve' }}
                        </button>
                    </div>
                </div>
            </article>

            <div
                v-if="filteredRows.length === 0"
                class="rounded-3xl border border-dashed p-10 text-center text-sm"
                :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-400' : 'border-[#034485]/20 bg-white text-slate-500'"
            >
                No student applications found for this sport and filter.
            </div>
        </section>
    </div>

    <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="rejectTarget = null">
        <div class="w-full max-w-lg rounded-2xl border p-5" :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-100' : 'border-[#034485]/20 bg-white text-slate-900'">
            <h2 class="text-lg font-bold">Reject Application</h2>
            <p class="mt-2 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                Add an optional note for {{ rejectTarget.name }}.
            </p>
            <textarea
                v-model="rejectRemarks"
                rows="4"
                class="mt-4 w-full rounded-xl border px-3 py-2 text-sm"
                :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-900'"
            />
            <div class="mt-4 flex justify-end gap-2">
                <button class="rounded-xl border px-4 py-2 text-sm font-semibold" :class="isDarkMode ? 'border-slate-700 text-slate-200' : 'border-slate-300 text-slate-700'" @click="rejectTarget = null">
                    Cancel
                </button>
                <button class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700" :disabled="processingId === rejectTarget.id" @click="submitReject">
                    {{ processingId === rejectTarget.id ? 'Saving...' : 'Reject Application' }}
                </button>
            </div>
        </div>
    </div>
</template>
