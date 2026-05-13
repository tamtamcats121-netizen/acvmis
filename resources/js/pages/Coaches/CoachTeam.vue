<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { useSportColors } from '@/composables/useSportColors'
import { useTheme } from '@/composables/useTheme'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import { resolveTeamAvatarUrl as teamAvatarUrl, resolveUserAvatarUrl as userAvatarUrl } from '@/utils/media'

defineOptions({
    layout: CoachDashboard,
})

type PlayerStatus = 'active' | 'injured' | 'suspended' | 'inactive'

type PlayerRow = {
    id: number
    jersey_number: string | number | null
    athlete_position: string | null
    player_status?: PlayerStatus | null
    student?: {
        first_name?: string
        last_name?: string
        student_id_number?: string
        course_or_strand?: string | null
        current_grade_level?: string | null
        academic_level_label?: string | null
        phone_number?: string | null
        gender?: string | null
        height?: string | null
        weight?: string | null
        emergency_contact_name?: string | null
        emergency_contact_relationship?: string | null
        emergency_contact_phone?: string | null
        user?: {
            email?: string | null
            avatar?: string | null
        } | null
    }
}

const props = defineProps<{
    team: any | null
    teams: Array<{ id: number; team_name: string; sport: string }>
    selectedTeamId: number | null
    currentUserId: number | null
}>()

const positionDrafts = ref<Record<number, string>>({})
const positionSaveState = ref<Record<number, 'idle' | 'saving' | 'saved' | 'error'>>({})

const detailsOpen = ref(false)
const selectedPlayer = ref<PlayerRow | null>(null)
const copiedField = ref<string | null>(null)
const inviteMenuOpen = ref(false)
let restoreBodyOverflow: string | null = null
let restoreHtmlOverflow: string | null = null

const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)
const { sportColor, sportTextColor, sportLabel } = useSportColors()
const { isDarkMode } = useTheme()

const players = computed<PlayerRow[]>(() => props.team?.players ?? [])
const selectedStudent = computed(() => selectedPlayer.value?.student ?? null)
const totalPlayers = computed(() => players.value.length)
const positionsFilled = computed(() => players.value.filter((player) => String(player.athlete_position ?? '').trim() !== '').length)
const jerseysAssigned = computed(() => players.value.filter((player) => String(player.jersey_number ?? '').trim() !== '').length)
const teamInvite = computed(() => props.team?.invite ?? null)
const inviteCode = computed(() => String(teamInvite.value?.code ?? ''))
const inviteLink = computed(() => String(teamInvite.value?.join_url ?? ''))
const inviteAvailable = computed(() => Boolean(teamInvite.value?.is_available))
const inviteActive = computed(() => Boolean(teamInvite.value?.is_active))

watch(
    () => props.team?.players,
    (list: PlayerRow[] | undefined) => {
        if (!list?.length) return
        const nextPositions: Record<number, string> = {}
        for (const player of list) {
            nextPositions[player.id] = player.athlete_position ?? ''
        }
        positionDrafts.value = nextPositions
    },
    { immediate: true }
)

watch(
    () => props.selectedTeamId,
    (val) => {
        selectedTeamId.value = val ?? null
    }
)

const sportPositionMap: Record<string, string[]> = {
    basketball: ['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center'],
    soccer: [
        'Goalkeeper',
        'Right Back',
        'Left Back',
        'Center Back',
        'Wing Back',
        'Defensive Midfielder',
        'Central Midfielder',
        'Attacking Midfielder',
        'Winger',
        'Striker',
    ],
    volleyball: ['Setter', 'Outside Hitter', 'Opposite Hitter', 'Middle Blocker', 'Libero', 'Defensive Specialist'],
}

function positionsForSport(): string[] {
    const sportName = String(props.team?.sport?.name ?? '')
        .trim()
        .toLowerCase()

    return sportPositionMap[sportName] ?? []
}

const filteredPlayers = computed(() => {
    return players.value
})

function initialsFromParts(...parts: Array<string | null | undefined>) {
    return parts
        .flatMap((part) => String(part ?? '').trim().split(/\s+/))
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'NA'
}

