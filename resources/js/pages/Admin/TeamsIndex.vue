<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import EmptyResultsState from '@/components/ui/EmptyResultsState.vue'
import SearchFilterPanel from '@/components/ui/SearchFilterPanel.vue'
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
    issue_count: number
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

const showFilters = ref(false)
const filters = reactive({
    search: String(props.filters?.search ?? ''),
    sport_id: props.filters?.sport_id ? String(props.filters.sport_id) : '',
    year: props.filters?.year ? String(props.filters.year) : '',
    coach_status: String(props.filters?.coach_status ?? 'all'),
    roster_status: String(props.filters?.roster_status ?? 'all'),
    sort: String(props.filters?.sort ?? 'updated_at'),
    direction: String(props.filters?.direction ?? 'desc'),
})

const activeFilterCount = computed(() => {
    let count = 0
    if (filters.search.trim()) count++
    if (filters.sport_id) count++
    if (filters.year) count++
    if (filters.coach_status !== 'all') count++
    if (filters.roster_status !== 'all') count++
    if (filters.sort !== 'updated_at' || filters.direction !== 'desc') count++
    return count
})

const teamActionDialogOpen = ref(false)
const pendingTeamAction = ref<{ type: 'archive' | 'reactivate'; team: TeamRow } | null>(null)
const { sportColor, sportTextColor, sportLabel } = useSportColors()
const { isDarkMode } = useTheme()
const currentSeasonYear = new Date().getFullYear()
let searchDebounce: ReturnType<typeof setTimeout> | null = null
let suppressAutoReload = false

const seasonSnapshots = computed(() => {
    const buckets: Record<string, TeamRow[]> = {}
    for (const team of props.teams.data) {
        const yearLabel = team.year ? String(team.year) : 'Unassigned Year'
        if (!buckets[yearLabel]) buckets[yearLabel] = []
        buckets[yearLabel].push(team)
    }

    return Object.entries(buckets)
        .map(([year, teams]) => {
            const rosterReview = teams.filter((t) => !t.is_archived && t.roster_health?.key !== 'complete').length
            const staffingReview = teams.filter((t) => !t.is_archived && (!t.coach?.id || !t.assistantCoach?.id)).length
            const archivedCount = teams.filter((t) => t.is_archived).length
            const priorSeasonCount = teams.filter((t) => isPriorSeasonTeam(t)).length

            return {
                year,
                teams,
                kpis: {
                    total: teams.length,
                    rosterReview,
                    staffingReview,
                    archivedCount,
                    priorSeasonCount,
                },
            }
        })
        .sort((a, b) => {
            if (a.year === 'Unassigned Year') return 1
            if (b.year === 'Unassigned Year') return -1
            return Number(b.year) - Number(a.year)
        })
})

function fullName(person: any): string {
    const first = person?.first_name ?? ''
    const last = person?.last_name ?? ''
    const out = `${first} ${last}`.trim()
    return out || 'N/A'
}

function normalizedTeamYear(team: TeamRow): number | null {
    const year = Number(team.year)
    return Number.isFinite(year) ? year : null
}

function isPriorSeasonTeam(team: TeamRow): boolean {
    const year = normalizedTeamYear(team)
    return !team.is_archived && year !== null && year < currentSeasonYear
}

function teamCardClass(team: TeamRow) {
    if (team.is_archived) {
        return 'border-slate-300 bg-slate-200/90 text-slate-700'
    }

    if (isPriorSeasonTeam(team)) {
        return 'border-slate-300 bg-slate-50 text-slate-800'
    }

    return 'border-[#034485]/35 bg-white text-slate-900'
}

function teamAvatarClass(team: TeamRow) {
    if (team.is_archived) {
        return 'border-slate-300 bg-slate-100'
    }

    if (isPriorSeasonTeam(team)) {
        return 'border-slate-300 bg-slate-50'
    }

    return 'border-[#034485]/12 bg-[#f8fbff]'
}

