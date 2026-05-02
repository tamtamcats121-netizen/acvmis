<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

import BackLinkButton from '@/components/ui/BackLinkButton.vue'
import { showAppToast } from '@/composables/useAppToast'
import { useSportColors } from '@/composables/useSportColors'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'

defineOptions({
    layout: CoachDashboard,
})

type AthleteRow = {
    student_id: number
    student_id_number: string | null
    name: string
    attendance_status: string
    wellness: {
        injury_observed: boolean
        injury_notes: string | null
        fatigue_level: number | null
        performance_condition: 'excellent' | 'good' | 'fair' | 'poor' | null
        remarks: string | null
        log_id: number | null
    }
}

type ScheduleRow = {
    id: number
    title: string
    type: string
    venue: string
    start: string
    end: string
}

type FormState = {
    injury_observed: boolean
    injury_notes: string
    fatigue_level: string
    performance_condition: 'excellent' | 'good' | 'fair' | 'poor'
    remarks: string
}

const props = defineProps<{
    team: { id: number; team_name: string; sport: string } | null
    schedule: ScheduleRow
    athletes: AthleteRow[]
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()
const savingKey = ref<string | null>(null)
const selectedAthleteId = ref<number | null>(null)

function buildRowForms(rows: AthleteRow[]) {
    return Object.fromEntries(
        rows.map((row) => [
            row.student_id,
            {
                injury_observed: !!row.wellness?.injury_observed,
                injury_notes: row.wellness?.injury_notes ?? '',
                fatigue_level: row.wellness?.fatigue_level ? String(row.wellness.fatigue_level) : '3',
                performance_condition: row.wellness?.performance_condition ?? 'good',
                remarks: row.wellness?.remarks ?? '',
            } satisfies FormState,
        ]),
    )
}

const rowForms = ref<Record<number, FormState>>(buildRowForms(props.athletes || []))

watch(
    () => props.athletes,
    (rows) => {
        rowForms.value = buildRowForms(rows || [])
        selectedAthleteId.value = null
    },
    { immediate: true },
)

const selectedAthlete = computed(() => (
    props.athletes.find((row) => row.student_id === selectedAthleteId.value) ?? null
))

const summary = computed(() => {
    const forms = props.athletes.map((row) => rowForms.value[row.student_id]).filter(Boolean)
    const fatigueValues = forms.map((form) => Number(form.fatigue_level)).filter((value) => Number.isFinite(value))

    return {
        total: props.athletes.length,
        logged: props.athletes.filter((row) => row.wellness?.log_id).length,
        injury: forms.filter((form) => form.injury_observed).length,
        needsReview: forms.filter((form) => (
            form.injury_observed
            || Number(form.fatigue_level) >= 4
            || ['fair', 'poor'].includes(form.performance_condition)
        )).length,
        averageFatigue: fatigueValues.length
            ? (fatigueValues.reduce((sum, value) => sum + value, 0) / fatigueValues.length).toFixed(1)
            : '-',
    }
})

function saveRow(studentId: number) {
    const form = rowForms.value[studentId]
    if (!form) return

    savingKey.value = `${props.schedule.id}:${studentId}`

    router.post('/coach/wellness', {
        schedule_id: props.schedule.id,
        student_id: studentId,
        injury_observed: form.injury_observed,
        injury_notes: form.injury_notes,
        fatigue_level: Number(form.fatigue_level),
        performance_condition: form.performance_condition,
        remarks: form.remarks,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast('Performance update saved.', 'success', {
                summary: 'Performance Monitoring',
            })
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {}).flat()[0]

            showAppToast(String(firstError || 'Unable to save performance update.'), 'error', {
                summary: 'Performance Monitoring',
            })
        },
        onFinish: () => {
            savingKey.value = null
        },
    })
}

function statusLabel(status: string) {
    if (status === 'present') return 'Present'
    if (status === 'late') return 'Late'
    return status
}

function statusTone(status: string) {
    if (status === 'late') return 'bg-amber-100 text-amber-800 border-amber-200'
    return 'bg-emerald-100 text-emerald-700 border-emerald-200'
}

function scheduleTypeLabel(type: string) {
    if (type === 'practice') return 'Practice'
    if (type === 'game') return 'Game'
    return type
}

