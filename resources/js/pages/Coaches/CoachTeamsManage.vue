<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import Checkbox from 'primevue/checkbox'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, watch } from 'vue'

import { useTheme } from '@/composables/useTheme'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import { resolveTeamAvatarUrl as teamAvatarUrl, resolveUserAvatarUrl as userAvatarUrl } from '@/utils/media'

defineOptions({ layout: CoachDashboard })

type TeamListRow = {
    id: number
    team_name: string
    year: string | null
    description: string | null
    players_count: number
    archived_at: string | null
    assistant_coach_name?: string | null
}

type AssistantCoachSummary = {
    id: number
    name: string
    email: string | null
    avatar: string | null
    coach_status?: string | null
}

type SelectedTeam = {
    id: number
    team_name: string
    team_avatar: string | null
    year: string | null
    description: string | null
    archived_at: string | null
    head_coach: AssistantCoachSummary | null
    assistant_coach: AssistantCoachSummary | null
    player_ids: number[]
    players: Array<{
        id: number
        student_id: number
        name: string | null
        student_id_number: string | null
    }>
}

type PlayerOption = {
    id: number
    name: string
    student_id_number: string | null
    academic_level_label: string | null
    course_or_strand: string | null
    applied_sport: string | null
}

type AssistantCoachOption = {
    id: number
    name: string
    email: string | null
    avatar: string | null
    coach_status: string | null
    is_available: boolean
    assigned_team_name: string | null
    assigned_role: string | null
    unavailable_reason: string | null
}

type SeasonTemplate = {
    id: number
    label: string
    year: string | number | null
    player_count: number
    assistant_coach_name: string | null
    archived_at: string | null
}

const props = defineProps<{
    sport: { id: number | null; name: string | null } | null
    teams: TeamListRow[]
    selectedTeam: SelectedTeam | null
    availablePlayers: PlayerOption[]
    availableAssistantCoaches: AssistantCoachOption[]
    seasonTemplates: SeasonTemplate[]
    maxPlayers: number
    mode: 'create' | 'edit'
}>()

const { isDarkMode } = useTheme()

const createForm = useForm({
    team_name: '',
    year: '',
    description: '',
    team_avatar: null as File | null,
    clone_from_team_id: '',
    copy_players: true,
    copy_assistant_coach: true,
})

const detailsForm = useForm({
    team_name: '',
    year: '',
    description: '',
    team_avatar: null as File | null,
})

const rosterForm = useForm({
    player_ids: [] as number[],
})

watch(
    () => props.selectedTeam,
    (team) => {
        detailsForm.team_name = team?.team_name ?? ''
        detailsForm.year = team?.year ?? ''
        detailsForm.description = team?.description ?? ''
        detailsForm.team_avatar = null
        detailsForm.clearErrors()

        rosterForm.player_ids = team?.player_ids ?? []
        rosterForm.clearErrors()
    },
    { immediate: true },
)

watch(
    () => createForm.clone_from_team_id,
    (value) => {
        if (value) return
        createForm.copy_players = true
        createForm.copy_assistant_coach = true
    },
)

const createMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => Boolean(props.selectedTeam?.id) && !createMode.value)
const isArchived = computed(() => Boolean(props.selectedTeam?.archived_at))
const currentPlayerCount = computed(() => rosterForm.player_ids.length)
const selectedTemplate = computed(() => props.seasonTemplates.find((team) => String(team.id) === String(createForm.clone_from_team_id)) ?? null)
const seasonTemplateOptions = computed(() =>
    props.seasonTemplates.map((team) => ({
        label: team.label,
        value: String(team.id),
    })),
)
const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear()
    return Array.from({ length: 10 }, (_, index) => String(currentYear + 3 - index))
})

