<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import { showAppToast } from '@/composables/useAppToast'
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

type PlayerOption = {
    id: number
    name: string
    student_id_number: string | null
    academic_level_label: string | null
    course_or_strand: string | null
    email: string | null
    avatar: string | null
    status: string | null
    height: string | null
    weight: string | null
    is_available: boolean
    assigned_team_id: number | null
    assigned_team_name: string | null
    assigned_sport_name: string | null
    unavailable_reason: string | null
}

const props = defineProps<{
    team: {
        id: number
        team_name: string
        team_avatar: string | null
        sport: { id: number; name: string } | null
        year: string | number | null
        description: string | null
        players: TeamPlayerRow[]
    }
    players: PlayerOption[]
    maxPlayers: number
    readOnly: boolean
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()

const search = ref('')
const availabilityFilter = ref<'all' | 'available' | 'assigned'>('all')
const addPendingId = ref<number | null>(null)
const removePendingId = ref<number | null>(null)

const teamStudentIds = computed(() => new Set(props.team.players.map((player) => player.student_id)))

const filteredPlayers = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.players.filter((player) => {
        if (availabilityFilter.value === 'available' && !(player.is_available || teamStudentIds.value.has(player.id))) {
            return false
        }

        if (availabilityFilter.value === 'assigned' && (player.is_available || teamStudentIds.value.has(player.id))) {
            return false
        }

        if (!query) return true

        return [
            player.name,
            player.student_id_number,
            player.academic_level_label,
            player.course_or_strand,
            player.email,
            player.assigned_team_name,
            player.assigned_sport_name,
        ]
            .map((value) => String(value ?? '').toLowerCase())
            .some((value) => value.includes(query))
    })
})

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

function canAddToTeam(player: PlayerOption) {
    return player.is_available || teamStudentIds.value.has(player.id)
}

function addPlayer(player: PlayerOption) {
    router.post(`/teams/${props.team.id}/players/${player.id}`, {}, {
        preserveScroll: true,
        onStart: () => {
            addPendingId.value = player.id
        },
        onSuccess: () => {
            showAppToast(`${player.name} added to the team.`, 'success', {
                summary: 'Player Assignment',
            })
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {}).flat()[0]
            showAppToast(String(firstError || 'Unable to add the student-athlete right now.'), 'error', {
                summary: 'Player Assignment',
            })
        },
        onFinish: () => {
            addPendingId.value = null
        },
    })
}

function removePlayer(player: TeamPlayerRow) {
    router.delete(`/teams/${props.team.id}/players/${player.student_id}`, {
        preserveScroll: true,
        onStart: () => {
            removePendingId.value = player.student_id
        },
        onSuccess: () => {
            showAppToast(`${player.name} removed from the team.`, 'success', {
                summary: 'Player Assignment',
            })
        },
        onError: () => {
            showAppToast('Unable to remove the student-athlete right now.', 'error', {
                summary: 'Player Assignment',
            })
        },
        onFinish: () => {
            removePendingId.value = null
        },
    })
}
</script>

<template>
    <Head :title="`${team.team_name} Players`" />

    <div class="space-y-6">
        <div class="space-y-1">
            <Link :href="`/teams/${team.id}/view-roster`" class="text-sm font-medium text-[#034485] hover:text-[#033a70]">
                ← Back to View Roster
            </Link>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Player assignment manager</p>
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
                            Add and remove student-athletes from a dedicated team selection page with direct actions.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Current Roster</p>
                        <p class="mt-1 text-xl font-bold">{{ team.players.length }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Roster Limit</p>
                        <p class="mt-1 text-xl font-bold">{{ maxPlayers }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Open Slots</p>
                        <p class="mt-1 text-xl font-bold">{{ Math.max(maxPlayers - team.players.length, 0) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
            <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Current Players</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Assigned Student-Athletes</h2>
                    <p class="mt-1 text-sm text-slate-500">Review the current roster and remove players directly when needed.</p>
                </div>

                <div class="mt-5 space-y-4">
                    <article
                        v-for="player in team.players"
                        :key="player.id"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700">
                                    <img v-if="player.avatar" :src="userAvatarUrl(player.avatar)" alt="Player photo" class="h-full w-full object-cover" />
                                    <span v-else>{{ initialsFromText(player.name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-base font-semibold text-slate-900">{{ player.name }}</p>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="playerStatusTone(player.player_status)">
                                            {{ String(player.player_status || 'active').toUpperCase() }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ player.student_id_number || '-' }} • {{ player.academic_level_label || 'No level set' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ player.course_or_strand || player.email || 'No extra details' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Height: {{ formatMeasure(player.height, 'cm') }} • Weight: {{ formatMeasure(player.weight, 'kg') }}</p>
                                </div>
                            </div>

                            <button
                                v-if="!readOnly"
                                type="button"
                                class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-60"
                                :disabled="removePendingId === player.student_id"
                                @click="removePlayer(player)"
                            >
                                {{ removePendingId === player.student_id ? 'Removing...' : 'Remove from Team' }}
                            </button>
                        </div>
                    </article>

                    <div v-if="team.players.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        No student-athletes are assigned to this team yet.
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Available Student-Athletes</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Select Players</h2>
                        <p class="mt-1 text-sm text-slate-500">Search and filter the student pool, then add eligible players directly to this team.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name, ID, course"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:w-64"
                        />
                        <select v-model="availabilityFilter" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="all">All Students</option>
                            <option value="available">Available</option>
                            <option value="assigned">Assigned Elsewhere</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 grid gap-4">
                    <article
                        v-for="player in filteredPlayers"
                        :key="player.id"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700">
                                    <img v-if="player.avatar" :src="userAvatarUrl(player.avatar)" alt="Student photo" class="h-full w-full object-cover" />
                                    <span v-else>{{ initialsFromText(player.name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-base font-semibold text-slate-900">{{ player.name }}</p>
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                            :class="canAddToTeam(player) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                        >
                                            {{ canAddToTeam(player) ? 'AVAILABLE' : 'ASSIGNED' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ player.student_id_number || '-' }} • {{ player.academic_level_label || 'No level set' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ player.course_or_strand || player.email || 'No extra details' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Status: {{ player.status || 'Not set' }} • Height: {{ formatMeasure(player.height, 'cm') }} • Weight: {{ formatMeasure(player.weight, 'kg') }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Current assignment:
                                        {{ player.assigned_team_name ? `${player.assigned_team_name}${player.assigned_sport_name ? ` • ${player.assigned_sport_name}` : ''}` : 'No active team assignment' }}
                                    </p>
                                    <p v-if="player.unavailable_reason" class="mt-1 text-xs text-amber-700">{{ player.unavailable_reason }}</p>
                                </div>
                            </div>

                            <button
                                v-if="!readOnly"
                                type="button"
                                class="rounded-full bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#033a70] disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!canAddToTeam(player) || addPendingId === player.id || teamStudentIds.has(player.id)"
                                @click="addPlayer(player)"
                            >
                                {{
                                    teamStudentIds.has(player.id)
                                        ? 'Already on Team'
                                        : addPendingId === player.id
                                            ? 'Adding...'
                                            : 'Add to Team'
                                }}
                            </button>
                        </div>
                    </article>

                    <div v-if="filteredPlayers.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        No student-athletes matched the current search or filter.
                    </div>
                </div>
            </section>
        </section>
    </div>
</template>
