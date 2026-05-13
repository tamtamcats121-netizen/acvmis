<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { reactive, ref, watch } from 'vue'

import BackLinkButton from '@/components/ui/BackLinkButton.vue'
import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { showAppToast } from '@/composables/useAppToast'
import { useSportColors } from '@/composables/useSportColors'
import { useTheme } from '@/composables/useTheme'
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
    canArchive?: boolean
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()
const { isDarkMode } = useTheme()

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
let searchDebounce: ReturnType<typeof setTimeout> | null = null
let suppressAutoReload = false

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
    suppressAutoReload = true
    filters.search = ''
    filters.sport_id = ''
    filters.year = ''
    filters.sort = 'updated_at'
    filters.direction = 'desc'
    showFilters.value = false
    reload()
    setTimeout(() => {
        suppressAutoReload = false
    }, 0)
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
    if (!props.canArchive) return
    pendingRestoreTeam.value = team
    restoreDialogOpen.value = true
}

function goToPage(page: number) {
    if (page < 1 || page > props.teams.meta.last_page) return
    reload({ page })
}

function confirmRestore() {
    if (!pendingRestoreTeam.value) return
    const team = pendingRestoreTeam.value
    restoreDialogOpen.value = false
    router.post(`/teams/${team.id}/reactivate`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast(`${team.team_name} reactivated successfully.`, 'success', {
                summary: 'Team Reactivated',
            })
        },
        onError: () => {
            showAppToast('Unable to reactivate the archived team right now.', 'error', {
                summary: 'Reactivation Failed',
            })
        },
    })
}

watch(
    () => filters.search,
    () => {
        if (suppressAutoReload) return
        if (searchDebounce) clearTimeout(searchDebounce)
        searchDebounce = setTimeout(() => reload({ page: 1 }), 250)
    },
)

watch(
    () => [filters.sport_id, filters.year, filters.sort, filters.direction],
    () => {
        if (suppressAutoReload) return
        reload({ page: 1 })
    },
)
</script>