function selectTeam(teamId: number) {
    router.get('/coach/teams/manage', { team_id: teamId }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function startCreate() {
    router.get('/coach/teams/manage', { mode: 'create' }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function backToMyTeam() {
    router.get('/coach/team', props.selectedTeam?.id ? { team_id: props.selectedTeam.id } : {}, {
        preserveScroll: true,
        preserveState: true,
    })
}

function onCreateAvatarSelect(files: File[]) {
    createForm.team_avatar = files.length ? files[0] : null
}

function onDetailsAvatarSelect(files: File[]) {
    detailsForm.team_avatar = files.length ? files[0] : null
}

function submitCreate() {
    createForm
        .transform((data) => ({
            ...data,
            clone_from_team_id: data.clone_from_team_id || null,
            team_avatar: createForm.team_avatar,
        }))
        .post('/coach/teams', {
            forceFormData: true,
            preserveScroll: true,
        })
}

function submitDetails() {
    if (!props.selectedTeam) return

    detailsForm
        .transform(() => ({
            ...detailsForm.data(),
            team_avatar: detailsForm.team_avatar,
        }))
        .post(`/coach/teams/${props.selectedTeam.id}?_method=PUT`, {
            forceFormData: true,
            preserveScroll: true,
        })
}

function submitRoster() {
    if (!props.selectedTeam) return

    rosterForm.put(`/coach/teams/${props.selectedTeam.id}/roster`, {
        preserveScroll: true,
    })
}

function assignAssistant(coachId: number) {
    if (!props.selectedTeam) return

    router.post(`/coach/teams/${props.selectedTeam.id}/assistant-coaches/${coachId}`, {}, {
        preserveScroll: true,
    })
}

function removeAssistant() {
    if (!props.selectedTeam) return

    router.delete(`/coach/teams/${props.selectedTeam.id}/assistant-coach`, {
        preserveScroll: true,
    })
}

function archiveTeam() {
    if (!props.selectedTeam) return
    router.post(`/coach/teams/${props.selectedTeam.id}/archive`, {}, { preserveScroll: true })
}

function reactivateTeam() {
    if (!props.selectedTeam) return
    router.post(`/coach/teams/${props.selectedTeam.id}/reactivate`, {}, { preserveScroll: true })
}

function initialsFromText(value?: string | null) {
    return String(value ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'NA'
}

function assistantButtonLabel(option: AssistantCoachOption) {
    if (props.selectedTeam?.assistant_coach?.id === option.id) return 'Assigned'
    return 'Assign'
}

function canAssignAssistant(option: AssistantCoachOption) {
    return option.is_available || props.selectedTeam?.assistant_coach?.id === option.id
}
</script>

<template>
    <Head title="Coach Team Management" />

    <div class="space-y-6">
        <div>
            <button
                type="button"
                class="inline-flex items-center rounded-full border border-[#034485]/25 bg-white px-4 py-2 text-sm font-semibold text-[#034485] transition hover:bg-[#eef5ff]"
                @click="backToMyTeam"
            >
                Back to My Team
            </button>
        </div>

        <section class="rounded-3xl border border-[#02315f] bg-[#034485] p-6 text-white shadow-[0_24px_60px_-36px_rgba(3,68,133,0.55)]">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">Coach Workflow</p>
                    <h1 class="mt-2 text-2xl font-bold">Manage Teams</h1>
                    <p class="mt-2 text-sm text-white/85">
                        Create a new season team first, then manage the roster and assistant coach as separate steps. Your account is always the head coach for teams you create here.
                    </p>
                </div>
                <button
                    class="rounded-xl border border-white/25 bg-white/15 px-4 py-2 text-sm font-semibold text-white shadow-[0_12px_30px_-18px_rgba(15,23,42,0.8)] backdrop-blur-md transition hover:bg-white/20"
                    @click="startCreate"
                >
                    Create New Season Team
                </button>
            </div>
        </section>

        <div class="space-y-6">
            <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-800 bg-slate-950' : 'border-[#034485]/20 bg-white'">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Season Teams</h2>
                    <span class="rounded-full bg-[#eef5ff] px-3 py-1 text-[11px] font-semibold text-[#034485]">
                        {{ props.teams.length }} total
                    </span>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="team in props.teams"
                        :key="team.id"
                        type="button"
                        class="w-full rounded-2xl border p-4 text-left transition"
                        :class="[
                            props.selectedTeam?.id === team.id && !createMode
                                ? 'border-[#034485] bg-[#eef5ff] text-slate-900'
                                : isDarkMode
                                    ? 'border-slate-800 bg-slate-900 text-slate-100 hover:border-slate-700'
                                    : 'border-[#034485]/15 bg-white text-slate-900 hover:border-[#034485]/40',
                        ]"
                        @click="selectTeam(team.id)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ team.team_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ team.year || 'No year' }} • {{ team.players_count }} players
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Assistant: {{ team.assistant_coach_name || 'Unassigned' }}
                                </p>
                            </div>
                            <span v-if="team.archived_at" class="rounded-full bg-amber-100 px-3 py-1 text-[11px] font-semibold text-amber-700">Archived</span>
                        </div>
                    </button>
                    <div v-if="props.teams.length === 0" class="rounded-2xl border border-dashed p-5 text-sm text-slate-500 md:col-span-2 xl:col-span-3" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-[#034485]/15 bg-[#f6faff]'">
                        No teams created yet for this sport.
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section
                    v-if="createMode"
                    class="rounded-3xl border p-5"
                    :class="isDarkMode ? 'border-slate-800 bg-slate-950' : 'border-[#034485]/20 bg-white'"
                >
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Step 1</p>
                        <h2 class="mt-2 text-xl font-bold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Create New Season Team</h2>
                        <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                            Start with the team details, then choose whether to carry forward the previous season’s players or assistant coach.
                        </p>
                    </div>

                    <form class="mt-5 space-y-5" @submit.prevent="submitCreate">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Team Name</label>
                                <InputText v-model="createForm.team_name" class="w-full" :invalid="Boolean(createForm.errors.team_name)" />
                                <Message v-if="createForm.errors.team_name" severity="error" size="small" variant="simple" class="mt-1">
                                    {{ createForm.errors.team_name }}
                                </Message>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Season / Year</label>
                                <Select v-model="createForm.year" :options="yearOptions" placeholder="Select season year" class="w-full" :invalid="Boolean(createForm.errors.year)" />
                                <Message v-if="createForm.errors.year" severity="error" size="small" variant="simple" class="mt-1">
                                    {{ createForm.errors.year }}
                                </Message>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Team Avatar</label>
                            <FileUpload
                                mode="basic"
                                customUpload
                                chooseLabel="Choose Team Avatar"
                                accept="image/*"
                                class="team-avatar-upload w-full"
                                :invalid="Boolean(createForm.errors.team_avatar)"
                                @select="(event) => onCreateAvatarSelect(event.files)"
                            />
                            <p class="mt-1 text-xs text-slate-500">Selected: {{ createForm.team_avatar?.name || 'No file selected' }}</p>
                            <Message v-if="createForm.errors.team_avatar" severity="error" size="small" variant="simple" class="mt-1">
                                {{ createForm.errors.team_avatar }}
                            </Message>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Description</label>
                            <Textarea v-model="createForm.description" rows="3" autoResize class="w-full" :invalid="Boolean(createForm.errors.description)" />
                            <Message v-if="createForm.errors.description" severity="error" size="small" variant="simple" class="mt-1">
                                {{ createForm.errors.description }}
                            </Message>
                        </div>

                        <div class="rounded-2xl border p-4" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-[#034485]/15 bg-[#f6faff]'">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Copy From Previous Team</p>
                                    <p class="mt-1 text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                                        Use a previous season as a starting point instead of rebuilding everything from scratch.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <Select
                                    v-model="createForm.clone_from_team_id"
                                    :options="seasonTemplateOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Start Fresh"
                                    showClear
                                    class="w-full"
                                    :invalid="Boolean(createForm.errors.clone_from_team_id)"
                                />
                                <Message v-if="createForm.errors.clone_from_team_id" severity="error" size="small" variant="simple" class="mt-1">
                                    {{ createForm.errors.clone_from_team_id }}
                                </Message>
                            </div>

                            <div v-if="selectedTemplate" class="mt-4 space-y-3">
                                <div class="rounded-2xl border px-4 py-3 text-sm" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-200' : 'border-[#034485]/10 bg-white text-slate-700'">
                                    <p><span class="font-semibold">Template:</span> {{ selectedTemplate.label }}</p>
                                    <p class="mt-1"><span class="font-semibold">Players:</span> {{ selectedTemplate.player_count }}</p>
                                    <p class="mt-1"><span class="font-semibold">Assistant Coach:</span> {{ selectedTemplate.assistant_coach_name || 'Unassigned' }}</p>
                                </div>

                                <label class="flex items-start gap-3 rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-[#034485]/10 bg-white text-slate-900'">
                                    <div class="mt-1">
                                        <Checkbox v-model="createForm.copy_players" binary />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold">Copy previous roster</p>
                                        <p class="mt-1 text-xs text-slate-500">Preload the same approved players so you can remove graduates and add new athletes later.</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-[#034485]/10 bg-white text-slate-900'">
                                    <div class="mt-1">
                                        <Checkbox v-model="createForm.copy_assistant_coach" binary />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold">Copy assistant coach</p>
                                        <p class="mt-1 text-xs text-slate-500">Reuse the previous assistant coach when that assignment is still available.</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-xl bg-[#034485] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#02315f] disabled:opacity-60" :disabled="createForm.processing">
                                {{ createForm.processing ? 'Creating...' : 'Create Team' }}
                            </button>
                        </div>
                    </form>
                </section>

                <template v-else-if="isEditMode && props.selectedTeam">
                    <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-800 bg-slate-950' : 'border-[#034485]/20 bg-white'">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-4">
                                <div class="h-20 w-20 overflow-hidden rounded-2xl border border-[#034485]/15 bg-white">
                                    <img :src="teamAvatarUrl(props.selectedTeam.team_avatar)" alt="Team avatar" class="h-full w-full object-cover" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Current Team Workspace</p>
                                    <h2 class="mt-2 text-2xl font-bold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ props.selectedTeam.team_name }}</h2>
                                    <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                                        {{ props.selectedTeam.year || 'No year' }} • Head Coach: {{ props.selectedTeam.head_coach?.name || 'Current coach' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    v-if="!isArchived"
                                    class="rounded-xl border border-amber-200 bg-white px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50"
                                    @click="archiveTeam"
                                >
                                    Archive
                                </button>
                                <button
                                    v-else
                                    class="rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                                    @click="reactivateTeam"
                                >
                                    Reactivate
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-800 bg-slate-950' : 'border-[#034485]/20 bg-white'">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Step 1</p>
                                <h3 class="mt-2 text-xl font-bold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Team Details</h3>
                                <p class="mt-1 text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                                    Update the season details here. Head coach assignment stays tied to the current signed-in coach.
                                </p>
                            </div>
                        </div>

                        <form class="mt-5 space-y-5" @submit.prevent="submitDetails">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Team Name</label>
                                    <InputText v-model="detailsForm.team_name" class="w-full" :invalid="Boolean(detailsForm.errors.team_name)" />
                                    <Message v-if="detailsForm.errors.team_name" severity="error" size="small" variant="simple" class="mt-1">
                                        {{ detailsForm.errors.team_name }}
                                    </Message>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Season / Year</label>
                                    <Select v-model="detailsForm.year" :options="yearOptions" placeholder="Select season year" class="w-full" :invalid="Boolean(detailsForm.errors.year)" />
                                    <Message v-if="detailsForm.errors.year" severity="error" size="small" variant="simple" class="mt-1">
                                        {{ detailsForm.errors.year }}
                                    </Message>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Team Avatar</label>
                                <FileUpload
                                    mode="basic"
                                    customUpload
                                    chooseLabel="Choose Team Avatar"
                                    accept="image/*"
                                    class="team-avatar-upload w-full"
                                    :invalid="Boolean(detailsForm.errors.team_avatar)"
                                    @select="(event) => onDetailsAvatarSelect(event.files)"
                                />
                                <p class="mt-1 text-xs text-slate-500">Selected: {{ detailsForm.team_avatar?.name || 'No file selected' }}</p>
                                <Message v-if="detailsForm.errors.team_avatar" severity="error" size="small" variant="simple" class="mt-1">
                                    {{ detailsForm.errors.team_avatar }}
                                </Message>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Description</label>
                                <Textarea v-model="detailsForm.description" rows="3" autoResize class="w-full" :invalid="Boolean(detailsForm.errors.description)" />
                                <Message v-if="detailsForm.errors.description" severity="error" size="small" variant="simple" class="mt-1">
                                    {{ detailsForm.errors.description }}
                                </Message>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-xl bg-[#034485] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#02315f] disabled:opacity-60" :disabled="detailsForm.processing">
                                    {{ detailsForm.processing ? 'Saving...' : 'Save Team Details' }}
                                </button>
                            </div>
                        </form>
                    </section>

                    <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-800 bg-slate-950' : 'border-[#034485]/20 bg-white'">
                        <div class="flex flex-col gap-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Step 2</p>
                            <h3 class="text-xl font-bold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Assistant Coach</h3>
                            <p class="text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                                Choose one assistant coach for this season team. The same coach cannot be assigned to another active team at the same time.
                            </p>
                        </div>

                        <div class="mt-5 grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                            <article class="rounded-2xl border p-4" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-[#034485]/12 bg-[#f6faff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Current Assistant</p>
                                <div v-if="props.selectedTeam.assistant_coach" class="mt-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-sm font-bold text-slate-700">
                                            <img v-if="props.selectedTeam.assistant_coach.avatar" :src="userAvatarUrl(props.selectedTeam.assistant_coach.avatar)" alt="Assistant coach avatar" class="h-full w-full object-cover" />
                                            <span v-else>{{ initialsFromText(props.selectedTeam.assistant_coach.name) }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ props.selectedTeam.assistant_coach.name }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ props.selectedTeam.assistant_coach.email || 'No email available' }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Status: {{ props.selectedTeam.assistant_coach.coach_status || 'Not set' }}</p>
                                        </div>
                                    </div>
                                    <button class="mt-4 rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50" @click="removeAssistant">
                                        Remove Assistant Coach
                                    </button>
                                </div>
                                <div v-else class="mt-4 rounded-2xl border border-dashed p-4 text-sm text-slate-500" :class="isDarkMode ? 'border-slate-700 bg-slate-950' : 'border-[#034485]/12 bg-white'">
                                    No assistant coach is assigned to this team yet.
                                </div>
                            </article>

                            <article class="rounded-2xl border p-4" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-[#034485]/12 bg-[#f6faff]'">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Available Assistant Coaches</p>
                                <div class="mt-4 max-h-[26rem] space-y-3 overflow-y-auto">
                                    <div
                                        v-for="option in props.availableAssistantCoaches"
                                        :key="option.id"
                                        class="rounded-2xl border p-4"
                                        :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-slate-100' : 'border-[#034485]/10 bg-white text-slate-900'"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold">{{ option.name }}</p>
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ option.email || 'No email available' }}</p>
                                                <p class="mt-1 text-xs text-slate-500">Status: {{ option.coach_status || 'Not set' }}</p>
                                                <p v-if="!option.is_available && props.selectedTeam.assistant_coach?.id !== option.id" class="mt-2 text-xs text-amber-600">
                                                    {{ option.unavailable_reason || 'Currently unavailable' }}
                                                </p>
                                            </div>
                                            <button
                                                class="rounded-xl px-3 py-2 text-xs font-semibold transition"
                                                :class="canAssignAssistant(option) ? 'bg-[#034485] text-white hover:bg-[#02315f]' : 'cursor-not-allowed border border-slate-200 bg-slate-100 text-slate-400'"
                                                :disabled="!canAssignAssistant(option)"
                                                @click="assignAssistant(option.id)"
                                            >
                                                {{ assistantButtonLabel(option) }}
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="props.availableAssistantCoaches.length === 0" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500" :class="isDarkMode ? 'border-slate-700 bg-slate-950' : 'border-[#034485]/10 bg-white'">
                                        No assistant-coach options are available for this sport yet.
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="rounded-3xl border p-5" :class="isDarkMode ? 'border-slate-800 bg-slate-950' : 'border-[#034485]/20 bg-white'">
                        <div class="flex flex-col gap-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Step 3</p>
                            <h3 class="text-xl font-bold" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">Roster Management</h3>
                            <p class="text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">
                                Add or remove approved student-athletes for this season. Players must belong to the same sport and cannot already be on another active team.
                            </p>
                        </div>

                        <form class="mt-5 space-y-5" @submit.prevent="submitRoster">
                            <div class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-800 bg-slate-900 text-slate-100' : 'border-[#034485]/12 bg-[#f6faff] text-slate-900'">
                                <div>
                                    <p class="text-sm font-semibold">Selected Players</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ currentPlayerCount }} / {{ props.maxPlayers }} roster slots used</p>
                                </div>
                                <span class="rounded-full bg-[#034485] px-3 py-1 text-[11px] font-semibold text-white">
                                    {{ props.selectedTeam.players.length }} current entries
                                </span>
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Eligible Players</label>
                                    <span class="text-xs text-slate-500">Same sport, approved, and not on another active team</span>
                                </div>
                                <div class="mt-2 max-h-80 space-y-2 overflow-y-auto rounded-2xl border p-3" :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-[#034485]/15 bg-[#f6faff]'">
                                    <label
                                        v-for="player in props.availablePlayers"
                                        :key="player.id"
                                        class="flex items-start justify-between gap-3 rounded-2xl border px-3 py-3"
                                        :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-100' : 'border-[#034485]/10 bg-white text-slate-900'"
                                    >
                                        <div>
                                            <p class="font-semibold">{{ player.name }}</p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ player.student_id_number || 'No ID' }} • {{ player.academic_level_label || '-' }} • {{ player.course_or_strand || '-' }}
                                            </p>
                                        </div>
                                        <div class="mt-1">
                                            <Checkbox v-model="rosterForm.player_ids" :value="player.id" />
                                        </div>
                                    </label>
                                    <div v-if="props.availablePlayers.length === 0" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500" :class="isDarkMode ? 'border-slate-800 bg-slate-950' : 'border-[#034485]/10 bg-white'">
                                        No eligible players are currently available for this sport.
                                    </div>
                                </div>
                                <Message v-if="rosterForm.errors.player_ids" severity="error" size="small" variant="simple" class="mt-1">
                                    {{ rosterForm.errors.player_ids }}
                                </Message>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-xl bg-[#034485] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#02315f] disabled:opacity-60" :disabled="rosterForm.processing">
                                    {{ rosterForm.processing ? 'Saving...' : 'Save Roster' }}
                                </button>
                            </div>
                        </form>
                    </section>
                </template>

                <section
                    v-else
                    class="rounded-3xl border border-dashed p-8 text-center"
                    :class="isDarkMode ? 'border-slate-800 bg-slate-950 text-slate-200' : 'border-[#034485]/20 bg-white text-slate-700'"
                >
                    Select a season team from the list or create a new one to start managing details, assistant coach assignments, and roster updates.
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.team-avatar-upload .p-button) {
    width: 100%;
    justify-content: center;
    border-color: #034485;
    background: #034485;
    color: #ffffff;
}

:deep(.team-avatar-upload .p-button:hover) {
    border-color: #02315f;
    background: #02315f;
}
</style>
