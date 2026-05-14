<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AccountShell from '@/components/Account/AccountShell.vue'
import { normalizeWorkspaceRole, resolveAccountLayout } from '@/pages/Account/accountRole'

defineOptions({
  layout: (h: any, page: any) => h(resolveAccountLayout(page), [page]),
})

type HelpTask = {
  title: string
  description: string
  href: string
  cta: string
}

type HelpFaq = {
  question: string
  answer: string
}

type HelpContent = {
  roleLabel: string
  roleSummary: string
  focusLabel: string
  topTaskLabel: string
  taskSummary: string
  tasks: HelpTask[]
  faqs: HelpFaq[]
  supportNotes: string[]
}

const page = usePage()
const role = computed(() => normalizeWorkspaceRole((page.props as any)?.auth?.user?.role))
const activeFaq = ref<number | null>(0)

const helpContent = computed<HelpContent>(() => {
  if (role.value === 'admin') {
    return {
      roleLabel: 'Administrator',
      roleSummary: 'Monitor AC-VMIS records, coach accounts, team activity, and institutional oversight from one workspace.',
      focusLabel: 'Best place to start',
      topTaskLabel: 'High-priority admin actions',
      taskSummary: 'Shortcuts for the tasks administrators usually need when monitoring users, teams, and system activity.',
      tasks: [
        {
          title: 'Create coach accounts',
          description: 'Provision official coach access, assign a supported sport, and send secure activation links from the records workspace.',
          href: '/people',
          cta: 'Manage Coaches',
        },
        {
          title: 'Monitor teams',
          description: 'Review coach-created teams, current sport assignments, active rosters, and archive status without editing team composition directly.',
          href: '/teams',
          cta: 'Open Team Monitoring',
        },
        {
          title: 'Monitor operations',
          description: 'Review schedules, attendance exceptions, and operational follow-ups in the shared operations workspace.',
          href: '/operations',
          cta: 'Open Operations',
        },
      ],
      faqs: [
        {
          question: 'Why is a newly registered student-athlete unable to log in yet?',
          answer: 'Student-athlete accounts now stay pending until a coach from the same sport reviews the application. Admin can monitor the user record, but the sport coach confirms tryout fit and approves or rejects the account.',
        },
        {
          question: 'How should coach accounts be handled in AC-VMIS?',
          answer: 'Coach accounts remain admin-managed. Create them from the admin side, assign the coach to Basketball, Soccer, or Volleyball, and send a secure activation link so the coach can set their own password.',
        },
        {
          question: 'What should I do if a coach or player is missing from a team?',
          answer: 'Team creation and roster assignment now happen on the coach side. Admin should confirm the user record, sport assignment, approval state, and account status first, then ask the assigned sport coach to complete the operational update if needed.',
        },
      ],
      supportNotes: [
        'Include the affected user, role, and page name when reporting issues.',
        'Attach screenshots for approval, attendance, roster, or academic workflow errors.',
        'If an action affects official records, note the time and the team involved so audit checks are easier.',
      ],
    }
  }

  if (role.value === 'coach') {
    return {
      roleLabel: 'Coach',
      roleSummary: 'Track team schedules, attendance, academics, and roster activity with direct shortcuts for daily coaching tasks.',
      focusLabel: 'Best place to start',
      topTaskLabel: 'Daily coaching actions',
      taskSummary: 'These are the most common tasks coaches perform before, during, and after team activities.',
      tasks: [
        {
          title: 'Review student applications',
          description: 'Open sport-based student applications, confirm tryout fit, and approve or reject pending registrants for your assigned sport.',
          href: '/coach/applications',
          cta: 'Open Applications',
        },
        {
          title: 'Manage your teams',
          description: 'Create teams only for your assigned sport, build rosters, and keep team status accurate throughout the season.',
          href: '/coach/teams/manage',
          cta: 'Manage Teams',
        },
        {
          title: 'Review your team roster',
          description: 'Check players, roles, and status changes so your roster stays accurate before training or competition.',
          href: '/coach/team',
          cta: 'Open My Team',
        },
        {
          title: 'Manage schedules',
          description: 'Create, update, or review team practices and events so athletes always have the latest schedule.',
          href: '/coach/schedule',
          cta: 'View Schedule',
        },
        {
          title: 'Verify attendance',
          description: 'Open the coach schedule workspace and record attendance directly from the schedule modal once the session starts.',
          href: '/coach/schedule',
          cta: 'Open Schedule',
        },
      ],
      faqs: [
        {
          question: 'Where do I record attendance now?',
          answer: 'Open Coach Schedule, choose the active or completed schedule, and use the attendance sheet modal to mark each athlete before saving the record.',
        },
        {
          question: 'Can I approve players and build the roster directly?',
          answer: 'Yes. Coaches now review student-athlete applications for their assigned sport and can create teams under that sport. Only approved student-athletes from the same sport who are not already in another active team will appear for roster assignment.',
        },
        {
          question: 'Where do I check athlete academic visibility?',
          answer: 'Use Team Documents to review the documents that are visible to coaches for your assigned student-athletes. That helps you identify students who may need follow-up without editing admin-only academic records.',
        },
      ],
      supportNotes: [
        'When reporting attendance issues, include the schedule date and the student-athlete involved.',
        'For roster concerns, mention whether the issue is a role update, availability issue, or assignment conflict.',
        'If attendance or roster data appears incomplete, include the session date and team when reporting the issue.',
      ],
    }
  }

  return {
    roleLabel: 'Student-Athlete',
    roleSummary: 'Stay on top of approval status, schedules, attendance, and academic submissions from one place.',
    focusLabel: 'Best place to start',
    topTaskLabel: 'Most helpful student actions',
    taskSummary: 'These shortcuts cover the main actions student-athletes usually need after registration and during the season.',
    tasks: [
      {
        title: 'Check your dashboard status',
        description: 'See your current account access, announcements, and active varsity reminders in one overview.',
        href: '/StudentAthleteDashboard',
        cta: 'Open Dashboard',
      },
      {
        title: 'Review your team and schedule',
        description: 'Confirm your assigned team, upcoming sessions, and schedule adjustments before reporting to activities.',
        href: '/MySchedule',
        cta: 'View Schedule',
      },
      {
        title: 'Handle attendance correctly',
        description: 'Respond to schedule attendance or generate your QR token when your coach uses QR verification.',
        href: '/MySchedule',
        cta: 'Go to My Schedule',
      },
      {
        title: 'Submit academic requirements',
        description: 'Upload and track academic documents for eligibility reviews and compliance windows.',
        href: '/AcademicSubmissions',
        cta: 'Open Academics',
      },
    ],
    faqs: [
      {
        question: 'Why can’t I access the dashboard right after registering?',
        answer: 'Your account must be reviewed by a coach from the same sport you selected during registration. While your application is pending, you may only see the approval-status page until the coach approves your registration.',
      },
      {
        question: 'What should I do if my attendance is incorrect?',
        answer: 'Check the specific schedule entry first. If your response or QR scan was not recorded properly, contact your coach or admin and include the date, time, and session involved.',
      },
      {
        question: 'When should I upload academic documents?',
        answer: 'Submit them as soon as an academic period is opened or when your school requirements are updated. Delays can affect eligibility evaluation and may temporarily limit varsity access until an eligible result is confirmed.',
      },
    ],
    supportNotes: [
      'Include your full name, student number, and team when asking for help.',
      'If the issue is about login or approval, mention whether you recently registered or were already approved.',
      'For file upload issues, specify which academic submission page or document type was involved.',
    ],
  }
})

