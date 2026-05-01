<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import { resolveUserAvatarUrl } from '@/utils/media'

const props = withDefaults(defineProps<{
  inverse?: boolean
  menuPlacement?: 'bottom' | 'top' | 'right'
  compact?: boolean
}>(), {
  inverse: false,
  menuPlacement: 'bottom',
  compact: false,
})

const page = usePage()
const menuOpen = ref(false)
const closeTimer = ref<number | null>(null)

const user = computed(() => page.props.auth?.user ?? null)
const identity = computed(() => page.props.auth?.identity ?? null)
const userRole = computed(() => String(user.value?.role ?? ''))
const isStudentUser = computed(() => ['student', 'student-athlete'].includes(userRole.value))

const avatarUrl = computed(() => {
  return resolveUserAvatarUrl(String(user.value?.avatar_url ?? user.value?.avatar ?? ''))
})

const fullName = computed(() => String(user.value?.name ?? 'User'))
const studentStatusLabel = computed(() => {
  const subtitle = String(identity.value?.subtitle ?? '').trim()
  if (subtitle) return subtitle
  return 'Status unavailable'
})
const buttonTitle = computed(() => (isStudentUser.value ? `${fullName.value} · ${studentStatusLabel.value}` : fullName.value))
const menuPanelClass = computed(() => {
  if (props.menuPlacement === 'top') {
    return 'absolute right-0 bottom-full mb-2 w-56 rounded-lg border border-slate-200 bg-white shadow-xl z-40 overflow-hidden'
  }
  if (props.menuPlacement === 'right') {
    return 'absolute left-full -top-2 ml-2 w-56 rounded-lg border border-slate-200 bg-white shadow-xl z-40 overflow-hidden'
  }
  return 'absolute right-0 mt-2 w-56 rounded-lg border border-slate-200 bg-white shadow-xl z-40 overflow-hidden'
})

function goProfile() {
  menuOpen.value = false
  router.get('/account/profile')
}

function goSettings() {
  menuOpen.value = false
  router.get('/account/settings')
}

function logout() {
  menuOpen.value = false
  router.post('/logout')
}

function openMenu() {
  if (closeTimer.value) {
    window.clearTimeout(closeTimer.value)
    closeTimer.value = null
  }
  menuOpen.value = true
}

function scheduleClose() {
  if (closeTimer.value) {
    window.clearTimeout(closeTimer.value)
  }
  closeTimer.value = window.setTimeout(() => {
    menuOpen.value = false
    closeTimer.value = null
  }, 180)
}
</script>

<template>
  <div
    class="relative account-menu"
    @mouseenter="openMenu"
    @mouseleave="scheduleClose"
    @focusin="openMenu"
    @focusout="scheduleClose"
  >
    <button
      type="button"
      class="account-card"
      :class="[
        inverse ? 'account-card-inverse' : 'account-card-light',
        compact ? 'account-card-compact' : '',
      ]"
      @click="menuOpen = !menuOpen"
      :title="compact ? buttonTitle : ''"
    >
      <img :src="avatarUrl" alt="Profile" class="account-avatar h-9 w-9 rounded-full object-cover" />
      <div class="account-copy min-w-0 text-left">
        <p class="account-name text-sm font-semibold truncate leading-tight">{{ fullName }}</p>
        <p v-if="isStudentUser" class="account-meta text-xs leading-tight">{{ studentStatusLabel }}</p>
      </div>
    </button>

    <div
      v-if="menuOpen"
      :class="menuPanelClass"
    >
      <button @click="goProfile" class="menu-item">Profile</button>
      <button @click="goSettings" class="menu-item">Settings</button>
      <button @click="logout" class="menu-item menu-item-danger">Logout</button>
    </div>
  </div>
</template>

<style scoped>
.account-card {
  display: flex;
  align-items: center;
  flex: 0 0 auto;
  gap: 10px;
  border-radius: 10px;
  padding: 6px 10px;
  min-width: 180px;
  max-width: 240px;
}

.account-card-light {
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
}

.account-card-compact {
  min-width: 0;
  max-width: 240px;
  padding: 0;
  border: none !important;
  background: transparent !important;
  box-shadow: none !important;
}

.account-card-inverse {
  border: 1px solid #475569;
  background: #1e293b;
  color: #e2e8f0;
}

.account-copy {
  display: flex;
  flex: 1 1 auto;
  min-width: 0;
  flex-direction: column;
  justify-content: center;
  gap: 2px;
  overflow: hidden;
}

.account-name,
.account-meta {
  margin: 0;
}

.account-meta {
  color: rgb(100 116 139);
}

.account-card-inverse .account-meta {
  color: rgb(148 163 184);
}

.menu-item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 10px 12px;
  font-size: 14px;
  color: #1e293b;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.menu-item:hover {
  background: #f8fafc;
}

.menu-item-danger {
  width: calc(100% - 16px);
  margin: 6px 8px 8px;
  border-radius: 10px;
  border: 1px solid #fecdd3;
  color: #e11d48;
  font-weight: 600;
  background: #ffffff;
}

.menu-item-danger:hover {
  background: #fff1f2;
  border-color: #fda4af;
}
</style>
