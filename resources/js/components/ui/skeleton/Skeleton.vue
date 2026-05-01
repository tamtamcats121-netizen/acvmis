<script setup lang="ts">
defineOptions({
  inheritAttrs: false,
})
</script>

<template>
  <div v-bind="$attrs" class="animate-pulse rounded-md bg-slate-200/80" />
</template>
