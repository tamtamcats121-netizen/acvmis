<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import BackLinkButton from '@/components/ui/BackLinkButton.vue'
import EmptyResultsState from '@/components/ui/EmptyResultsState.vue'
import SearchFilterPanel from '@/components/ui/SearchFilterPanel.vue'
import { showAppToast } from '@/composables/useAppToast'
import { useSportColors } from '@/composables/useSportColors'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import { resolveTeamAvatarUrl as teamAvatarUrl, resolveUserAvatarUrl as userAvatarUrl } from '@/utils/media'

defineOptions({
    layout: AdminDashboard,
})

type TeamCoach = {
    id: number
    user_id: number | null
    name: string
    email: string | null
    avatar: string | null
    coach_status: string | null
}

type CoachOption = {
    id: number
    name: string
    status: string | null
    email: string | null
    avatar: string | null
    is_available: boolean
    assigned_team_id: number | null
    assigned_team_name: string | null
    assigned_sport_name: string | null
    assigned_role: string | null
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
        coach: TeamCoach | null
        assistantCoach: TeamCoach | null
    }
    coaches: CoachOption[]
    readOnly: boolean
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()

const search = ref('')
const availabilityFilter = ref<'all' | 'available' | 'assigned'>('all')
const assigningKey = ref<string | null>(null)
const removingRole = ref<string | null>(null)

const filteredCoaches = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.coaches.filter((coach) => {
        if (availabilityFilter.value === 'available' && !coach.is_available) return false
        if (availabilityFilter.value === 'assigned' && coach.is_available) return false

        if (!query) return true

        return [
            coach.name,
            coach.email,
            coach.status,
            coach.assigned_team_name,
            coach.assigned_sport_name,
            coach.assigned_role,
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

function canAssignHead(coach: CoachOption) {
    return coach.is_available || coach.id === props.team.coach?.id
}

function canAssignAssistant(coach: CoachOption) {
    return (coach.is_available || coach.id === props.team.assistantCoach?.id) && coach.id !== props.team.coach?.id
}

function assignCoach(coach: CoachOption, role: 'head' | 'assistant') {
    router.post(`/teams/${props.team.id}/coaches/${coach.id}`, {
        role,
    }, {
        preserveScroll: true,
        onStart: () => {
            assigningKey.value = `${coach.id}:${role}`
        },
        onSuccess: () => {
            showAppToast(`${coach.name} assigned successfully.`, 'success', {
                summary: 'Coach Assignment',
            })
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {}).flat()[0]
            showAppToast(String(firstError || 'Unable to update coach assignment.'), 'error', {
                summary: 'Coach Assignment',
            })
        },
        onFinish: () => {
            assigningKey.value = null
        },
    })
}

function removeAssistantCoach() {
    router.delete(`/teams/${props.team.id}/coaches/assistant`, {
        preserveScroll: true,
        onStart: () => {
            removingRole.value = 'assistant'
        },
        onSuccess: () => {
            showAppToast('Assistant coach removed successfully.', 'success', {
                summary: 'Coach Assignment',
            })
        },
        onError: () => {
            showAppToast('Unable to remove the assistant coach right now.', 'error', {
                summary: 'Coach Assignment',
            })
        },
        onFinish: () => {
            removingRole.value = null
        },
    })
}
</script>

<template>
    <Head :title="`${team.team_name} Coaches`" />

    <div class="space-y-6">
        <div class="space-y-1">
            <BackLinkButton :href="`/teams/${team.id}/view-roster`" label="Back to View Roster" />
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Coach assignment manager</p>
        </div>

        <section class="page-card rounded-3xl bg-[#034485] p-6 text-white shadow-[0_24px_60px_-36px_rgba(3,68,133,0.55)]">
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
                            Assign or replace the head coach and assistant coach from this dedicated staff selection page.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <section class="page-card rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Current Assignments</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Team Staff</h2>
                    <p class="mt-1 text-sm text-slate-500">Review the current head and assistant coach before making updates.</p>
                </div>

                <div class="mt-5 space-y-4">
                    <article class="page-card rounded-2xl border border-slate-200 bg-slate-50 p-4">
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

                    <article class="page-card rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Assistant Coach</p>
                            </div>
                            <button
                                v-if="!readOnly && team.assistantCoach"
                                type="button"
                                class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-60"
                                :disabled="removingRole === 'assistant'"
                                @click="removeAssistantCoach"
                            >
                                {{ removingRole === 'assistant' ? 'Removing...' : 'Remove Assistant' }}
                            </button>
                        </div>
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

            <section class="page-card rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Available Coaches</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Select a Coach</h2>
                        <p class="mt-1 text-sm text-slate-500">Search the coach pool and assign each coach directly to this team.</p>
                    </div>
                    <div class="w-full lg:w-[28rem]">
                        <SearchFilterPanel
                            v-model="search"
                            placeholder="Search by name, email, team, sport, or status"
                            :show-submit="false"
                            :show-filters-toggle="false"
                        >
                            <template #actions>
                                <select v-model="availabilityFilter" class="rounded-xl border border-[#034485]/20 px-3 py-2 text-sm">
                                    <option value="all">All Coaches</option>
                                    <option value="available">Available</option>
                                    <option value="assigned">Assigned Elsewhere</option>
                                </select>
                            </template>
                        </SearchFilterPanel>
                    </div>
                </div>

                <div class="mt-5 grid gap-4">
                    <article
                        v-for="coach in filteredCoaches"
                        :key="coach.id"
                        class="page-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700">
                                    <img v-if="coach.avatar" :src="userAvatarUrl(coach.avatar)" alt="Coach photo" class="h-full w-full object-cover" />
                                    <span v-else>{{ initialsFromText(coach.name) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-base font-semibold text-slate-900">{{ coach.name }}</p>
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                            :class="coach.is_available ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                        >
                                            {{ coach.is_available ? 'AVAILABLE' : 'ASSIGNED' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ coach.email || 'No email available' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Coach status: {{ coach.status || 'Not set' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Current assignment:
                                        {{ coach.assigned_team_name ? `${coach.assigned_team_name}${coach.assigned_sport_name ? ` • ${coach.assigned_sport_name}` : ''}${coach.assigned_role ? ` • ${coach.assigned_role}` : ''}` : 'No active team assignment' }}
                                    </p>
                                    <p v-if="coach.unavailable_reason" class="mt-1 text-xs text-amber-700">{{ coach.unavailable_reason }}</p>
                                </div>
                            </div>

                            <div v-if="!readOnly" class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full bg-[#034485] px-4 py-2 text-sm font-semibold text-white hover:bg-[#033a70] disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canAssignHead(coach) || assigningKey === `${coach.id}:head`"
                                    @click="assignCoach(coach, 'head')"
                                >
                                    {{ assigningKey === `${coach.id}:head` ? 'Assigning...' : 'Assign as Head Coach' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-[#034485]/25 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#034485]/5 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canAssignAssistant(coach) || assigningKey === `${coach.id}:assistant`"
                                    @click="assignCoach(coach, 'assistant')"
                                >
                                    {{ assigningKey === `${coach.id}:assistant` ? 'Assigning...' : 'Assign as Assistant' }}
                                </button>
                            </div>
                        </div>
                    </article>

                    <div v-if="filteredCoaches.length === 0">
                        <EmptyResultsState
                            title="No coaches matched your search"
                            description="Try another name, email, assignment, or availability filter."
                        />
                    </div>
                </div>
            </section>
        </section>
    </div>
</template>