function coachAvatarUrl(coach?: { user?: { avatar?: string | null } | null } | null) {
    return userAvatarUrl(coach?.user?.avatar ?? null)
}

function isCurrentCoach(coach?: { user_id?: number | null; user?: { id?: number | null } | null } | null) {
    const coachUserId = coach?.user_id ?? coach?.user?.id ?? null
    return coachUserId !== null && props.currentUserId !== null && coachUserId === props.currentUserId
}

function statusTone(status: PlayerStatus) {
    if (status === 'inactive') return 'bg-slate-200 text-slate-700'
    if (status === 'injured') return 'bg-rose-600 text-white'
    if (status === 'suspended') return 'bg-red-100 text-red-700'
    return 'bg-emerald-100 text-emerald-700'
}

function setSaveState(
    stateRef: typeof positionSaveState,
    playerId: number,
    state: 'idle' | 'saving' | 'saved' | 'error'
) {
    stateRef.value = { ...stateRef.value, [playerId]: state }
    if (state === 'saved' || state === 'error') {
        window.setTimeout(() => {
            stateRef.value = { ...stateRef.value, [playerId]: 'idle' }
        }, 2000)
    }
}

function savePosition(teamPlayerId: number) {
    setSaveState(positionSaveState, teamPlayerId, 'saving')
    router.put(
        `/coach/team-players/${teamPlayerId}/position`,
        { athlete_position: positionDrafts.value[teamPlayerId] ?? '' },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setSaveState(positionSaveState, teamPlayerId, 'saved'),
            onError: () => setSaveState(positionSaveState, teamPlayerId, 'error'),
        },
    )
}

function openDetails(player: PlayerRow) {
    selectedPlayer.value = player
    detailsOpen.value = true
}

function closeDetails() {
    detailsOpen.value = false
}

watch(detailsOpen, (open) => {
    if (typeof document === 'undefined') return

    if (open) {
        restoreBodyOverflow = document.body.style.overflow
        restoreHtmlOverflow = document.documentElement.style.overflow
        document.body.style.overflow = 'hidden'
        document.documentElement.style.overflow = 'hidden'
        return
    }

    document.body.style.overflow = restoreBodyOverflow ?? ''
    document.documentElement.style.overflow = restoreHtmlOverflow ?? ''
})

onBeforeUnmount(() => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = restoreBodyOverflow ?? ''
    document.documentElement.style.overflow = restoreHtmlOverflow ?? ''
})

function formatSimple(value?: string | number | null) {
    if (value === null || value === undefined) return '-'
    const text = String(value).trim()
    return text === '' ? '-' : text
}

function formatMeasure(value?: string | number | null, unit?: string) {
    const text = formatSimple(value)
    if (text === '-') return text
    if (!unit) return text
    if (/[a-zA-Z]/.test(text)) return text
    return `${text} ${unit}`
}

async function copyToClipboard(value?: string | number | null, label?: string) {
    const text = formatSimple(value)
    if (text === '-') return
    try {
        await navigator.clipboard.writeText(text)
        copiedField.value = label ?? text
        window.setTimeout(() => {
            if (copiedField.value === (label ?? text)) copiedField.value = null
        }, 1200)
    } catch {
        // silent fail
    }
}

function changeTeam() {
    if (!selectedTeamId.value) return
    router.get('/coach/team', { team_id: selectedTeamId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function printTeamRoster() {
    if (!selectedTeamId.value) return
    const params = new URLSearchParams()
    params.set('team_id', String(selectedTeamId.value))
    window.open(`/coach/team/print?${params.toString()}`, '_blank')
}

function openManageTeams() {
    router.get('/coach/teams/manage', selectedTeamId.value ? { team_id: selectedTeamId.value } : {}, {
        preserveScroll: true,
    })
}

function generateInviteCode() {
    if (!props.team?.id) return
    router.post(`/coach/teams/${props.team.id}/invite-code`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            inviteMenuOpen.value = false
        },
    })
}

function regenerateInviteCode() {
    if (!props.team?.id) return
    if (!window.confirm('Regenerate this team invite code? The old code will stop working.')) return
    router.post(`/coach/teams/${props.team.id}/invite-code/regenerate`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            inviteMenuOpen.value = false
        },
    })
}

