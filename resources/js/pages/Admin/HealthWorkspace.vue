<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue';

defineOptions({ layout: AdminDashboard });

type WellnessRow = {
    id: number;
    log_date: string | null;
    student_name: string;
    student_id_number: string | null;
    team_name: string | null;
    schedule_title: string | null;
    schedule_type: string | null;
    injury_observed: boolean;
    injury_notes: string | null;
    fatigue_level: number | null;
    performance_condition: string | null;
    remarks: string | null;
    logged_by: string | null;
};

const props = defineProps<{
    wellness: {
        logs: {
            data: WellnessRow[];
            meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
        };
        filters: {
            search?: string;
            injury_only?: boolean;
            fatigue_min?: number | null;
            team_id?: number | null;
            start_date?: string | null;
            end_date?: string | null;
            per_page?: number;
        };
        kpis: {
            total_logs: number;
            injury_observed_count: number;
            avg_fatigue: number;
            unique_athletes: number;
            injury_severity: { score: number; label: string; description: string };
            fatigue_severity: { score: number; label: string; description: string; scale: string };
        };
        options: { teams: Array<{ id: number; team_name: string }> };
    };
}>();

const form = reactive({
    search: props.wellness.filters.search ?? '',
    injury_only: Boolean(props.wellness.filters.injury_only ?? false),
    fatigue_min: props.wellness.filters.fatigue_min ? String(props.wellness.filters.fatigue_min) : '',
    team_id: props.wellness.filters.team_id ? String(props.wellness.filters.team_id) : '',
    start_date: props.wellness.filters.start_date ?? '',
    end_date: props.wellness.filters.end_date ?? '',
});

function performanceTone(value: string | null) {
    const normalized = String(value ?? '').toLowerCase();
    if (normalized === 'excellent') return 'bg-emerald-100 text-emerald-700';
    if (normalized === 'good') return 'bg-sky-100 text-sky-700';
    if (normalized === 'fair') return 'bg-amber-100 text-amber-700';
    if (normalized === 'poor') return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-700';
}

