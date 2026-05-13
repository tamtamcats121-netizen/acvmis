<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import DatePicker from 'primevue/datepicker'
import { computed, ref, watch } from 'vue'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { showAppToast } from '@/composables/useAppToast'
import { useTheme } from '@/composables/useTheme'
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

const props = defineProps<{
    periods: Period[]
    selectedPeriodId: number | null
}>()

const selectedPeriodId = ref<number | null>(props.selectedPeriodId)
const periodSaving = ref(false)
const deletePeriodDialogOpen = ref(false)
const periodErrors = ref<Record<string, string>>({})
const { isDarkMode } = useTheme()
const noticeDialog = ref<{ open: boolean; title: string; description: string }>({
    open: false,
    title: '',
    description: '',
})
const schoolYear = ref('')
const term = ref<'1st_sem' | '2nd_sem' | 'summer'>('1st_sem')
const startsOn = ref('')
const endsOn = ref('')

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

const startsOnModel = computed<Date | null>({
    get: () => parseFilterDate(startsOn.value),
    set: (value) => {
        startsOn.value = formatFilterDate(value)
    },
})

const endsOnModel = computed<Date | null>({
    get: () => parseFilterDate(endsOn.value),
    set: (value) => {
        endsOn.value = formatFilterDate(value)
    },
})

const selectedPeriod = computed(() =>
    (props.periods || []).find((p) => p.id === selectedPeriodId.value) || null,
)

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

function openEvaluationsWorkspace() {
    router.get('/academics/evaluations', {
        period_id: selectedPeriodId.value || undefined,
    })
}

</script>

<template>
    <Head title="Academics Workspace" />

    <div class="space-y-5">
        <section class="academics-workspace-hero page-card rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Academic Oversight</p>
                    <h1 class="mt-2 text-2xl font-bold">Academics Workspace</h1>
                    <p class="mt-2 text-sm text-white/85">Period settings, evaluation records, and quick access to the academic review workflows.</p>
                </div>
            </div>
        </section>

        <section class="space-y-5">
            <section class="page-card rounded-3xl border border-[#034485]/35 bg-white p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Current Period</p>
                        <h2 class="mt-1 text-sm font-semibold text-slate-800">Active Academic Period</h2>
                    </div>
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
                        Period History
                    </button>
                </div>

                <div class="mt-4 rounded-2xl border border-[#034485]/35 bg-white p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs text-slate-500">The current active period is shown here. Closed periods move to Period History.</p>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                            <button
                                v-if="selectedPeriod"
                                type="button"
                                class="hidden rounded-full border border-rose-200 bg-rose-500 px-2 py-0.5 text-xs font-semibold text-white transition hover:bg-rose-600 sm:inline-flex sm:min-h-0 sm:items-center sm:justify-center"
                                title="Delete period"
                                aria-label="Delete active academic period"
                                @click="openDeletePeriod"
                            >
                                X
                            </button>
                        </div>
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
                                class="rounded-full border border-[#034485]/20 bg-[#034485]/5 px-3 py-1 text-xs font-semibold text-[#034485] hover:bg-[#034485]/10"
                                @click="openEvaluationsWorkspace()"
                            >
                                Open Evaluation Workspace
                            </button>
                        </div>
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-500 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-600 sm:hidden"
                            title="Delete period"
                            aria-label="Delete active academic period"
                            @click="openDeletePeriod"
                        >
                            Delete Period
                        </button>
                    </div>
                    <div v-else class="mt-3 rounded-2xl border border-dashed border-[#034485]/25 bg-[#034485]/5 p-4 text-sm text-slate-500">
                        No open or upcoming academic period is available right now. Closed periods are kept in Period History.
                    </div>
                </div>
            </section>

            <section class="page-card rounded-3xl border border-[#034485]/35 bg-white p-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">New Period</p>
                    <h2 class="mt-1 text-sm font-semibold text-slate-800">Create Academic Period</h2>
                    <p class="mt-1 text-xs text-slate-500">Add a new submission window for an upcoming school year or term.</p>
                </div>

                <div class="mt-4 rounded-2xl border border-[#034485]/35 bg-white p-4">
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
                            <DatePicker
                                v-model="startsOnModel"
                                showIcon
                                iconDisplay="input"
                                inputClass="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                                :pt="{
                                    pcInputText: {
                                        root: {
                                            class: isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-900',
                                        },
                                    },
                                }"
                                panelClass="text-sm"
                                placeholder="Start date"
                                dateFormat="yy-mm-dd"
                                :manualInput="false"
                            />
                            <p v-if="periodErrors.starts_on" class="mt-1 text-xs text-rose-600">{{ periodErrors.starts_on }}</p>
                        </div>
                        <div>
                            <DatePicker
                                v-model="endsOnModel"
                                showIcon
                                iconDisplay="input"
                                inputClass="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                                :pt="{
                                    pcInputText: {
                                        root: {
                                            class: isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-900',
                                        },
                                    },
                                }"
                                panelClass="text-sm"
                                placeholder="End date"
                                dateFormat="yy-mm-dd"
                                :manualInput="false"
                            />
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
            </section>
        </section>

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