function toggleFaq(index: number) {
  activeFaq.value = activeFaq.value === index ? null : index
}

function cardMotion(order: number) {
  return { '--card-order': String(order) }
}
</script>

<template>
  <Head title="Help and Support" />

  <AccountShell active="help">
    <div class="space-y-6">
      <section class="account-card support-hero overflow-hidden rounded-3xl border border-[#034485]/25 bg-[#034485] px-6 py-6 text-white shadow-[0_24px_60px_-36px_rgba(3,68,133,0.45)]" :style="cardMotion(1)">
        <div class="flex justify-end">
          <div class="support-aside w-full rounded-2xl border border-white/20 bg-white/10 p-4 lg:max-w-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Need Assistance?</p>
            <p class="mt-2 text-sm font-semibold text-white">Varsity support desk</p>
            <p class="mt-1 text-sm text-white/80">Please provide a screenshot and a brief description of the concern when requesting assistance.</p>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
              <a href="mailto:varsity.support@asiancollege.edu.ph" class="support-btn support-btn-fill">Send Email</a>
              <a href="tel:+63281234567" class="support-btn support-btn-ghost">Call Office</a>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-5 xl:grid-cols-[1.3fr_0.9fr]">
        <div class="account-card rounded-2xl border border-[#034485]/20 bg-white p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.45)]" :style="cardMotion(2)">
          <div class="flex flex-col gap-1">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">{{ helpContent.topTaskLabel }}</p>
            <h3 class="text-lg font-semibold text-slate-900">Quick Access Tasks</h3>
            <p class="text-sm text-slate-600">{{ helpContent.taskSummary }}</p>
          </div>

          <div class="mt-5 grid gap-3 md:grid-cols-2">
            <article v-for="(task, index) in helpContent.tasks" :key="task.title" class="account-card task-card rounded-2xl border border-slate-200 bg-slate-50/70 p-4" :style="cardMotion(3 + index)">
              <div class="flex h-full flex-col">
                <p class="text-sm font-semibold text-slate-900">{{ task.title }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ task.description }}</p>
              <div class="mt-4 pt-1">
                <a :href="task.href" class="task-link">{{ task.cta }}</a>
              </div>
              </div>
            </article>
          </div>
        </div>

        <aside class="space-y-5">
          <section class="account-card rounded-2xl border border-[#034485]/20 bg-white p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.45)]" :style="cardMotion(7)">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Issue Reporting</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-900">Information to Include</h3>
            <div class="mt-4 space-y-3">
              <div v-for="note in helpContent.supportNotes" :key="note" class="note-row">
                <span class="note-dot" />
                <p class="text-sm leading-6 text-slate-600">{{ note }}</p>
              </div>
            </div>
          </section>

          <section class="account-card rounded-2xl border border-[#034485]/20 bg-white p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.45)]" :style="cardMotion(8)">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Response Guide</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-900">Support Expectations</h3>
            <div class="mt-4 grid gap-3">
              <div class="account-card rounded-xl border border-slate-200 bg-slate-50 px-4 py-3" :style="cardMotion(9)">
                <p class="text-sm font-semibold text-slate-900">Best channel</p>
                <p class="mt-1 text-sm text-slate-600">Use email for account, process, and data-record concerns.</p>
              </div>
              <div class="account-card rounded-xl border border-slate-200 bg-slate-50 px-4 py-3" :style="cardMotion(10)">
                <p class="text-sm font-semibold text-slate-900">Urgent issues</p>
                <p class="mt-1 text-sm text-slate-600">Call the office for urgent issues affecting attendance processing or account access.</p>
              </div>
            </div>
          </section>
        </aside>
      </section>

      <section class="account-card rounded-2xl border border-[#034485]/20 bg-white p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.45)]" :style="cardMotion(11)">
        <div class="flex flex-col gap-1">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#034485]">Role-Specific Questions</p>
          <h3 class="text-lg font-semibold text-slate-900">Common questions for {{ helpContent.roleLabel }}</h3>
          <p class="text-sm text-slate-600">Clear answers for common concerns encountered during regular system use.</p>
        </div>

        <div class="mt-5 space-y-3">
          <article
            v-for="(faq, index) in helpContent.faqs"
            :key="faq.question"
            class="account-card faq-card overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/70"
            :class="{ 'faq-card--active': activeFaq === index }"
            :style="cardMotion(12 + index)"
          >
            <button type="button" class="faq-trigger" @click="toggleFaq(index)">
              <span class="text-left text-sm font-semibold text-slate-900">{{ faq.question }}</span>
              <span class="faq-chevron" :class="{ 'faq-chevron--open': activeFaq === index }">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="m6 9 6 6 6-6" />
                </svg>
              </span>
            </button>

            <transition name="faq">
              <div v-if="activeFaq === index" class="faq-panel">
                <p class="text-sm leading-6 text-slate-600">{{ faq.answer }}</p>
              </div>
            </transition>
          </article>
        </div>
      </section>
    </div>
  </AccountShell>