function reload(page = 1) {
    router.get(
        '/health',
        {
            search: form.search.trim() || undefined,
            injury_only: form.injury_only ? 1 : undefined,
            fatigue_min: form.fatigue_min || undefined,
            team_id: form.team_id || undefined,
            start_date: form.start_date || undefined,
            end_date: form.end_date || undefined,
            page,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetFilters() {
    form.search = '';
    form.injury_only = false;
    form.fatigue_min = '';
    form.team_id = '';
    form.start_date = '';
    form.end_date = '';
    reload(1);
}
</script>

<template>
    <Head title="Performance Workspace" />

    <div class="space-y-5">
        <section class="page-card overflow-hidden rounded-3xl border border-[#034485]/35 bg-white">
            <div class="bg-[#034485] p-6 text-white">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Performance Workspace</p>
                    <h1 class="mt-2 text-3xl font-bold">Post-Session Performance Monitoring</h1>
                    <p class="mt-2 text-sm text-white/80">
                        Review coach-led post-session evaluations of athlete condition, fatigue level, injury observation, performance condition, and coach remarks across teams and sessions.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <article class="page-card rounded-2xl border border-[#034485]/20 bg-white px-5 py-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Total Logs</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ wellness.kpis.total_logs }}</p>
                </article>
                <article class="page-card rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs uppercase tracking-wide text-rose-700">Injury Observed</p>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                            {{ wellness.kpis.injury_severity.label }}
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-rose-900">{{ wellness.kpis.injury_observed_count }}</p>
                </article>
                <article class="page-card rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs uppercase tracking-wide text-amber-700">Average Fatigue</p>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                            {{ wellness.kpis.fatigue_severity.label }}
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-amber-900">{{ wellness.kpis.avg_fatigue }}</p>
                </article>
            </div>
        </section>

        <section class="page-card rounded-3xl border border-[#034485]/35 bg-white p-5">
            <div class="mt-3 space-y-2">
                <div class="flex flex-col gap-3 lg:flex-row">
                    <input
                        v-model="form.search"
                        type="text"
                        placeholder="Search athlete, team, or schedule"
                        class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:flex-1"
                        @keyup.enter="reload(1)"
                    />
                    <div class="flex gap-2">
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-100" @click="reload(1)">
                            Search
                        </button>
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-100" @click="resetFilters">
                            Reset
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-700">
                        <input v-model="form.injury_only" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#034485] focus:ring-[#034485]" />
                        Injury only
                    </label>
                    <select v-model="form.fatigue_min" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Fatigue: Any</option>
                        <option value="2">Fatigue: 2 and above</option>
                        <option value="3">Fatigue: 3 and above</option>
                        <option value="4">Fatigue: 4 and above</option>
                        <option value="5">Fatigue: 5 only</option>
                    </select>
                    <select v-model="form.team_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Team: All</option>
                        <option v-for="team in wellness.options.teams" :key="team.id" :value="String(team.id)">
                            {{ team.team_name }}
                        </option>
                    </select>
                    <div class="grid grid-cols-2 gap-3">
                        <input v-model="form.start_date" type="date" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                        <input v-model="form.end_date" type="date" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                    </div>
                </div>
            </div>
        </section>

        <section class="page-card overflow-hidden rounded-3xl border border-[#034485]/35 bg-white">
            <div class="border-b border-[#034485]/30 bg-[#034485] px-5 py-4 text-white">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Performance Records</p>
                <h2 class="mt-2 text-lg font-semibold text-white">Recorded Performance Entries</h2>
            </div>

            <div class="grid grid-cols-1 gap-4 p-5 xl:grid-cols-2">
                <article
                    v-for="row in wellness.logs.data"
                    :key="row.id"
                    class="page-card rounded-2xl border border-[#034485]/35 bg-[#034485] p-5 text-white shadow-[0_20px_38px_-24px_rgba(3,68,133,0.6)]"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-lg font-semibold text-white">{{ row.student_name }}</p>
                            <p class="text-sm text-white/72">{{ row.student_id_number || 'No student ID' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="row.injury_observed ? 'bg-rose-100 text-rose-700' : 'bg-[#DDF5F2] text-[#0F766E]'"
                            >
                                {{ row.injury_observed ? 'Injury Observed' : 'No Injury Observed' }}
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="performanceTone(row.performance_condition)">
                                {{ row.performance_condition || 'No condition' }}
                            </span>
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-1 gap-3 text-sm text-white/82 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-white/60">Team</dt>
                            <dd class="mt-1 text-white">{{ row.team_name || 'No team' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-white/60">Session</dt>
                            <dd class="mt-1 text-white">{{ row.schedule_title || 'Untitled session' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-white/60">Type</dt>
                            <dd class="mt-1 capitalize text-white">{{ row.schedule_type || 'Unknown' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-white/60">Fatigue Level</dt>
                            <dd class="mt-1 text-white">{{ row.fatigue_level ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-white/60">Log Date</dt>
                            <dd class="mt-1 text-white">{{ row.log_date || 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-white/60">Logged By</dt>
                            <dd class="mt-1 text-white">{{ row.logged_by || 'N/A' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 space-y-3">
                        <div v-if="row.injury_notes" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Injury Notes</p>
                            <p class="mt-1 text-sm text-rose-900">{{ row.injury_notes }}</p>
                        </div>
                        <div v-if="row.remarks" class="rounded-2xl border border-white/18 bg-white/10 px-4 py-3 backdrop-blur-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-white/72">Remarks</p>
                            <p class="mt-1 text-sm text-white">{{ row.remarks }}</p>
                        </div>
                    </div>
                </article>

                <div
                    v-if="wellness.logs.data.length === 0"
                    class="xl:col-span-2 rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center text-slate-500"
                >
                    No performance records found for the selected filters.
                </div>
            </div>
        </section>

        <div class="flex items-center justify-between text-sm text-slate-600">
            <p>Showing {{ wellness.logs.meta.from || 0 }}-{{ wellness.logs.meta.to || 0 }} of {{ wellness.logs.meta.total }}</p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                    :disabled="wellness.logs.meta.current_page <= 1"
                    @click="reload(wellness.logs.meta.current_page - 1)"
                >
                    Previous
                </button>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                    :disabled="wellness.logs.meta.current_page >= wellness.logs.meta.last_page"
                    @click="reload(wellness.logs.meta.current_page + 1)"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
