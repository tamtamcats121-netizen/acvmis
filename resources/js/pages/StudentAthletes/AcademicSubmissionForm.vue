<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import FileUpload from 'primevue/fileupload'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, ref } from 'vue'

import Spinner from '@/components/ui/spinner/Spinner.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'

defineOptions({
  layout: StudentAthleteDashboard,
})

type Period = {
  id: number
  school_year: string
  term: string
  starts_on: string
  ends_on: string
  eligibility_status?: string | null
  is_eligible?: boolean
  can_submit?: boolean
}

type Submission = {
  id: number
  period_id: number | null
  period_label: string | null
  document_type: string
  file_url: string | null
  uploaded_at: string | null
  notes: string | null
  evaluation: {
    gpa: number | null
    status: string
    remarks: string | null
    evaluated_at: string | null
  } | null
}

const props = defineProps<{
  student: { id: number; name: string; student_id_number: string | null } | null
  openPeriods: Period[]
  submissions: Submission[]
  selectedPeriodId?: number
}>()

const initialPeriodId = props.selectedPeriodId && props.openPeriods.some((p) => p.id === props.selectedPeriodId)
  ? props.selectedPeriodId
  : props.openPeriods?.[0]?.id
const academicPeriodId = ref<number | null>(initialPeriodId ?? null)
const documentType = ref<'grade_report' | 'supporting_document'>('grade_report')
const notes = ref('')
const file = ref<File | null>(null)
const submitError = ref('')
const isSubmitting = ref(false)
const uploadProgress = ref(0)

const selectedPeriod = computed(() => props.openPeriods.find((p) => p.id === academicPeriodId.value) ?? null)
const eligibilityLabel = computed(() => {
  const status = String(selectedPeriod.value?.eligibility_status ?? '').trim().toLowerCase()
  if (!status) return null
  if (status === 'eligible') return 'Eligible'
  if (status === 'pending_review') return 'Pending Review'
  if (status === 'ineligible') return 'Ineligible'
  return status.replace(/\b\w/g, (c) => c.toUpperCase())
})
const canSubmit = computed(() => {
  if (!selectedPeriod.value) return true
  if (selectedPeriod.value.is_eligible) return false
  if (selectedPeriod.value.can_submit === false) return false
  return true
})

function termLabel(termCode: string) {
  if (termCode === '1st_sem') return '1st Sem'
  if (termCode === '2nd_sem') return '2nd Sem'
  if (termCode === 'summer') return 'Summer'
  return termCode
}

function handlePrimeFileSelect(files: File[] | undefined) {
  file.value = files?.[0] ?? null
}

function submit() {
  if (isSubmitting.value) return
  submitError.value = ''

  if (!academicPeriodId.value) {
    submitError.value = 'Please select an academic period.'
    return
  }
  if (!canSubmit.value) {
    submitError.value = 'You are already eligible for this period. Further submissions are locked.'
    return
  }
  if (!file.value) {
    submitError.value = 'Please attach your semestral grade document.'
    return
  }

  const fd = new FormData()
  fd.append('academic_period_id', String(academicPeriodId.value))
  fd.append('document_type', documentType.value)
  fd.append('notes', notes.value)
  fd.append('document_file', file.value)

  router.post('/AcademicSubmissions', fd, {
    forceFormData: true,
    onStart: () => {
      isSubmitting.value = true
      uploadProgress.value = 0
    },
    onProgress: (event) => {
      uploadProgress.value = Math.round(event?.percentage ?? 0)
    },
    onFinish: () => {
      isSubmitting.value = false
      uploadProgress.value = 0
    },
    onError: (errors) => {
      const firstError = Object.values(errors || {})[0]
      submitError.value = Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Submission failed.')
    },
  })
}
</script>