<template>
    <div class="space-y-6">
        <div class="flex justify-start">
            <BackLinkButton href="/teams" label="Back to Active Teams" />
        </div>

        <section class="page-card rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <div class="space-y-3">
                <div>
                    <h1 class="text-3xl font-bold">Archived Team Records</h1>
                    <p class="mt-2 max-w-2xl text-sm text-white/75">
                        Review past varsity team compositions, inspect archived rosters, print historical summaries, and restore a team when it needs to return to active operations.
                    </p>
                </div>
            </div>
        </section>

        <section
            class="page-card rounded-3xl border p-5"
            :class="isDarkMode ? 'border-[#034485]/40 bg-[#0f172a]' : 'border-[#034485]/35 bg-white'"
        >
            <div>
                <div>
                    <h2 class="text-lg font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Search Archived Teams</h2>
                    <p class="text-sm" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">Filter by sport, year, or coach to locate older team records quickly.</p>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Search archived teams, sport, year, or coach"
                    class="w-full rounded-xl border px-3 py-2 text-sm sm:flex-1"
                    :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500' : 'border-slate-300 bg-white text-slate-900'"
                />
                <button
                    type="button"
                    class="rounded-xl border px-3 py-2 text-sm font-medium"
                    :class="isDarkMode ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-300 text-slate-700 hover:bg-slate-50'"
                    @click="showFilters = !showFilters"
                >
                    Filters
                </button>
            </div>

            <div v-if="showFilters" class="mt-4 grid grid-cols-1 gap-3 border-t pt-4 md:grid-cols-2 lg:grid-cols-4" :class="isDarkMode ? 'border-slate-800' : 'border-slate-200'">
                <select v-model="filters.sport_id" class="rounded-xl border px-3 py-2 text-sm" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-900'">
                    <option value="">All Sports</option>
                    <option v-for="sport in options.sports" :key="sport.id" :value="String(sport.id)">{{ sport.name }}</option>
                </select>
                <select v-model="filters.year" class="rounded-xl border px-3 py-2 text-sm" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-900'">
                    <option value="">All Years</option>
                    <option v-for="y in options.years" :key="String(y)" :value="String(y)">{{ y }}</option>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select v-model="filters.sort" class="rounded-xl border px-3 py-2 text-sm" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-900'">
                        <option value="updated_at">Sort: Updated</option>
                        <option value="team_name">Sort: Team Name</option>
                        <option value="year">Sort: Year</option>
                        <option value="sport">Sort: Sport</option>
                        <option value="players">Sort: Players</option>
                    </select>
                    <select v-model="filters.direction" class="rounded-xl border px-3 py-2 text-sm" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-900'">
                        <option value="desc">Desc</option>
                        <option value="asc">Asc</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded-xl border px-3 py-2 text-sm font-medium" :class="isDarkMode ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-300 text-slate-700 hover:bg-slate-50'" @click="clearFilters">Reset</button>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div
                v-if="teams.data.length === 0"
                class="page-card rounded-3xl border border-dashed px-5 py-12 text-center text-sm"
                :class="isDarkMode ? 'border-[#034485]/30 bg-[#0f172a] text-slate-400' : 'border-[#034485]/25 bg-white text-slate-500'"
            >
                No archived teams found for the selected filters.
            </div>

            <article
                v-for="team in teams.data"
                :key="team.id"
                class="page-card rounded-3xl border border-[#034485]/35 bg-white p-5 text-slate-900"
            >
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="relative h-20 w-20 overflow-hidden rounded-2xl border border-[#034485]/12 bg-[#f8fbff]">
                            <img :src="teamAvatarUrl(team.team_avatar)" alt="Team avatar" loading="lazy" decoding="async" class="h-full w-full object-cover" />
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

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
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
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-full border border-[#034485]/25 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#eef5ff]"
                            @click="toggleTeamExpanded(team.id)"
                        >
                            {{ expandedTeamIds.includes(team.id) ? 'Hide Roster' : 'View Roster' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-white/18 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#EAF4FF]"
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
                    class="page-card mt-5 rounded-3xl border p-4"
                    :class="'border-white/12 bg-[#023567]'"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/68">Archived Roster</p>
                            <h4 class="mt-1 text-lg font-semibold text-white">Read-Only Player List</h4>
                        </div>
                    </div>

                    <p v-if="rosterLoading[team.id]" class="mt-4 text-sm text-white/72">Loading roster...</p>

                    <div
                        v-else-if="(rosterCache[team.id] || []).length"
                        class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="player in rosterCache[team.id]"
                            :key="player.id"
                            class="page-card rounded-2xl border border-white/14 bg-white/10 p-4 text-white backdrop-blur-sm"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-white">
                                    {{ player.name }}
                                </p>
                                <span class="rounded-full bg-white/12 px-2.5 py-1 text-[11px] font-semibold text-white/78">
                                    {{ player.student_id_number || 'No ID' }}
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div class="rounded-xl border border-white/12 bg-white/10 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wide text-white/60">Height</p>
                                    <p class="mt-1 text-sm font-semibold text-white">{{ formatMeasure(player.height, 'cm') }}</p>
                                </div>
                                <div class="rounded-xl border border-white/12 bg-white/10 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wide text-white/60">Weight</p>
                                    <p class="mt-1 text-sm font-semibold text-white">{{ formatMeasure(player.weight, 'kg') }}</p>
                                </div>
                                <div class="rounded-xl border border-white/12 bg-white/10 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wide text-white/60">Jersey</p>
                                    <p class="mt-1 text-sm font-semibold text-white">{{ player.jersey_number || '-' }}</p>
                                </div>
                                <div class="rounded-xl border border-white/12 bg-white/10 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wide text-white/60">Position</p>
                                    <p class="mt-1 text-sm font-semibold text-white">{{ player.athlete_position || '-' }}</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <p v-else class="mt-4 text-sm text-white/72">No players assigned.</p>
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

        <section v-if="props.teams.meta.last_page > 1" class="page-card rounded-3xl border p-4" :class="isDarkMode ? 'border-[#034485]/40 bg-[#0f172a]' : 'border-[#034485]/35 bg-white'">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                    Page {{ props.teams.meta.current_page }} of {{ props.teams.meta.last_page }} • {{ props.teams.meta.total }} teams
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-xl border px-4 py-2 text-sm font-semibold disabled:opacity-40"
                        :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-700'"
                        :disabled="props.teams.meta.current_page <= 1"
                        @click="goToPage(props.teams.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border px-4 py-2 text-sm font-semibold disabled:opacity-40"
                        :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-slate-300 bg-white text-slate-700'"
                        :disabled="props.teams.meta.current_page >= props.teams.meta.last_page"
                        @click="goToPage(props.teams.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
