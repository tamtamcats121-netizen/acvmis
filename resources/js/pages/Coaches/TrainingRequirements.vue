<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

import { showAppToast } from '@/composables/useAppToast'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'

defineOptions({
    layout: CoachDashboard,
})

const props = defineProps<{
    schedule: {
        id: number
        title: string
        type: string
        venue: string | null
        notes: string | null
        start: string | null
        end: string | null
    }
    team: {
        id: number
        team_name: string
        sport: string
    }
    coach: {
        id: number
        full_name: string
    } | null
    categories: string[]
    students: Array<{
        student_id: number
        student_name: string
        student_id_number: string | null
        jersey_number: string | number | null
        position: string | null
    }>
    requirements: Array<{
        id: number
        student_id: number
        student_name: string
        student_id_number: string | null
        category: string
        title: string
        description: string | null
        coach_name: string
        created_at: string | null
    }>
    canManage: boolean
    printUrl: string
}>()

const page = usePage()
const form = reactive({
    student_ids: [] as number[],
    title: '',
    category: props.categories[0] ?? '',
    description: '',
})
const submitting = ref(false)

const selectedCount = computed(() => form.student_ids.length)
const groupedRequirements = computed(() => props.requirements)

function formatPHT(value: string | null) {
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

function isSelected(studentId: number) {
    return form.student_ids.includes(studentId)
}

function toggleStudent(studentId: number) {
    if (isSelected(studentId)) {
        form.student_ids = form.student_ids.filter((id) => id !== studentId)
        return
    }

    form.student_ids = [...form.student_ids, studentId]
}

function toggleAllStudents() {
    if (selectedCount.value === props.students.length) {
        form.student_ids = []
        return
    }

    form.student_ids = props.students.map((student) => student.student_id)
}

function resetForm() {
    form.student_ids = []
    form.title = ''
    form.category = props.categories[0] ?? ''
    form.description = ''
}

function submit() {
    if (!props.canManage || submitting.value) return

    submitting.value = true

    router.post(`/coach/schedules/${props.schedule.id}/training-requirements`, form, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast('Training requirements assigned successfully.', 'success', {
                summary: 'Training Requirements',
            })
            resetForm()
        },
        onError: () => {
            showAppToast('Unable to save training requirements right now.', 'error', {
                summary: 'Training Requirements',
            })
        },
        onFinish: () => {
            submitting.value = false
        },
    })
}

function removeRequirement(requirementId: number) {
    if (!props.canManage) return

    router.delete(`/coach/schedules/${props.schedule.id}/training-requirements/${requirementId}`, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast('Training requirement removed.', 'success', {
                summary: 'Training Requirements',
            })
        },
        onError: () => {
            showAppToast('Unable to remove the training requirement.', 'error', {
                summary: 'Training Requirements',
            })
        },
    })
}

const flashSuccess = computed(() => String((page.props as any)?.flash?.success ?? ''))
</script>