<template>
  <Head title="Submit Semester Grades" />

  <div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <Link
          href="/AcademicSubmissions"
          class="inline-flex items-center rounded-full bg-[#034485] px-4 py-1 text-xs font-semibold text-white hover:bg-[#033a70]"
        >
          Back
        </Link>
      </div>
    </div>

    <section class="page-card rounded-3xl border border-[#034485]/35 bg-white p-5">
      <form @submit.prevent="submit">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="rounded-full bg-[#034485] px-3 py-1 text-xs font-semibold text-white">Submission Form</span>
        </div>
            <Message v-if="submitError" severity="error" size="small" class="mt-3">
              {{ submitError }}
            </Message>
        <div class="mt-4 grid gap-4 lg:grid-cols-[1.1fr,0.9fr]">
          <div class="space-y-3">
            <div
              v-if="eligibilityLabel"
              class="rounded-xl border border-[#034485]/25 bg-[#034485]/5 px-3 py-2 text-xs text-slate-700"
            >
              Status: <span class="font-semibold">{{ eligibilityLabel }}</span>
              <span v-if="!canSubmit">: submissions locked for this period.</span>
            </div>
            <p class="text-xs text-slate-500">Choose the academic period and upload your latest grade document.</p>
            <p class="text-xs text-slate-500">
              Grade reports are automatically scanned to extract the academic result from the document.
            </p>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
              <Select
                v-model="academicPeriodId"
                :options="openPeriods.map((p) => ({ label: `${p.school_year} - ${termLabel(p.term)}`, value: p.id }))"
                optionLabel="label"
                optionValue="value"
                placeholder="Select period"
                class="w-full"
              />
              <Select
                v-model="documentType"
                :options="[
                  { label: 'Grade Report', value: 'grade_report' },
                  { label: 'Supporting Document', value: 'supporting_document' },
                ]"
                optionLabel="label"
                optionValue="value"
                :disabled="!canSubmit"
                class="w-full"
              />
            </div>
            <Textarea
              v-model="notes"
              rows="2"
              placeholder="Notes (optional)"
              :disabled="!canSubmit"
              autoResize
              class="w-full"
            />
            <FileUpload
              mode="basic"
              customUpload
              chooseLabel="Choose Academic File"
              accept=".pdf,image/*"
              @select="(event) => handlePrimeFileSelect(event.files)"
              :disabled="!canSubmit"
              class="w-full"
            />
            <p v-if="file" class="text-xs text-slate-500">Selected: {{ file.name }}</p>
            <button
              type="submit"
              :disabled="isSubmitting || !canSubmit"
              class="px-4 py-2 rounded-lg bg-[#034485] text-white hover:bg-[#033a70] disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span class="inline-flex items-center gap-2">
                <Spinner v-if="isSubmitting" class="h-4 w-4 text-white" />
                {{ isSubmitting ? 'Submitting...' : 'Finish Submission' }}
              </span>
            </button>
            <div v-if="isSubmitting" class="space-y-1">
              <div class="flex justify-between text-xs text-slate-500">
                <span>Uploading file...</span>
                <span>{{ uploadProgress }}%</span>
              </div>
              <div class="h-2 rounded bg-[#034485]/10 overflow-hidden">
                <div class="h-full bg-[#034485] transition-all duration-150" :style="{ width: `${uploadProgress}%` }" />
              </div>
            </div>
          </div>
          <aside class="page-card rounded-2xl border border-[#034485]/25 bg-[#034485]/5 p-4 text-xs text-slate-700">
            <div class="text-[11px] font-semibold text-[#034485]">Selected Period</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">
              {{ selectedPeriod ? `${selectedPeriod.school_year} - ${termLabel(selectedPeriod.term)}` : 'Select a period' }}
            </div>
            <div class="mt-1 text-[11px] text-slate-600">
              Window:
              {{ selectedPeriod ? `${selectedPeriod.starts_on} to ${selectedPeriod.ends_on}` : 'Not available' }}
            </div>
            <div class="mt-3 text-[11px] text-slate-500">Accepted: PDF, PNG, JPG.</div>
          </aside>
        </div>
      </form>
    </section>
  </div>
</template>

<style scoped>
.page-card {
  opacity: 0;
  animation: student-form-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

@keyframes student-form-card-rise {
  from {
    opacity: 0;
    transform: translateY(16px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .page-card {
    animation: none;
    opacity: 1;
  }
}
</style>
