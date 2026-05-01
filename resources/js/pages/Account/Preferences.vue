<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AccountShell from '@/components/Account/AccountShell.vue'
import { useTheme } from '@/composables/useTheme'
import { normalizeWorkspaceRole, resolveAccountLayout, workspaceNavigationPreview } from '@/pages/Account/accountRole'

defineOptions({
  layout: (h: any, page: any) => h(resolveAccountLayout(page), [page]),
})

const props = defineProps<{
  settings: {
    notification_email_enabled: boolean
    notify_approvals: boolean
    notify_schedule_changes: boolean
    notify_attendance_changes: boolean
    notify_wellness_alerts: boolean
    notify_academic_alerts: boolean
    notify_attendance_exceptions: boolean
    notify_wellness_injury_threshold: boolean
    wellness_injury_threshold_level: number
  }
  scope: {
    notifications: string[]
    coach_preferences: boolean
  }
  compliance: Record<string, unknown> | null
}>()

const saved = ref(false)
const page = usePage()
const role = computed(() => normalizeWorkspaceRole((page.props as any)?.auth?.user?.role))

const navOrder = ref(workspaceNavigationPreview(role.value))
const { themeMode, setTheme } = useTheme()

function moveNavItem(index: number, direction: 'up' | 'down') {
  const target = direction === 'up' ? index - 1 : index + 1
  if (target < 0 || target >= navOrder.value.length) return
  const next = [...navOrder.value]
  ;[next[index], next[target]] = [next[target], next[index]]
  navOrder.value = next
}

const form = useForm({
  notification_email_enabled: Boolean(props.settings?.notification_email_enabled ?? true),
  notify_approvals: Boolean(props.settings?.notify_approvals ?? true),
  notify_schedule_changes: Boolean(props.settings?.notify_schedule_changes ?? true),
  notify_attendance_changes: Boolean(props.settings?.notify_attendance_changes ?? true),
  notify_wellness_alerts: Boolean(props.settings?.notify_wellness_alerts ?? true),
  notify_academic_alerts: Boolean(props.settings?.notify_academic_alerts ?? true),
  notify_attendance_exceptions: Boolean(props.settings?.notify_attendance_exceptions ?? true),
  notify_wellness_injury_threshold: Boolean(props.settings?.notify_wellness_injury_threshold ?? true),
  wellness_injury_threshold_level: Number(props.settings?.wellness_injury_threshold_level ?? 3),
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
  <Head title="Preferences" />

  <AccountShell active="preferences">
      <form @submit.prevent="submitSettings" class="space-y-4">
      <section
        v-if="role === 'student'"
        class="account-card rounded-2xl border border-[#034485]/35 bg-[#034485] p-5 text-white"
        :style="cardMotion(1)"
      >
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Student preferences</p>
        <h1 class="mt-2 text-2xl font-bold text-white">Preferences</h1>
        <p class="mt-2 text-sm leading-6 text-white/85">Customize how your student workspace looks and how your navigation feels during daily use.</p>
      </section>

      <section class="account-card rounded-2xl border border-[#034485]/40 bg-white p-5" :style="cardMotion(2)">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="section-title">Appearance</h2>
            <p class="settings-muted mt-1 text-sm">Choose how AC-VMIS looks while you use the system.</p>
          </div>
          <div class="inline-flex rounded-full border border-[#034485]/20 bg-[#034485]/5 p-1">
            <button
              type="button"
              class="rounded-full px-4 py-2 text-sm font-semibold transition"
              :class="themeMode === 'light' ? 'bg-[#034485] text-white' : 'text-[#034485] hover:bg-[#034485]/10'"
              @click="setTheme('light')"
            >
              Light
            </button>
            <button
              type="button"
              class="rounded-full px-4 py-2 text-sm font-semibold transition"
              :class="themeMode === 'dark' ? 'bg-[#034485] text-white' : 'text-[#034485] hover:bg-[#034485]/10'"
              @click="setTheme('dark')"
            >
              Dark
            </button>
          </div>
        </div>
      </section>

      <section class="account-card rounded-2xl border border-[#034485]/40 bg-white p-5" :style="cardMotion(3)">
        <h2 class="section-title">Workspace Navigation</h2>
        <div class="mt-3 grid gap-2">
          <div
            v-for="(item, index) in navOrder"
            :key="item"
            class="nav-item flex flex-col gap-3 rounded-xl border border-[#034485] bg-[#034485] px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
          >
            <span class="text-sm font-semibold text-white">{{ item }}</span>
            <div class="flex items-center gap-2">
              <button type="button" class="nav-move" :disabled="index === 0" @click="moveNavItem(index, 'up')">↑</button>
              <button type="button" class="nav-move" :disabled="index === navOrder.length - 1" @click="moveNavItem(index, 'down')">↓</button>
            </div>
          </div>
        </div>
      </section>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
          <button type="submit" class="rounded-lg bg-[#1f2937] px-4 py-2 text-white font-semibold hover:bg-[#334155] transition" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Preferences' }}
          </button>
          <p v-if="saved" class="text-sm text-green-700">Preferences updated.</p>
        </div>
      </form>
  </AccountShell>
</template>

<style scoped>
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #0f172a;
  font-weight: 600;
}

.settings-muted,
.settings-label {
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

.nav-item {
  transition: background 0.2s ease;
}

.nav-move {
  border-radius: 999px;
  border: 1px solid rgba(3, 68, 133, 0.4);
  padding: 0.15rem 0.55rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: #034485;
  background: #ffffff;
  transition: background 0.2s ease;
}

.nav-move:hover:not(:disabled) {
  background: rgba(3, 68, 133, 0.1);
}

.nav-move:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (prefers-reduced-motion: reduce) {
  .account-card {
    animation: none;
    opacity: 1;
    transform: none;
  }
}

</style>
