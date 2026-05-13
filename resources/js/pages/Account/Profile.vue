<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import DatePicker from 'primevue/datepicker'
import FileUpload from 'primevue/fileupload'
import InputMask from 'primevue/inputmask'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, onBeforeUnmount, ref } from 'vue'

import AccountShell from '@/components/Account/AccountShell.vue'
import { showAppToast } from '@/composables/useAppToast'
import { useTheme } from '@/composables/useTheme'
import { normalizeWorkspaceRole, resolveAccountLayout } from '@/pages/Account/accountRole'
import { resolveUserAvatarUrl } from '@/utils/media'

defineOptions({
  layout: (h: any, page: any) => h(resolveAccountLayout(page), [page]),
})

const props = defineProps<{
  profile: {
    admin: {
      role: string
      status: string
      capabilities: string[]
    } | null
    student: {
      student_id_number: string | null
      first_name: string | null
      middle_name: string | null
      last_name: string | null
      date_of_birth: string | null
      gender: string | null
      phone_number: string | null
      home_address: string | null
      course_or_strand: string | null
      academic_level_label: string | null
      student_status: string | null
      approval_status: string | null
      emergency_contact_name: string | null
      emergency_contact_relationship: string | null
      emergency_contact_phone: string | null
      height: string | number | null
      weight: string | number | null
    } | null
    coach: {
      phone_number: string | null
      home_address: string | null
      date_of_birth: string | null
      gender: string | null
    } | null
  }
}>()

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const role = computed(() => normalizeWorkspaceRole(user.value?.role))
const { isDarkMode } = useTheme()

const form = useForm({
  name: String(user.value?.name ?? ''),
  avatar: null as File | null,
  phone_number: props.profile.student?.phone_number ?? props.profile.coach?.phone_number ?? '',
  home_address: props.profile.student?.home_address ?? props.profile.coach?.home_address ?? '',
  emergency_contact_name: props.profile.student?.emergency_contact_name ?? '',
  emergency_contact_relationship: props.profile.student?.emergency_contact_relationship ?? '',
  emergency_contact_phone: props.profile.student?.emergency_contact_phone ?? '',
  date_of_birth: props.profile.coach?.date_of_birth ?? '',
  gender: props.profile.coach?.gender ?? '',
})

const emergencyRelationshipOptions = ['Parent', 'Guardian', 'Sibling', 'Grandparent', 'Relative', 'Spouse', 'Other']
const genderOptions = ['Male', 'Female', 'Other']

const saved = ref(false)
const avatarPreview = ref<string | null>(null)

const cropModalOpen = ref(false)
const cropSourceUrl = ref<string | null>(null)
const cropImageEl = ref<HTMLImageElement | null>(null)
const cropFrameEl = ref<HTMLDivElement | null>(null)
const cropScale = ref(1)
const cropMinScale = ref(1)
const cropX = ref(0)
const cropY = ref(0)
const cropError = ref('')

let dragActive = false
let dragStartX = 0
let dragStartY = 0
let dragOriginX = 0
let dragOriginY = 0
let savedTimer: ReturnType<typeof setTimeout> | null = null

const maxCropScale = computed(() => Math.max(cropMinScale.value * 4, cropMinScale.value + 1))

const cropImageStyle = computed(() => ({
  transform: `translate(calc(-50% + ${cropX.value}px), calc(-50% + ${cropY.value}px)) scale(${cropScale.value})`,
}))

const avatarUrl = computed(() => {
  if (avatarPreview.value) return avatarPreview.value
  return resolveUserAvatarUrl(String(user.value?.avatar_url ?? user.value?.avatar ?? ''))
})

const roleLabel = computed(() => {
  if (role.value === 'student') return 'Student-Athlete'
  if (role.value === 'coach') return 'Coach'
  if (role.value === 'admin') return 'Administrator'
  return role.value || 'User'
})

function parseDateString(value: string | null | undefined): Date | null {
  if (!value) return null
  const [year, month, day] = value.split('-').map(Number)
  if (!year || !month || !day) return null
  return new Date(year, month - 1, day)
}

