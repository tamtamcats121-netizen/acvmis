<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    unreadCount?: number
    inverse?: boolean
    loading?: boolean
}>()

const hasUnread = computed(() => (props.unreadCount ?? 0) > 0)
</script>

<template>
    <button
        type="button"
        class="announcement-bell relative inline-flex h-10 w-10 items-center justify-center rounded-full border transition"
        :class="
            inverse
                ? 'border-gray-600 text-gray-200 hover:bg-gray-700'
                : 'border-gray-300 text-gray-700 hover:bg-gray-100'
        "
        aria-label="Open announcements"
    >
        <svg
            class="h-5 w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M10.268 21a2 2 0 0 0 3.464 0" />
            <path
                d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 1 0 6 8c0 4.499-1.411 5.956-2.738 7.326"
            />
        </svg>
        <span v-if="loading" class="absolute right-1.5 top-1.5 h-2.5 w-2.5 rounded-full bg-gray-400 animate-pulse" />
        <span v-else-if="hasUnread" class="absolute right-1.5 top-1.5 h-2.5 w-2.5 rounded-full bg-red-500" />
    </button>
</template>
