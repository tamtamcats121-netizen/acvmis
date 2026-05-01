<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { showAppToast } from '@/composables/useAppToast';
import { useSportColors } from '@/composables/useSportColors';
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue';
import { resolveTeamAvatarUrl as teamAvatarUrl, resolveUserAvatarUrl as userAvatarUrl } from '@/utils/media';

defineOptions({
    layout: StudentAthleteDashboard,
});

const props = defineProps<{
    team: any | null
    teams: Array<{ id: number; team_name: string; sport: string }>
    selectedTeamId: number | null
    currentStudentId: number | null
}>();

const showTeam = computed(() => !!props.team);
const jerseyDraft = ref('');
const selectedTeamId = ref<number | null>(props.selectedTeamId ?? null)
const { sportColor, sportTextColor, sportLabel } = useSportColors()
const copiedField = ref<string | null>(null)
const jerseySaving = ref(false)
const jerseyStatus = ref<'idle' | 'saved' | 'error'>('idle')

const myMembership = computed(() => {
    if (!props.team?.players?.length || !props.currentStudentId) return null;
    return props.team.players.find((player: any) => player.student?.id === props.currentStudentId) ?? null;
});
const otherPlayers = computed(() => {
    if (!props.team?.players?.length) return []
    return props.team.players.filter((player: any) => player.student?.id !== props.currentStudentId)
})

const totalPlayers = computed(() => props.team?.players?.length ?? 0)
const positionsFilled = computed(() => {
    if (!props.team?.players?.length) return 0
    return props.team.players.filter((player: any) => (player.athlete_position ?? '').toString().trim() !== '').length
})
const jerseysAssigned = computed(() => {
    if (!props.team?.players?.length) return 0
    return props.team.players.filter((player: any) => (player.jersey_number ?? '').toString().trim() !== '').length
})
const jerseyDirty = computed(() => {
    return String(jerseyDraft.value ?? '') !== String(myMembership.value?.jersey_number ?? '')
})

const detailsOpen = ref(false)
const selectedPlayer = ref<any | null>(null)
const selectedStudent = computed(() => selectedPlayer.value?.student ?? null)

function openDetails(player: any) {
    selectedPlayer.value = player
    detailsOpen.value = true
}

function closeDetails() {
    detailsOpen.value = false
}

function finishDetailsClose() {
    selectedPlayer.value = null
}

watch(
    myMembership,
    (membership) => {
        jerseyDraft.value = membership?.jersey_number ?? '';
    },
    { immediate: true },
);

watch(
    () => props.selectedTeamId,
    (val) => {
        selectedTeamId.value = val ?? null
    },
)

function saveDesiredJersey() {
    if (!myMembership.value) return;

    const nextJersey = String(jerseyDraft.value ?? '').trim()
    const previousJersey = String(myMembership.value.jersey_number ?? '').trim()

    router.put(
        `/Student/TeamPlayers/${myMembership.value.id}/jersey`,
        { jersey_number: jerseyDraft.value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                jerseySaving.value = true
                jerseyStatus.value = 'idle'
            },
            onFinish: () => {
                jerseySaving.value = false
            },
            onSuccess: () => {
                jerseyStatus.value = 'saved'

                const detail = nextJersey === ''
                    ? 'Your jersey request has been cleared.'
                    : previousJersey === ''
                        ? `Your jersey request for #${nextJersey} has been submitted.`
                        : `Your jersey request has been updated to #${nextJersey}.`

                showAppToast(detail, 'success', {
                    summary: 'Jersey Request Updated',
                })

                setTimeout(() => {
                    jerseyStatus.value = 'idle'
                }, 1500)
            },
            onError: (errors: Record<string, string | string[]>) => {
                jerseySaving.value = false
                jerseyStatus.value = 'error'

                const jerseyError = errors?.jersey_number
                const detail = Array.isArray(jerseyError)
                    ? String(jerseyError[0] ?? 'We could not update your jersey request. Please try again.')
                    : String(jerseyError ?? 'We could not update your jersey request. Please try again.')

                showAppToast(detail, 'error', {
                    summary: 'Jersey Request Failed',
                })
            },
        },
    );
}

function initialsFromParts(...parts: Array<string | null | undefined>) {
    return parts
        .flatMap((part) => String(part ?? '').trim().split(/\s+/))
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'NA'
}

