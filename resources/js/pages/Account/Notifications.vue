<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import Checkbox from 'primevue/checkbox'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, ref } from 'vue'

import AccountShell from '@/components/Account/AccountShell.vue'
import { useTheme } from '@/composables/useTheme'
import { normalizeWorkspaceRole, resolveAccountLayout } from '@/pages/Account/accountRole'

defineOptions({
  layout: (h: any, page: any) => h(resolveAccountLayout(page), [page]),
})

const props = defineProps<{
  settings: {
    notification_email_enabled: boolean
    notify_approvals: boolean
    notify_schedule_changes: boolean
    notify_attendance_changes: boolean
    notify_academic_alerts: boolean
    notify_attendance_exceptions: boolean
  }
  scope: {
    notifications: string[]
    coach_preferences: boolean
  }
  compliance: Record<string, unknown> | null
}>()

const hasNotificationField = (field: string) => props.scope.notifications.includes(field)
const saved = ref(false)
const page = usePage()
const role = computed(() => normalizeWorkspaceRole((page.props as any)?.auth?.user?.role))
const { isDarkMode } = useTheme()

const labelMap = computed(() => {
  if (role.value === 'coach') {
    return {
      notify_academic_alerts: 'Academic Submission Updates',
      notify_schedule_changes: 'Team Assignment (Coach)',
      notify_attendance_exceptions: 'Roster Changes (Assistants & Athletes)',
      notify_attendance_changes: 'Attendance Status Updates',
      notify_approvals: 'Newly pending accounts',
    }
  }

  if (role.value === 'student') {
    return {
      notify_academic_alerts: 'Academic Period Openings',
      notify_attendance_changes: 'Submission Status Updates',
      notify_schedule_changes: 'Schedule Updates (Start, Change, Cancel)',
      notify_attendance_exceptions: 'Team Roster & Coaching Updates',
      notify_approvals: 'Account Status Updates',
    }
  }

  return {
    notify_academic_alerts: 'Academic Submissions',
    notify_schedule_changes: 'Schedules',
    notify_attendance_exceptions: 'Team Operations Updates',
    notify_attendance_changes: 'Period Ending Soon',
    notify_approvals: 'Newly Pending Accounts',
  }
})

const form = useForm({
  notification_email_enabled: Boolean(props.settings?.notification_email_enabled ?? true),
  notify_approvals: Boolean(props.settings?.notify_approvals ?? true),
  notify_schedule_changes: Boolean(props.settings?.notify_schedule_changes ?? true),
  notify_attendance_changes: Boolean(props.settings?.notify_attendance_changes ?? true),
  notify_academic_alerts: Boolean(props.settings?.notify_academic_alerts ?? true),
  notify_attendance_exceptions: Boolean(props.settings?.notify_attendance_exceptions ?? true),
})

function submitSettings() {
  saved.value = false
  form.put('/account/settings', {
    preserveScroll: true,
    onSuccess: () => {
      saved.value = true
      setTimeout(() => (saved.value = false), 2200)
    },
  })
}

function cardMotion(order: number) {
  return { '--card-order': String(order) }
}
</script>

