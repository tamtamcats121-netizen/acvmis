<script setup lang="ts">
defineProps<{
    modelValue: string
    placeholder: string
    filterCount?: number
    showFilters?: boolean
    showFiltersToggle?: boolean
    showSubmit?: boolean
    showClear?: boolean
    submitLabel?: string
    clearLabel?: string
}>()

defineEmits<{
    (event: 'update:modelValue', value: string): void
    (event: 'submit'): void
    (event: 'toggle-filters'): void
    (event: 'clear'): void
}>()
</script>

<template>
    <section class="page-card rounded-2xl border border-[#034485]/30 bg-white p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex-1">
                <input
                    :value="modelValue"
                    type="text"
                    :placeholder="placeholder"
                    class="w-full rounded-xl border border-[#034485]/20 px-3 py-2.5 text-sm text-slate-900 transition outline-none focus:border-[#034485] focus:ring-2 focus:ring-[#034485]/20"
                    @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
                    @keyup.enter="$emit('submit')"
                />
            </div>

            <div class="flex flex-wrap gap-2">
                <slot name="actions" />

                <button
                    v-if="showSubmit !== false"
                    type="button"
                    class="rounded-xl bg-[#034485] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#02315f]"
                    @click="$emit('submit')"
                >
                    {{ submitLabel || 'Search' }}
                </button>

                <button
                    v-if="showFiltersToggle !== false"
                    type="button"
                    class="rounded-xl border border-[#034485]/30 bg-white px-4 py-2 text-sm font-semibold text-[#034485] transition hover:bg-[#eef5ff]"
                    @click="$emit('toggle-filters')"
                >
                    Filters
                    <span
                        v-if="filterCount"
                        class="ml-2 rounded-full bg-[#dcecff] px-2 py-0.5 text-[11px] font-bold text-[#034485]"
                    >
                        {{ filterCount }}
                    </span>
                </button>

                <button
                    v-if="showClear"
                    type="button"
                    class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click="$emit('clear')"
                >
                    {{ clearLabel || 'Reset' }}
                </button>
            </div>
        </div>

        <div v-if="$slots.filters && showFilters" class="mt-4 border-t border-[#034485]/12 pt-4">
            <slot name="filters" />
        </div>

        <div v-if="$slots.footer" class="mt-3">
            <slot name="footer" />
        </div>
    </section>
</template>