function disableInviteCode() {
    if (!props.team?.id) return
    if (!window.confirm('Disable this team invite code? Students will no longer be able to join with this link.')) return
    router.delete(`/coach/teams/${props.team.id}/invite-code`, {
        preserveScroll: true,
        onSuccess: () => {
            inviteMenuOpen.value = false
        },
    })
}

function copyInviteLink() {
    copyToClipboard(inviteLink.value, 'team-invite-link')
}

function copyInviteCode() {
    copyToClipboard(inviteCode.value, 'team-invite-code')
    inviteMenuOpen.value = false
}

function openJoinPage() {
    if (!inviteLink.value) return
    window.open(inviteLink.value, '_blank')
    inviteMenuOpen.value = false
}

</script>

<template>
    <div class="space-y-5">
        <div v-if="props.teams.length" class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
            <div v-if="props.teams.length > 1" class="flex items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Team</span>
                <select
                    v-model.number="selectedTeamId"
                    @change="changeTeam"
                    class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700"
                >
                    <option v-for="teamOption in props.teams" :key="teamOption.id" :value="teamOption.id">
                        {{ teamOption.team_name }}
                    </option>
                </select>
            </div>
        </div>

        <div v-if="!props.team" class="page-card rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-slate-500">You are not assigned to any team yet.</p>
        </div>

        <div v-else class="space-y-5">
            <section class="page-card relative z-10 rounded-3xl border border-[#034485]/35 bg-gradient-to-br from-[#034485] via-[#0b5aa6] to-[#02315f] p-6 shadow-[0_22px_48px_-30px_rgba(3,68,133,0.42)]">
                <div class="relative flex flex-col gap-5">
                    <div class="flex min-w-0 flex-1 flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-[22px] border border-white/18 bg-[#0a4f96]/70 shadow-[0_16px_36px_-24px_rgba(15,23,42,0.55)] sm:h-28 sm:w-28">
                            <img
                                :src="teamAvatarUrl(props.team.team_avatar)"
                                loading="lazy"
                                decoding="async"
                                class="h-full w-full object-cover"
                            />
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/72">Team Overview</p>
                            <h2 class="mt-2 text-2xl font-bold text-white sm:text-[2rem]">{{ props.team.team_name }}</h2>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                                <span
                                    class="rounded-full px-3 py-1"
                                    :style="{
                                        backgroundColor: sportColor(props.team.sport?.name ?? props.team.sport?.id ?? props.team.sport),
                                        color: sportTextColor(props.team.sport?.name ?? props.team.sport?.id ?? props.team.sport),
                                    }"
                                >
                                    {{ sportLabel(props.team.sport?.name ?? props.team.sport?.id ?? props.team.sport) }}
                                </span>
                                <span class="rounded-full border border-white/18 bg-[#0a4f96]/55 px-3 py-1 text-white/90">{{ props.team.year ?? 'N/A' }}</span>
                                <span class="rounded-full border border-white/18 bg-[#0a4f96]/55 px-3 py-1 text-white/90">{{ totalPlayers }} Players</span>
                                <span class="rounded-full border border-white/18 bg-[#0a4f96]/55 px-3 py-1 text-white/90">{{ positionsFilled }} Positions Set</span>
                                <span class="rounded-full border border-white/18 bg-[#0a4f96]/55 px-3 py-1 text-white/90">{{ jerseysAssigned }} Jerseys Set</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex w-full flex-col gap-2 border-t border-white/14 pt-4 sm:flex-row sm:flex-wrap sm:items-center">
                        <button
                            class="rounded-full border border-white/24 bg-white px-4 py-2 text-xs font-semibold text-[#034485] shadow-sm transition hover:bg-[#eef5ff]"
                            @click="openManageTeams"
                        >
                            Manage Teams
                        </button>
                        <button
                            class="rounded-full border border-white/24 bg-white px-4 py-2 text-xs font-semibold text-[#034485] shadow-sm transition hover:bg-[#eef5ff]"
                            @click="printTeamRoster"
                        >
                            Print Roster
                        </button>
                    </div>

                    <div class="relative z-30 rounded-3xl border border-white/20 bg-white/12 p-4 text-white shadow-[0_16px_30px_-24px_rgba(15,23,42,0.36)] backdrop-blur-md">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">Team Invite Code</p>
                                <div v-if="inviteAvailable" class="mt-2 flex flex-wrap items-center gap-3">
                                    <p
                                        v-if="inviteCode"
                                        class="rounded-2xl border border-white/24 bg-white/18 px-4 py-2 font-mono text-xl font-bold tracking-[0.18em] text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                                    >
                                        {{ inviteCode }}
                                    </p>
                                    <p v-else class="text-sm font-medium text-white/75">
                                        Generate a code so student-athletes can join this current-year roster.
                                    </p>
                                    <span
                                        v-if="inviteCode"
                                        class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-wide"
                                        :class="inviteActive ? 'border-emerald-200/50 bg-emerald-400/18 text-emerald-50' : 'border-amber-200/50 bg-amber-400/18 text-amber-50'"
                                    >
                                        {{ inviteActive ? 'Active' : 'Disabled' }}
                                    </span>
                                </div>
                                <p v-else class="mt-2 text-sm font-medium text-white/72">
                                    Invite codes are only available for current active teams.
                                </p>
                            </div>

                            <div v-if="inviteAvailable" class="flex shrink-0 items-center gap-2">
                                <button
                                    v-if="inviteActive && inviteLink"
                                    type="button"
                                    class="rounded-full border border-white/24 bg-white px-4 py-2 text-xs font-semibold text-[#034485] shadow-sm transition hover:bg-[#eef5ff]"
                                    @click="copyInviteLink"
                                >
                                    {{ copiedField === 'team-invite-link' ? 'Copied' : 'Copy Link' }}
                                </button>
                                <button
                                    v-else-if="!inviteCode || !inviteActive"
                                    type="button"
                                    class="rounded-full border border-white/24 bg-white px-4 py-2 text-xs font-semibold text-[#034485] shadow-sm transition hover:bg-[#eef5ff]"
                                    @click="generateInviteCode"
                                >
                                    Generate Invite Code
                                </button>

                                <div v-if="inviteCode" class="relative z-40">
                                    <button
                                        type="button"
                                        class="flex h-10 w-10 items-center justify-center rounded-full border border-white/24 bg-white/14 text-white transition hover:bg-white/22"
                                        aria-label="Team invite actions"
                                        @click="inviteMenuOpen = !inviteMenuOpen"
                                    >
                                        <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
                                            <path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4m0 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4" />
                                        </svg>
                                    </button>

                                    <transition name="invite-menu">
                                        <div
                                            v-if="inviteMenuOpen"
                                            class="absolute right-0 z-[80] mt-2 w-52 overflow-hidden rounded-2xl border border-[#034485]/20 bg-white py-2 text-sm text-slate-700 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.45)]"
                                        >
                                            <button type="button" class="block w-full px-4 py-2 text-left font-semibold hover:bg-[#eef5ff] hover:text-[#034485]" @click="copyInviteCode">
                                                {{ copiedField === 'team-invite-code' ? 'Code Copied' : 'Copy Code' }}
                                            </button>
                                            <button v-if="inviteLink" type="button" class="block w-full px-4 py-2 text-left font-semibold hover:bg-[#eef5ff] hover:text-[#034485]" @click="openJoinPage">
                                                View Join Page
                                            </button>
                                            <button type="button" class="block w-full px-4 py-2 text-left font-semibold hover:bg-[#eef5ff] hover:text-[#034485]" @click="regenerateInviteCode">
                                                Regenerate Code
                                            </button>
                                            <button
                                                v-if="inviteActive"
                                                type="button"
                                                class="block w-full px-4 py-2 text-left font-semibold text-red-600 hover:bg-red-50"
                                                @click="disableInviteCode"
                                            >
                                                Disable Code
                                            </button>
                                        </div>
                                    </transition>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="page-card rounded-3xl border border-white/20 bg-white/12 p-4 text-white shadow-[0_16px_30px_-24px_rgba(15,23,42,0.36)] backdrop-blur-md">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs uppercase tracking-wide text-white/78">Head Coach</p>
                            <span
                                v-if="isCurrentCoach(props.team.coach)"
                                class="inline-flex items-center rounded-full border border-white/28 bg-white/16 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.24)]"
                            >
                                You
                            </span>
                        </div>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/24 bg-white/22 text-sm font-bold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.35)] backdrop-blur-xl">
                                <img
                                    v-if="props.team.coach?.user?.avatar"
                                    :src="coachAvatarUrl(props.team.coach)"
                                    alt="Head coach profile photo"
                                    loading="lazy"
                                    decoding="async"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initialsFromParts(props.team.coach?.first_name, props.team.coach?.last_name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-white">
                                    {{ props.team.coach?.first_name }} {{ props.team.coach?.last_name }}
                                </p>
                                <p class="mt-1 text-xs text-white/72">
                                    {{ props.team.coach?.email || props.team.coach?.phone_number || 'Contact available below' }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 space-y-2 text-xs">
                            <div
                                v-if="props.team.coach?.email"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/24 bg-white/16 px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.24)] backdrop-blur-xl"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">Email</p>
                                    <p class="truncate text-sm font-medium text-white">{{ props.team.coach.email }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.coach.email, 'coach-email')"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/30 bg-white/18 px-2.5 py-1 text-[11px] font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.24)] transition hover:bg-white/26"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'coach-email' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <div
                                v-if="props.team.coach?.phone_number"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/24 bg-white/16 px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.24)] backdrop-blur-xl"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">Phone</p>
                                    <p class="text-sm font-medium text-white">{{ props.team.coach.phone_number }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.coach.phone_number, 'coach-phone')"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/30 bg-white/18 px-2.5 py-1 text-[11px] font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.24)] transition hover:bg-white/26"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'coach-phone' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <span
                                v-if="!props.team.coach?.email && !props.team.coach?.phone_number"
                                class="text-white/55"
                            >
                                Contact the administrator for assistance
                            </span>
                        </div>
                    </div>
                    <div class="page-card rounded-3xl border border-white/20 bg-white/12 p-4 text-white shadow-[0_16px_30px_-24px_rgba(15,23,42,0.36)] backdrop-blur-md">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs uppercase tracking-wide text-white/78">Assistant Coach</p>
                            <span
                                v-if="isCurrentCoach(props.team.assistantCoach)"
                                class="inline-flex items-center rounded-full border border-white/28 bg-white/16 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.24)]"
                            >
                                You
                            </span>
                        </div>
                        <div v-if="props.team.assistantCoach" class="mt-3 flex items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/24 bg-white/22 text-sm font-bold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.35)] backdrop-blur-xl">
                                <img
                                    v-if="props.team.assistantCoach?.user?.avatar"
                                    :src="coachAvatarUrl(props.team.assistantCoach)"
                                    alt="Assistant coach profile photo"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initialsFromParts(props.team.assistantCoach?.first_name, props.team.assistantCoach?.last_name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-white">
                                    {{ props.team.assistantCoach?.first_name }} {{ props.team.assistantCoach?.last_name }}
                                </p>
                                <p class="mt-1 text-xs text-white/72">
                                    {{ props.team.assistantCoach?.email || props.team.assistantCoach?.phone_number || 'Contact available below' }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm font-medium text-white/55">Not assigned</p>
                        <div v-if="props.team.assistantCoach" class="mt-3 space-y-2 text-xs">
                            <div
                                v-if="props.team.assistantCoach?.email"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/24 bg-white/16 px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.24)] backdrop-blur-xl"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">Email</p>
                                    <p class="truncate text-sm font-medium text-white">{{ props.team.assistantCoach.email }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.assistantCoach.email, 'assistant-email')"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/30 bg-white/18 px-2.5 py-1 text-[11px] font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.24)] transition hover:bg-white/26"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'assistant-email' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <div
                                v-if="props.team.assistantCoach?.phone_number"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/24 bg-white/16 px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.24)] backdrop-blur-xl"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">Phone</p>
                                    <p class="text-sm font-medium text-white">{{ props.team.assistantCoach.phone_number }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.assistantCoach.phone_number, 'assistant-phone')"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/30 bg-white/18 px-2.5 py-1 text-[11px] font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.24)] transition hover:bg-white/26"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'assistant-phone' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <span
                                v-if="!props.team.assistantCoach?.email && !props.team.assistantCoach?.phone_number"
                                class="text-white/55"
                            >
                                Contact the administrator for assistance
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="filteredPlayers.length === 0" class="page-card rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                No players found for this team.
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="player in filteredPlayers" :key="player.id" class="page-card rounded-2xl border border-[#034485]/30 bg-[#eef4fb] p-4 shadow-[0_16px_34px_-28px_rgba(3,68,133,0.24)]">
                    <div class="pointer-events-none -mx-4 -mt-4 mb-4 h-12 rounded-t-2xl bg-gradient-to-r from-[#034485] via-[#0b5aa6] to-[#034485]/90"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-[#034485]/20 bg-white text-sm font-bold text-[#034485] shadow-sm">
                                <img
                                    v-if="player.student?.user?.avatar"
                                    :src="userAvatarUrl(player.student.user.avatar)"
                                    alt="Player profile photo"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initialsFromParts(player.student?.first_name, player.student?.last_name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-slate-900">{{ player.student?.first_name }} {{ player.student?.last_name }}</p>
                                <p class="text-xs text-slate-600">{{ player.student?.student_id_number || '-' }}</p>
                                <button
                                    type="button"
                                    class="mt-2 inline-flex rounded-full border border-[#034485] px-2.5 py-1 text-[11px] font-semibold text-[#034485] hover:bg-[#034485]/10"
                                    @click="openDetails(player)"
                                >
                                    View Details
                                </button>
                            </div>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusTone((player.player_status ?? 'active') as PlayerStatus)">
                            {{ (player.player_status ?? 'active').toString().toUpperCase() }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-600 sm:grid-cols-4">
                        <div class="rounded-2xl border border-[#034485]/12 bg-white px-3 py-2">
                            <span class="text-[#034485]">Jersey</span>
                            <p class="font-semibold text-slate-900">
                                <span v-if="player.jersey_number">{{ player.jersey_number }}</span>
                                <span v-else class="text-amber-600">Pending</span>
                            </p>
                        </div>
                        <div class="rounded-2xl border border-[#034485]/12 bg-white px-3 py-2">
                            <span class="text-[#034485]">Position</span>
                            <p class="font-semibold text-slate-900">
                                <span v-if="player.athlete_position">{{ player.athlete_position }}</span>
                                <span v-else class="text-red-600">Unassigned</span>
                            </p>
                        </div>
                        <div class="rounded-2xl border border-[#034485]/12 bg-white px-3 py-2">
                            <span class="text-[#034485]">Height</span>
                            <p class="font-semibold text-slate-900">{{ formatMeasure(player.student?.height, 'cm') }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#034485]/12 bg-white px-3 py-2">
                            <span class="text-[#034485]">Weight</span>
                            <p class="font-semibold text-slate-900">{{ formatMeasure(player.student?.weight, 'kg') }}</p>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Update Position</label>
                            <select
                                v-if="positionsForSport().length > 0"
                                v-model="positionDrafts[player.id]"
                                class="mt-1 w-full rounded-md border border-[#034485]/20 bg-white px-2 py-1.5 text-sm text-slate-800"
                            >
                                <option value="">Select position</option>
                                <option v-for="position in positionsForSport()" :key="position" :value="position">
                                    {{ position }}
                                </option>
                            </select>
                            <input
                                v-else
                                v-model="positionDrafts[player.id]"
                                type="text"
                                class="mt-1 w-full rounded-md border border-[#034485]/20 bg-white px-2 py-1.5 text-sm text-slate-800"
                                placeholder="Assign position"
                            />
                            <div class="mt-2 flex items-center justify-between">
                                <button @click="savePosition(player.id)" class="rounded-md bg-[#034485] px-3 py-1.5 text-xs text-white hover:bg-[#033a70]">Save Position</button>
                                <span v-if="positionSaveState[player.id] && positionSaveState[player.id] !== 'idle'" class="text-[11px] text-slate-500">
                                    {{ positionSaveState[player.id] === 'saving' ? 'Saving...' : positionSaveState[player.id] === 'saved' ? 'Saved' : 'Error' }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Status</label>
                            <div class="mt-1">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusTone((player.player_status ?? 'active') as PlayerStatus)">
                                    {{ (player.player_status ?? 'active').toString().toUpperCase() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <Teleport to="body">
        <transition name="athlete-modal">
            <div v-if="detailsOpen" class="fixed inset-0 z-[100] overflow-y-auto bg-slate-900/40 px-4 py-6 backdrop-blur-sm">
                <div class="flex min-h-full items-center justify-center" @click="closeDetails">
                <div
                    class="flex max-h-[calc(100vh-3rem)] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border shadow-[0_28px_70px_-34px_rgba(2,12,27,0.45)]"
                    :class="isDarkMode ? 'border-slate-700 bg-slate-950' : 'border-[#034485]/35 bg-white'"
                    @click.stop
                >
                <div class="rounded-t-3xl bg-[#034485] px-6 py-5 text-white sm:px-8">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/75">Player Details</p>
                    <h3 class="mt-1 text-2xl font-bold text-white">
                        {{ formatSimple(selectedStudent?.first_name) }} {{ formatSimple(selectedStudent?.last_name) }}
                    </h3>
                    <p class="mt-1 text-xs text-white/80">Student ID: {{ formatSimple(selectedStudent?.student_id_number) }}</p>
                </div>
                <div class="overflow-y-auto p-6 sm:p-8" :class="isDarkMode ? 'text-slate-200' : 'text-slate-700'">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="min-w-[220px] flex-1 space-y-4">
                        <div class="grid gap-3 text-sm sm:grid-cols-2" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">
                            <div class="rounded-2xl border px-3 py-3 sm:col-span-2" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/15 bg-[#f7fbff]'">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Student ID</p>
                                <p class="mt-1 inline-flex items-center gap-2 font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">
                                    {{ formatSimple(selectedStudent?.student_id_number) }}
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#034485] hover:text-[#033a70]"
                                        @click="copyToClipboard(selectedStudent?.student_id_number, 'student-id')"
                                        title="Copy student ID"
                                        aria-label="Copy student ID"
                                    >
                                        <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4">
                                            <path fill="currentColor" d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                        </svg>
                                        <span v-if="copiedField === 'student-id'" class="text-[10px]">Student ID Copied</span>
                                    </button>
                                </p>
                            </div>
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/15 bg-[#f7fbff]'">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Position</p>
                                <p class="mt-1 font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ formatSimple(selectedPlayer?.athlete_position) }}</p>
                            </div>
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/15 bg-[#f7fbff]'">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Jersey</p>
                                <p class="mt-1 font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ formatSimple(selectedPlayer?.jersey_number) }}</p>
                            </div>
                        </div>

                        <div class="grid gap-3 text-sm sm:grid-cols-2" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/15 bg-[#f7fbff]'">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Course/Strand</p>
                                <p class="mt-1 font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ formatSimple(selectedStudent?.course_or_strand) }}</p>
                            </div>
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/15 bg-[#f7fbff]'">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Academic Level</p>
                                <p class="mt-1 font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ formatSimple(selectedStudent?.academic_level_label ?? selectedStudent?.current_grade_level) }}</p>
                            </div>
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/15 bg-[#f7fbff]'">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Gender</p>
                                <p class="mt-1 font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ formatSimple(selectedStudent?.gender) }}</p>
                            </div>
                            <div class="rounded-2xl border px-3 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/15 bg-[#f7fbff]'">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Height</p>
                                <p class="mt-1 font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ formatMeasure(selectedStudent?.height, 'cm') }}</p>
                            </div>
                            <div class="rounded-2xl border px-3 py-3 sm:col-span-2" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/15 bg-[#f7fbff]'">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Weight</p>
                                <p class="mt-1 font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ formatMeasure(selectedStudent?.weight, 'kg') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-full border" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/20 bg-[#f7fbff]'">
                        <img :src="userAvatarUrl(selectedStudent?.user?.avatar ?? null)" alt="Student avatar" class="h-full w-full object-cover" />
                    </div>
                </div>

                <div class="mt-6 border-t pt-4" :class="isDarkMode ? 'border-slate-700' : 'border-slate-200'">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Contact</p>
                    <div class="mt-2 grid gap-2 text-sm sm:grid-cols-2" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">
                        <p>
                            <span class="font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Email:</span>
                            <span class="ml-1 inline-flex items-center gap-2">
                                {{ formatSimple(selectedStudent?.user?.email) }}
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#034485] hover:text-[#033a70]"
                                    @click="copyToClipboard(selectedStudent?.user?.email, 'email')"
                                    title="Copy email"
                                    aria-label="Copy email"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4">
                                        <path
                                            fill="currentColor"
                                            d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z"
                                        />
                                    </svg>
                                    <span v-if="copiedField === 'email'" class="text-[10px]">Email Copied</span>
                                </button>
                            </span>
                        </p>
                        <p>
                            <span class="font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Phone:</span>
                            <span class="ml-1 inline-flex items-center gap-2">
                                {{ formatSimple(selectedStudent?.phone_number) }}
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#034485] hover:text-[#033a70]"
                                    @click="copyToClipboard(selectedStudent?.phone_number, 'phone')"
                                    title="Copy phone number"
                                    aria-label="Copy phone number"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4">
                                        <path
                                            fill="currentColor"
                                            d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z"
                                        />
                                    </svg>
                                    <span v-if="copiedField === 'phone'" class="text-[10px]">Phone Number Copied</span>
                                </button>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="mt-6 border-t pt-4" :class="isDarkMode ? 'border-slate-700' : 'border-slate-200'">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Emergency Contact</p>
                    <div class="mt-2 grid gap-2 text-sm sm:grid-cols-2" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">
                        <p><span class="font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Name:</span> {{ formatSimple(selectedStudent?.emergency_contact_name) }}</p>
                        <p><span class="font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Relationship:</span> {{ formatSimple(selectedStudent?.emergency_contact_relationship) }}</p>
                        <p class="sm:col-span-2">
                            <span class="font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Phone:</span>
                            <span class="ml-1 inline-flex items-center gap-2">
                                {{ formatSimple(selectedStudent?.emergency_contact_phone) }}
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#034485] hover:text-[#033a70]"
                                    @click="copyToClipboard(selectedStudent?.emergency_contact_phone, 'emergency-phone')"
                                    title="Copy emergency phone"
                                    aria-label="Copy emergency phone"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4">
                                        <path
                                            fill="currentColor"
                                            d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z"
                                        />
                                    </svg>
                                    <span v-if="copiedField === 'emergency-phone'" class="text-[10px]">Emergency Contact Number Copied</span>
                                </button>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-full bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#033a70]"
                        @click="closeDetails"
                    >
                        Close
                    </button>
                </div>
                </div>
                </div>
                </div>
            </div>
        </transition>
        </Teleport>

    </div>
</template>

<style scoped>
.athlete-modal-enter-active,
.athlete-modal-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}
.athlete-modal-enter-from,
.athlete-modal-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}
.athlete-modal-enter-to,
.athlete-modal-leave-from {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.invite-menu-enter-active,
.invite-menu-leave-active {
    transition: opacity 150ms ease, transform 150ms ease;
}

.invite-menu-enter-from,
.invite-menu-leave-to {
    opacity: 0;
    transform: translateY(-4px) scale(0.98);
}
</style>
<style scoped>
.page-card {
    opacity: 0;
    animation: coach-team-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

@keyframes coach-team-card-rise {
    from {
        opacity: 0;
        transform: translateY(16px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .page-card {
        animation: none;
        opacity: 1;
    }
}
</style>
