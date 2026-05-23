<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

import { useTheme } from '@/composables/useTheme'

type TeamPreview = {
    id: number
    team_name: string
    sport: string
    year: number | string | null
    coach: string
    roster_count: number
    max_players: number
}

const props = defineProps<{
    code: string
    team: TeamPreview | null
    canJoin: boolean
    message: string | null
}>()

const { isDarkMode } = useTheme()
const codeDraft = ref(props.code ?? '')
const joining = ref(false)
const formError = ref('')

const normalizedCode = computed(() => codeDraft.value.trim().toUpperCase())
const hasCode = computed(() => normalizedCode.value.length > 0)
const statusMessage = computed(() => formError.value || props.message || '')

watch(
    () => props.code,
    (code) => {
        codeDraft.value = code ?? ''
        formError.value = ''
    },
)

function previewCode() {
    if (!hasCode.value) {
        formError.value = 'Enter a team invite code first.'
        return
    }

    router.get(`/join-team/${encodeURIComponent(normalizedCode.value)}`, {}, {
        preserveScroll: true,
    })
}

function joinTeam() {
    if (!props.canJoin || !props.team || !hasCode.value) return

    router.post('/join-team', {
        code: normalizedCode.value,
    }, {
        preserveScroll: true,
        onStart: () => {
            joining.value = true
            formError.value = ''
        },
        onFinish: () => {
            joining.value = false
        },
        onError: (errors: Record<string, string | string[]>) => {
            const codeError = errors.code
            formError.value = Array.isArray(codeError)
                ? String(codeError[0] ?? 'Unable to join this team.')
                : String(codeError ?? 'Unable to join this team.')
        },
    })
}

function goDashboard() {
    router.get('/StudentAthleteDashboard')
}
</script>

<template>
    <main
        class="min-h-screen px-4 py-8 sm:px-6 lg:px-8"
        :class="isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-[#f4f8fc] text-slate-900'"
    >
        <div class="mx-auto max-w-4xl space-y-5">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl border px-4 py-2 text-sm font-semibold transition"
                :class="isDarkMode ? 'border-slate-700 bg-slate-900 text-slate-100 hover:border-sky-500 hover:text-sky-200' : 'border-[#034485]/20 bg-white text-[#034485] hover:border-[#034485]/40 hover:bg-[#eef5ff]'"
                @click="goDashboard"
            >
                <span aria-hidden="true">←</span>
                Back to Dashboard
            </button>

            <section class="overflow-hidden rounded-3xl border border-[#034485]/35 bg-[#034485] text-white shadow-[0_24px_60px_-36px_rgba(3,68,133,0.55)]">
                <div class="p-6 sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Join Team</p>
                    <h1 class="mt-2 text-3xl font-bold">Use a team invite code</h1>
                    <p class="mt-2 max-w-2xl text-sm text-white/78">
                        Enter the code your coach shared. After confirmation, AC-VMIS checks your account, sport, roster status, and the team season before adding you to the roster.
                    </p>
                </div>
            </section>

            <section
                class="rounded-3xl border p-5 sm:p-6"
                :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-[#034485]/20 bg-white'"
            >
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="min-w-0 flex-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Invite Code</label>
                        <input
                            v-model="codeDraft"
                            type="text"
                            placeholder="Example: LF26-K7Q9"
                            class="mt-2 w-full rounded-2xl border px-4 py-3 font-mono text-base font-bold uppercase tracking-[0.14em] outline-none transition focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/18"
                            :class="isDarkMode ? 'border-slate-700 bg-slate-950 text-white placeholder:text-slate-500' : 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-400'"
                            @keyup.enter="previewCode"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            type="button"
                            class="w-full rounded-2xl bg-[#034485] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#033a70] sm:w-auto"
                            @click="previewCode"
                        >
                            Preview Team
                        </button>
                    </div>
                </div>

                <p
                    v-if="statusMessage"
                    class="mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold"
                    :class="props.canJoin ? 'border-[#034485]/25 bg-[#eef5ff] text-[#034485]' : 'border-amber-200 bg-amber-50 text-amber-700'"
                >
                    {{ statusMessage }}
                </p>
            </section>

            <section
                v-if="props.team"
                class="overflow-hidden rounded-3xl border"
                :class="isDarkMode ? 'border-slate-800 bg-slate-900' : 'border-[#034485]/20 bg-white'"
            >
                <div class="bg-[#034485] px-6 py-5 text-white">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Team Preview</p>
                    <h2 class="mt-1 text-2xl font-bold">{{ props.team.team_name }}</h2>
                </div>

                <div class="grid gap-3 p-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-950' : 'border-[#034485]/12 bg-[#f7fbff]'">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Sport</p>
                        <p class="mt-1 font-bold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ props.team.sport }}</p>
                    </div>
                    <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-950' : 'border-[#034485]/12 bg-[#f7fbff]'">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Year</p>
                        <p class="mt-1 font-bold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ props.team.year ?? 'Current' }}</p>
                    </div>
                    <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-950' : 'border-[#034485]/12 bg-[#f7fbff]'">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Coach</p>
                        <p class="mt-1 font-bold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ props.team.coach }}</p>
                    </div>
                    <div class="rounded-2xl border px-4 py-3" :class="isDarkMode ? 'border-slate-700 bg-slate-950' : 'border-[#034485]/12 bg-[#f7fbff]'">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#034485]">Roster</p>
                        <p class="mt-1 font-bold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ props.team.roster_count }} / {{ props.team.max_players }}</p>
                    </div>
                </div>

                <div class="border-t p-5" :class="isDarkMode ? 'border-slate-800' : 'border-slate-200'">
                    <button
                        type="button"
                        class="w-full rounded-2xl px-5 py-3 text-sm font-semibold transition sm:w-auto"
                        :class="props.canJoin ? 'bg-[#034485] text-white hover:bg-[#033a70]' : 'cursor-not-allowed bg-slate-200 text-slate-500'"
                        :disabled="!props.canJoin || joining"
                        @click="joinTeam"
                    >
                        {{ joining ? 'Joining...' : 'Confirm Join Team' }}
                    </button>
                </div>
            </section>
        </div>
    </main>
</template>