function formatScheduleDate(value?: string | null) {
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

function athleteNeedsAttention(studentId: number) {
    const form = rowForms.value[studentId]
    if (!form) return false

    return form.injury_observed
        || Number(form.fatigue_level) >= 4
        || ['fair', 'poor'].includes(form.performance_condition)
}

function athleteInitials(name: string) {
    return name
        .split(',')
        .flatMap((part) => part.trim().split(/\s+/))
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('')
}

function openAthleteReview(studentId: number) {
    selectedAthleteId.value = studentId
}

function cardMotion(order: number) {
    return { '--card-order': String(order) }
}
</script>

<template>
    <Head title="Evaluate Athlete Performance" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3">
            <BackLinkButton href="/coach/wellness" label="Back to Performance Monitoring" />
            <section class="page-card rounded-3xl border border-[#034485]/35 bg-[#034485] p-5 text-white" :style="cardMotion(1)">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Player review</p>
                <h1 class="mt-2 text-2xl font-bold text-white">Evaluate Athlete Performance</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-white/85">
                    Evaluate present and late athletes for this completed session, then save post-session performance records per athlete.
                </p>
            </section>
        </div>

        <section class="page-card rounded-[2rem] border border-[#034485]/30 bg-white p-4 shadow-[0_24px_60px_-40px_rgba(3,68,133,0.35)]" :style="cardMotion(2)">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold shadow-sm"
                            :style="{ backgroundColor: sportColor(team?.sport ?? ''), color: sportTextColor(team?.sport ?? '') }"
                        >
                            {{ sportLabel(team?.sport ?? '') }}
                        </span>
                        <span class="rounded-full border border-[#034485]/20 bg-[#f7fbff] px-3 py-1 text-xs font-semibold text-[#034485]">
                            {{ team?.team_name }}
                        </span>
                    </div>

                    <div>
                        <h2 class="text-lg font-bold text-slate-900">{{ schedule.title }}</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ scheduleTypeLabel(schedule.type) }} • {{ schedule.venue || '-' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ formatScheduleDate(schedule.start) }} to {{ formatScheduleDate(schedule.end) }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] px-3 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#034485]">Athletes</p>
                        <p class="mt-1 text-lg font-bold text-[#034485]">{{ summary.total }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Saved</p>
                        <p class="mt-1 text-lg font-bold text-emerald-700">{{ summary.logged }}</p>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Injuries</p>
                        <p class="mt-1 text-lg font-bold text-rose-700">{{ summary.injury }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800">Fatigue</p>
                        <p class="mt-1 text-lg font-bold text-amber-800">{{ summary.averageFatigue }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="props.athletes.length === 0" class="page-card rounded-3xl border border-dashed border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-500" :style="cardMotion(3)">
            No present or late athletes are available for this session.
        </div>

        <div v-else class="space-y-4">
            <div class="grid gap-4 xl:grid-cols-2">
                <button
                    v-for="row in props.athletes"
                    :key="row.student_id"
                    type="button"
                    class="page-card rounded-2xl border bg-[#f7fbff] p-5 text-left shadow-[0_18px_48px_-40px_rgba(3,68,133,0.35)] transition"
                    :class="selectedAthleteId === row.student_id
                        ? 'border-[#034485] shadow-[0_24px_60px_-42px_rgba(3,68,133,0.4)]'
                        : athleteNeedsAttention(row.student_id)
                            ? 'border-amber-200 shadow-[0_24px_60px_-42px_rgba(245,158,11,0.35)]'
                            : 'border-[#034485]/15'"
                    :style="cardMotion(4 + props.athletes.findIndex((entry) => entry.student_id === row.student_id))"
                    @click="openAthleteReview(row.student_id)"
                >
                    <div class="pointer-events-none -mx-5 -mt-5 mb-4 h-12 rounded-t-2xl bg-gradient-to-r from-[#034485] via-[#0b5aa6] to-[#034485]/90"></div>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#034485]/15 bg-white text-sm font-bold text-[#034485]">
                                {{ athleteInitials(row.name) }}
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :class="statusTone(row.attendance_status)"
                                    >
                                        {{ statusLabel(row.attendance_status) }}
                                    </span>
                                    <span
                                        v-if="row.wellness?.log_id"
                                        class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700"
                                    >
                                        Saved
                                    </span>
                                </div>
                                <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ row.name }}</h3>
                                <p class="mt-1 text-xs text-slate-600">{{ row.student_id_number || '-' }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-2">
                            <div v-if="athleteNeedsAttention(row.student_id)" class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-800">
                                Needs follow-up
                            </div>
                            <span
                                class="text-xs font-semibold"
                                :class="selectedAthleteId === row.student_id ? 'text-[#034485]' : 'text-slate-400'"
                            >
                                {{ selectedAthleteId === row.student_id ? 'Opened' : 'Open Review' }}
                            </span>
                        </div>
                    </div>
                </button>
            </div>

            <article
                v-if="selectedAthlete"
                class="page-card rounded-2xl border border-[#034485]/30 bg-white p-5 shadow-[0_20px_54px_-42px_rgba(3,68,133,0.35)]"
                :style="cardMotion(5 + props.athletes.length)"
            >
                <div class="-mx-5 -mt-5 mb-5 rounded-t-2xl bg-[#034485] px-5 py-4 text-white">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-white/75">Athlete Review</p>
                    <h3 class="mt-1 text-lg font-semibold text-white">{{ selectedAthlete.name }}</h3>
                    <p class="mt-1 text-xs text-white/80">{{ selectedAthlete.student_id_number || '-' }}</p>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#034485]/15 bg-[#f7fbff] text-sm font-bold text-[#034485]">
                            {{ athleteInitials(selectedAthlete.name) }}
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                    :class="statusTone(selectedAthlete.attendance_status)"
                                >
                                    {{ statusLabel(selectedAthlete.attendance_status) }}
                                </span>
                                <span
                                    v-if="selectedAthlete.wellness?.log_id"
                                    class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700"
                                >
                                    Saved
                                </span>
                            </div>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ selectedAthlete.name }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ selectedAthlete.student_id_number || '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid gap-4">
                    <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Injury Observed</p>
                                <p class="mt-1 text-sm text-slate-600">Mark this if the athlete showed a confirmed injury observation during or after the session.</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border px-3 py-2 text-xs font-semibold transition"
                                :class="rowForms[selectedAthlete.student_id].injury_observed
                                    ? 'border-rose-600 bg-rose-600 text-white'
                                    : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'"
                                @click="rowForms[selectedAthlete.student_id].injury_observed = !rowForms[selectedAthlete.student_id].injury_observed"
                            >
                                {{ rowForms[selectedAthlete.student_id].injury_observed ? 'Injury Observed' : 'No Injury Observed' }}
                            </button>
                        </div>

                        <textarea
                            v-model="rowForms[selectedAthlete.student_id].injury_notes"
                            rows="2"
                            class="mt-3 w-full rounded-lg border border-[#034485]/20 bg-white px-3 py-2 text-sm text-slate-900"
                            :disabled="!rowForms[selectedAthlete.student_id].injury_observed"
                            placeholder="Add injury notes if observed"
                        />
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl border border-[#034485]/15 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Fatigue Level</p>
                            <p class="mt-1 text-sm text-slate-600">Select the athlete’s post-session fatigue from 1 to 5.</p>
                            <select
                                v-model="rowForms[selectedAthlete.student_id].fatigue_level"
                                class="mt-3 w-full rounded-lg border border-[#034485]/20 bg-[#f7fbff] px-3 py-2.5 text-sm text-slate-900"
                            >
                                <option value="1">1 - Very Low</option>
                                <option value="2">2 - Low</option>
                                <option value="3">3 - Moderate</option>
                                <option value="4">4 - High</option>
                                <option value="5">5 - Very High</option>
                            </select>
                        </div>

                        <div class="rounded-2xl border border-[#034485]/15 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Performance Condition</p>
                            <p class="mt-1 text-sm text-slate-600">Capture the athlete’s performance condition during or after the session.</p>
                            <select
                                v-model="rowForms[selectedAthlete.student_id].performance_condition"
                                class="mt-3 w-full rounded-lg border border-[#034485]/20 bg-[#f7fbff] px-3 py-2.5 text-sm text-slate-900"
                            >
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#034485]/15 bg-white p-4">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Coach Remarks</label>
                        <textarea
                            v-model="rowForms[selectedAthlete.student_id].remarks"
                            rows="3"
                            class="mt-3 w-full rounded-lg border border-[#034485]/20 bg-[#f7fbff] px-3 py-2 text-sm text-slate-900"
                            placeholder="Add coach remarks, condition notes, or post-session evaluation comments"
                        />
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">
                        Save the athlete’s performance update once the post-session evaluation is complete.
                    </p>
                    <button
                        type="button"
                        class="rounded-lg bg-[#034485] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#033a70] disabled:cursor-not-allowed disabled:bg-slate-300"
                        :disabled="savingKey === `${schedule.id}:${selectedAthlete.student_id}`"
                        @click="saveRow(selectedAthlete.student_id)"
                    >
                        {{ savingKey === `${schedule.id}:${selectedAthlete.student_id}` ? 'Saving...' : 'Submit Performance Update' }}
                    </button>
                </div>
            </article>
        </div>
    </div>
</template>

<style scoped>
.page-card {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
    animation: coach-wellness-review-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: calc(var(--card-order, 0) * 45ms);
    will-change: transform, opacity;
}

@keyframes coach-wellness-review-card-rise {
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
    .page-card {
        animation: none;
        opacity: 1;
        transform: none;
    }
}
</style>
