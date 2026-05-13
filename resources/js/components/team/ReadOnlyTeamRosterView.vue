<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { useSportColors } from '@/composables/useSportColors'
import { useTheme } from '@/composables/useTheme'
import { resolveTeamAvatarUrl as teamAvatarUrl, resolveUserAvatarUrl as userAvatarUrl } from '@/utils/media'

type TeamOption = {
    id: number
    team_name: string
    sport?: string
}

type TeamCoach = {
    id?: number | null
    user_id?: number | null
    first_name?: string | null
    last_name?: string | null
    name?: string | null
    phone_number?: string | null
    email?: string | null
    coach_status?: string | null
    user?: {
        id?: number | null
        avatar?: string | null
        email?: string | null
    } | null
    avatar?: string | null
}

type TeamPlayer = {
    id: number
    jersey_number?: string | number | null
    athlete_position?: string | null
    player_status?: string | null
    student?: {
        id?: number | null
        first_name?: string | null
        last_name?: string | null
        student_id_number?: string | null
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
    } | null
}

type TeamRecord = {
    id: number
    team_name: string
    team_avatar: string | null
    sport?: { id?: number | null; name?: string | null } | string | null
    year?: string | number | null
    coach?: TeamCoach | null
    assistantCoach?: TeamCoach | null
    players?: TeamPlayer[]
}

const props = withDefaults(defineProps<{
    team: TeamRecord | null
    teamOptions?: TeamOption[]
    selectedTeamId?: number | null
    emptyTitle?: string
    emptyDescription?: string
    heroLabel?: string
    heroDescription?: string
}>(), {
    teamOptions: () => [],
    selectedTeamId: null,
    emptyTitle: 'No team available.',
    emptyDescription: 'Team information will appear here once a record is selected.',
    heroLabel: 'Team Overview',
    heroDescription: 'Review the roster, assigned coaches, player status, and athlete details in one place.',
})

const emit = defineEmits<{
    (e: 'team-change', teamId: number): void
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()
const { isDarkMode } = useTheme()

const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)
const detailsOpen = ref(false)
const selectedPlayer = ref<TeamPlayer | null>(null)
const copiedField = ref<string | null>(null)
let restoreBodyOverflow: string | null = null
let restoreHtmlOverflow: string | null = null

const players = computed(() => props.team?.players ?? [])
const totalPlayers = computed(() => players.value.length)
const positionsFilled = computed(() => players.value.filter((player) => String(player.athlete_position ?? '').trim() !== '').length)
const jerseysAssigned = computed(() => players.value.filter((player) => String(player.jersey_number ?? '').trim() !== '').length)
const selectedStudent = computed(() => selectedPlayer.value?.student ?? null)
const showTeamSelector = computed(() => props.teamOptions.length > 1)

watch(
    () => props.selectedTeamId,
    (value) => {
        selectedTeamId.value = value ?? null
    },
)

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

function changeTeam() {
    if (!selectedTeamId.value) return
    emit('team-change', selectedTeamId.value)
}

function initialsFromParts(...parts: Array<string | null | undefined>) {
    return parts
        .flatMap((part) => String(part ?? '').trim().split(/\s+/))
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'NA'
}

function formatSimple(value?: string | number | null) {
    if (value === null || value === undefined) return '-'
    const text = String(value).trim()
    return text === '' ? '-' : text
}

function formatMeasure(value?: string | number | null, unit?: string) {
    const text = formatSimple(value)
    if (text === '-' || !unit) return text
    if (/[a-zA-Z]/.test(text)) return text
    return `${text} ${unit}`
}

function fullCoachName(coach?: TeamCoach | null) {
    const named = String(coach?.name ?? '').trim()
    if (named) return named
    return `${String(coach?.first_name ?? '').trim()} ${String(coach?.last_name ?? '').trim()}`.trim() || 'Unassigned'
}