function formatDateString(value: Date | null): string {
  if (!value) return ''
  const year = value.getFullYear()
  const month = String(value.getMonth() + 1).padStart(2, '0')
  const day = String(value.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const today = new Date()
const dateOfBirthModel = computed<Date | null>({
  get: () => parseDateString(form.date_of_birth),
  set: (value) => {
    form.date_of_birth = formatDateString(value)
  },
})

function displayValue(value: string | number | null | undefined) {
  if (value === null || value === undefined) return 'N/A'
  const text = String(value).trim()
  return text.length ? text : 'N/A'
}

const studentDetails = computed(() => {
  const student = props.profile.student
  if (!student) return []
  const recordName = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ').trim()
  return [
    { label: 'Student Record Name', value: recordName || null },
    { label: 'Student ID', value: student.student_id_number },
    { label: 'Date of Birth', value: student.date_of_birth },
    { label: 'Gender', value: student.gender },
    { label: 'Course/Strand', value: student.course_or_strand },
    { label: 'Academic Level', value: student.academic_level_label },
    { label: 'Enrollment Status', value: student.student_status },
    { label: 'Approval Status', value: student.approval_status },
    { label: 'Height', value: student.height },
    { label: 'Weight', value: student.weight },
  ]
})

function clearSavedTimer() {
  if (!savedTimer) return
  clearTimeout(savedTimer)
  savedTimer = null
}

function firstErrorMessage() {
  const errorEntry = Object.values(form.errors).find((value) => typeof value === 'string' && value.trim().length > 0)

  return errorEntry ?? 'Unable to update profile.'
}

function cardMotion(order: number) {
  return { '--card-order': String(order) }
}


function revokeUrl(value: string | null) {
  if (value?.startsWith('blob:')) {
    URL.revokeObjectURL(value)
  }
}

function resetCropState() {
  cropScale.value = 1
  cropMinScale.value = 1
  cropX.value = 0
  cropY.value = 0
  cropError.value = ''
}

function closeCropModal() {
  cropModalOpen.value = false
  cropImageEl.value = null
  revokeUrl(cropSourceUrl.value)
  cropSourceUrl.value = null
  resetCropState()
  removeDragListeners()
}

function onPrimeAvatarSelect(files: File[] | undefined) {
  const file = files?.[0] ?? null

  if (!file) return

  if (!file.type.startsWith('image/')) {
    form.setError('avatar', 'Please select a valid image file.')
    return
  }

  form.clearErrors('avatar')
  resetCropState()
  revokeUrl(cropSourceUrl.value)
  cropSourceUrl.value = URL.createObjectURL(file)
  cropModalOpen.value = true
}

function onCropImageLoad() {
  if (!cropImageEl.value || !cropFrameEl.value) return

  const image = cropImageEl.value
  const frame = cropFrameEl.value
  const frameSize = Math.max(frame.clientWidth, 1)
  const width = Math.max(image.naturalWidth, 1)
  const height = Math.max(image.naturalHeight, 1)
  const minScale = Math.max(frameSize / width, frameSize / height)

  cropMinScale.value = minScale
  cropScale.value = minScale
  cropX.value = 0
  cropY.value = 0
}

function beginCropDrag(event: PointerEvent) {
  if (!cropModalOpen.value) return

  dragActive = true
  dragStartX = event.clientX
  dragStartY = event.clientY
  dragOriginX = cropX.value
  dragOriginY = cropY.value

  const target = event.currentTarget as HTMLElement | null
  target?.setPointerCapture?.(event.pointerId)
  window.addEventListener('pointermove', onCropDrag)
  window.addEventListener('pointerup', endCropDrag)
  window.addEventListener('pointercancel', endCropDrag)
}

function onCropDrag(event: PointerEvent) {
  if (!dragActive) return
  cropX.value = dragOriginX + (event.clientX - dragStartX)
  cropY.value = dragOriginY + (event.clientY - dragStartY)
}

function endCropDrag() {
  dragActive = false
  removeDragListeners()
}

function removeDragListeners() {
  window.removeEventListener('pointermove', onCropDrag)
  window.removeEventListener('pointerup', endCropDrag)
  window.removeEventListener('pointercancel', endCropDrag)
}

function adjustCropZoom(delta: number) {
  const nextScale = cropScale.value + delta
  cropScale.value = Math.min(maxCropScale.value, Math.max(cropMinScale.value, nextScale))
}

function onCropWheel(event: WheelEvent) {
  const zoomDelta = event.deltaY < 0 ? 0.05 : -0.05
  adjustCropZoom(zoomDelta)
}

async function applyCroppedAvatar() {
  if (!cropImageEl.value || !cropFrameEl.value) {
    cropError.value = 'Unable to prepare image crop.'
    return
  }

  const image = cropImageEl.value
  const frameSize = Math.max(cropFrameEl.value.clientWidth, 1)
  const outputSize = 512
  const ratio = outputSize / frameSize

  const canvas = document.createElement('canvas')
  canvas.width = outputSize
  canvas.height = outputSize

  const ctx = canvas.getContext('2d')
  if (!ctx) {
    cropError.value = 'Canvas is not available in this browser.'
    return
  }

  ctx.clearRect(0, 0, outputSize, outputSize)
  ctx.save()
  ctx.translate(outputSize / 2 + cropX.value * ratio, outputSize / 2 + cropY.value * ratio)
  ctx.scale(cropScale.value * ratio, cropScale.value * ratio)
  ctx.drawImage(image, -image.naturalWidth / 2, -image.naturalHeight / 2)
  ctx.restore()

  const blob = await new Promise<Blob | null>((resolve) => {
    canvas.toBlob(resolve, 'image/jpeg', 0.92)
  })

  if (!blob) {
    cropError.value = 'Failed to create cropped image.'
    return
  }

  const file = new File([blob], `avatar-${Date.now()}.jpg`, { type: 'image/jpeg' })

  revokeUrl(avatarPreview.value)
  avatarPreview.value = URL.createObjectURL(file)
  form.avatar = file
  form.clearErrors('avatar')

  closeCropModal()
  submit()
}

function submit() {
  saved.value = false
  clearSavedTimer()
  form.put('/account/profile', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      saved.value = true
      showAppToast('Profile updated successfully.', 'success')
      savedTimer = setTimeout(() => {
        saved.value = false
        savedTimer = null
      }, 2200)
    },
    onError: () => {
      showAppToast(firstErrorMessage(), 'error')
    },
  })
}

