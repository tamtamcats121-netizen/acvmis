<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

type ApprovalHistoryItem = {
    id: number
    student_name: string
    decision: string | null
    remarks: string | null
    admin_name: string
    created_at: string | null
}

type AccountActionItem = {
    id: number
    user_name: string
    user_role: string
    action: string | null
    remarks: string | null
    admin_name: string
    created_at: string | null
}

type AcademicEvaluationItem = {
    id: number
    student_name: string
    status: string | null
    gpa: number | null
    remarks: string | null
    period_label: string | null
    evaluator_name: string
    evaluated_at: string | null
}

type AuditRow = {
    key: string
    category: 'Approval' | 'Account Action' | 'Academic Evaluation'
    subject: string
    context: string
    outcome: string
    details: string
    actor: string
    timestamp: string | null
}

const props = defineProps<{
    summary: {
        approval_events: number
        account_actions: number
        academic_evaluations: number
    }
    approvalHistory: ApprovalHistoryItem[]
    accountActions: AccountActionItem[]
    academicEvaluations: AcademicEvaluationItem[]
}>()

const auditRows = computed<AuditRow[]>(() => {
    const approvalRows = (props.approvalHistory ?? []).map((item) => ({
        key: `approval-${item.id}`,
        category: 'Approval' as const,
        subject: item.student_name,
        context: 'Student account approval',
        outcome: startCase(item.decision),
        details: item.remarks || 'No additional remarks provided.',
        actor: item.admin_name,
        timestamp: item.created_at,
    }))

    const accountActionRows = (props.accountActions ?? []).map((item) => ({
        key: `account-${item.id}`,
        category: 'Account Action' as const,
        subject: item.user_name,
        context: startCase(item.user_role),
        outcome: startCase(item.action),
        details: item.remarks || 'No additional remarks provided.',
        actor: item.admin_name,
        timestamp: item.created_at,
    }))

    const evaluationRows = (props.academicEvaluations ?? []).map((item) => ({
        key: `evaluation-${item.id}`,
        category: 'Academic Evaluation' as const,
        subject: item.student_name,
        context: item.period_label || 'No academic period',
        outcome: startCase(item.status),
        details: buildEvaluationDetails(item),
        actor: item.evaluator_name,
        timestamp: item.evaluated_at,
    }))

    return [...approvalRows, ...accountActionRows, ...evaluationRows]
        .sort((a, b) => toTimestamp(b.timestamp) - toTimestamp(a.timestamp))
})

function buildEvaluationDetails(item: AcademicEvaluationItem) {
    const parts = [`GPA: ${item.gpa !== null ? item.gpa.toFixed(2) : 'N/A'}`]

    if (item.remarks) {
        parts.push(item.remarks)
    }

    return parts.join(' • ')
}

function toTimestamp(value: string | null) {
    if (!value) return 0

    const parsed = new Date(value).getTime()
    return Number.isFinite(parsed) ? parsed : 0
}

function formatDateTime(value: string | null) {
    if (!value) return 'No timestamp'

    return new Date(value).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}

function startCase(value: string | null) {
    return String(value ?? 'Unknown')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase())
}

function outcomeTone(value: string) {
    const normalized = String(value ?? '').toLowerCase()

    if (normalized === 'approved' || normalized === 'eligible' || normalized.includes('added')) {
        return 'bg-emerald-100 text-emerald-700'
    }
    if (normalized === 'rejected' || normalized === 'ineligible' || normalized.includes('removed') || normalized.includes('deactivated')) {
        return 'bg-rose-100 text-rose-700'
    }
    if (normalized === 'pending_review') {
        return 'bg-amber-100 text-amber-700'
    }

    return 'bg-slate-100 text-slate-600'
}

function categoryTone(value: AuditRow['category']) {
    return 'bg-slate-100 text-slate-700'
}
</script>

<template>
    <Head title="Audit Trail" />

    <div class="space-y-6">
        <section class="audit-trail-hero page-card rounded-3xl border border-[#034485] bg-[#034485] p-6 text-white">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-white/80">Admin Oversight</p>
                    <h1 class="mt-2 text-2xl font-bold">Audit Trail</h1>
                    <p class="mt-2 max-w-3xl text-sm text-white/85">
                        Review approvals, account actions, roster changes, and academic evaluations in one consistent activity table.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="page-card rounded-2xl border border-white/25 bg-white/10 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">Approvals</p>
                        <p class="mt-2 text-2xl font-bold text-white">{{ props.summary.approval_events }}</p>
                    </div>
                    <div class="page-card rounded-2xl border border-white/25 bg-white/10 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">Account Actions</p>
                        <p class="mt-2 text-2xl font-bold text-white">{{ props.summary.account_actions }}</p>
                    </div>
                    <div class="page-card rounded-2xl border border-white/25 bg-white/10 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">Evaluations</p>
                        <p class="mt-2 text-2xl font-bold text-white">{{ props.summary.academic_evaluations }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-card overflow-hidden rounded-3xl border border-[#034485]/35 bg-white">
            <header class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Activity Timeline</h2>
                <p class="mt-1 text-sm text-slate-500">
                    A consolidated view of the most recent tracked admin-facing events across the system.
                </p>
            </header>

            <div v-if="auditRows.length === 0" class="px-6 py-8 text-sm text-slate-500">
                No audit history found.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-[#034485] text-white shadow-[inset_0_-1px_0_rgba(255,255,255,0.08)]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-white">
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">Subject</th>
                            <th class="px-6 py-4">Context</th>
                            <th class="px-6 py-4">Outcome</th>
                            <th class="px-6 py-4">Details</th>
                            <th class="px-6 py-4">By</th>
                            <th class="px-6 py-4">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in auditRows" :key="row.key" class="align-top">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="categoryTone(row.category)">
                                    {{ row.category }}
                                </span>
                            </td>
                            <td class="min-w-[12rem] px-6 py-4 font-semibold text-slate-900 whitespace-normal break-words">{{ row.subject }}</td>
                            <td class="min-w-[12rem] px-6 py-4 text-slate-600 whitespace-normal break-words">{{ row.context }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="outcomeTone(row.outcome)">
                                    {{ row.outcome }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <p class="max-w-md leading-6">{{ row.details }}</p>
                            </td>
                            <td class="min-w-[9rem] px-6 py-4 text-slate-700 whitespace-normal break-words">{{ row.actor }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-500">{{ formatDateTime(row.timestamp) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