function statusTone(status?: string | null) {
    if (status === 'inactive') return 'bg-slate-200 text-slate-700'
    if (status === 'injured') return 'bg-rose-600 text-white'
    if (status === 'suspended') return 'bg-red-100 text-red-700'
    return 'bg-emerald-100 text-emerald-700'
}

function openDetails(player: TeamPlayer) {
    selectedPlayer.value = player
    detailsOpen.value = true
}

function closeDetails() {
    detailsOpen.value = false
}

async function copyToClipboard(value?: string | number | null, key?: string) {
    const text = formatSimple(value)
    if (text === '-') return
    try {
        await navigator.clipboard.writeText(text)
        copiedField.value = key ?? text
        window.setTimeout(() => {
            if (copiedField.value === (key ?? text)) copiedField.value = null
        }, 1200)
    } catch {
        // silent fail
    }
}
</script>

<template>
    <div class="team-page-view space-y-6">
        <div v-if="showTeamSelector" class="flex flex-wrap items-center gap-2 text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">Team</span>
                <select
                    v-model.number="selectedTeamId"
                    @change="changeTeam"
                    class="rounded-md border px-2 py-1 text-xs"
                    :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-[#034485]/40 bg-white text-slate-700'"
                >
                    <option v-for="teamOption in props.teamOptions" :key="teamOption.id" :value="teamOption.id">
                        {{ teamOption.team_name }}
                    </option>
                </select>
            </div>
        </div>

        <div
            v-if="!props.team"
            class="page-card rounded-3xl border p-6 shadow-[0_18px_40px_-30px_rgba(3,68,133,0.35)]"
            :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-[#034485]/35 bg-white text-slate-600'"
        >
            <p class="font-medium">{{ props.emptyTitle }}</p>
            <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">{{ props.emptyDescription }}</p>
        </div>

        <div v-else class="space-y-6">
            <section class="page-card overflow-hidden rounded-3xl border border-[#034485]/35 bg-gradient-to-br from-[#034485] via-[#0b5aa6] to-[#02315f] p-6 text-white shadow-[0_22px_48px_-30px_rgba(3,68,133,0.42)]">
                <div class="relative flex flex-col gap-5">
                    <div class="flex min-w-0 flex-1 flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-[22px] border border-white/18 bg-[#0a4f96]/70 shadow-[0_16px_36px_-24px_rgba(15,23,42,0.55)] sm:h-28 sm:w-28">
                            <img
                                :src="teamAvatarUrl(props.team.team_avatar)"
                                class="h-full w-full object-cover"
                                alt="Team avatar"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/72">{{ props.heroLabel }}</p>
                            <h2 class="text-2xl font-bold text-white sm:text-[2rem]">{{ props.team.team_name }}</h2>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 font-semibold"
                                    :style="{ backgroundColor: sportColor(props.team?.sport?.name ?? props.team?.sport), color: sportTextColor(props.team?.sport?.name ?? props.team?.sport) }"
                                >
                                    {{ sportLabel(props.team?.sport?.name ?? props.team?.sport) }}
                                </span>
                                <span class="inline-flex items-center rounded-full border border-white/18 bg-[#0a4f96]/55 px-3 py-1 font-semibold text-white/90">
                                    {{ props.team.year ?? 'N/A' }}
                                </span>
                                <span class="inline-flex items-center rounded-full border border-white/18 bg-[#0a4f96]/55 px-3 py-1 font-semibold text-white/90">
                                    {{ totalPlayers }} Players
                                </span>
                                <span class="inline-flex items-center rounded-full border border-white/18 bg-[#0a4f96]/55 px-3 py-1 font-semibold text-white/90">
                                    {{ positionsFilled }} Positions Set
                                </span>
                                <span class="inline-flex items-center rounded-full border border-white/18 bg-[#0a4f96]/55 px-3 py-1 font-semibold text-white/90">
                                    {{ jerseysAssigned }} Jerseys Set
                                </span>
                            </div>
                            <p class="mt-3 max-w-2xl text-sm text-white/80">
                                {{ props.heroDescription }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="page-card rounded-3xl border border-white/20 bg-white/12 p-4 text-white shadow-[0_16px_30px_-24px_rgba(15,23,42,0.36)] backdrop-blur-md">
                        <p class="text-xs uppercase tracking-wide text-white/78">Head Coach</p>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/18 bg-white/14 text-sm font-bold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.2)] backdrop-blur-md">
                                <img
                                    v-if="props.team.coach?.user?.avatar || props.team.coach?.avatar"
                                    :src="userAvatarUrl(props.team.coach?.user?.avatar ?? props.team.coach?.avatar ?? null)"
                                    alt="Head coach profile photo"
                                    loading="lazy"
                                    decoding="async"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initialsFromParts(props.team.coach?.first_name, props.team.coach?.last_name, props.team.coach?.name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-white">{{ fullCoachName(props.team.coach) }}</p>
                                <p class="mt-1 text-xs text-white/72">
                                    {{ props.team.coach?.email || props.team.coach?.phone_number || 'Contact available in details below' }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 space-y-2 text-xs">
                            <div
                                v-if="props.team.coach?.email"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/18 bg-white/10 px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] backdrop-blur-md"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">Email</p>
                                    <p class="truncate text-sm font-medium text-white">{{ props.team.coach.email }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.coach.email, 'coach-email')"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/22 bg-white/12 px-2.5 py-1 text-[11px] font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] transition hover:bg-white/18"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'coach-email' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <div
                                v-if="props.team.coach?.phone_number"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/18 bg-white/10 px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] backdrop-blur-md"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">Phone</p>
                                    <p class="text-sm font-medium text-white">{{ props.team.coach.phone_number }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.coach.phone_number, 'coach-phone')"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/22 bg-white/12 px-2.5 py-1 text-[11px] font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] transition hover:bg-white/18"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'coach-phone' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <span v-if="!props.team.coach?.email && !props.team.coach?.phone_number" class="text-white/55">
                                Contact the administrator for assistance
                            </span>
                        </div>
                    </div>

                    <div class="page-card rounded-3xl border border-white/20 bg-white/12 p-4 text-white shadow-[0_16px_30px_-24px_rgba(15,23,42,0.36)] backdrop-blur-md">
                        <p class="text-xs uppercase tracking-wide text-white/78">Assistant Coach</p>
                        <div v-if="props.team.assistantCoach" class="mt-3 flex items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/18 bg-white/14 text-sm font-bold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.2)] backdrop-blur-md">
                                <img
                                    v-if="props.team.assistantCoach?.user?.avatar || props.team.assistantCoach?.avatar"
                                    :src="userAvatarUrl(props.team.assistantCoach?.user?.avatar ?? props.team.assistantCoach?.avatar ?? null)"
                                    alt="Assistant coach profile photo"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initialsFromParts(props.team.assistantCoach?.first_name, props.team.assistantCoach?.last_name, props.team.assistantCoach?.name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-white">{{ fullCoachName(props.team.assistantCoach) }}</p>
                                <p class="mt-1 text-xs text-white/72">
                                    {{ props.team.assistantCoach?.email || props.team.assistantCoach?.phone_number || 'Contact available in details below' }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm font-medium text-white/55">Not assigned</p>
                        <div v-if="props.team.assistantCoach" class="mt-3 space-y-2 text-xs">
                            <div
                                v-if="props.team.assistantCoach?.email"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/18 bg-white/10 px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] backdrop-blur-md"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">Email</p>
                                    <p class="truncate text-sm font-medium text-white">{{ props.team.assistantCoach.email }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.assistantCoach.email, 'assistant-email')"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/22 bg-white/12 px-2.5 py-1 text-[11px] font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] transition hover:bg-white/18"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'assistant-email' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <div
                                v-if="props.team.assistantCoach?.phone_number"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/18 bg-white/10 px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] backdrop-blur-md"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">Phone</p>
                                    <p class="text-sm font-medium text-white">{{ props.team.assistantCoach.phone_number }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.assistantCoach.phone_number, 'assistant-phone')"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/22 bg-white/12 px-2.5 py-1 text-[11px] font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] transition hover:bg-white/18"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'assistant-phone' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <span v-if="!props.team.assistantCoach?.email && !props.team.assistantCoach?.phone_number" class="text-white/55">
                                Contact the administrator for assistance
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="page-card rounded-3xl border p-6 shadow-[0_20px_44px_-32px_rgba(3,68,133,0.32)]"
                :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/35 bg-white'"
            >
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-[#034485]'">Team Members</h3>
                    <span class="text-sm" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">{{ totalPlayers }} total</span>
                </div>

                <div v-if="players.length" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="player in players"
                        :key="player.id"
                        class="page-card rounded-2xl border p-4 shadow-[0_16px_34px_-28px_rgba(3,68,133,0.24)]"
                        :class="isDarkMode ? 'border-slate-700 bg-slate-950' : 'border-[#034485]/30 bg-[#eef4fb]'"
                    >
                        <div class="pointer-events-none -mx-4 -mt-4 mb-4 h-12 rounded-t-2xl bg-gradient-to-r from-[#034485] via-[#0b5aa6] to-[#034485]/90"></div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <div
                                    class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border text-sm font-bold shadow-sm"
                                    :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100' : 'border-[#034485]/20 bg-white text-[#034485]'"
                                >
                                    <img
                                        v-if="player.student?.user?.avatar"
                                        :src="userAvatarUrl(player.student.user.avatar)"
                                        alt="Player profile photo"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>{{ initialsFromParts(player.student?.first_name, player.student?.last_name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-base font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">
                                        {{ player.student?.first_name }} {{ player.student?.last_name }}
                                    </p>
                                    <p class="text-xs" :class="isDarkMode ? 'text-slate-400' : 'text-slate-600'">{{ player.student?.student_id_number || '-' }}</p>
                                    <button
                                        type="button"
                                        class="mt-2 inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :class="isDarkMode ? 'border-slate-500 text-slate-200 hover:bg-slate-800' : 'border-[#034485] text-[#034485] hover:bg-[#034485]/10'"
                                        @click="openDetails(player)"
                                    >
                                        View Details
                                    </button>
                                </div>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusTone(player.player_status)">
                                {{ (player.player_status ?? 'active').toString().toUpperCase() }}
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                            <div class="rounded-2xl border px-3 py-2" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/12 bg-white'">
                                <span :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Jersey</span>
                                <p class="font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">
                                    <span v-if="player.jersey_number">{{ player.jersey_number }}</span>
                                    <span v-else class="text-amber-600">Pending</span>
                                </p>
                            </div>
                            <div class="rounded-2xl border px-3 py-2" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/12 bg-white'">
                                <span :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Position</span>
                                <p class="font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">
                                    <span v-if="player.athlete_position">{{ player.athlete_position }}</span>
                                    <span v-else class="text-red-600">Unassigned</span>
                                </p>
                            </div>
                            <div class="rounded-2xl border px-3 py-2" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/12 bg-white'">
                                <span :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Height</span>
                                <p class="font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ formatMeasure(player.student?.height, 'cm') }}</p>
                            </div>
                            <div class="rounded-2xl border px-3 py-2" :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-[#034485]/12 bg-white'">
                                <span :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Weight</span>
                                <p class="font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ formatMeasure(player.student?.weight, 'kg') }}</p>
                            </div>
                        </div>
                    </article>
                </div>

                <p v-else class="mt-4" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">No players assigned.</p>
            </section>
        </div>

        <Teleport to="body">
            <transition name="athlete-modal">
                <div v-if="detailsOpen" class="fixed inset-0 z-[100] overflow-y-auto bg-slate-900/40 px-4 py-6 backdrop-blur-sm">
                    <div class="flex min-h-full items-center justify-center" @click="closeDetails">
                        <div class="flex max-h-[calc(100vh-3rem)] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-[#034485]/35 bg-white shadow-[0_28px_70px_-34px_rgba(2,12,27,0.45)]" @click.stop>
                            <div class="rounded-t-3xl bg-[#034485] px-6 py-5 text-white sm:px-8">
                                <p class="text-xs font-semibold uppercase tracking-wide text-white/75">Player Details</p>
                                <h3 class="mt-1 text-2xl font-bold text-white">
                                    {{ formatSimple(selectedStudent?.first_name) }} {{ formatSimple(selectedStudent?.last_name) }}
                                </h3>
                                <p class="mt-1 text-xs text-white/80">Student ID: {{ formatSimple(selectedStudent?.student_id_number) }}</p>
                            </div>
                            <div class="overflow-y-auto p-6 sm:p-8">
                                <div class="flex flex-wrap items-start justify-between gap-6">
                                    <div class="min-w-[220px] flex-1 space-y-4">
                                        <div class="grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                                            <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] px-3 py-3 sm:col-span-2">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Student ID</p>
                                                <p class="mt-1 inline-flex items-center gap-2 font-semibold text-slate-900">
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
                                            <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] px-3 py-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Position</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ formatSimple(selectedPlayer?.athlete_position) }}</p>
                                            </div>
                                            <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] px-3 py-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Jersey</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ formatSimple(selectedPlayer?.jersey_number) }}</p>
                                            </div>
                                        </div>

                                        <div class="grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                                            <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] px-3 py-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Course/Strand</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ formatSimple(selectedStudent?.course_or_strand) }}</p>
                                            </div>
                                            <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] px-3 py-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Academic Level</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ formatSimple(selectedStudent?.academic_level_label ?? selectedStudent?.current_grade_level) }}</p>
                                            </div>
                                            <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] px-3 py-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Gender</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ formatSimple(selectedStudent?.gender) }}</p>
                                            </div>
                                            <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] px-3 py-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Height</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ formatMeasure(selectedStudent?.height, 'cm') }}</p>
                                            </div>
                                            <div class="rounded-2xl border border-[#034485]/15 bg-[#f7fbff] px-3 py-3 sm:col-span-2">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#034485]">Weight</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ formatMeasure(selectedStudent?.weight, 'kg') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-full border border-[#034485]/20 bg-[#f7fbff]">
                                        <img :src="userAvatarUrl(selectedStudent?.user?.avatar ?? null)" alt="Student avatar" class="h-full w-full object-cover" />
                                    </div>
                                </div>

                                <div class="mt-6 border-t border-slate-200 pt-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Contact</p>
                                    <div class="mt-2 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
                                        <p>
                                            <span class="font-semibold text-slate-900">Email:</span>
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
                                                        <path fill="currentColor" d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                                    </svg>
                                                    <span v-if="copiedField === 'email'" class="text-[10px]">Email Copied</span>
                                                </button>
                                            </span>
                                        </p>
                                        <p>
                                            <span class="font-semibold text-slate-900">Phone:</span>
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
                                                        <path fill="currentColor" d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                                    </svg>
                                                    <span v-if="copiedField === 'phone'" class="text-[10px]">Phone Number Copied</span>
                                                </button>
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-6 border-t border-slate-200 pt-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Emergency Contact</p>
                                    <div class="mt-2 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
                                        <p><span class="font-semibold text-slate-900">Name:</span> {{ formatSimple(selectedStudent?.emergency_contact_name) }}</p>
                                        <p><span class="font-semibold text-slate-900">Relationship:</span> {{ formatSimple(selectedStudent?.emergency_contact_relationship) }}</p>
                                        <p class="sm:col-span-2">
                                            <span class="font-semibold text-slate-900">Phone:</span>
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
                                                        <path fill="currentColor" d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
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

@media (prefers-reduced-motion: reduce) {
    .athlete-modal-enter-active,
    .athlete-modal-leave-active {
        transition: none;
    }
}
</style>
