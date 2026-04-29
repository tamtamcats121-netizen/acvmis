<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref } from 'vue'

import Spinner from '@/components/ui/spinner/Spinner.vue'
import { useSportColors } from '@/composables/useSportColors'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import { resolveTeamAvatarUrl } from '@/utils/media'

defineOptions({
    layout: AdminDashboard,
})

type TeamPayload = {
    id: number
    team_name: string
    team_avatar: string | null
    sport_id: number | null
    year: string | null
    head_coach?: { id: number; name: string | null } | null
    assistant_coach?: { id: number; name: string | null } | null
    description: string | null
    player_ids: number[]
}

const props = defineProps<{
    sports: { id: number; name: string; max_players: number }[]
    selectedTeam?: TeamPayload | null
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()

const teamName = ref(props.selectedTeam?.team_name ?? '')
const teamAvatar = ref<File | null>(null)
const avatarPreview = ref<string | null>(
    props.selectedTeam?.team_avatar ? resolveTeamAvatarUrl(props.selectedTeam.team_avatar) : null,
)
const avatarPreviewFromUpload = ref(false)
const sport = ref(props.selectedTeam?.sport_id ? String(props.selectedTeam.sport_id) : '')
const year = ref(props.selectedTeam?.year ?? '')
const description = ref(props.selectedTeam?.description ?? '')
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)

const isEditMode = computed(() => !!props.selectedTeam?.id)
const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 10 }, (_, i) => String(currentYear + 3 - i))
const selectedSport = computed(() => props.sports.find((item) => String(item.id) === sport.value) || null)
const rosterCount = computed(() => props.selectedTeam?.player_ids?.length ?? 0)
const currentHeadCoach = computed(() => props.selectedTeam?.head_coach?.name || 'Unassigned')
const currentAssistantCoach = computed(() => props.selectedTeam?.assistant_coach?.name || 'Unassigned')

function goBack() {
    router.get('/teams')
}

function handleAvatarUpload(event: Event) {
    const files = (event.target as HTMLInputElement).files
    if (!files || !files.length) return

    if (avatarPreview.value && avatarPreviewFromUpload.value) {
        URL.revokeObjectURL(avatarPreview.value)
    }

    teamAvatar.value = files[0]
    avatarPreview.value = URL.createObjectURL(files[0])
    avatarPreviewFromUpload.value = true
}

function validate() {
    const nextErrors: Record<string, string> = {}

    if (!teamName.value.trim()) nextErrors.team_name = 'Team name is required.'
    if (!sport.value) nextErrors.sport_id = 'Select a sport.'
    if (!year.value) nextErrors.year = 'Select a year.'

    errors.value = nextErrors
    return Object.keys(nextErrors).length === 0
}

function submitTeam() {
    if (isSaving.value) return
    if (!validate()) return

    const formData = new FormData()
    formData.append('team_name', teamName.value.trim())
    if (teamAvatar.value) formData.append('team_avatar', teamAvatar.value)
    formData.append('sport_id', sport.value)
    formData.append('year', year.value)
    formData.append('description', description.value)

    const endpoint = isEditMode.value ? `/teams/${props.selectedTeam?.id}` : '/teams/create'
    const requestOptions = {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => {
            isSaving.value = true
        },
        onFinish: () => {
            isSaving.value = false
        },
        onError: (backendErrors: Record<string, any>) => {
            const normalized: Record<string, string> = {}
            Object.keys(backendErrors).forEach((key) => {
                const val = backendErrors[key]
                normalized[key] = Array.isArray(val) ? String(val[0]) : String(val)
            })
            errors.value = normalized
        },
    }

    if (isEditMode.value) {
        router.put(endpoint, formData, requestOptions)
        return
    }

    router.post(endpoint, formData, requestOptions)
}

onBeforeUnmount(() => {
    if (avatarPreview.value && avatarPreviewFromUpload.value) {
        URL.revokeObjectURL(avatarPreview.value)
    }
})
</script>

