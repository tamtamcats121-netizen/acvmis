import { computed, ref } from 'vue'

export type ThemeMode = 'light' | 'dark'

const STORAGE_KEY = 'ac-vmis-theme-mode'
const themeModeState = ref<ThemeMode>('light')
const activeThemeContext = ref<{
  userKey: string | null
  allowDarkMode: boolean
}>({
  userKey: null,
  allowDarkMode: false,
})

function normalizeThemeMode(value: unknown): ThemeMode {
  return value === 'dark' ? 'dark' : 'light'
}

function storageKeyForUser(userKey: string) {
  return `${STORAGE_KEY}:user:${userKey}`
}

function readStoredThemeMode(userKey: string | null): ThemeMode {
  if (typeof window === 'undefined') {
    return 'light'
  }

  if (userKey) {
    return normalizeThemeMode(window.localStorage.getItem(storageKeyForUser(userKey)))
  }

  return 'light'
}

function applyTheme(mode: ThemeMode, persist = true) {
  const normalizedMode = activeThemeContext.value.allowDarkMode
    ? normalizeThemeMode(mode)
    : 'light'

  themeModeState.value = normalizedMode

  if (typeof document === 'undefined') {
    return
  }

  const root = document.documentElement
  root.classList.remove('theme-light', 'theme-dark')
  root.classList.add(`theme-${normalizedMode}`)
  root.setAttribute('data-theme', normalizedMode)

  if (persist && typeof window !== 'undefined') {
    const userKey = activeThemeContext.value.userKey

    if (userKey && activeThemeContext.value.allowDarkMode) {
      window.localStorage.setItem(storageKeyForUser(userKey), normalizedMode)
    }
  }
}

function migrateLegacyTheme(userKey: string) {
  if (typeof window === 'undefined') {
    return
  }

  const userStorageKey = storageKeyForUser(userKey)
  if (window.localStorage.getItem(userStorageKey) !== null) {
    return
  }

  const legacyValue = window.localStorage.getItem(STORAGE_KEY)
  if (legacyValue === null) {
    return
  }

  window.localStorage.setItem(userStorageKey, normalizeThemeMode(legacyValue))
  window.localStorage.removeItem(STORAGE_KEY)
}

export function syncThemeContext(context: { userKey: string | null; allowDarkMode: boolean }) {
  activeThemeContext.value = context

  if (context.userKey && context.allowDarkMode) {
    migrateLegacyTheme(context.userKey)
  }

  applyTheme(readStoredThemeMode(context.userKey), false)
}

export function initTheme(context: { userKey: string | null; allowDarkMode: boolean }) {
  syncThemeContext(context)
}

export function useTheme() {
  const themeMode = computed(() => themeModeState.value)
  const isDarkMode = computed(() => themeModeState.value === 'dark')

  return {
    themeMode,
    isDarkMode,
    setTheme: (mode: ThemeMode) => applyTheme(mode),
    toggleTheme: () => applyTheme(themeModeState.value === 'dark' ? 'light' : 'dark'),
  }
}
