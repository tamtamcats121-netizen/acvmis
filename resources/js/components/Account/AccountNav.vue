<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  active: 'profile' | 'settings' | 'account' | 'notifications' | 'preferences' | 'help'
}>()

const items = computed(() => [
  { key: 'profile', label: 'Profile', href: '/account/profile', icon: 'user' },
  { key: 'account', label: 'Account', href: '/account/account-settings', icon: 'lock' },
  { key: 'notifications', label: 'Alerts', href: '/account/notifications', icon: 'bell' },
  { key: 'preferences', label: 'Preferences', href: '/account/preferences', icon: 'sliders' },
  { key: 'help', label: 'Help', href: '/account/help', icon: 'help' },
])

function goTo(href: string) {
  router.get(href)
}
</script>

<template>
  <div class="account-nav rounded-3xl border border-[#034485]/18 bg-white p-4 shadow-[0_24px_50px_-38px_rgba(15,23,42,0.45)]">
    <div class="account-nav__header">
      <h1 class="account-nav__title">Settings</h1>
    </div>
    <div class="account-nav__items">
      <button
        v-for="item in items"
        :key="item.key"
        type="button"
        class="nav-link"
        :class="props.active === item.key ? 'nav-link--active' : ''"
        @click="goTo(item.href)"
      >
        <span class="nav-link__icon">
          <svg v-if="item.icon === 'grid'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <rect x="3" y="3" width="7" height="7" rx="1.5" />
            <rect x="14" y="3" width="7" height="7" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" />
            <rect x="14" y="14" width="7" height="7" rx="1.5" />
          </svg>
          <svg v-else-if="item.icon === 'user'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <path d="M20 21a8 8 0 0 0-16 0" />
            <circle cx="12" cy="8" r="4" />
          </svg>
          <svg v-else-if="item.icon === 'lock'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <rect x="4" y="11" width="16" height="9" rx="2" />
            <path d="M8 11V8a4 4 0 1 1 8 0v3" />
          </svg>
          <svg v-else-if="item.icon === 'bell'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          <svg v-else-if="item.icon === 'sliders'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <path d="M4 21v-7" />
            <path d="M4 10V3" />
            <path d="M12 21v-9" />
            <path d="M12 8V3" />
            <path d="M20 21v-4" />
            <path d="M20 13V3" />
            <path d="M2 14h4" />
            <path d="M10 8h4" />
            <path d="M18 17h4" />
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <circle cx="12" cy="12" r="10" />
            <path d="M9.09 9a3 3 0 1 1 5.82 1c-.6.84-1.83 1.31-2.38 2.1-.22.31-.32.66-.32 1.4" />
            <path d="M12 17h.01" />
          </svg>
        </span>
        <span class="nav-link__label">{{ item.label }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.account-nav {
  position: relative;
  overflow: hidden;
}

.account-nav::before {
  content: '';
  position: absolute;
  inset: -2rem auto auto -2rem;
  width: 8rem;
  height: 8rem;
  border-radius: 999px;
  background: radial-gradient(circle, rgba(3, 68, 133, 0.12) 0%, rgba(3, 68, 133, 0) 72%);
  pointer-events: none;
}

.account-nav__header {
  position: relative;
  z-index: 1;
}

.account-nav__title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.25;
}

.account-nav__items {
  margin-top: 0.85rem;
  display: grid;
  gap: 0.55rem;
}

.nav-link {
  display: flex;
  width: 100%;
  align-items: flex-start;
  gap: 0.8rem;
  border-radius: 1rem;
  border: 1px solid rgba(148, 163, 184, 0.2);
  background: #ffffff;
  padding: 0.78rem 0.9rem;
  color: #334155;
  text-align: left;
  transition:
    transform 0.18s ease,
    background 0.18s ease,
    border-color 0.18s ease,
    box-shadow 0.18s ease;
}

.nav-link:hover {
  transform: translateY(-1px);
  border-color: rgba(3, 68, 133, 0.18);
  background: rgba(248, 250, 252, 0.96);
}

.nav-link__icon {
  display: inline-flex;
  width: 2.15rem;
  height: 2.15rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.8rem;
  background: rgba(3, 68, 133, 0.08);
  color: #034485;
  flex-shrink: 0;
}

.nav-link__icon svg {
  width: 1rem;
  height: 1rem;
}

.nav-link__label {
  font-size: 0.88rem;
  font-weight: 700;
  line-height: 1.35;
  white-space: normal;
  word-break: break-word;
}

.nav-link--active {
  border-color: rgba(3, 68, 133, 0.18);
  background: linear-gradient(135deg, #034485 0%, #0a5fb1 100%);
  color: #ffffff;
  box-shadow: 0 18px 30px -24px rgba(3, 68, 133, 0.55);
}

.nav-link--active:hover {
  transform: none;
  border-color: rgba(3, 68, 133, 0.18);
  background: linear-gradient(135deg, #034485 0%, #0a5fb1 100%);
  color: #ffffff;
}

.nav-link--active .nav-link__icon {
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff;
}

:global(html.theme-dark) .account-nav {
  border-color: rgba(3, 68, 133, 0.28) !important;
  background: #171717 !important;
  box-shadow: 0 16px 30px rgba(0, 0, 0, 0.22) !important;
}

:global(html.theme-dark) .account-nav__title {
  color: #f8fafc !important;
}

:global(html.theme-dark) .nav-link {
  border-color: #30363f !important;
  background: #1b1b1b !important;
  color: #e2e8f0 !important;
}

:global(html.theme-dark) .nav-link:hover {
  border-color: rgba(3, 68, 133, 0.34) !important;
  background: #202020 !important;
}

:global(html.theme-dark) .nav-link__icon {
  background: rgba(3, 68, 133, 0.16) !important;
  color: #93c5fd !important;
}

:global(html.theme-dark) .nav-link--active {
  border-color: rgba(3, 68, 133, 0.42) !important;
  background: linear-gradient(135deg, #034485 0%, #0a5fb1 100%) !important;
  color: #ffffff !important;
  box-shadow: 0 18px 30px -24px rgba(3, 68, 133, 0.45) !important;
}

:global(html.theme-dark) .nav-link--active .nav-link__icon {
  background: rgba(255, 255, 255, 0.16) !important;
  color: #ffffff !important;
}
</style>