onBeforeUnmount(() => {
  revokeUrl(avatarPreview.value)
  revokeUrl(cropSourceUrl.value)
  removeDragListeners()
  clearSavedTimer()
})
</script>

<template>
  <Head title="My Profile" />

  <AccountShell active="profile">
    <form @submit.prevent="submit" class="space-y-5">
      <section
        v-if="role === 'student'"
        class="account-card rounded-[24px] border border-[#034485]/35 bg-[#034485] p-5 text-white shadow-[0_24px_50px_-38px_rgba(3,68,133,0.38)]"
        :style="cardMotion(1)"
      >
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/80">Student profile</p>
        <h1 class="mt-2 text-2xl font-bold text-white">{{ user?.name || 'My Profile' }}</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-white/85">
          Keep your personal and emergency details accurate while your official student record remains available below for reference.
        </p>
      </section>

      <section
        class="account-card rounded-[24px] border p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.45)]"
        :class="isDarkMode ? 'border-slate-700 bg-[#111827]' : 'border-[#034485]/16 bg-white'"
        :style="cardMotion(2)"
      >
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_260px] lg:items-center">
          <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Profile</p>
            <h1 class="mt-1 text-2xl font-bold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">{{ user?.name || 'My Profile' }}</h1>
            <p class="mt-2 max-w-2xl text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">Update your direct-contact and emergency information here. Official student record values are shown below for reference.</p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
              <span class="rounded-full border border-[#034485]/15 bg-[#034485]/5 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-[#034485]">
                {{ roleLabel }}
              </span>
              <span
                v-if="profile.student?.student_id_number"
                class="rounded-full border px-3 py-1 text-xs font-semibold"
                :class="isDarkMode ? 'border-slate-600 bg-slate-800 text-slate-200' : 'border-slate-200 bg-slate-50 text-slate-600'"
              >
                ID {{ profile.student.student_id_number }}
              </span>
            </div>
          </div>

          <div
            class="rounded-[22px] border p-4 transition-colors"
            :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-slate-50/80'"
          >
            <div class="flex items-center gap-4">
              <img
                :src="avatarUrl"
                alt="Avatar"
                class="h-20 w-20 rounded-[20px] border object-cover shadow-sm"
                :class="isDarkMode ? 'border-slate-600 bg-slate-800' : 'border-slate-200 bg-white'"
              />
              <div class="min-w-0">
                <p class="text-sm font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Profile Photo</p>
                <p class="mt-1 text-xs leading-5" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">JPG, PNG, or WebP up to 2MB. Crop before saving for the best fit.</p>
                <FileUpload
                  mode="basic"
                  customUpload
                  chooseLabel="Choose Photo"
                  accept="image/png,image/jpeg,image/webp"
                  class="mt-3"
                  @select="(event) => onPrimeAvatarSelect(event.files)"
                />
              </div>
            </div>
            <Message v-if="form.errors.avatar" severity="error" size="small" variant="simple" class="mt-3">
              {{ form.errors.avatar }}
            </Message>
          </div>
        </div>
      </section>

      <div class="space-y-5">
          <section
            class="account-card rounded-[24px] border p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.45)]"
            :class="isDarkMode ? 'border-slate-700 bg-[#111827]' : 'border-[#034485]/16 bg-white'"
            :style="cardMotion(3)"
          >
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Editable Information</p>
                <h2 class="mt-1 text-xl font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Personal Details</h2>
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full Name</label>
                <InputText v-model="form.name" class="mt-1 w-full" required />
                <Message v-if="form.errors.name" severity="error" size="small" variant="simple" class="mt-1">{{ form.errors.name }}</Message>
              </div>

              <template v-if="role !== 'admin'">
                <div>
                  <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</label>
                  <InputMask v-model="form.phone_number" mask="0999-999-9999" class="mt-1 w-full" />
                  <Message v-if="form.errors.phone_number" severity="error" size="small" variant="simple" class="mt-1">{{ form.errors.phone_number }}</Message>
                </div>
                <div>
                  <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</label>
                  <InputText v-model="form.home_address" class="mt-1 w-full" />
                  <Message v-if="form.errors.home_address" severity="error" size="small" variant="simple" class="mt-1">{{ form.errors.home_address }}</Message>
                </div>
              </template>

              <template v-if="role === 'student'">
                <div>
                  <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Emergency Contact Name</label>
                  <InputText v-model="form.emergency_contact_name" class="mt-1 w-full" />
                  <Message v-if="form.errors.emergency_contact_name" severity="error" size="small" variant="simple" class="mt-1">{{ form.errors.emergency_contact_name }}</Message>
                </div>
                <div>
                  <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Relationship</label>
                  <Select v-model="form.emergency_contact_relationship" :options="emergencyRelationshipOptions" placeholder="Select relationship" class="mt-1 w-full" />
                  <Message v-if="form.errors.emergency_contact_relationship" severity="error" size="small" variant="simple" class="mt-1">{{ form.errors.emergency_contact_relationship }}</Message>
                </div>
                <div class="md:col-span-2">
                  <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Emergency Contact Phone</label>
                  <InputMask v-model="form.emergency_contact_phone" mask="0999-999-9999" class="mt-1 w-full" />
                  <Message v-if="form.errors.emergency_contact_phone" severity="error" size="small" variant="simple" class="mt-1">{{ form.errors.emergency_contact_phone }}</Message>
                </div>
              </template>

              <template v-if="role === 'coach'">
                <div>
                  <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date of Birth</label>
                  <DatePicker
                    v-model="dateOfBirthModel"
                    showIcon
                    iconDisplay="input"
                    inputClass="w-full"
                    :maxDate="today"
                    panelClass="text-sm"
                    placeholder="Select date of birth"
                    dateFormat="yy-mm-dd"
                    :manualInput="false"
                  />
                  <Message v-if="form.errors.date_of_birth" severity="error" size="small" variant="simple" class="mt-1">{{ form.errors.date_of_birth }}</Message>
                </div>
                <div>
                  <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gender</label>
                  <Select v-model="form.gender" :options="genderOptions" placeholder="Select gender" class="mt-1 w-full" />
                  <Message v-if="form.errors.gender" severity="error" size="small" variant="simple" class="mt-1">{{ form.errors.gender }}</Message>
                </div>
              </template>
            </div>
          </section>

          <div class="flex flex-wrap items-center gap-3" :style="cardMotion(4)">
            <button type="submit" class="rounded-full bg-[#1f2937] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#334155]" :disabled="form.processing">
              {{ form.processing ? 'Saving...' : 'Save Profile' }}
            </button>
          </div>
      </div>

      <section
        v-if="role === 'student' && profile.student"
        class="account-card rounded-[24px] border p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.45)]"
        :class="isDarkMode ? 'border-slate-700 bg-[#111827]' : 'border-[#034485]/16 bg-white'"
        :style="cardMotion(5)"
      >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :class="isDarkMode ? 'text-sky-300' : 'text-[#034485]'">Read-Only Student Record</p>
            <h2 class="mt-1 text-xl font-semibold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Institutional Details</h2>
            <p class="mt-1 max-w-3xl text-sm" :class="isDarkMode ? 'text-slate-300' : 'text-slate-500'">These values come from your official student-athlete record and are shown here for quick reference.</p>
          </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <div
            v-for="item in studentDetails"
            :key="item.label"
            class="rounded-2xl border p-4"
            :class="isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-slate-50/75'"
          >
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em]" :class="isDarkMode ? 'text-slate-400' : 'text-slate-500'">{{ item.label }}</p>
            <p class="mt-2 text-sm font-semibold leading-6" :class="isDarkMode ? 'text-slate-100' : 'text-slate-900'">{{ displayValue(item.value) }}</p>
          </div>
        </div>
      </section>
    </form>

    <div v-if="cropModalOpen" class="crop-overlay">
      <div class="crop-modal">
        <div class="crop-header">
          <h3 class="text-base font-semibold text-slate-900">Crop Profile Photo</h3>
          <p class="text-xs text-slate-500">Drag to reposition. Use zoom for better framing.</p>
        </div>

        <div ref="cropFrameEl" class="crop-frame" @pointerdown.prevent="beginCropDrag" @wheel.prevent="onCropWheel">
          <img v-if="cropSourceUrl" ref="cropImageEl" :src="cropSourceUrl" alt="Crop preview" class="crop-image" :style="cropImageStyle" @load="onCropImageLoad" />
          <div class="crop-frame-ring" />
        </div>

        <div class="mt-3 space-y-2">
          <div class="flex items-center gap-2">
            <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs" @click="adjustCropZoom(-0.1)">-</button>
            <input
              :value="cropScale"
              type="range"
              class="w-full"
              :min="cropMinScale"
              :max="maxCropScale"
              step="0.01"
              @input="cropScale = Number(($event.target as HTMLInputElement).value)"
            />
            <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs" @click="adjustCropZoom(0.1)">+</button>
          </div>
          <p v-if="cropError" class="text-xs text-red-600">{{ cropError }}</p>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeCropModal">
            Cancel
          </button>
          <button type="button" class="rounded-md bg-[#1f2937] px-3 py-1.5 text-sm font-semibold text-white hover:bg-[#334155]" @click="applyCroppedAvatar">
            Use Cropped Photo
          </button>
        </div>
      </div>
    </div>
  </AccountShell>