<template>
    <Head :title="isEditMode ? 'Edit Team' : 'Create Team'" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <button
                    type="button"
                    class="text-sm font-medium text-[#034485] hover:text-[#033a70]"
                    @click="goBack"
                >
                    ← Back to Teams
                </button>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">
                    {{ isEditMode ? 'Edit Team' : 'Create Team' }}
                </p>
            </div>

            <div v-if="isEditMode && selectedTeam" class="flex flex-wrap gap-2">
                <Link
                    :href="`/teams/${selectedTeam.id}/view-roster`"
                    class="rounded-full border border-[#034485]/25 bg-white px-4 py-2 text-sm font-semibold text-[#034485] hover:bg-[#034485]/5"
                >
                    Open Roster Workspace
                </Link>
            </div>
        </div>

        <section class="rounded-3xl bg-[#034485] p-6 text-white shadow-[0_24px_60px_-36px_rgba(3,68,133,0.55)]">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <div class="h-20 w-20 overflow-hidden rounded-2xl border border-white/20 bg-white/10">
                        <img v-if="avatarPreview" :src="avatarPreview" alt="Team preview" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center text-sm font-semibold text-white/70">
                            Team
                        </div>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :style="{ backgroundColor: sportColor(selectedSport?.name ?? ''), color: sportTextColor(selectedSport?.name ?? '') }"
                            >
                                {{ selectedSport ? sportLabel(selectedSport.name) : 'Select Sport' }}
                            </span>
                            <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90">
                                {{ year || 'Select Year' }}
                            </span>
                        </div>
                        <h1 class="mt-3 text-3xl font-bold">{{ teamName || (isEditMode ? 'Edit Team Details' : 'Create a New Team') }}</h1>
                        <p class="mt-2 max-w-2xl text-sm text-white/80">
                            {{ isEditMode
                                ? 'Update the core team details here. Coach and player assignments are managed from the roster workspace.'
                                : 'Start by defining the team identity first. Coach and player assignments will be handled right after creation in the roster workspace.' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Max Players</p>
                        <p class="mt-1 text-xl font-bold">{{ selectedSport?.max_players ?? '-' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Current Head Coach</p>
                        <p class="mt-1 text-sm font-semibold">{{ currentHeadCoach }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Current Assistant</p>
                        <p class="mt-1 text-sm font-semibold">{{ currentAssistantCoach }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Players on Roster</p>
                        <p class="mt-1 text-xl font-bold">{{ rosterCount }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
            <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Team Identity</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Basic Team Information</h2>
                    <p class="mt-1 text-sm text-slate-500">Create the team record first, then move into coach and player management from the roster page.</p>
                </div>

                <div class="mt-5 grid gap-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Team Name</label>
                        <input
                            v-model="teamName"
                            type="text"
                            placeholder="e.g., Falcons A-Team"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        />
                        <p v-if="errors.team_name" class="mt-1 text-xs text-red-600">{{ errors.team_name }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Sport</label>
                            <select v-model="sport" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                <option value="" disabled>Select sport</option>
                                <option v-for="item in sports" :key="item.id" :value="String(item.id)">
                                    {{ item.name }} (Max {{ item.max_players }})
                                </option>
                            </select>
                            <p v-if="errors.sport_id" class="mt-1 text-xs text-red-600">{{ errors.sport_id }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
                            <select v-model="year" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                <option value="" disabled>Select year</option>
                                <option v-for="item in yearOptions" :key="item" :value="item">{{ item }}</option>
                            </select>
                            <p v-if="errors.year" class="mt-1 text-xs text-red-600">{{ errors.year }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                        <textarea
                            v-model="description"
                            rows="4"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            placeholder="Add optional notes about the team."
                        />
                    </div>
                </div>
            </section>

            <section class="space-y-6">
                <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Avatar</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Team Photo</h2>
                        <p class="mt-1 text-sm text-slate-500">Upload a logo or team image now, or add it later from the edit flow.</p>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div class="flex h-52 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                            <img
                                v-if="avatarPreview"
                                :src="avatarPreview"
                                alt="Team avatar preview"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="text-sm text-slate-400">No image selected</span>
                        </div>

                        <div>
                            <input type="file" accept="image/*" @change="handleAvatarUpload" class="w-full text-sm text-slate-600" />
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Workflow</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Next Step After Save</h2>
                        <p class="mt-1 text-sm text-slate-500">The team record is created here. Staff and roster assignments continue in the dedicated roster pages.</p>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">1. Save Team</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ isEditMode ? 'Update the team identity details.' : 'Create the base team record.' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">2. Open Roster Workspace</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">Use `Manage Coaches` and `Manage Players` from the roster page.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">3. Finalize Assignments</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">Assign coaches and student-athletes through the dedicated selection pages.</p>
                        </div>
                    </div>
                </section>
            </section>
        </section>

        <section class="rounded-3xl border border-[#034485]/20 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Save Team</h2>
                    <p class="text-sm text-slate-500">
                        {{ isEditMode
                            ? 'After saving, you will return to the roster workspace for coach and player management.'
                            : 'After creation, you will be redirected to the roster workspace to assign coaches and players.' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="goBack"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-[#034485] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#033a70] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="isSaving"
                        @click="submitTeam"
                    >
                        <span class="inline-flex items-center gap-2">
                            <Spinner v-if="isSaving" class="h-4 w-4 text-white" />
                            {{ isSaving ? (isEditMode ? 'Updating...' : 'Creating...') : (isEditMode ? 'Update Team' : 'Create Team') }}
                        </span>
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
