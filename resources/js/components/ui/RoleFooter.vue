<script setup lang="ts">
type FooterLink = {
  label: string
  href: string
}

const props = defineProps<{
  title: string
  description: string
  links: FooterLink[]
  bottomNav?: boolean
}>()

const currentYear = new Date().getFullYear()
</script>

<template>
  
  <footer
    class="role-footer mt-10 mr-4 rounded-tr-[2rem] border-t border-slate-200/60 bg-[#b91c1c] sm:mr-6 lg:mr-10"
    :class="props.bottomNav ? 'pb-[calc(env(safe-area-inset-bottom,0px)+5.25rem)] md:pb-5' : 'pb-5'"
  >
    <div class="mx-auto w-full max-w-[1240px] px-4 pt-5 sm:px-6 lg:px-10">
      <div class="grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,0.85fr)] lg:items-end">
        <section class="space-y-3">
          <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-blue-200">AC-VMIS</p>
          <div class="space-y-2">
            <p class="text-[15px] font-semibold text-white">{{ props.title }}</p>
            <p v-if="props.description" class="max-w-2xl text-[12px] leading-6 text-blue-100/90">
              {{ props.description }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2 text-[11px] text-blue-100">
            <p class="inline-flex items-center gap-2">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                <rect x="3" y="5" width="18" height="14" rx="2" />
                <path d="M3 7l9 6 9-6" />
              </svg>
              <span>varsity.support@asiancollege.edu.ph</span>
            </p>
            <p class="inline-flex items-center gap-2">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.34 1.77.66 2.6a2 2 0 0 1-.45 2.11L8 9.9a16 16 0 0 0 6.1 6.1l1.47-1.32a2 2 0 0 1 2.11-.45c.83.32 1.7.54 2.6.66A2 2 0 0 1 22 16.92z" />
              </svg>
              <span>+63 000 000 0000</span>
            </p>
          </div>
        </section>

        <section aria-label="Institution Mission" class="rounded-2xl border border-white/12 bg-white/6 px-4 py-4 backdrop-blur-sm">
          <div class="space-y-3 text-[11px] text-blue-100">
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-white/90">Vision</p>
              <p>To be a transformative educational institution committed to the success of its graduates through quality instruction, relevant research, and strong community engagement.</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-white/90">Mission</p>
              <p>To educate and develop globally competitive future leaders.</p>
            </div>
          </div>
        </section>
      </div>

      <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-white/10 pt-3 text-[10px] text-blue-200">
        <span>© {{ currentYear }} Asian College</span>
        <span class="text-white">Varsity Management Information System</span>
      </div>
    </div>
  </footer>
</template>