</template>

<style scoped>
:deep(.p-fileupload-basic .p-button) {
  background: #034485 !important;
  border-color: #034485 !important;
  color: #ffffff !important;
  border-radius: 0.9rem !important;
  padding: 0.65rem 1rem !important;
  font-weight: 600 !important;
}

:deep(.p-fileupload-basic .p-button:hover) {
  background: #02376b !important;
  border-color: #02376b !important;
  color: #ffffff !important;
}
</style>

<style scoped>
.crop-overlay {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.7);
  padding: 1rem;
}

.crop-modal {
  width: min(96vw, 480px);
  max-height: 94vh;
  overflow: auto;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  padding: 1rem;
  box-shadow: 0 14px 40px rgba(15, 23, 42, 0.24);
}

.crop-header {
  margin-bottom: 0.75rem;
}

.crop-frame {
  position: relative;
  width: min(80vw, 320px);
  height: min(80vw, 320px);
  max-width: 320px;
  max-height: 320px;
  margin: 0 auto;
  border-radius: 999px;
  overflow: hidden;
  background: #e2e8f0;
  touch-action: none;
  user-select: none;
  cursor: grab;
}

.crop-frame:active {
  cursor: grabbing;
}

.crop-image {
  position: absolute;
  top: 50%;
  left: 50%;
  transform-origin: center center;
  max-width: none;
  max-height: none;
  will-change: transform;
}

.crop-frame-ring {
  position: absolute;
  inset: 0;
  border:  1px solid rgba(255, 255, 255, 0.95);
  border-radius: 999px;
  pointer-events: none;
  box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.2);
}

.account-card {
  opacity: 0;
  transform: translateY(18px) scale(0.985);
  animation: account-card-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
  animation-delay: calc(var(--card-order, 0) * 70ms);
  will-change: transform, opacity;
}

@keyframes account-card-rise {
  from {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .account-card {
    animation: none;
    opacity: 1;
    transform: none;
  }
}

</style>
