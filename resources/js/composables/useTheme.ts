import { computed, ref } from 'vue'

export type ThemeMode = 'light' | 'dark'

const STORAGE_KEY = 'ac-vmis-theme-mode'
const themeModeState = ref<ThemeMode>('light')

function normalizeThemeMode(value: unknown): ThemeMode {
  return value === 'dark' ? 'dark' : 'light'
}

function readStoredThemeMode(): ThemeMode {
  if (typeof window === 'undefined') {
    return 'light'
  }

  return normalizeThemeMode(window.localStorage.getItem(STORAGE_KEY))
}

function applyTheme(mode: ThemeMode, persist = true) {
  themeModeState.value = mode

  if (typeof document === 'undefined') {
    return
  }

  const root = document.documentElement
  root.classList.remove('theme-light', 'theme-dark')
  root.classList.add(`theme-${mode}`)
  root.setAttribute('data-theme', mode)

  if (persist && typeof window !== 'undefined') {
    window.localStorage.setItem(STORAGE_KEY, mode)
  }
}

export function initTheme() {
  applyTheme(readStoredThemeMode(), false)
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
