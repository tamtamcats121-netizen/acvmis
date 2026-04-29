<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

import ConfirmDialog from '@/components/ui/dialog/ConfirmDialog.vue'
import { showAppToast } from '@/composables/useAppToast'
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
    conflicts: {
        coach: any[]
        player: any[]
    }
    readOnly: boolean
    teamChangeRequests: Array<{
        id: number
        title: string
        message: string
        is_read: boolean
        published_at: string | null
        requested_by?: string | null
    }>
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
    if (filters.sport_id) count++
    if (filters.year) count++
    if (filters.coach_status !== 'all') count++
    if (filters.roster_status !== 'all') count++
    if (filters.sort !== 'updated_at' || filters.direction !== 'desc') count++
    return count
})

const unreadRequestCount = computed(() => props.teamChangeRequests.filter((req) => !req.is_read).length)

const teamActionDialogOpen = ref(false)
const pendingTeamAction = ref<{ type: 'archive' | 'reactivate'; team: TeamRow } | null>(null)
const { sportColor, sportTextColor, sportLabel } = useSportColors()

const seasonSnapshots = computed(() => {
    const buckets: Record<string, TeamRow[]> = {}
    for (const team of props.teams.data) {
        const yearLabel = team.year ? String(team.year) : 'Unassigned Year'
        if (!buckets[yearLabel]) buckets[yearLabel] = []
        buckets[yearLabel].push(team)
    }

    return Object.entries(buckets)
        .map(([year, teams]) => {
            const complete = teams.filter((t) => t.roster_health?.key === 'complete').length
            const needsPlayers = teams.filter((t) => t.roster_health?.key === 'needs_players').length
            const overLimit = teams.filter((t) => t.roster_health?.key === 'over_limit').length
            const conflictTeams = teams.filter((t) => (t.issue_count ?? 0) > 0).length
            const missingAssistant = teams.filter((t) => !t.is_archived && !t.assistantCoach?.id).length

            return {
                year,
                teams,
                kpis: {
                    total: teams.length,
                    complete,
                    needsPlayers,
                    overLimit,
                    conflictTeams,
                    missingAssistant,
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

function rosterToneClass(tone: string) {
    if (tone === 'success') return 'bg-emerald-100 text-emerald-700'
    if (tone === 'danger') return 'bg-red-100 text-red-700'
    return 'bg-amber-100 text-amber-700'
}

function issueToneClass(count: number) {
    if (count > 2) return 'bg-red-100 text-red-700'
    if (count > 0) return 'bg-amber-100 text-amber-700'
    return 'bg-emerald-100 text-emerald-700'
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
    filters.search = ''
    filters.sport_id = ''
    filters.year = ''
    filters.coach_status = 'all'
    filters.roster_status = 'all'
    filters.sort = 'updated_at'
    filters.direction = 'desc'
    showFilters.value = false
    reload()
}

function goToCreateTeam() {
    router.get('/teams/create')
}

function goToArchivedTeams() {
    router.get('/teams/archived')
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
    if (props.readOnly) return
    pendingTeamAction.value = { type: 'archive', team }
    teamActionDialogOpen.value = true
}

function reactivateTeam(team: TeamRow) {
    if (props.readOnly) return
    pendingTeamAction.value = { type: 'reactivate', team }
    teamActionDialogOpen.value = true
}

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

function markRequestRead(id: number) {
    router.put(`/announcements/${id}/read`, {}, { preserveScroll: true })
}

function approveRequest(id: number) {
    router.post(`/teams/requests/${id}/approve`, {}, {
        preserveScroll: true,
        onError: () => {
            showAppToast('Unable to approve the team change request.', 'error', {
                summary: 'Team Change Request',
            })
        },
    })
}

function rejectRequest(id: number) {
    router.post(`/teams/requests/${id}/reject`, {}, {
        preserveScroll: true,
        onError: () => {
            showAppToast('Unable to reject the team change request.', 'error', {
                summary: 'Team Change Request',
            })
        },
    })
}

function parseRequestMessage(message: string) {
    const lines = String(message ?? '').split('\n').map((line) => line.trim()).filter(Boolean)
    const data = {
        team: '',
        requestedBy: '',
        target: '',
        notes: '',
        extra: [] as string[],
    }

    for (const line of lines) {
        const lower = line.toLowerCase()
        if (lower.startsWith('team:')) data.team = line.slice(5).trim()
        else if (lower.startsWith('requested by:')) data.requestedBy = line.slice(13).trim()
        else if (lower.startsWith('target:')) data.target = line.slice(7).trim()
        else if (lower.startsWith('notes:')) data.notes = line.slice(6).trim()
        else data.extra.push(line)
    }

    return data
}

function searchTeamFromRequest(teamName: string) {
    if (!teamName) return
    filters.search = teamName
    showFilters.value = false
    reload()
}

function formatTimestamp(value: string | null) {
    if (!value) return '-'
    const date = new Date(value)
    return date.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })
}

</script>

<template>
    <div class="space-y-5">
        <section class="rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <button
                        type="button"
                        class="rounded-md bg-[#1f2937] px-4 py-2 text-sm font-semibold text-white hover:bg-[#334155]"
                        @click="goToCreateTeam"
                    >
                        Create Team
                    </button>
                </div>
                <button
                    type="button"
                    class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100 md:ml-auto"
                    @click="goToArchivedTeams"
                >
                    Archived Teams
                </button>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Search team, sport, year, coach"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm sm:flex-1"
                    @keyup.enter="reload()"
                />
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="reload()">
                    Search
                </button>
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="showFilters = !showFilters">
                    Filters <span v-if="activeFilterCount" class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs">{{ activeFilterCount }}</span>
                </button>
            </div>

            <div v-if="showFilters" class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-200 pt-3 md:grid-cols-2 lg:grid-cols-4">
                <select v-model="filters.sport_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Sports</option>
                    <option v-for="sport in options.sports" :key="sport.id" :value="String(sport.id)">{{ sport.name }}</option>
                </select>
                <select v-model="filters.year" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Years</option>
                    <option v-for="y in options.years" :key="String(y)" :value="String(y)">{{ y }}</option>
                </select>
                <select v-model="filters.coach_status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="all">Coach Status: All</option>
                    <option value="complete_staff">Coach Status: Complete Staff</option>
                    <option value="missing_assistant">Coach Status: Missing Assistant</option>
                </select>
                <select v-model="filters.roster_status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="all">Roster: All</option>
                    <option value="complete">Roster: Complete</option>
                    <option value="needs_players">Roster: Needs Players</option>
                    <option value="over_limit">Roster: Over Limit</option>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select v-model="filters.sort" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="updated_at">Sort: Updated</option>
                        <option value="team_name">Sort: Team Name</option>
                        <option value="year">Sort: Year</option>
                        <option value="sport">Sort: Sport</option>
                        <option value="players">Sort: Players</option>
                    </select>
                    <select v-model="filters.direction" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="desc">Desc</option>
                        <option value="asc">Asc</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="reload()">Apply</button>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="clearFilters">Reset</button>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <section class="rounded-xl border border-[#034485]/45 bg-white p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Team Change Requests</h2>
                        <p class="text-sm text-slate-500">Coach-initiated changes waiting for admin review.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        Unread: {{ unreadRequestCount }}
                    </span>
                </div>

                <div v-if="teamChangeRequests.length === 0" class="mt-4 rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                    No team change requests right now.
                </div>

                <div v-else class="mt-4 grid gap-3 lg:grid-cols-2">
                    <article
                        v-for="req in teamChangeRequests"
                        :key="req.id"
                        class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ req.title }}</p>
                                <p class="text-xs text-slate-500">{{ formatTimestamp(req.published_at) }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="req.is_read ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ req.is_read ? 'Read' : 'Unread' }}
                            </span>
                        </div>

                        <div class="mt-3 space-y-1 text-xs text-slate-600">
                            <p v-if="parseRequestMessage(req.message).team">
                                <span class="font-semibold text-slate-700">Team:</span> {{ parseRequestMessage(req.message).team }}
                            </p>
                            <p v-if="req.requested_by || parseRequestMessage(req.message).requestedBy">
                                <span class="font-semibold text-slate-700">Requested by:</span>
                                {{ req.requested_by || parseRequestMessage(req.message).requestedBy }}
                            </p>
                            <p v-if="parseRequestMessage(req.message).target">
                                <span class="font-semibold text-slate-700">Target:</span> {{ parseRequestMessage(req.message).target }}
                            </p>
                            <p v-if="parseRequestMessage(req.message).notes">
                                <span class="font-semibold text-slate-700">Notes:</span> {{ parseRequestMessage(req.message).notes }}
                            </p>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs text-emerald-700"
                                @click="approveRequest(req.id)"
                            >
                                Approve
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1 text-xs text-rose-700"
                                @click="rejectRequest(req.id)"
                            >
                                Reject
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-1 text-xs text-slate-700"
                                @click="searchTeamFromRequest(parseRequestMessage(req.message).team)"
                            >
                                Find Team
                            </button>
                            <button
                                v-if="!req.is_read"
                                type="button"
                                class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs text-emerald-700"
                                @click="markRequestRead(req.id)"
                            >
                                Mark Read
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section class="rounded-xl border border-[#034485]/45 bg-white p-4">
                <h3 class="text-base font-semibold text-slate-900">Coach Time Conflicts</h3>
                <p class="text-xs text-slate-500">Same coach assigned to overlapping team schedule windows.</p>
                <ul v-if="conflicts.coach?.length" class="mt-3 space-y-2 text-sm">
                    <li v-for="item in conflicts.coach" :key="`${item.coach_id}-${item.team_a_id}-${item.team_b_id}-${item.window}`" class="rounded-md border border-slate-200 bg-slate-50 p-2">
                        <p class="font-medium text-slate-900">{{ item.coach_name }}</p>
                        <p class="text-xs text-slate-600">{{ item.team_a_name }} vs {{ item.team_b_name }}</p>
                        <p class="text-xs text-amber-700">{{ item.window }}</p>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">No coach conflicts detected.</p>
            </section>
        </section>

        <section class="rounded-xl border border-[#034485]/45 bg-white">
            <div v-if="seasonSnapshots.length === 0" class="p-6 text-sm text-slate-500">
                No teams found for the selected filters.
            </div>

            <div v-else class="space-y-5 p-4">
                <article
                    v-for="season in seasonSnapshots"
                    :key="season.year"
                    class="rounded-lg border border-slate-200 bg-slate-50 p-3"
                >
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">{{ season.year }}</h3>
                            <p class="text-xs text-slate-500">{{ season.kpis.total }} teams</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5 text-xs">
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-slate-700">Teams: {{ season.kpis.total }}</span>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">Complete: {{ season.kpis.complete }}</span>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700">Needs Players: {{ season.kpis.needsPlayers }}</span>
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-red-700">Over Limit: {{ season.kpis.overLimit }}</span>
                            <span class="rounded-full bg-orange-100 px-2 py-0.5 text-orange-700">Conflicts: {{ season.kpis.conflictTeams }}</span>
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-indigo-700">Missing Assistant: {{ season.kpis.missingAssistant }}</span>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="team in season.teams"
                            :key="team.id"
                            class="rounded-3xl bg-[#034485] p-5 text-white shadow-[0_24px_60px_-34px_rgba(3,68,133,0.5)]"
                        >
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <span
                                    class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold"
                                    :style="{ backgroundColor: sportColor(team.sport?.name ?? ''), color: sportTextColor(team.sport?.name ?? '') }"
                                >
                                    {{ sportLabel(team.sport?.name ?? '') }}
                                </span>
                                <p v-if="team.is_archived" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">Archived</p>
                            </div>

                            <div class="flex items-start gap-3">
                                <img :src="teamAvatarUrl(team.team_avatar)" alt="Team Avatar" class="h-14 w-14 rounded-2xl border border-white/20 bg-white/10 object-cover" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xl font-semibold text-white">{{ team.team_name }}</p>
                                    <p class="mt-1 text-sm text-white/80">{{ team.sport?.name || 'No sport' }}</p>
                                    <p class="mt-2 text-sm text-white/85">Head: {{ fullName(team.coach) }}</p>
                                    <p class="text-sm text-white/70">Assistant: {{ fullName(team.assistantCoach) }}</p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] font-medium">
                                <span class="rounded-full px-2 py-0.5" :class="rosterToneClass(team.roster_health?.tone)">
                                    {{ team.roster_health?.label }}
                                </span>
                                <span class="rounded-full px-2 py-0.5" :class="issueToneClass(team.issue_count || 0)">
                                    Conflicts: {{ team.issue_count || 0 }}
                                </span>
                                <span
                                    class="rounded-full px-2 py-0.5"
                                    :class="!team.assistantCoach?.id ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-700'"
                                >
                                    {{ !team.assistantCoach?.id ? 'Missing Assistant' : 'Assistant Ready' }}
                                </span>
                            </div>

                            <p class="mt-3 text-sm text-white/80">Players: {{ team.players_count }} / {{ team.max_players }}</p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full border border-white/25 bg-white px-3 py-1.5 text-xs font-semibold text-[#034485] hover:bg-white/90"
                                    @click="goToRosterPage(team.id)"
                                >
                                    View Roster
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/15"
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