<template>
  <Head title="Notifications" />

  <AccountShell active="notifications">
      <form @submit.prevent="submitSettings" class="space-y-4">
        <section
          v-if="role === 'student'"
          class="account-card rounded-2xl border border-[#034485]/35 bg-[#034485] p-5 text-white"
          :style="cardMotion(1)"
        >
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Student alerts</p>
          <h1 class="mt-2 text-2xl font-bold text-white">Notifications</h1>
          <p class="mt-2 text-sm leading-6 text-white/85">Choose how academic, schedule, and roster updates reach you.</p>
        </section>

        <section
          class="account-card rounded-2xl border p-5 transition-colors"
          :class="
            isDarkMode
              ? 'border-slate-700/80 bg-slate-950/90 text-slate-100'
              : 'border-[#034485]/40 bg-white text-slate-900'
          "
          :style="cardMotion(2)"
        >
        <h2 class="section-title" :class="isDarkMode ? 'text-white' : 'text-slate-900'">
          <svg
            class="h-4 w-4"
            :class="isDarkMode ? 'text-sky-300' : 'text-[#1f2937]'"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
          >
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          Notifications
        </h2>
        <p class="settings-muted mt-1 text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">Choose how and when you want to be alerted.</p>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
          <div
            class="account-card rounded-xl border p-4 transition-colors"
            :class="
              isDarkMode
                ? 'border-slate-700/80 bg-slate-900/95 text-slate-100'
                : 'border-[#034485]/30 bg-slate-50 text-slate-900'
            "
            :style="cardMotion(3)"
          >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="settings-kicker text-xs font-semibold uppercase tracking-wide" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">Email Notifications</p>
                <p class="text-xs" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">
                  {{
                    role === 'coach'
                      ? 'Send coach alerts to your email.'
                      : role === 'student'
                        ? 'Send student alerts to your email.'
                        : 'Send admin alerts to your email.'
                  }}
                </p>
              </div>
              <ToggleSwitch v-if="hasNotificationField('notification_email_enabled')" v-model="form.notification_email_enabled" />
            </div>
            <div class="mt-4 grid gap-2">
              <div
                v-if="hasNotificationField('notify_approvals')"
                class="toggle-row rounded-lg px-3 py-2 transition-colors"
                :class="[isDarkMode ? 'text-white' : 'text-slate-700', { 'toggle-row--disabled': !form.notification_email_enabled }]"
              >
                <span class="font-medium" :class="isDarkMode ? 'text-white' : 'text-slate-700'">{{ labelMap.notify_approvals }}</span>
                <Checkbox v-model="form.notify_approvals" binary :disabled="!form.notification_email_enabled" />
              </div>
              <div
                v-if="hasNotificationField('notify_attendance_exceptions')"
                class="toggle-row rounded-lg px-3 py-2 transition-colors"
                :class="[isDarkMode ? 'text-white' : 'text-slate-700', { 'toggle-row--disabled': !form.notification_email_enabled }]"
              >
                <span class="font-medium" :class="isDarkMode ? 'text-white' : 'text-slate-700'">{{ labelMap.notify_attendance_exceptions }}</span>
                <Checkbox v-model="form.notify_attendance_exceptions" binary :disabled="!form.notification_email_enabled" />
              </div>
              <div
                v-if="hasNotificationField('notify_schedule_changes')"
                class="toggle-row rounded-lg px-3 py-2 transition-colors"
                :class="[isDarkMode ? 'text-white' : 'text-slate-700', { 'toggle-row--disabled': !form.notification_email_enabled }]"
              >
                <span class="font-medium" :class="isDarkMode ? 'text-white' : 'text-slate-700'">{{ labelMap.notify_schedule_changes }}</span>
                <Checkbox v-model="form.notify_schedule_changes" binary :disabled="!form.notification_email_enabled" />
              </div>
              <div
                v-if="hasNotificationField('notify_academic_alerts')"
                class="toggle-row rounded-lg px-3 py-2 transition-colors"
                :class="[isDarkMode ? 'text-white' : 'text-slate-700', { 'toggle-row--disabled': !form.notification_email_enabled }]"
              >
                <span class="font-medium" :class="isDarkMode ? 'text-white' : 'text-slate-700'">{{ labelMap.notify_academic_alerts }}</span>
                <Checkbox v-model="form.notify_academic_alerts" binary :disabled="!form.notification_email_enabled" />
              </div>
              <div
                v-if="hasNotificationField('notify_attendance_changes')"
                class="toggle-row rounded-lg px-3 py-2 transition-colors"
                :class="[isDarkMode ? 'text-white' : 'text-slate-700', { 'toggle-row--disabled': !form.notification_email_enabled }]"
              >
                <span class="font-medium" :class="isDarkMode ? 'text-white' : 'text-slate-700'">{{ labelMap.notify_attendance_changes }}</span>
                <Checkbox v-model="form.notify_attendance_changes" binary :disabled="!form.notification_email_enabled" />
              </div>
            </div>
          </div>
        </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
          <button
            type="submit"
            class="rounded-lg px-4 py-2 font-semibold text-white transition"
            :class="
              isDarkMode
                ? 'bg-[#034485] hover:bg-[#02376b]'
                : 'bg-[#034485] hover:bg-[#02376b]'
            "
            :disabled="form.processing"
          >
            {{ form.processing ? 'Saving...' : 'Save Notifications' }}
          </button>
          <p v-if="saved" class="text-sm" :class="isDarkMode ? 'text-emerald-300' : 'text-green-700'">Notification settings updated.</p>
        </div>
      </form>
  </AccountShell>
</template>

<style scoped>
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}

.settings-muted,
.settings-kicker {
  color: #64748b;
}

.account-card {
  opacity: 0;
  transform: translateY(18px) scale(0.985);
  animation: account-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
  animation-delay: calc(var(--card-order, 0) * 70ms);
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

.toggle-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  font-size: 0.9rem;
  color: #334155;
  background: rgba(148, 163, 184, 0.08);
}

.toggle-row span:first-child {
  min-width: 0;
  line-height: 1.4;
}

.toggle-row--disabled {
  opacity: 0.5;
}

:deep(.p-checkbox-box.p-highlight),
:deep(.p-checkbox-checked .p-checkbox-box) {
  background: #034485 !important;
  border-color: #034485 !important;
}

:deep(.p-checkbox-box.p-highlight .p-checkbox-icon),
:deep(.p-checkbox-checked .p-checkbox-icon) {
  color: #ffffff !important;
}

:deep(.p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-slider),
:deep(.p-toggleswitch-checked .p-toggleswitch-slider) {
  background: #034485 !important;
  border-color: #034485 !important;
}

:deep(.p-toggleswitch.p-focus .p-toggleswitch-slider),
:deep(.p-toggleswitch:focus-within .p-toggleswitch-slider) {
  box-shadow: 0 0 0 3px rgba(3, 68, 133, 0.18) !important;
}

@media (prefers-reduced-motion: reduce) {
  .account-card {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