function changeTeam() {
    if (!selectedTeamId.value) return
    router.get('/MyTeam', { team_id: selectedTeamId.value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function copyToClipboard(value: string | null | undefined, key: string) {
    if (!value) return
    navigator.clipboard?.writeText(value).then(() => {
        copiedField.value = key
        setTimeout(() => {
            if (copiedField.value === key) copiedField.value = null
        }, 1500)
    }).catch(() => {})
}

function statusTone(status?: string | null) {
    if (status === 'inactive') return 'bg-slate-200 text-slate-700'
    if (status === 'injured') return 'bg-rose-600 text-white'
    if (status === 'suspended') return 'bg-red-100 text-red-700'
    return 'bg-emerald-100 text-emerald-700'
}

function formatMeasure(value?: string | number | null, unit?: string) {
    if (value === null || value === undefined) return '-'
    const text = String(value).trim()
    if (!text) return '-'
    if (!unit || /[a-zA-Z]/.test(text)) return text
    return `${text} ${unit}`
}

function cardMotion(order: number) {
    return { '--card-order': String(order) }
}
</script>

<template>
    <div class="team-page-view space-y-6">
        <div v-if="props.teams.length" class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
            <div v-if="props.teams.length > 1" class="flex items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Team</span>
                <select
                    v-model.number="selectedTeamId"
                    @change="changeTeam"
                    class="rounded-md border border-[#034485]/40 px-2 py-1 text-xs text-slate-700"
                >
                    <option v-for="teamOption in props.teams" :key="teamOption.id" :value="teamOption.id">
                        {{ teamOption.team_name }}
                    </option>
                </select>
            </div>
        </div>

        <!-- No team assigned -->
        <div v-if="!showTeam" class="page-card bg-white rounded-3xl p-6 border border-[#034485]/35" :style="cardMotion(1)">
            <p class="text-slate-600 font-medium">You are not assigned to a team yet.</p>
            <p class="text-sm text-slate-500 mt-1">Once your assignment is confirmed, your team information, schedule, and post-training condition records will appear here.</p>
        </div>

        <!-- Team card -->
        <div v-else class="space-y-6">
            <section class="page-card bg-[#034485] rounded-3xl border border-[#034485]/35 p-6 text-white" :style="cardMotion(2)">
                <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div
                            class="h-20 w-20 rounded-2xl bg-white/10 border-2 overflow-hidden flex items-center justify-center"
                            :style="{ borderColor: sportColor(props.team?.sport?.name ?? props.team?.sport) }"
                        >
                            <img
                                :src="teamAvatarUrl(props.team.team_avatar)"
                                class="h-full w-full object-cover"
                                alt="Team avatar"
                            />
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-white/70">My Team</p>
                            <h2 class="text-2xl font-bold text-white">{{ props.team.team_name }}</h2>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 font-semibold"
                                    :style="{ backgroundColor: sportColor(props.team?.sport?.name ?? props.team?.sport), color: sportTextColor(props.team?.sport?.name ?? props.team?.sport) }"
                                >
                                    {{ sportLabel(props.team?.sport?.name ?? props.team?.sport) }}
                                </span>
                                <span class="inline-flex items-center rounded-full border border-white/30 px-3 py-1 font-semibold text-white/80">
                                    {{ props.team.year }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full border border-white/40 bg-white/10 px-3 py-1 text-xs font-semibold text-white">Athlete</span>
                        <span class="inline-flex items-center rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs font-semibold text-white">
                            {{ totalPlayers }} Players
                        </span>
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            {{ positionsFilled }} Positions Set
                        </span>
                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                            {{ jerseysAssigned }} Jerseys Set
                        </span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="page-card rounded-3xl border border-[#034485]/35 bg-white p-4 text-slate-800" :style="cardMotion(3)">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Head Coach</p>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700">
                                <img
                                    v-if="props.team.coach?.user?.avatar"
                                    :src="userAvatarUrl(props.team.coach.user.avatar)"
                                    alt="Head coach profile photo"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initialsFromParts(props.team.coach?.first_name, props.team.coach?.last_name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800">
                                    {{ props.team.coach?.first_name }} {{ props.team.coach?.last_name }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ props.team.coach?.email || props.team.coach?.phone_number || 'Contact available in details below' }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 space-y-2 text-xs">
                            <div
                                v-if="props.team.coach?.email"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Email</p>
                                    <p class="truncate text-sm font-medium text-slate-700">{{ props.team.coach.email }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.coach.email, 'coach-email')"
                                    class="inline-flex items-center gap-1 rounded-full border border-[#034485]/30 px-2.5 py-1 text-[11px] font-semibold text-[#034485] hover:bg-[#034485]/10"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'coach-email' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <div
                                v-if="props.team.coach?.phone_number"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Phone</p>
                                    <p class="text-sm font-medium text-slate-700">{{ props.team.coach.phone_number }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.coach.phone_number, 'coach-phone')"
                                    class="inline-flex items-center gap-1 rounded-full border border-[#034485]/30 px-2.5 py-1 text-[11px] font-semibold text-[#034485] hover:bg-[#034485]/10"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'coach-phone' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <span
                                v-if="!props.team.coach?.email && !props.team.coach?.phone_number"
                                class="text-slate-400"
                            >
                                Contact the administrator for assistance
                            </span>
                        </div>
                    </div>
                    <div class="page-card rounded-3xl border border-[#034485]/35 bg-white p-4 text-slate-800" :style="cardMotion(4)">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Assistant Coach</p>
                        <div v-if="props.team.assistantCoach" class="mt-3 flex items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700">
                                <img
                                    v-if="props.team.assistantCoach?.user?.avatar"
                                    :src="userAvatarUrl(props.team.assistantCoach.user.avatar)"
                                    alt="Assistant coach profile photo"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initialsFromParts(props.team.assistantCoach?.first_name, props.team.assistantCoach?.last_name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800">
                                    {{ props.team.assistantCoach?.first_name }} {{ props.team.assistantCoach?.last_name }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ props.team.assistantCoach?.email || props.team.assistantCoach?.phone_number || 'Contact available in details below' }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm font-medium text-slate-400">Not assigned</p>
                        <div v-if="props.team.assistantCoach" class="mt-3 space-y-2 text-xs">
                            <div
                                v-if="props.team.assistantCoach?.email"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Email</p>
                                    <p class="truncate text-sm font-medium text-slate-700">{{ props.team.assistantCoach.email }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.assistantCoach.email, 'assistant-email')"
                                    class="inline-flex items-center gap-1 rounded-full border border-[#034485]/30 px-2.5 py-1 text-[11px] font-semibold text-[#034485] hover:bg-[#034485]/10"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'assistant-email' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <div
                                v-if="props.team.assistantCoach?.phone_number"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Phone</p>
                                    <p class="text-sm font-medium text-slate-700">{{ props.team.assistantCoach.phone_number }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.team.assistantCoach.phone_number, 'assistant-phone')"
                                    class="inline-flex items-center gap-1 rounded-full border border-[#034485]/30 px-2.5 py-1 text-[11px] font-semibold text-[#034485] hover:bg-[#034485]/10"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor">
                                        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                    </svg>
                                    <span>{{ copiedField === 'assistant-phone' ? 'Copied' : 'Copy' }}</span>
                                </button>
                            </div>
                            <span
                                v-if="!props.team.assistantCoach?.email && !props.team.assistantCoach?.phone_number"
                                class="text-slate-400"
                            >
                                Contact the administrator for assistance
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="myMembership" class="page-card bg-white rounded-3xl border border-[#034485]/35 p-6" :style="cardMotion(5)">

                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jersey Request</span>
                    <div class="flex items-center gap-2 rounded-full border border-[#034485]/30 bg-white px-3 py-2">
                        <input
                            id="desired-jersey"
                            v-model="jerseyDraft"
                            type="text"
                            maxlength="20"
                            class="w-20 bg-transparent text-sm font-semibold text-slate-700 outline-none"
                            placeholder="e.g. 7"
                        />
                        <button
                            @click="saveDesiredJersey"
                            :disabled="!jerseyDirty || jerseySaving"
                            class="rounded-full bg-[#034485] px-3 py-1 text-xs font-semibold text-white hover:bg-[#033a70] disabled:opacity-50"
                        >
                            {{ jerseySaving ? 'Saving' : 'Save' }}
                        </button>
                    </div>
                    <span v-if="jerseyStatus === 'saved'" class="text-xs text-emerald-600">Saved</span>
                    <span v-else-if="jerseyStatus === 'error'" class="text-xs text-rose-600">Failed</span>
                </div>
            </section>

            <section class="page-card bg-white rounded-3xl border border-[#034485]/35 p-6" :style="cardMotion(6)">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">Team Members</h3>
                    <span class="text-sm text-slate-500">{{ totalPlayers }} total</span>
                </div>

                <div v-if="props.team.players?.length" class="mt-4 space-y-4">
                    <div v-if="myMembership" class="page-card rounded-2xl border border-[#034485]/35 bg-white p-4 shadow-sm" :style="cardMotion(7)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700">
                                    <img
                                        v-if="myMembership.student?.user?.avatar"
                                        :src="userAvatarUrl(myMembership.student.user.avatar)"
                                        alt="Student profile photo"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>{{ initialsFromParts(myMembership.student?.first_name, myMembership.student?.last_name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-base font-semibold text-slate-900">{{ myMembership.student?.first_name }} {{ myMembership.student?.last_name }}</p>
                                    <p class="text-xs text-slate-500">{{ myMembership.student?.student_id_number || '-' }}</p>
                                    <button
                                        type="button"
                                        class="mt-2 inline-flex rounded-full border border-[#034485] px-2.5 py-1 text-[11px] font-semibold text-[#034485] hover:bg-[#034485]/10"
                                        @click="openDetails(myMembership)"
                                    >
                                        View Details
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="rounded-full bg-[#034485] px-2 py-0.5 text-[10px] font-semibold text-white">You</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusTone(myMembership.player_status)">
                                    {{ (myMembership.player_status ?? 'active').toString().toUpperCase() }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-600 sm:grid-cols-4">
                            <div>
                                <span class="text-slate-500">Jersey</span>
                                <p class="font-semibold text-slate-900">
                                    <span v-if="myMembership.jersey_number">{{ myMembership.jersey_number }}</span>
                                    <span v-else class="text-amber-600">Pending</span>
                                </p>
                            </div>
                            <div>
                                <span class="text-slate-500">Position</span>
                                <p class="font-semibold text-slate-900">
                                    <span v-if="myMembership.athlete_position">{{ myMembership.athlete_position }}</span>
                                    <span v-else class="text-red-600">Unassigned</span>
                                </p>
                            </div>
                            <div>
                                <span class="text-slate-500">Height</span>
                                <p class="font-semibold text-slate-900">{{ formatMeasure(myMembership.student?.height, 'cm') }}</p>
                            </div>
                            <div>
                                <span class="text-slate-500">Weight</span>
                                <p class="font-semibold text-slate-900">{{ formatMeasure(myMembership.student?.weight, 'kg') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="(player, index) in otherPlayers" :key="player.id" class="page-card rounded-2xl border border-[#034485]/35 bg-white p-4 shadow-sm" :style="cardMotion(8 + index)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700">
                                    <img
                                        v-if="player.student?.user?.avatar"
                                        :src="userAvatarUrl(player.student.user.avatar)"
                                        alt="Student profile photo"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>{{ initialsFromParts(player.student?.first_name, player.student?.last_name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-base font-semibold text-slate-900">{{ player.student?.first_name }} {{ player.student?.last_name }}</p>
                                    <p class="text-xs text-slate-500">{{ player.student?.student_id_number || '-' }}</p>
                                    <button
                                        type="button"
                                        class="mt-2 inline-flex rounded-full border border-[#034485] px-2.5 py-1 text-[11px] font-semibold text-[#034485] hover:bg-[#034485]/10"
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

                        <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-600 sm:grid-cols-4">
                            <div>
                                <span class="text-slate-500">Jersey</span>
                                <p class="font-semibold text-slate-900">
                                    <span v-if="player.jersey_number">{{ player.jersey_number }}</span>
                                    <span v-else class="text-amber-600">Pending</span>
                                </p>
                            </div>
                            <div>
                                <span class="text-slate-500">Position</span>
                                <p class="font-semibold text-slate-900">
                                    <span v-if="player.athlete_position">{{ player.athlete_position }}</span>
                                    <span v-else class="text-red-600">Unassigned</span>
                                </p>
                            </div>
                            <div>
                                <span class="text-slate-500">Height</span>
                                <p class="font-semibold text-slate-900">{{ formatMeasure(player.student?.height, 'cm') }}</p>
                            </div>
                            <div>
                                <span class="text-slate-500">Weight</span>
                                <p class="font-semibold text-slate-900">{{ formatMeasure(player.student?.weight, 'kg') }}</p>
                            </div>
                        </div>
                    </article>
                    </div>
                </div>

                <p v-else class="text-slate-500 mt-4">No players assigned.</p>
            </section>
        </div>

        <transition name="athlete-modal" @after-leave="finishDetailsClose">
            <div v-if="detailsOpen && selectedPlayer" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm" @click.self="closeDetails">
                <div class="w-full max-w-2xl rounded-3xl border border-[#0f4f9f] bg-[#091120] p-6 text-slate-100 shadow-[0_28px_80px_-30px_rgba(2,12,27,0.9)] sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-6">
                        <div class="min-w-[220px] flex-1 space-y-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Team Member</p>
                                <h3 class="text-2xl font-bold text-white">
                                    {{ selectedStudent?.first_name }} {{ selectedStudent?.last_name }}
                                </h3>
                                <p class="mt-1 text-xs text-slate-400">ID: {{ selectedStudent?.student_id_number || '-' }}</p>
                            </div>

                            <div class="grid gap-2 text-sm text-slate-300 sm:grid-cols-2">
                                <p><span class="font-semibold text-white">Position:</span> {{ selectedPlayer?.athlete_position || 'Unassigned' }}</p>
                                <p><span class="font-semibold text-white">Jersey:</span> {{ selectedPlayer?.jersey_number || '-' }}</p>
                                <p><span class="font-semibold text-white">Course/Strand:</span> {{ selectedStudent?.course_or_strand || '-' }}</p>
                                <p><span class="font-semibold text-white">Academic Level:</span> {{ selectedStudent?.academic_level_label || selectedStudent?.current_grade_level || '-' }}</p>
                                <p><span class="font-semibold text-white">Height:</span> {{ formatMeasure(selectedStudent?.height, 'cm') }}</p>
                                <p><span class="font-semibold text-white">Weight:</span> {{ formatMeasure(selectedStudent?.weight, 'kg') }}</p>
                                <p v-if="selectedStudent?.user?.email" class="sm:col-span-2">
                                    <span class="font-semibold text-white">Email:</span>
                                    <span class="ml-1 inline-flex items-center gap-2">
                                        {{ selectedStudent.user.email }}
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#6db1ff] hover:text-[#9cc9ff]"
                                            @click="copyToClipboard(selectedStudent.user.email, 'student-email')"
                                            title="Copy email"
                                            aria-label="Copy email"
                                        >
                                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor">
                                                <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                            </svg>
                                            <span v-if="copiedField === 'student-email'" class="text-[10px]">Email Copied</span>
                                        </button>
                                    </span>
                                </p>
                                <p v-if="selectedStudent?.phone_number" class="sm:col-span-2">
                                    <span class="font-semibold text-white">Phone:</span>
                                    <span class="ml-1 inline-flex items-center gap-2">
                                        {{ selectedStudent.phone_number }}
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#6db1ff] hover:text-[#9cc9ff]"
                                            @click="copyToClipboard(selectedStudent.phone_number, 'student-phone')"
                                            title="Copy phone number"
                                            aria-label="Copy phone number"
                                        >
                                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor">
                                                <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10ZM19 5H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H10V7h9Z" />
                                            </svg>
                                            <span v-if="copiedField === 'student-phone'" class="text-[10px]">Phone Number Copied</span>
                                        </button>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border border-[#0f4f9f] bg-[#0d1a32]">
                            <img
                                v-if="selectedStudent?.user?.avatar"
                                :src="userAvatarUrl(selectedStudent.user.avatar)"
                                alt="Student avatar"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="text-lg font-semibold text-[#8bbcff]">
                                {{ (selectedStudent?.first_name?.[0] || '') + (selectedStudent?.last_name?.[0] || '') }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="button"
                            class="rounded-full border border-[#0f4f9f] bg-[#0d1a32] px-4 py-2 text-sm font-semibold text-[#8bbcff] transition hover:bg-[#12305c]"
                            @click="closeDetails"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.team-page-view .page-card {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
    animation: student-page-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: calc(var(--card-order, 0) * 55ms);
    will-change: transform, opacity;
}

@keyframes student-page-card-rise {
    from {
        opacity: 0;
        transform: translateY(18px) scale(0.985);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.athlete-modal-enter-active,
.athlete-modal-leave-active {
    transition: opacity 220ms ease, transform 220ms ease;
}

.athlete-modal-enter-from,
.athlete-modal-leave-to {
    opacity: 0;
}

.athlete-modal-enter-from > div,
.athlete-modal-leave-to > div {
    transform: translateY(16px) scale(0.98);
}

@media (prefers-reduced-motion: reduce) {
    .team-page-view .page-card {
        animation: none;
        opacity: 1;
        transform: none;
    }

    .athlete-modal-enter-active,
    .athlete-modal-leave-active {
        transition: none;
    }
}
</style>
