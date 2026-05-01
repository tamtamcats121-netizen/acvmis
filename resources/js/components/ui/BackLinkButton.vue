<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{
    href: string
    label: string
}>()
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center gap-2 rounded-full border border-[#034485]/30 bg-white px-3.5 py-2 text-sm font-semibold text-[#034485] transition hover:border-[#034485] hover:bg-[#eef5ff] active:bg-[#dcecff]"
    >
        <svg viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current stroke-[2.2]" aria-hidden="true">
            <path d="M11.5 4.5 6 10l5.5 5.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M6.5 10H14" stroke-linecap="round" />
        </svg>
        <span>{{ label }}</span>
    </Link>
</template>
