<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'

import BackLinkButton from '@/components/ui/BackLinkButton.vue'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type Period = {
    id: number
    school_year: string
    term: string
    starts_on: string
    ends_on: string
    status: 'draft' | 'open' | 'closed' | 'locked'
    submissions_count: number
}

const props = defineProps<{
    periods: Period[]
    activePeriod: { id: number; school_year: string; term: string } | null
}>()

function termLabel(termCode: string) {
    if (termCode === '1st_sem') return '1st Sem'
    if (termCode === '2nd_sem') return '2nd Sem'
    if (termCode === 'summer') return 'Summer'
    return termCode
}
</script>

<template>
    <Head title="Academic Period History" />

    <div class="space-y-5">
        <section class="page-card rounded-xl border border-[#034485]/45 bg-[#034485] p-5 text-white">
            <div class="flex flex-wrap items-center gap-3">
                <BackLinkButton href="/academics" label="Back to Academics" />
                <div>
                    <h1 class="text-2xl font-bold text-white">Academic Period History</h1>
                    <p class="text-sm text-white/80">Closed academic periods kept for reference and archived evaluation access.</p>
                </div>
            </div>

            <div v-if="activePeriod" class="mt-3 text-xs text-white/75">
                Active period: {{ activePeriod.school_year }} - {{ termLabel(activePeriod.term) }}
            </div>
        </section>

        <section class="page-card overflow-hidden rounded-xl border border-[#034485]/45 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-[#034485] text-white">
                        <tr>
                            <th class="px-4 py-3 text-left">Period</th>
                            <th class="px-4 py-3 text-left">Dates</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Submissions</th>
                        </tr>
                    </thead>
                    <transition-group name="table-fade" tag="tbody">
                        <tr v-for="period in props.periods" :key="period.id" class="border-t border-slate-200 text-slate-700">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ period.school_year }} - {{ termLabel(period.term) }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ period.starts_on }}</div>
                                <div class="text-xs text-slate-500">to {{ period.ends_on }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="period.status === 'open'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : period.status === 'locked'
                                            ? 'bg-slate-800 text-white'
                                            : period.status === 'draft'
                                                ? 'bg-slate-100 text-slate-600'
                                                : 'bg-rose-100 text-rose-700'"
                                >
                                    {{ period.status.toUpperCase() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="period.submissions_count > 0"
                                    type="button"
                                    class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="router.get('/academics/evaluations', { period_id: period.id })"
                                >
                                    Open Evaluations ({{ period.submissions_count }})
                                </button>
                                <span v-else class="text-xs text-slate-400">None</span>
                            </td>
                        </tr>
                        <tr v-if="props.periods.length === 0" key="empty">
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">No closed periods found.</td>
                        </tr>
                    </transition-group>
                </table>
            </div>
        </section>
    </div>
</template>

<style scoped>
.table-fade-enter-active,
.table-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
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
