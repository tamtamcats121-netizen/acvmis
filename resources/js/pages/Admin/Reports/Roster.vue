<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type TeamOption = {
    id: number
    team_name: string
    sport_name: string
}

type RosterReportRow = {
    student_id: number
    student_id_number: string | null
    student_name: string
    team_name: string
    sport_name: string
    year: string | null
    jersey_number: string
    athlete_position: string
    player_status: string
}

const reportTabs = [
    { label: 'Attendance', href: '/reports/attendance' },
    { label: 'Roster', href: '/reports/roster' },
    { label: 'Academics', href: '/reports/academics' },
]

const props = defineProps<{
    filters: {
        selected: {
            sport_id: number | null
            team_id: number | null
            player_status: string | null
            year: string | null
        }
        options: {
            sports: { id: number; name: string }[]
            teams: TeamOption[]
            player_statuses: { value: string; label: string }[]
            years: string[]
        }
    }
    rosterReport: {
        summary: {
            total_players: number
            active: number
            injured: number
            suspended: number
            jersey_pending: number
            position_pending: number
        }
        rows: RosterReportRow[]
    }
}>()

const form = reactive({
    sport_id: props.filters.selected.sport_id ? String(props.filters.selected.sport_id) : '',
    team_id: props.filters.selected.team_id ? String(props.filters.selected.team_id) : '',
    player_status: props.filters.selected.player_status ?? '',
    year: props.filters.selected.year ?? '',
})

const queryString = computed(() => {
    const params = new URLSearchParams()

    if (form.sport_id) params.set('sport_id', form.sport_id)
    if (form.team_id) params.set('team_id', form.team_id)
    if (form.player_status) params.set('player_status', form.player_status)
    if (form.year) params.set('year', form.year)

    const query = params.toString()
    return query ? `?${query}` : ''
})

function applyFilters() {
    router.get('/reports/roster', {
        sport_id: form.sport_id || undefined,
        team_id: form.team_id || undefined,
        player_status: form.player_status || undefined,
        year: form.year || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    form.sport_id = ''
    form.team_id = ''
    form.player_status = ''
    form.year = ''
    applyFilters()
}
</script>

<template>
    <Head title="Roster Report" />

    <div class="space-y-5">
        <section class="page-card rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Team Roster Report</h1>
                    <p class="text-sm text-slate-600">Review team membership, player status, jersey assignments, and position readiness in one view.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        v-for="tab in reportTabs"
                        :key="tab.href"
                        :href="tab.href"
                        class="rounded-md border px-3 py-2 text-sm font-semibold transition"
                        :class="tab.href === '/reports/roster' ? 'border-[#1f2937] bg-[#1f2937] text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100'"
                    >
                        {{ tab.label }}
                    </a>
                </div>
            </div>
        </section>

        <section class="page-card rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sport</label>
                    <select v-model="form.sport_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Sports</option>
                        <option v-for="sport in props.filters.options.sports" :key="sport.id" :value="String(sport.id)">
                            {{ sport.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Team</label>
                    <select v-model="form.team_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Teams</option>
                        <option v-for="team in props.filters.options.teams" :key="team.id" :value="String(team.id)">
                            {{ team.team_name }} ({{ team.sport_name }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Player Status</label>
                    <select v-model="form.player_status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Player Statuses</option>
                        <option v-for="status in props.filters.options.player_statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
                    <select v-model="form.year" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All Years</option>
                        <option v-for="year in props.filters.options.years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="applyFilters">
                    Apply
                </button>
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="resetFilters">
                    Reset
                </button>
                <a :href="`/reports/roster/export.csv${queryString}`" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Export CSV
                </a>
                <a :href="`/reports/roster/print${queryString}`" target="_blank" rel="noreferrer" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Open PDF View
                </a>
            </div>
        </section>

        <section class="page-card space-y-4 rounded-xl border border-[#034485]/45 bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Total Players</p>
                    <p class="mt-1 text-2xl font-bold text-[#034485]">{{ props.rosterReport.summary.total_players }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Active</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ props.rosterReport.summary.active }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Injured</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ props.rosterReport.summary.injured }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Suspended</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ props.rosterReport.summary.suspended }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Jersey Pending</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.rosterReport.summary.jersey_pending }}</p>
                </article>
                <article class="page-card rounded-xl border border-[#034485]/45 bg-white p-4">
                    <p class="text-xs text-slate-500">Position Pending</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.rosterReport.summary.position_pending }}</p>
                </article>
            </div>

            <div v-if="props.rosterReport.rows.length === 0" class="rounded-xl border border-dashed border-slate-300 px-5 py-8 text-sm text-slate-500">
                No roster data found for the selected filters.
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-[#034485]/45">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <th class="px-5 py-4">Student</th>
                            <th class="px-5 py-4">Team</th>
                            <th class="px-5 py-4">Year</th>
                            <th class="px-5 py-4">Jersey</th>
                            <th class="px-5 py-4">Position</th>
                            <th class="px-5 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.rosterReport.rows" :key="`${row.student_id}-${row.team_name}-${row.year}`" class="align-top">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ row.student_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.student_id_number || 'No student ID' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <p>{{ row.team_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ row.sport_name }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ row.year || 'N/A' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.jersey_number }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ row.athlete_position }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ row.player_status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
