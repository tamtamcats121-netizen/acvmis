<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, onBeforeUnmount, ref } from 'vue'

import BackLinkButton from '@/components/ui/BackLinkButton.vue'
import Spinner from '@/components/ui/spinner/Spinner.vue'
import { showAppToast } from '@/composables/useAppToast'
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
const sportOptions = computed(() => props.sports.map((item) => ({
    id: String(item.id),
    label: `${item.name} (Max ${item.max_players})`,
})))

function goBack() {
    router.get('/teams')
}

function handleAvatarUpload(files: File[]) {
    if (!files.length) return

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
            const firstError = Object.values(normalized)[0]
            showAppToast(firstError || 'Unable to save the team right now.', 'error', {
                summary: isEditMode.value ? 'Team Update Failed' : 'Team Creation Failed',
            })
        },
        onSuccess: () => {
            showAppToast(
                isEditMode.value ? 'Team details updated successfully.' : 'Team created successfully.',
                'success',
                {
                    summary: isEditMode.value ? 'Team Updated' : 'Team Created',
                },
            )
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
                <BackLinkButton href="/teams" label="Back to Teams" />
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

        <section class="page-card rounded-3xl border border-[#034485]/45 bg-[#034485] p-6 text-white">
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
                                : 'Create the team record here, then return to the teams workspace to continue management.' }}
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
            <section class="page-card rounded-3xl border border-[#034485]/30 bg-white p-5">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Team Identity</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Basic Team Information</h2>
                    <p class="mt-1 text-sm text-slate-500">Create the team record first, then move into coach and player management from the roster page.</p>
                </div>

                <div class="mt-5 grid gap-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Team Name</label>
                        <InputText
                            v-model="teamName"
                            placeholder="e.g., Falcons A-Team"
                            class="w-full"
                            :invalid="Boolean(errors.team_name)"
                        />
                        <Message v-if="errors.team_name" severity="error" size="small" variant="simple" class="mt-1">{{ errors.team_name }}</Message>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Sport</label>
                            <Select
                                v-model="sport"
                                :options="sportOptions"
                                optionLabel="label"
                                optionValue="id"
                                placeholder="Select sport"
                                class="w-full"
                                :invalid="Boolean(errors.sport_id)"
                            />
                            <Message v-if="errors.sport_id" severity="error" size="small" variant="simple" class="mt-1">{{ errors.sport_id }}</Message>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
                            <Select v-model="year" :options="yearOptions" placeholder="Select year" class="w-full" :invalid="Boolean(errors.year)" />
                            <Message v-if="errors.year" severity="error" size="small" variant="simple" class="mt-1">{{ errors.year }}</Message>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                        <Textarea
                            v-model="description"
                            rows="4"
                            autoResize
                            class="w-full"
                            placeholder="Add optional notes about the team."
                            :invalid="Boolean(errors.description)"
                        />
                        <Message v-if="errors.description" severity="error" size="small" variant="simple" class="mt-1">{{ errors.description }}</Message>
                    </div>
                </div>
            </section>

            <section class="space-y-6">
                <section class="page-card rounded-3xl border border-[#034485]/30 bg-white p-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Avatar</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Team Photo</h2>
                        <p class="mt-1 text-sm text-slate-500">Upload a logo or team image now, or add it later from the edit flow.</p>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div class="flex h-52 items-center justify-center overflow-hidden rounded-2xl border border-[#034485]/20 bg-[#f8fbff]">
                            <img
                                v-if="avatarPreview"
                                :src="avatarPreview"
                                alt="Team avatar preview"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="text-sm text-slate-400">No image selected</span>
                        </div>

                        <div>
                            <FileUpload
                                mode="basic"
                                customUpload
                                chooseLabel="Choose Team Photo"
                                accept="image/*"
                                class="w-full"
                                :invalid="Boolean(errors.team_avatar)"
                                @select="(event) => handleAvatarUpload(event.files)"
                            />
                            <p class="mt-2 text-xs text-slate-500">Selected: {{ teamAvatar?.name || 'No file selected' }}</p>
                            <Message v-if="errors.team_avatar" severity="error" size="small" variant="simple" class="mt-1">{{ errors.team_avatar }}</Message>
                        </div>
                    </div>
                </section>

                <section class="page-card rounded-3xl border border-[#034485]/30 bg-white p-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#034485]">Workflow</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Next Step After Save</h2>
                        <p class="mt-1 text-sm text-slate-500">The team record is created here. After saving, you will return to the teams workspace.</p>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div class="rounded-2xl border border-[#034485]/16 bg-[#f8fbff] px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">1. Save Team</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ isEditMode ? 'Update the team identity details.' : 'Create the base team record.' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#034485]/16 bg-[#f8fbff] px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">2. Return To Teams</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">Review the new team from the teams workspace.</p>
                        </div>
                        <div class="rounded-2xl border border-[#034485]/16 bg-[#f8fbff] px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">3. Manage Assignments</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">Open roster management from the team list whenever you are ready to assign coaches and players.</p>
                        </div>
                    </div>
                </section>
            </section>
        </section>

        <section class="page-card rounded-3xl border border-[#034485]/30 bg-white p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Save Team</h2>
                    <p class="text-sm text-slate-500">
                        {{ isEditMode
                            ? 'After saving, you will return to the roster workspace for coach and player management.'
                            : 'After creation, you will be redirected back to the teams workspace.' }}
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