<template>
    <div class="space-y-5">
        <section class="rounded-3xl border border-[#034485]/35 bg-[#034485] p-6 text-white shadow-[0_20px_44px_-28px_rgba(3,68,133,0.45)]">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">Schedule-Based Training Requirements</p>
                    <h1 class="mt-2 text-2xl font-bold">Training Requirements</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-white/85">
                        Assign schedule-specific training instructions to one or more student-athletes and keep a printable coach record.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        href="/coach/schedule"
                        class="rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/15"
                    >
                        Back to Schedule
                    </Link>
                    <a
                        :href="printUrl"
                        target="_blank"
                        rel="noopener"
                        class="rounded-full border border-white/20 bg-white px-4 py-2 text-sm font-semibold text-[#034485] transition hover:bg-[#eef5ff]"
                    >
                        Print Requirements
                    </a>
                </div>
            </div>
        </section>

        <p v-if="flashSuccess" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ flashSuccess }}
        </p>

        <section class="grid gap-4 xl:grid-cols-[1fr_1.2fr]">
            <div class="space-y-4">
                <div class="rounded-3xl border border-[#034485]/18 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Assign Requirements</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-900">Create a schedule-based instruction</h2>
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-[#034485]/22 px-3 py-1 text-xs font-semibold text-[#034485] transition hover:bg-[#f3f8ff]"
                            @click="toggleAllStudents"
                        >
                            {{ selectedCount === students.length ? 'Clear All' : 'Select All' }}
                        </button>
                    </div>

                    <div class="mt-4 rounded-2xl border border-[#034485]/15 bg-[#f7fbff] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Student-athletes</p>
                        <p class="mt-1 text-sm text-slate-600">{{ selectedCount }} selected from {{ students.length }}</p>

                        <div class="mt-3 max-h-72 space-y-2 overflow-auto pr-1">
                            <label
                                v-for="student in students"
                                :key="student.student_id"
                                class="flex cursor-pointer items-start gap-3 rounded-2xl border border-[#034485]/12 bg-white px-3 py-3 transition hover:border-[#034485]/28"
                            >
                                <input
                                    :checked="isSelected(student.student_id)"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-slate-300 text-[#034485] focus:ring-[#034485]"
                                    @change="toggleStudent(student.student_id)"
                                />
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ student.student_name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ student.student_id_number || 'No student number' }}
                                        <span v-if="student.position">• {{ student.position }}</span>
                                        <span v-if="student.jersey_number">• Jersey {{ student.jersey_number }}</span>
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Title</label>
                            <input
                                v-model="form.title"
                                type="text"
                                class="mt-2 w-full rounded-2xl border border-[#034485]/18 bg-[#f7fbff] px-4 py-3 text-sm text-slate-900 focus:border-[#034485] focus:outline-none focus:ring-2 focus:ring-[#034485]/10"
                                placeholder="e.g. Free Throw Drill"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Category</label>
                            <select
                                v-model="form.category"
                                class="mt-2 w-full rounded-2xl border border-[#034485]/18 bg-[#f7fbff] px-4 py-3 text-sm text-slate-900 focus:border-[#034485] focus:outline-none focus:ring-2 focus:ring-[#034485]/10"
                            >
                                <option v-for="category in categories" :key="category" :value="category">
                                    {{ category }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="5"
                                class="mt-2 w-full rounded-2xl border border-[#034485]/18 bg-[#f7fbff] px-4 py-3 text-sm text-slate-900 focus:border-[#034485] focus:outline-none focus:ring-2 focus:ring-[#034485]/10"
                                placeholder="Add the specific drill, recovery task, tactical instruction, or follow-up activity."
                            />
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="rounded-full border border-[#034485]/18 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-[#f5f8ff]"
                            @click="resetForm"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-[#034485] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#033a70] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="submitting || !canManage"
                            @click="submit"
                        >
                            {{ submitting ? 'Saving...' : 'Save Requirement' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-3xl border border-[#034485]/18 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Schedule Details</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ schedule.title }}</h2>
                            <p class="mt-1 text-sm text-slate-600">{{ team.team_name }} • {{ team.sport }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#034485]/18 bg-[#f4f8ff] px-4 py-3 text-sm text-slate-700">
                            <p><span class="font-semibold text-[#034485]">When:</span> {{ formatPHT(schedule.start) }}</p>
                            <p class="mt-1"><span class="font-semibold text-[#034485]">Until:</span> {{ formatPHT(schedule.end) }}</p>
                            <p class="mt-1"><span class="font-semibold text-[#034485]">Venue:</span> {{ schedule.venue || '-' }}</p>
                        </div>
                    </div>
                    <div v-if="schedule.notes" class="mt-4 rounded-2xl border border-[#034485]/14 bg-[#f8fbff] px-4 py-3 text-sm text-slate-600">
                        {{ schedule.notes }}
                    </div>
                </div>

                <div class="rounded-3xl border border-[#034485]/18 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Assigned Requirements</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ groupedRequirements.length }} records</h2>
                        </div>
                        <div class="rounded-full bg-[#edf4ff] px-3 py-1 text-xs font-semibold text-[#034485]">
                            Coach: {{ coach?.full_name || 'Assigned Coach' }}
                        </div>
                    </div>

                    <div v-if="groupedRequirements.length === 0" class="mt-4 rounded-2xl border border-dashed border-[#034485]/28 bg-[#f8fbff] px-4 py-8 text-center text-sm text-slate-500">
                        No training requirements have been assigned to this schedule yet.
                    </div>

                    <div v-else class="mt-4 overflow-hidden rounded-2xl border border-[#034485]/15">
                        <div class="max-h-[32rem] overflow-auto">
                            <table class="w-full min-w-[760px] text-left text-sm">
                                <thead class="bg-[#034485] text-white">
                                    <tr>
                                        <th class="px-4 py-3">Student-Athlete</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Title</th>
                                        <th class="px-4 py-3">Description</th>
                                        <th class="px-4 py-3">Created</th>
                                        <th v-if="canManage" class="px-4 py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="requirement in groupedRequirements"
                                        :key="requirement.id"
                                        class="border-t border-[#034485]/10 align-top even:bg-[#f9fbff]"
                                    >
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-900">{{ requirement.student_name }}</p>
                                            <p class="text-xs text-slate-500">{{ requirement.student_id_number || 'No student number' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full bg-[#edf4ff] px-2.5 py-1 text-[11px] font-semibold text-[#034485]">
                                                {{ requirement.category }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-slate-800">{{ requirement.title }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ requirement.description || 'No description provided.' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-500">{{ requirement.created_at || '-' }}</td>
                                        <td v-if="canManage" class="px-4 py-3">
                                            <button
                                                type="button"
                                                class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-50"
                                                @click="removeRequirement(requirement.id)"
                                            >
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
