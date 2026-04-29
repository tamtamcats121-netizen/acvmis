<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { useSportColors } from '@/composables/useSportColors'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import { resolveTeamAvatarUrl as teamAvatarUrl } from '@/utils/media'

defineOptions({
    layout: AdminDashboard,
})

type TeamRow = {
    id: number
    team_name: string
    team_avatar: string | null
    sport?: { id: number; name: string } | null
    year?: string | number | null
    coach?: { id: number; first_name?: string; last_name?: string } | null
    assistantCoach?: { id: number; first_name?: string; last_name?: string } | null
    players_count: number
    max_players: number
    roster_health: { key: string; label: string; tone: string }
    is_archived: boolean
    archived_at?: string | null
}

const props = defineProps<{
    teams: {
        data: TeamRow[]
        meta: {
            current_page: number
            last_page: number
            per_page: number
            total: number
        }
    }
    filters: Record<string, any>
    options: {
        sports: { id: number; name: string }[]
        years: (string | number)[]
    }
    readOnly: boolean
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()

const showFilters = ref(false)
const filters = reactive({
    search: String(props.filters?.search ?? ''),
    sport_id: props.filters?.sport_id ? String(props.filters.sport_id) : '',
    year: props.filters?.year ? String(props.filters.year) : '',
    sort: String(props.filters?.sort ?? 'updated_at'),
    direction: String(props.filters?.direction ?? 'desc'),
})

const expandedTeamIds = ref<number[]>([])
const rosterCache = ref<Record<number, any[]>>({})
const rosterLoading = ref<Record<number, boolean>>({})
const restoreDialogOpen = ref(false)
const pendingRestoreTeam = ref<TeamRow | null>(null)

const archivedCount = computed(() => props.teams.meta.total)
const totalPlayers = computed(() => props.teams.data.reduce((sum, team) => sum + Number(team.players_count || 0), 0))

function buildQuery(extra: Record<string, any> = {}) {
    return {
        search: filters.search || undefined,
        sport_id: filters.sport_id || undefined,
        year: filters.year || undefined,
        sort: filters.sort,
        direction: filters.direction,
        ...extra,
    }
}

function reload(extra: Record<string, any> = {}) {
    router.get('/teams/archived', buildQuery(extra), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function clearFilters() {
    filters.search = ''
    filters.sport_id = ''
    filters.year = ''
    filters.sort = 'updated_at'
    filters.direction = 'desc'
    showFilters.value = false
    reload()
}

function goBackToActiveTeams() {
    router.get('/teams')
}

function fullName(person: any): string {
    const first = person?.first_name ?? ''
    const last = person?.last_name ?? ''
    const out = `${first} ${last}`.trim()
    return out || 'N/A'
}

function initialsFromText(value?: string | null) {
    return String(value ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'NA'
}

function formatArchivedAt(value: string | null | undefined) {
    if (!value) return 'Unknown date'
    const date = new Date(value)
    return date.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })
}

function formatMeasure(value: string | number | null | undefined, unit: string) {
    if (value === null || value === undefined) return '-'
    const text = String(value).trim()
    if (!text) return '-'
    if (/[a-zA-Z]/.test(text)) return text
    return `${text} ${unit}`
}

function rosterToneClasses(tone?: string | null) {
    const value = String(tone ?? '').toLowerCase()
    if (value.includes('rose') || value.includes('red')) return 'bg-rose-100 text-rose-700'
    if (value.includes('amber') || value.includes('yellow')) return 'bg-amber-100 text-amber-700'
    if (value.includes('emerald') || value.includes('green')) return 'bg-emerald-100 text-emerald-700'
    return 'bg-slate-100 text-slate-700'
}

function printRoster(teamId: number) {
    window.open(`/teams/${teamId}/print`, '_blank')
}

async function toggleTeamExpanded(teamId: number) {
    if (expandedTeamIds.value.includes(teamId)) {
        expandedTeamIds.value = expandedTeamIds.value.filter((id) => id !== teamId)
        return
    }

    expandedTeamIds.value.push(teamId)
    if (rosterCache.value[teamId]) return

    rosterLoading.value = { ...rosterLoading.value, [teamId]: true }
    try {
        const response = await fetch(`/teams/${teamId}/roster`, { credentials: 'same-origin' })
        const data = await response.json()
        rosterCache.value = {
            ...rosterCache.value,
            [teamId]: data.players ?? [],
        }
    } catch {
        rosterCache.value = {
            ...rosterCache.value,
            [teamId]: [],
        }
    } finally {
        rosterLoading.value = { ...rosterLoading.value, [teamId]: false }
    }
}

function promptRestore(team: TeamRow) {
    if (props.readOnly) return
    pendingRestoreTeam.value = team
    restoreDialogOpen.value = true
}

function confirmRestore() {
    if (!pendingRestoreTeam.value) return
    restoreDialogOpen.value = false
    router.post(`/teams/${pendingRestoreTeam.value.id}/reactivate`, {}, { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-300/80 bg-[linear-gradient(135deg,_#0f172a_0%,_#334155_55%,_#475569_100%)] p-6 text-white shadow-[0_24px_60px_-36px_rgba(15,23,42,0.55)]">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white/85">
                            Archived Teams
                        </span>
                        <span class="rounded-full border border-amber-200/40 bg-amber-100/10 px-3 py-1 text-xs font-semibold text-amber-100">
                            Historical / Read-Only
                        </span>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">Archived Team Records</h1>
                        <p class="mt-2 max-w-2xl text-sm text-white/75">
                            Review past varsity team compositions, inspect archived rosters, print historical summaries, and restore a team when it needs to return to active operations.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/65">Archived Teams</p>
                        <p class="mt-1 text-2xl font-bold">{{ archivedCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/65">Players Listed</p>
                        <p class="mt-1 text-2xl font-bold">{{ totalPlayers }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/65">Workflow</p>
                        <p class="mt-1 text-sm font-semibold">Restore When Needed</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Search Archived Teams</h2>
                    <p class="text-sm text-slate-500">Filter by sport, year, or coach to locate older team records quickly.</p>
                </div>
                <button
                    type="button"
                    class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="goBackToActiveTeams"
                >
                    Back to Active Teams
                </button>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Search archived teams, sport, year, or coach"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:flex-1"
                    @keyup.enter="reload()"
                />
                <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="reload()">
                    Search
                </button>
                <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="showFilters = !showFilters">
                    Filters
                </button>
            </div>

            <div v-if="showFilters" class="mt-4 grid grid-cols-1 gap-3 border-t border-slate-200 pt-4 md:grid-cols-2 lg:grid-cols-4">
                <select v-model="filters.sport_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Sports</option>
                    <option v-for="sport in options.sports" :key="sport.id" :value="String(sport.id)">{{ sport.name }}</option>
                </select>
                <select v-model="filters.year" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Years</option>
                    <option v-for="y in options.years" :key="String(y)" :value="String(y)">{{ y }}</option>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select v-model="filters.sort" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="updated_at">Sort: Updated</option>
                        <option value="team_name">Sort: Team Name</option>
                        <option value="year">Sort: Year</option>
                        <option value="sport">Sort: Sport</option>
                        <option value="players">Sort: Players</option>
                    </select>
                    <select v-model="filters.direction" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="desc">Desc</option>
                        <option value="asc">Asc</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="reload()">Apply</button>
                    <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="clearFilters">Reset</button>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div v-if="teams.data.length === 0" class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-500 shadow-sm">
                No archived teams found for the selected filters.
            </div>

            <article
                v-for="team in teams.data"
                :key="team.id"
                class="rounded-3xl border border-[#034485]/15 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="relative h-20 w-20 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                            <img :src="teamAvatarUrl(team.team_avatar)" alt="Team avatar" class="h-full w-full object-cover" />
                            <span class="absolute left-2 top-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                Archived
                            </span>
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                    :style="{ backgroundColor: sportColor(team.sport?.name ?? ''), color: sportTextColor(team.sport?.name ?? '') }"
                                >
                                    {{ sportLabel(team.sport?.name ?? '') }}
                                </span>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ team.year || 'No Year' }}
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="rosterToneClasses(team.roster_health?.tone)">
                                    {{ team.roster_health?.label || 'Archived roster' }}
                                </span>
                            </div>

                            <h3 class="mt-3 truncate text-2xl font-bold text-slate-900">{{ team.team_name }}</h3>
                            <p class="mt-2 text-sm text-slate-500">Archived on {{ formatArchivedAt(team.archived_at) }}</p>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Head Coach</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">
                                            {{ initialsFromText(fullName(team.coach)) }}
                                        </div>
                                        <p class="min-w-0 truncate text-sm font-semibold text-slate-900">{{ fullName(team.coach) }}</p>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Assistant</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">
                                            {{ initialsFromText(fullName(team.assistantCoach)) }}
                                        </div>
                                        <p class="min-w-0 truncate text-sm font-semibold text-slate-900">{{ fullName(team.assistantCoach) }}</p>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Players</p>
                                    <p class="mt-2 text-lg font-bold text-slate-900">{{ team.players_count }} / {{ team.max_players }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-500">State</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">Read-only archived record</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="toggleTeamExpanded(team.id)"
                        >
                            {{ expandedTeamIds.includes(team.id) ? 'Hide Roster' : 'View Roster' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-[#034485]/25 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#034485]/5"
                            @click="printRoster(team.id)"
                        >
                            Print Roster
                        </button>
                        <button
                            v-if="!readOnly"
                            type="button"
                            class="rounded-full border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100"
                            @click="promptRestore(team)"
                        >
                            Reactivate Team
                        </button>
                    </div>
                </div>

                <div
                    v-if="expandedTeamIds.includes(team.id)"
                    class="mt-5 rounded-3xl border border-slate-200 bg-slate-50/70 p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Archived Roster</p>
                            <h4 class="mt-1 text-lg font-semibold text-slate-900">Read-Only Player List</h4>
                        </div>
                    </div>

                    <p v-if="rosterLoading[team.id]" class="mt-4 text-sm text-slate-500">Loading roster...</p>

                    <div
                        v-else-if="(rosterCache[team.id] || []).length"
                        class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="player in rosterCache[team.id]"
                            :key="player.id"
                            class="rounded-2xl border border-slate-200 bg-white p-4"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ player.name }}
                                </p>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                    {{ player.student_id_number || 'No ID' }}
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Height</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatMeasure(player.height, 'cm') }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Weight</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatMeasure(player.weight, 'kg') }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Jersey</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ player.jersey_number || '-' }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Position</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ player.athlete_position || '-' }}</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <p v-else class="mt-4 text-sm text-slate-500">No players assigned.</p>
                </div>
            </article>
        </section>

        <ConfirmDialog
            :open="restoreDialogOpen"
            title="Restore Team"
            :description="pendingRestoreTeam ? `Restore ${pendingRestoreTeam.team_name} to active teams?` : ''"
            confirm-text="Restore"
            confirm-variant="default"
            @update:open="restoreDialogOpen = $event"
            @confirm="confirmRestore"
        />
    </div>
</template>
