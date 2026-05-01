<script setup lang="ts">
defineProps<{
    title: string
    description: string
}>()
</script>

<template>
    <div class="rounded-2xl border border-dashed border-[#034485]/25 bg-[#f8fbff] px-4 py-8 text-center">
        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-[#e6f0ff] text-[#034485]">
            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true">
                <path d="M10.5 17a6.5 6.5 0 1 1 4.34-11.34A6.5 6.5 0 0 1 10.5 17Z" />
                <path d="m16 16 4.5 4.5" stroke-linecap="round" />
            </svg>
        </div>
        <p class="mt-3 text-sm font-semibold text-slate-900">{{ title }}</p>
        <p class="mt-1 text-sm text-slate-500">{{ description }}</p>
    </div>
</template>
