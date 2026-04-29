<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'

import { useSportColors } from '@/composables/useSportColors'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import { resolveTeamAvatarUrl as teamAvatarUrl, resolveUserAvatarUrl as userAvatarUrl } from '@/utils/media'

defineOptions({
    layout: AdminDashboard,
})

type TeamPlayerRow = {
    id: number
    student_id: number
    name: string
    student_id_number: string | null
    academic_level_label: string | null
    course_or_strand: string | null
    email: string | null
    avatar: string | null
    height: string | null
    weight: string | null
    jersey_number: string | null
    athlete_position: string | null
    player_status: string
    manual_inactive: boolean
}

const props = defineProps<{
    team: {
        id: number
        team_name: string
        team_avatar: string | null
        sport: { id: number; name: string } | null
        year: string | number | null
        description: string | null
        coach: { id: number; user_id: number | null; name: string; email: string | null; avatar: string | null; coach_status: string | null } | null
        assistantCoach: { id: number; user_id: number | null; name: string; email: string | null; avatar: string | null; coach_status: string | null } | null
        players: TeamPlayerRow[]
    }
    readOnly: boolean
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()

function initialsFromText(value?: string | null) {
    return String(value ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'NA'
}

function playerStatusTone(status?: string | null) {
    const value = String(status ?? 'active').toLowerCase()
    if (value === 'inactive') return 'bg-slate-200 text-slate-700'
    if (value === 'injured') return 'bg-amber-100 text-amber-700'
    if (value === 'suspended') return 'bg-red-100 text-red-700'
    return 'bg-emerald-100 text-emerald-700'
}

function formatMeasure(value: string | null | undefined, unit: string) {
    const text = String(value ?? '').trim()
    if (!text) return '-'
    if (/[a-zA-Z]/.test(text)) return text
    return `${text} ${unit}`
}

function goToSchedules() {
    router.get('/operations', {
        tab: 'calendar',
        team_id: props.team.id,
    })
}

function archiveTeam() {
    router.post(`/teams/${props.team.id}/archive`, {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="`${team.team_name} Roster`" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <Link href="/teams" class="text-sm font-medium text-[#034485] hover:text-[#033a70]">
                    ← Back to Teams
                </Link>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Admin team workspace</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full border border-[#034485]/30 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#034485]/5"
                    @click="goToSchedules"
                >
                    Schedules
                </button>
                <button
                    v-if="!readOnly"
                    type="button"
                    class="rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100"
                    @click="archiveTeam"
                >
                    Archive
                </button>
            </div>
        </div>

        <section class="rounded-3xl bg-[#034485] p-6 text-white shadow-[0_24px_60px_-36px_rgba(3,68,133,0.55)]">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <div class="h-20 w-20 overflow-hidden rounded-2xl border border-white/20 bg-white/10">
                        <img :src="teamAvatarUrl(team.team_avatar)" alt="Team avatar" class="h-full w-full object-cover" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :style="{ backgroundColor: sportColor(team.sport?.name ?? ''), color: sportTextColor(team.sport?.name ?? '') }"
                            >
                                {{ sportLabel(team.sport?.name ?? '') }}
                            </span>
                            <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90">
                                {{ team.year || 'No Year' }}
                            </span>
                        </div>
                        <h1 class="mt-3 text-3xl font-bold">{{ team.team_name }}</h1>
                        <p class="mt-2 max-w-2xl text-sm text-white/80">
                            Review the current roster at a glance, then open dedicated coach or player management pages for assignment changes.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Players</p>
                        <p class="mt-1 text-xl font-bold">{{ team.players.length }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Head Coach</p>
                        <p class="mt-1 text-sm font-semibold">{{ team.coach?.name || 'Unassigned' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Assistant</p>
                        <p class="mt-1 text-sm font-semibold">{{ team.assistantCoach?.name || 'Unassigned' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Workflow</p>
                        <p class="mt-1 text-sm font-semibold">Dedicated Pages</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Roster Actions</h2>
                    <p class="text-sm text-slate-500">Open the focused assignment pages to manage staff and student-athletes separately.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link
                        :href="`/teams/${team.id}/manage-coaches`"
                        class="rounded-full bg-[#034485] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#033a70]"
                    >
                        Manage Coaches
                    </Link>
                    <Link
                        :href="`/teams/${team.id}/manage-players`"
                        class="rounded-full border border-[#034485]/25 bg-white px-5 py-2.5 text-sm font-semibold text-[#034485] hover:bg-[#034485]/5"
                    >
                        Manage Players
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
            <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Current Coaches</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Assigned Staff</h2>
                        <p class="mt-1 text-sm text-slate-500">Head and assistant coach assignments for this team.</p>
                    </div>
                    <Link
                        :href="`/teams/${team.id}/manage-coaches`"
                        class="rounded-full border border-[#034485]/20 bg-[#034485]/5 px-3 py-1.5 text-xs font-semibold text-[#034485] hover:bg-[#034485]/10"
                    >
                        Edit
                    </Link>
                </div>

                <div class="mt-5 space-y-4">
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Head Coach</p>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-sm font-bold text-slate-700">
                                <img v-if="team.coach?.avatar" :src="userAvatarUrl(team.coach.avatar)" alt="Head coach photo" class="h-full w-full object-cover" />
                                <span v-else>{{ initialsFromText(team.coach?.name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ team.coach?.name || 'Unassigned' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ team.coach?.email || 'No email available' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Status: {{ team.coach?.coach_status || 'Not set' }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Assistant Coach</p>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-sm font-bold text-slate-700">
                                <img v-if="team.assistantCoach?.avatar" :src="userAvatarUrl(team.assistantCoach.avatar)" alt="Assistant coach photo" class="h-full w-full object-cover" />
                                <span v-else>{{ initialsFromText(team.assistantCoach?.name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ team.assistantCoach?.name || 'Unassigned' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ team.assistantCoach?.email || 'No assistant coach assigned' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Status: {{ team.assistantCoach?.coach_status || 'Not set' }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Current Players</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Assigned Student-Athletes</h2>
                        <p class="mt-1 text-sm text-slate-500">The roster below shows the players currently assigned to this team.</p>
                    </div>
                    <Link
                        :href="`/teams/${team.id}/manage-players`"
                        class="rounded-full border border-[#034485]/20 bg-[#034485]/5 px-3 py-1.5 text-xs font-semibold text-[#034485] hover:bg-[#034485]/10"
                    >
                        Edit
                    </Link>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <article
                        v-for="player in team.players"
                        :key="player.id"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700">
                                <img v-if="player.avatar" :src="userAvatarUrl(player.avatar)" alt="Player photo" class="h-full w-full object-cover" />
                                <span v-else>{{ initialsFromText(player.name) }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-base font-semibold text-slate-900">{{ player.name }}</p>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="playerStatusTone(player.player_status)">
                                        {{ String(player.player_status || 'active').toUpperCase() }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ player.student_id_number || '-' }} • {{ player.academic_level_label || 'No level set' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ player.course_or_strand || player.email || 'No extra details' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Height</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatMeasure(player.height, 'cm') }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Weight</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatMeasure(player.weight, 'kg') }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Jersey</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ player.jersey_number || '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Position</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ player.athlete_position || '-' }}</p>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="team.players.length === 0"
                        class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500 md:col-span-2"
                    >
                        No student-athletes are assigned to this team yet.
                    </div>
                </div>
            </section>
        </section>
    </div>
</template>