function formatArchivedAt(value: string | null | undefined) {
    if (!value) return '-'

    const date = new Date(value)
    return date.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

function buildQuery(extra: Record<string, any> = {}) {
    return {
        search: filters.search || undefined,
        sport_id: filters.sport_id || undefined,
        year: filters.year || undefined,
        coach_status: filters.coach_status,
        roster_status: filters.roster_status,
        sort: filters.sort,
        direction: filters.direction,
        tab: 'all-teams',
        ...extra,
    }
}

function reload(extra: Record<string, any> = {}) {
    router.get('/teams', buildQuery(extra), {
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
    filters.coach_status = 'all'
    filters.roster_status = 'all'
    filters.sort = 'updated_at'
    filters.direction = 'desc'
    showFilters.value = false
    reload()
    setTimeout(() => {
        suppressAutoReload = false
    }, 0)
}

function goToCreateTeam() {
    router.get('/teams/create')
}

function goToRosterPage(teamId: number) {
    router.get(`/teams/${teamId}/view-roster`)
}

function goToTeamSchedules(teamId: number) {
    router.get('/operations', {
        tab: 'calendar',
        team_id: teamId,
    })
}

function archiveTeam(team: TeamRow) {
    if (!props.canArchive) return
    pendingTeamAction.value = { type: 'archive', team }
    teamActionDialogOpen.value = true
}

function reactivateTeam(team: TeamRow) {
    if (!props.canArchive) return
    pendingTeamAction.value = { type: 'reactivate', team }
    teamActionDialogOpen.value = true
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
    () => [filters.sport_id, filters.year, filters.coach_status, filters.roster_status, filters.sort, filters.direction],
    () => {
        if (suppressAutoReload) return
        reload({ page: 1 })
    },
)

function confirmTeamAction() {
    const action = pendingTeamAction.value
    if (!action) return

    teamActionDialogOpen.value = false
    if (action.type === 'archive') {
        router.post(`/teams/${action.team.id}/archive`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                showAppToast(`${action.team.team_name} archived successfully.`, 'success', {
                    summary: 'Team Archived',
                })
            },
            onError: () => {
                showAppToast('Unable to archive the team right now.', 'error', {
                    summary: 'Archive Failed',
                })
            },
        })
        return
    }

    router.post(`/teams/${action.team.id}/reactivate`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showAppToast(`${action.team.team_name} reactivated successfully.`, 'success', {
                summary: 'Team Reactivated',
            })
        },
        onError: () => {
            showAppToast('Unable to reactivate the team right now.', 'error', {
                summary: 'Reactivation Failed',
            })
        },
    })
}

function goToPage(page: number) {
    if (page < 1 || page > props.teams.meta.last_page) return
    reload({ page })
}

</script>

