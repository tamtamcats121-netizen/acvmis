<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import { useSportColors } from '@/composables/useSportColors'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'

defineOptions({
    layout: CoachDashboard,
})

type ScheduleRow = {
    id: number
    title: string
    type: string
    venue: string
    start: string
    end: string
}

const props = defineProps<{
    team: { id: number; team_name: string; sport: string } | null
    schedules: ScheduleRow[]
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()

const teamTone = computed(() => {
    const base = sportColor(props.team?.sport ?? '')

    return {
        borderColor: `${base}55`,
    }
})

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

function openReview(scheduleId: number) {
    router.get(`/coach/wellness/${scheduleId}/review`)
}

function cardMotion(order: number) {
    return { '--card-order': String(order) }
}
</script>

<template>
    <Head title="Wellness Monitoring" />

    <div class="space-y-6">
        <section class="page-card rounded-3xl border border-[#034485]/35 bg-[#034485] p-5 text-white" :style="cardMotion(1)">
        <div class="flex flex-col gap-2">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Coach evaluation workflow</p>
            <h1 class="text-2xl font-bold text-white">Wellness Monitoring</h1>
            <p class="max-w-3xl text-sm leading-6 text-white/80">
                Choose a completed practice or game, then open a dedicated player review page for wellness evaluation.
            </p>
        </div>
        </section>

        <div v-if="!team" class="page-card rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500" :style="cardMotion(2)">
            You are not assigned to a team yet.
        </div>

        <div v-else class="space-y-6">
            <section
                class="page-card rounded-[2rem] border bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.98),rgba(248,250,252,0.96))] p-4 shadow-[0_24px_60px_-40px_rgba(15,23,42,0.45)]"
                :style="[{ borderColor: teamTone.borderColor }, cardMotion(2)]"
            >
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold shadow-sm"
                            :style="{ backgroundColor: sportColor(team.sport), color: sportTextColor(team.sport) }"
                        >
                            {{ sportLabel(team.sport) }}
                        </span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ team.team_name }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Completed Sessions</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Open the session you want to review, then continue to player wellness evaluation.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                            {{ schedules.length }} completed {{ schedules.length === 1 ? 'session' : 'sessions' }} ready for review
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">Practice & Game Sessions</h3>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ schedules.length }}</span>
                </div>

                <div v-if="schedules.length === 0" class="page-card rounded-xl border border-slate-200 bg-white py-10 text-center text-sm text-slate-500" :style="cardMotion(3)">
                    No completed practice or game schedules are available yet.
                </div>

                <div v-else class="space-y-4">
                    <article
                        v-for="(schedule, index) in schedules"
                        :key="schedule.id"
                        class="page-card relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4"
                        :style="cardMotion(4 + index)"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-base font-semibold text-slate-900">{{ schedule.title }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ scheduleTypeLabel(schedule.type) }} • {{ schedule.venue || '-' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ formatScheduleDate(schedule.start) }} to {{ formatScheduleDate(schedule.end) }}
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                    Ready for Review
                                </span>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-md bg-[#1f2937] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#111827]"
                                @click="openReview(schedule.id)"
                            >
                                Review Players
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.page-card {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
    animation: coach-wellness-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: calc(var(--card-order, 0) * 45ms);
    will-change: transform, opacity;
}

@keyframes coach-wellness-card-rise {
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