</template>

<style scoped>
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #0f172a;
  font-weight: 700;
}

.settings-muted {
  color: #64748b;
}

.account-card {
  opacity: 0;
  transform: translateY(18px) scale(0.985);
  animation: account-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
  animation-delay: calc(var(--card-order, 0) * 60ms);
  will-change: transform, opacity;
}

@keyframes account-card-rise {
  from {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.support-hero {
  position: relative;
  isolation: isolate;
}

.support-hero::before {
  content: '';
  position: absolute;
  inset: auto -80px -90px auto;
  width: 220px;
  height: 220px;
  border-radius: 999px;
  background: radial-gradient(circle, rgba(59, 130, 246, 0.18) 0%, rgba(59, 130, 246, 0) 72%);
  pointer-events: none;
}

.role-chip,
.focus-chip {
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  border-radius: 999px;
  padding: 0.42rem 0.9rem;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-align: left;
  text-transform: uppercase;
}

.role-chip {
  border: 1px solid rgba(3, 68, 133, 0.16);
  background: rgba(3, 68, 133, 0.08);
  color: #034485;
}

.focus-chip {
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #f8fafc;
  color: #334155;
}

.support-aside {
  min-width: min(100%, 320px);
}

.support-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 0.6rem 1rem;
  font-size: 0.86rem;
  font-weight: 700;
  transition:
    transform 0.2s ease,
    background 0.2s ease,
    color 0.2s ease,
    border-color 0.2s ease;
}

.support-btn:hover {
  transform: translateY(-1px);
}

.support-btn-fill {
  background: #034485;
  color: #ffffff;
}

.support-btn-fill:hover {
  background: #04519f;
}

.support-btn-ghost {
  border: 1px solid rgba(3, 68, 133, 0.18);
  background: #ffffff;
  color: #034485;
}

.support-btn-ghost:hover {
  background: rgba(3, 68, 133, 0.06);
}

.task-card {
  transition:
    transform 0.22s ease,
    box-shadow 0.22s ease,
    border-color 0.22s ease;
}

.task-card:hover {
  transform: translateY(-2px);
  border-color: rgba(3, 68, 133, 0.25);
  box-shadow: 0 18px 30px -28px rgba(3, 68, 133, 0.35);
}

.task-link {
  display: inline-flex;
  width: 100%;
  justify-content: center;
  text-align: center;
}

@media (min-width: 640px) {
  .task-link {
    width: auto;
  }
}

.support-btn {
  display: inline-flex;
  width: 100%;
  justify-content: center;
  text-align: center;
}

@media (min-width: 640px) {
  .support-btn {
    width: auto;
  }
}

.task-link {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  background: rgba(3, 68, 133, 0.08);
  padding: 0.45rem 0.85rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: #034485;
  transition: background 0.2s ease;
}

.task-link:hover {
  background: rgba(3, 68, 133, 0.14);
}

.note-row {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.note-dot {
  margin-top: 0.45rem;
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  background: #034485;
  flex-shrink: 0;
}

.faq-card {
  transition:
    border-color 0.2s ease,
    background 0.2s ease,
    box-shadow 0.2s ease;
}

.faq-card:hover,
.faq-card--active {
  border-color: rgba(3, 68, 133, 0.22);
  background: #ffffff;
  box-shadow: 0 18px 28px -30px rgba(3, 68, 133, 0.3);
}

.faq-trigger {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.1rem;
}

.faq-panel {
  padding: 0 1.1rem 1rem;
}

.faq-chevron {
  display: inline-flex;
  width: 2rem;
  height: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: rgba(3, 68, 133, 0.08);
  color: #034485;
  transition:
    transform 0.22s ease,
    background 0.22s ease;
  flex-shrink: 0;
}

.faq-chevron svg {
  width: 1rem;
  height: 1rem;
}

.faq-chevron--open {
  transform: rotate(180deg);
  background: rgba(3, 68, 133, 0.14);
}

.faq-enter-active,
.faq-leave-active {
  transition: all 0.2s ease;
}

.faq-enter-from,
.faq-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (prefers-reduced-motion: reduce) {
  .account-card {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
