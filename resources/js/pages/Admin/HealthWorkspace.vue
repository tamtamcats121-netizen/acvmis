<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue';

defineOptions({ layout: AdminDashboard });

type ClearanceRow = {
    id: number;
    student_name: string;
    student_id_number: string | null;
    clearance_date: string | null;
    valid_until: string | null;
    physician_name: string;
    clearance_status: string;
    certificate_url: string | null;
    reviewed_at: string | null;
    reviewed_by: string | null;
};

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
    tab: 'clearance' | 'wellness';
    clearance: null | {
        records: {
            data: ClearanceRow[];
            meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
        };
        filters: { search?: string; status?: string; validity?: string; reviewed?: string; per_page?: number };
        kpis: { total_records: number; expired_count: number; expiring_30_count: number; reviewed_count: number };
    };
    wellness: null | {
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

const isClearance = computed(() => props.tab === 'clearance');
const workspaceMeta = computed(() =>
    isClearance.value
        ? {
              eyebrow: 'Health Workspace',
              title: 'Athlete Health Clearance Monitoring',
              description:
                  'Review submitted medical clearances, check document validity, and monitor which student-athletes are cleared, restricted, or expired.',
              accentClass: 'from-[#034485] via-[#0b5db3] to-[#6da9ff]',
          }
        : {
              eyebrow: 'Wellness Workspace',
              title: 'Post-Session Wellness Monitoring',
              description:
                  'Track coach-recorded wellness observations, inspect fatigue and injury signals, and review recovery-related remarks across teams and sessions.',
              accentClass: 'from-[#034485] via-[#0b5db3] to-[#6da9ff]',
          },
);

const clearanceForm = reactive({
    search: props.clearance?.filters.search ?? '',
    status: props.clearance?.filters.status ?? 'all',
    validity: props.clearance?.filters.validity ?? 'all',
    reviewed: props.clearance?.filters.reviewed ?? 'all',
});

const wellnessForm = reactive({
    search: props.wellness?.filters.search ?? '',
    injury_only: Boolean(props.wellness?.filters.injury_only ?? false),
    fatigue_min: props.wellness?.filters.fatigue_min ? String(props.wellness.filters.fatigue_min) : '',
    team_id: props.wellness?.filters.team_id ? String(props.wellness.filters.team_id) : '',
    start_date: props.wellness?.filters.start_date ?? '',
    end_date: props.wellness?.filters.end_date ?? '',
});

function setTab(tab: 'clearance' | 'wellness') {
    router.get('/health', { tab }, { preserveState: true, preserveScroll: true, replace: true });
}

function formatStatus(status: string) {
    return status.replaceAll('_', ' ');
}

function statusTone(status: string) {
    if (status === 'fit') return 'bg-emerald-100 text-emerald-700';
    if (status === 'fit_with_restrictions') return 'bg-amber-100 text-amber-700';
    if (status === 'not_fit') return 'bg-red-100 text-red-700';
    if (status === 'expired') return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-700';
}

function performanceTone(value: string | null) {
    const normalized = String(value ?? '').toLowerCase();
    if (normalized === 'excellent') return 'bg-emerald-100 text-emerald-700';
    if (normalized === 'good') return 'bg-sky-100 text-sky-700';
    if (normalized === 'fair') return 'bg-amber-100 text-amber-700';
    if (normalized === 'poor') return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-700';
}

function reloadClearance(page = 1) {
    router.get(
        '/health',
        {
            tab: 'clearance',
            search: clearanceForm.search.trim() || undefined,
            status: clearanceForm.status,
            validity: clearanceForm.validity,
            reviewed: clearanceForm.reviewed,
            page,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetClearance() {
    clearanceForm.search = '';
    clearanceForm.status = 'all';
    clearanceForm.validity = 'all';
    clearanceForm.reviewed = 'all';
    reloadClearance(1);
}

function reloadWellness(page = 1) {
    router.get(
        '/health',
        {
            tab: 'wellness',
            search: wellnessForm.search.trim() || undefined,
            injury_only: wellnessForm.injury_only ? 1 : undefined,
            fatigue_min: wellnessForm.fatigue_min || undefined,
            team_id: wellnessForm.team_id || undefined,
            start_date: wellnessForm.start_date || undefined,
            end_date: wellnessForm.end_date || undefined,
            page,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetWellness() {
    wellnessForm.search = '';
    wellnessForm.injury_only = false;
    wellnessForm.fatigue_min = '';
    wellnessForm.team_id = '';
    wellnessForm.start_date = '';
    wellnessForm.end_date = '';
    reloadWellness(1);
}
</script>

<template>
    <Head title="Health Workspace" />

    <div class="space-y-5">
        <section class="overflow-hidden rounded-3xl border border-[#034485]/35 bg-white">
            <div class="bg-gradient-to-r p-6 text-white" :class="workspaceMeta.accentClass">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">{{ workspaceMeta.eyebrow }}</p>
                        <h1 class="mt-2 text-3xl font-bold">{{ workspaceMeta.title }}</h1>
                        <p class="mt-2 text-sm text-white/80">{{ workspaceMeta.description }}</p>
                    </div>

                    <div class="relative inline-grid min-w-[230px] grid-cols-2 rounded-full border border-white/25 bg-white/10 p-1 backdrop-blur">
                        <span
                            class="absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-full bg-white transition-transform duration-200"
                            :class="!isClearance ? 'translate-x-full' : 'translate-x-0'"
                            aria-hidden="true"
                        ></span>
                        <button
                            type="button"
                            class="relative z-10 rounded-full px-4 py-1.5 text-center text-sm font-semibold whitespace-nowrap transition-colors"
                            :class="isClearance ? 'text-slate-900' : 'text-white/85 hover:text-white'"
                            @click="setTab('clearance')"
                        >
                            Clearance
                        </button>
                        <button
                            type="button"
                            class="relative z-10 rounded-full px-4 py-1.5 text-center text-sm font-semibold whitespace-nowrap transition-colors"
                            :class="!isClearance ? 'text-slate-900' : 'text-white/85 hover:text-white'"
                            @click="setTab('wellness')"
                        >
                            Wellness
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-5">
                <div v-if="isClearance && clearance" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-[#034485]/20 bg-white px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Total Records</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ clearance.kpis.total_records }}</p>
                    </article>
                    <article class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-rose-700">Expired</p>
                        <p class="mt-2 text-2xl font-bold text-rose-900">{{ clearance.kpis.expired_count }}</p>
                    </article>
                    <article class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-amber-700">Expiring Soon</p>
                        <p class="mt-2 text-2xl font-bold text-amber-900">{{ clearance.kpis.expiring_30_count }}</p>
                    </article>
                    <article class="rounded-2xl border border-[#034485]/20 bg-[#034485]/5 px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-slate-600">Reviewed</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ clearance.kpis.reviewed_count }}</p>
                    </article>
                </div>

                <div v-else-if="!isClearance && wellness" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-[#034485]/20 bg-white px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Total Logs</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ wellness.kpis.total_logs }}</p>
                    </article>
                    <article class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs uppercase tracking-wide text-rose-700">Injury Observed</p>
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                                {{ wellness.kpis.injury_severity.label }}
                            </span>
                        </div>
                        <p class="mt-2 text-2xl font-bold text-rose-900">{{ wellness.kpis.injury_observed_count }}</p>
                    </article>
                    <article class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs uppercase tracking-wide text-amber-700">Average Fatigue</p>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                                {{ wellness.kpis.fatigue_severity.label }}
                            </span>
                        </div>
                        <p class="mt-2 text-2xl font-bold text-amber-900">{{ wellness.kpis.avg_fatigue }}</p>
                    </article>
                    <article class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-emerald-700">Unique Athletes</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-900">{{ wellness.kpis.unique_athletes }}</p>
                    </article>
                </div>
            </div>
        </section>

        <template v-if="isClearance && clearance">
            <section class="rounded-3xl border border-[#034485]/35 bg-white p-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Clearance Filters</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Medical Clearance Records</h2>
                    <p class="mt-1 text-sm text-slate-500">Filter active, expiring, expired, and reviewed health clearance submissions.</p>
                </div>

                <div class="mt-5 space-y-3">
                    <div class="flex flex-col gap-3 lg:flex-row">
                        <input
                            v-model="clearanceForm.search"
                            type="text"
                            placeholder="Search athlete, ID, physician"
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:flex-1"
                            @keyup.enter="reloadClearance(1)"
                        />
                        <div class="flex gap-2">
                            <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-100" @click="reloadClearance(1)">
                                Search
                            </button>
                            <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-100" @click="resetClearance">
                                Reset
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <select v-model="clearanceForm.status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="all">Status: All</option>
                        <option value="fit">Status: Fit</option>
                        <option value="fit_with_restrictions">Status: Fit With Restrictions</option>
                        <option value="not_fit">Status: Not Fit</option>
                        <option value="expired">Status: Expired</option>
                    </select>
                    <select v-model="clearanceForm.validity" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="all">Validity: All</option>
                        <option value="active">Validity: Active</option>
                        <option value="expiring_30">Validity: Expiring 30 Days</option>
                        <option value="expired">Validity: Expired</option>
                    </select>
                    <select v-model="clearanceForm.reviewed" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="all">Review: All</option>
                        <option value="reviewed">Review: Reviewed</option>
                        <option value="unreviewed">Review: Unreviewed</option>
                    </select>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-[#034485]/35 bg-white">
                <div class="border-b border-slate-200 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Clearance List</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-900">Submitted Health Clearances</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left">Athlete</th>
                                <th class="px-4 py-3 text-left">Clearance</th>
                                <th class="px-4 py-3 text-left">Physician</th>
                                <th class="px-4 py-3 text-left">Validity</th>
                                <th class="px-4 py-3 text-left">Certificate</th>
                                <th class="px-4 py-3 text-left">Reviewed</th>
                            </tr>
                        </thead>
                        <transition-group name="table-fade" tag="tbody">
                            <tr v-for="row in clearance.records.data" :key="row.id" class="border-t border-slate-200 text-slate-700">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ row.student_name }}</div>
                                    <div class="text-xs text-slate-500">{{ row.student_id_number || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusTone(row.clearance_status)">{{
                                        formatStatus(row.clearance_status)
                                    }}</span>
                                </td>
                                <td class="px-4 py-3">{{ row.physician_name }}</td>
                                <td class="px-4 py-3">
                                    <div>{{ row.clearance_date || '-' }}</div>
                                    <div class="text-xs text-slate-500">Until: {{ row.valid_until || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <a v-if="row.certificate_url" :href="row.certificate_url" target="_blank" class="font-medium text-[#034485] hover:underline">
                                        View Certificate
                                    </a>
                                    <span v-else class="text-slate-500">-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ row.reviewed_by || '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ row.reviewed_at || '-' }}</div>
                                </td>
                            </tr>
                            <tr v-if="clearance.records.data.length === 0" key="empty">
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">No health clearance records found.</td>
                            </tr>
                        </transition-group>
                    </table>
                </div>
            </section>

            <div class="flex items-center justify-between text-sm text-slate-600">
                <p>Showing {{ clearance.records.meta.from || 0 }}-{{ clearance.records.meta.to || 0 }} of {{ clearance.records.meta.total }}</p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                        :disabled="clearance.records.meta.current_page <= 1"
                        @click="reloadClearance(clearance.records.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                        :disabled="clearance.records.meta.current_page >= clearance.records.meta.last_page"
                        @click="reloadClearance(clearance.records.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </template>

        <template v-if="!isClearance && wellness">
            <section class="rounded-3xl border border-[#034485]/35 bg-white p-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Wellness Filters</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Coach Wellness Observations</h2>
                    <p class="mt-1 text-sm text-slate-500">Focus on injury cases, fatigue trends, team activity, and post-session wellness remarks.</p>
                </div>

                <div class="mt-5 space-y-3">
                    <div class="flex flex-col gap-3 lg:flex-row">
                        <input
                            v-model="wellnessForm.search"
                            type="text"
                            placeholder="Search athlete/team/schedule"
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:flex-1"
                            @keyup.enter="reloadWellness(1)"
                        />
                        <div class="flex gap-2">
                            <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-100" @click="reloadWellness(1)">
                                Search
                            </button>
                            <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-100" @click="resetWellness">
                                Reset
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <select v-model="wellnessForm.team_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">All Teams</option>
                        <option v-for="team in wellness.options.teams" :key="team.id" :value="String(team.id)">{{ team.team_name }}</option>
                    </select>
                    <select v-model="wellnessForm.fatigue_min" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Fatigue: Any</option>
                        <option value="3">Fatigue: 3+</option>
                        <option value="4">Fatigue: 4+</option>
                        <option value="5">Fatigue: 5</option>
                    </select>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-700">
                        <input v-model="wellnessForm.injury_only" type="checkbox" />
                        Injury Only
                    </label>
                    <input v-model="wellnessForm.start_date" type="date" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                    <input v-model="wellnessForm.end_date" type="date" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                <article
                    v-for="row in wellness.logs.data"
                    :key="row.id"
                    class="rounded-3xl border border-[#034485]/35 bg-white p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">{{ row.log_date || '-' }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ row.student_name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ row.student_id_number || '-' }}</p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="row.injury_observed ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                        >
                            {{ row.injury_observed ? 'Injury Observed' : 'No Injury' }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-[#034485]/20 bg-[#034485]/5 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Team</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ row.team_name || '-' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#034485]/20 bg-[#034485]/5 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Schedule</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ row.schedule_type || '-' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ row.schedule_title || '-' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#034485]/20 bg-[#034485]/5 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Fatigue Level</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ row.fatigue_level ?? '-' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#034485]/20 bg-[#034485]/5 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Performance</p>
                            <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="performanceTone(row.performance_condition)">
                                {{ row.performance_condition || '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3 text-sm">
                        <div v-if="row.injury_notes" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Injury Notes</p>
                            <p class="mt-1 text-slate-700">{{ row.injury_notes }}</p>
                        </div>
                        <div v-if="row.remarks" class="rounded-2xl border border-[#034485]/20 bg-[#034485]/5 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Remarks</p>
                            <p class="mt-1 text-slate-700">{{ row.remarks }}</p>
                        </div>
                        <div class="text-xs text-slate-500">Logged by {{ row.logged_by || '-' }}</div>
                    </div>
                </article>

                <div
                    v-if="wellness.logs.data.length === 0"
                    class="rounded-3xl border border-dashed border-[#034485]/25 bg-white px-5 py-12 text-center text-sm text-slate-500 md:col-span-2 2xl:col-span-3"
                >
                    No wellness logs found.
                </div>
            </section>

            <div class="flex items-center justify-between text-sm text-slate-600">
                <p>Showing {{ wellness.logs.meta.from || 0 }}-{{ wellness.logs.meta.to || 0 }} of {{ wellness.logs.meta.total }}</p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                        :disabled="wellness.logs.meta.current_page <= 1"
                        @click="reloadWellness(wellness.logs.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-100 disabled:opacity-40"
                        :disabled="wellness.logs.meta.current_page >= wellness.logs.meta.last_page"
                        @click="reloadWellness(wellness.logs.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.table-fade-enter-active,
.table-fade-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}

.table-fade-enter-from,
.table-fade-leave-to {
    opacity: 0;
    transform: translateY(6px);
}

.table-fade-move {
    transition: transform 0.2s ease;
}
</style>