<template>
    <div class="space-y-5">
        <section class="w-full rounded-3xl border border-[#02315f] bg-[#034485] px-5 py-4 text-white shadow-[0_18px_40px_-28px_rgba(3,68,133,0.9)]">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Admin Workspace</p>
            <h1 class="mt-2 text-xl font-semibold">Team Monitoring</h1>
            <p class="mt-1 text-sm leading-relaxed text-white/85">
                Review varsity teams by year, monitor roster health and staffing coverage, and keep archived records inside their original season groups.
            </p>
        </section>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-2 sm:max-w-xs">
                <button
                    v-if="!props.readOnly"
                    type="button"
                    class="inline-flex w-full items-center justify-center rounded-full bg-[#034485] px-3 py-2 text-center text-[11px] font-semibold text-white transition hover:bg-[#02315f]"
                    @click="goToCreateTeam"
                >
                    Create Team
                </button>
            </div>

            <div class="mt-4">
                <SearchFilterPanel
                    v-model="filters.search"
                    placeholder="Search by team, sport, year, or coach"
                    :filter-count="activeFilterCount"
                    :show-filters="showFilters"
                    :show-submit="false"
                    :show-clear="activeFilterCount > 0"
                    @toggle-filters="showFilters = !showFilters"
                    @clear="clearFilters"
                >
                    <template #filters>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                            <select v-model="filters.sport_id" class="rounded-xl border border-[#034485]/20 px-3 py-2 text-sm">
                                <option value="">All Sports</option>
                                <option v-for="sport in options.sports" :key="sport.id" :value="String(sport.id)">{{ sport.name }}</option>
                            </select>
                            <select v-model="filters.year" class="rounded-xl border border-[#034485]/20 px-3 py-2 text-sm">
                                <option value="">All Years</option>
                                <option v-for="y in options.years" :key="String(y)" :value="String(y)">{{ y }}</option>
                            </select>
                            <select v-model="filters.coach_status" class="rounded-xl border border-[#034485]/20 px-3 py-2 text-sm">
                                <option value="all">All Staffing</option>
                                <option value="complete_staff">Fully Staffed</option>
                                <option value="missing_assistant">Needs Staff Support</option>
                            </select>
                            <select v-model="filters.roster_status" class="rounded-xl border border-[#034485]/20 px-3 py-2 text-sm">
                                <option value="all">All Roster Sizes</option>
                                <option value="complete">Within Capacity</option>
                                <option value="needs_players">Below Capacity</option>
                                <option value="over_limit">Over Capacity</option>
                            </select>
                            <div class="grid grid-cols-2 gap-2 md:col-span-2">
                                <select v-model="filters.sort" class="rounded-xl border border-[#034485]/20 px-3 py-2 text-sm">
                                    <option value="updated_at">Sort: Updated</option>
                                    <option value="team_name">Sort: Team Name</option>
                                    <option value="year">Sort: Year</option>
                                    <option value="sport">Sort: Sport</option>
                                    <option value="players">Sort: Players</option>
                                </select>
                                <select v-model="filters.direction" class="rounded-xl border border-[#034485]/20 px-3 py-2 text-sm">
                                    <option value="desc">Newest First</option>
                                    <option value="asc">Oldest First</option>
                                </select>
                            </div>
                        </div>
                    </template>
                </SearchFilterPanel>
            </div>
        </div>

        <section class="page-card rounded-xl border border-[#034485]/45 bg-white">
            <div v-if="seasonSnapshots.length === 0" class="p-6">
                <EmptyResultsState
                    title="No teams matched your filters"
                    description="Try adjusting the team, sport, year, or staffing filters to broaden the results."
                />
            </div>

            <div v-else class="space-y-5 p-4">
                <article
                    v-for="season in seasonSnapshots"
                    :key="season.year"
                    class="page-card rounded-lg border border-slate-200 bg-slate-50 p-3"
                >
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-lg font-bold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ season.year }}</h3>
                            <p class="text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">{{ season.kpis.total }} teams</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5 text-xs">
                            <span class="rounded-full px-2 py-0.5" :class="isDarkMode ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-700'">Teams: {{ season.kpis.total }}</span>
                            <span v-if="season.kpis.priorSeasonCount > 0" class="rounded-full px-2 py-0.5" :class="isDarkMode ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-700'">Prior Season: {{ season.kpis.priorSeasonCount }}</span>
                            <span v-if="season.kpis.archivedCount > 0" class="rounded-full px-2 py-0.5" :class="isDarkMode ? 'bg-slate-950 text-white' : 'bg-slate-300 text-slate-700'">Archived: {{ season.kpis.archivedCount }}</span>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="team in season.teams"
                            :key="team.id"
                            class="page-card rounded-3xl border p-5"
                            :class="teamCardClass(team)"
                        >
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <span
                                    class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold"
                                    :style="{ backgroundColor: sportColor(team.sport?.name ?? ''), color: sportTextColor(team.sport?.name ?? '') }"
                                >
                                    {{ sportLabel(team.sport?.name ?? '') }}
                                </span>
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <p v-if="isPriorSeasonTeam(team)" class="rounded-full bg-slate-300 px-2 py-0.5 text-[11px] font-medium text-slate-700">Prior Season</p>
                                    <p v-if="team.is_archived" class="rounded-full bg-slate-400 px-2 py-0.5 text-[11px] font-medium text-white">Archived</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <img
                                    :src="teamAvatarUrl(team.team_avatar)"
                                    alt="Team Avatar"
                                    loading="lazy"
                                    decoding="async"
                                    class="h-14 w-14 rounded-2xl border object-cover"
                                    :class="teamAvatarClass(team)"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xl font-semibold text-slate-900">{{ team.team_name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ team.sport?.name || 'No sport' }}</p>
                                    <p class="mt-2 text-sm text-slate-700">Head: {{ fullName(team.coach) }}</p>
                                    <p class="text-sm text-slate-500">Assistant: {{ fullName(team.assistantCoach) }}</p>
                                </div>
                            </div>

                            <p class="mt-3 text-sm text-slate-600">Players: {{ team.players_count }} / {{ team.max_players }}</p>
                            <p v-if="team.is_archived" class="mt-1 text-xs text-slate-500">Archived on {{ formatArchivedAt(team.archived_at) }}</p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full border border-[#034485]/25 bg-[#034485] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#02315f]"
                                    @click="goToRosterPage(team.id)"
                                >
                                    View Roster
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-[#034485]/25 bg-white px-3 py-1.5 text-xs font-semibold text-[#034485] hover:bg-[#eef5ff]"
                                    @click="goToTeamSchedules(team.id)"
                                >
                                    Schedules
                                </button>
                                <button
                                    v-if="!readOnly && !team.is_archived"
                                    type="button"
                                    class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700"
                                    @click="archiveTeam(team)"
                                >
                                    Archive
                                </button>
                                <button
                                    v-else-if="!readOnly && team.is_archived"
                                    type="button"
                                    class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700"
                                    @click="reactivateTeam(team)"
                                >
                                    Reactivate
                                </button>
                            </div>
                        </article>
                    </div>
                </article>
            </div>
        </section>

        <section v-if="props.teams.meta.last_page > 1" class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600">
                    Page {{ props.teams.meta.current_page }} of {{ props.teams.meta.last_page }} • {{ props.teams.meta.total }} teams
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 disabled:opacity-40"
                        :disabled="props.teams.meta.current_page <= 1"
                        @click="goToPage(props.teams.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 disabled:opacity-40"
                        :disabled="props.teams.meta.current_page >= props.teams.meta.last_page"
                        @click="goToPage(props.teams.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>

        <ConfirmDialog
            :open="teamActionDialogOpen"
            :title="pendingTeamAction?.type === 'archive' ? 'Archive Team' : 'Reactivate Team'"
            :description="pendingTeamAction
                ? (pendingTeamAction.type === 'archive'
                    ? `Archive ${pendingTeamAction.team.team_name}? This can be restored later.`
                    : `Reactivate ${pendingTeamAction.team.team_name}?`)
                : ''"
            :confirm-text="pendingTeamAction?.type === 'archive' ? 'Archive' : 'Reactivate'"
            :confirm-variant="pendingTeamAction?.type === 'archive' ? 'destructive' : 'default'"
            @update:open="teamActionDialogOpen = $event"
            @confirm="confirmTeamAction"
        />
    </div>
</template>
