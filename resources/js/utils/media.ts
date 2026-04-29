export const DEFAULT_AVATAR_URL = '/images/default-avatar.svg'
export const DEFAULT_TEAM_AVATAR_URL = '/images/default-avatar.svg'

export function resolvePublicMediaUrl(path?: string | null, fallback = DEFAULT_AVATAR_URL) {
    const value = String(path ?? '').trim()

    if (!value) {
        return fallback
    }

    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) {
        return value
    }

    return `/storage/${value.replace(/^\/+/, '')}`
}

export function resolveUserAvatarUrl(path?: string | null) {
    return resolvePublicMediaUrl(path, DEFAULT_AVATAR_URL)
}

export function resolveTeamAvatarUrl(path?: string | null) {
    return resolvePublicMediaUrl(path, DEFAULT_TEAM_AVATAR_URL)
}
