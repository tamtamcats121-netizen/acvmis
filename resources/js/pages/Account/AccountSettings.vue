<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Password from 'primevue/password';
import { computed } from 'vue';

import AccountShell from '@/components/Account/AccountShell.vue';
import { showAppToast } from '@/composables/useAppToast';
import { normalizeWorkspaceRole, resolveAccountLayout } from '@/pages/Account/accountRole';

defineOptions({
    layout: (h: any, page: any) => h(resolveAccountLayout(page), [page]),
});

const passwordForm = useForm({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
});

const page = usePage();
const currentEmail = computed(() => String(page.props.auth?.user?.email ?? ''));
const mustChangePassword = computed(() => Boolean(page.props.auth?.user?.must_change_password));
const role = computed(() => normalizeWorkspaceRole((page.props as any)?.auth?.user?.role));
const props = defineProps<{
    verification?: {
        required?: boolean;
        email?: string;
        status?: 'verified' | 'not_verified';
        verified_at?: string | null;
        settingsHref?: string;
        send_verification_route?: string;
    } | null;
}>();

const emailForm = useForm({
    email: currentEmail.value,
});

const deleteForm = useForm({});
const verificationForm = useForm({});
const isVerified = computed(() => props.verification?.status === 'verified');
const verificationEmail = computed(() => String(props.verification?.email ?? currentEmail.value));
const verificationRoute = computed(() => String(props.verification?.send_verification_route ?? '/email/verification-notification'));
const verifiedAtLabel = computed(() => {
    if (!props.verification?.verified_at) return '';
    const date = new Date(props.verification.verified_at);
    if (Number.isNaN(date.getTime())) return String(props.verification.verified_at);

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
});

function cardMotion(order: number) {
    return { '--card-order': String(order) };
}

function submitPassword() {
    passwordForm.put('/account/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
        onError: (errors) => {
            const firstError = Object.values(errors || {})[0];
            showAppToast(Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Unable to update password.'), 'error');
        },
    });
}

function submitEmail() {
    emailForm.put('/account/account-settings', {
        preserveScroll: true,
        onError: (errors) => {
            const firstError = Object.values(errors || {})[0];
            showAppToast(Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Unable to update email.'), 'error');
        },
    });
}

function sendVerificationEmail() {
    verificationForm.post(verificationRoute.value, {
        preserveScroll: true,
        onError: (errors) => {
            const firstError = Object.values(errors || {})[0];
            showAppToast(Array.isArray(firstError) ? String(firstError[0]) : String(firstError || 'Unable to send verification email.'), 'error');
        },
    });
}

function confirmDelete() {
    if (!window.confirm('Delete your account? This will deactivate access immediately.')) return;
    deleteForm.delete('/account/delete');
}
</script>

<template>
    <Head title="Account Settings" />

    <AccountShell active="account">
        <section
            v-if="role === 'student'"
            class="account-card mb-4 rounded-2xl border border-[#034485]/35 bg-[#034485] p-5 text-white"
            :style="cardMotion(1)"
        >
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Student account</p>
            <h1 class="mt-2 text-2xl font-bold text-white">Account Settings</h1>
            <p class="mt-2 text-sm leading-6 text-white/85">Update your email access and password securely without leaving the student workspace.</p>
        </section>

        <div v-if="mustChangePassword" class="account-card rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800" :style="cardMotion(1)">
            Password update required. Set a new password to continue using AC-VMIS.
        </div>

        <section
            v-if="!mustChangePassword"
            class="account-card mb-4 rounded-2xl border"
            :class="isVerified ? 'border-[#034485]/25 bg-[#034485]/5' : 'border-amber-200 bg-amber-50/90'"
            :style="cardMotion(1)"
        >
            <div v-if="isVerified" class="mb-4 flex flex-col gap-3 rounded-2xl px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#034485] text-white">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Verified account</p>
                        <p class="text-xs text-slate-500">
                            {{ verificationEmail }}
                            <span v-if="verifiedAtLabel">• Verified on {{ verifiedAtLabel }}</span>
                        </p>
                    </div>
                </div>
                <span class="inline-flex w-fit items-center rounded-full border border-[#034485]/20 bg-white px-3 py-1 text-xs font-semibold text-[#034485]">
                    Verified
                </span>
            </div>

            <div v-else class="mb-4 flex flex-col gap-4 p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M12 9v4" />
                                <path d="M12 17h.01" />
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-amber-900">Verification required</p>
                            <p class="text-xs text-amber-800">
                                {{ verificationEmail }}
                                <span class="hidden sm:inline">•</span>
                                <span class="sm:ml-1">Not verified</span>
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-semibold text-amber-700">
                        Not Verified
                    </span>
                </div>

                <p class="text-sm leading-6 text-amber-800">
                    Please verify your email address to secure your account and complete your profile setup.
                </p>

                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <button
                        type="button"
                        class="w-full rounded-lg bg-amber-500 px-4 py-2 font-semibold text-white transition hover:bg-amber-600 sm:w-auto"
                        :disabled="verificationForm.processing"
                        @click="sendVerificationEmail"
                    >
                        {{ verificationForm.processing ? 'Sending...' : 'Send Verification Email' }}
                    </button>
                </div>
            </div>
        </section>

        <form id="settings-account" @submit.prevent="submitPassword" class="account-card space-y-3 rounded-2xl border border-[#034485]/40 bg-white p-5" :style="cardMotion(mustChangePassword ? 2 : 2)">
            <h2 class="section-title">
                <svg class="h-4 w-4 text-[#1f2937]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2" />
                    <path d="M7 11V8a5 5 0 1 1 10 0v3" />
                </svg>
                Account Settings
            </h2>
            <p class="settings-muted text-xs text-slate-500">Change password &amp; review two-factor authentication.</p>

            <div v-if="!mustChangePassword">
                <label class="settings-label text-sm text-slate-500">Current Password</label>
                <Password
                    v-model="passwordForm.current_password"
                    toggleMask
                    :feedback="false"
                    inputClass="mt-1 w-full"
                    :invalid="!!passwordForm.errors.current_password"
                    class="w-full"
                />
                <Message v-if="passwordForm.errors.current_password" severity="error" size="small" variant="simple" class="mt-1">
                    {{ passwordForm.errors.current_password }}
                </Message>
            </div>

            <div>
                <label class="settings-label text-sm text-slate-500">New Password</label>
                <Password
                    v-model="passwordForm.new_password"
                    toggleMask
                    :feedback="false"
                    inputClass="mt-1 w-full"
                    :invalid="!!passwordForm.errors.new_password"
                    class="w-full"
                />
                <Message v-if="passwordForm.errors.new_password" severity="error" size="small" variant="simple" class="mt-1">
                    {{ passwordForm.errors.new_password }}
                </Message>
            </div>

            <div>
                <label class="settings-label text-sm text-slate-500">Confirm New Password</label>
                <Password
                    v-model="passwordForm.new_password_confirmation"
                    toggleMask
                    :feedback="false"
                    inputClass="mt-1 w-full"
                    :invalid="!!passwordForm.errors.new_password_confirmation"
                    class="w-full"
                />
                <Message v-if="passwordForm.errors.new_password_confirmation" severity="error" size="small" variant="simple" class="mt-1">
                    {{ passwordForm.errors.new_password_confirmation }}
                </Message>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <button
                    type="submit"
                    class="w-full rounded-lg bg-slate-800 px-4 py-2 font-semibold text-white transition hover:bg-slate-900 sm:w-auto"
                    :disabled="passwordForm.processing"
                >
                    {{ passwordForm.processing ? 'Updating...' : 'Update Password' }}
                </button>
            </div>

            <div class="rounded-xl border border-[#034485]/30 bg-slate-50 p-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Two-Factor Authentication</p>
                        <p class="text-xs text-slate-500">Static placeholder for upcoming 2FA configuration.</p>
                    </div>
                    <span class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-500">Coming Soon</span>
                </div>
            </div>
        </form>

        <form
            v-if="!mustChangePassword"
            id="settings-email"
            @submit.prevent="submitEmail"
            class="account-card mt-4 space-y-3 rounded-2xl border border-[#034485]/40 bg-white p-5"
            :style="cardMotion(3)"
        >
            <h2 class="section-title">Account Email</h2>
            <p class="settings-muted text-xs text-slate-500">Update the email address tied to your account. Changing it will require a new verification.</p>
            <div>
                <label class="settings-label text-sm text-slate-500">Email Address</label>
                <InputText v-model="emailForm.email" type="email" class="mt-1 w-full" />
                <Message v-if="emailForm.errors.email" severity="error" size="small" variant="simple" class="mt-1">
                    {{ emailForm.errors.email }}
                </Message>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <button
                    type="submit"
                    class="w-full rounded-lg bg-slate-800 px-4 py-2 font-semibold text-white transition hover:bg-slate-900 sm:w-auto"
                    :disabled="emailForm.processing"
                >
                    {{ emailForm.processing ? 'Saving...' : 'Update Email' }}
                </button>
            </div>
        </form>

        <section v-if="!mustChangePassword" class="account-card mt-4 space-y-3 rounded-2xl border border-red-200 bg-red-50 p-5" :style="cardMotion(4)">
            <h2 class="section-title text-red-700">Delete Account</h2>
            <p class="text-xs text-red-700">This action will deactivate your access immediately.</p>
            <button
                type="button"
                class="w-full rounded-lg bg-red-600 px-4 py-2 font-semibold text-white transition hover:bg-red-700 sm:w-auto"
                @click="confirmDelete"
                :disabled="deleteForm.processing"
            >
                {{ deleteForm.processing ? 'Processing...' : 'Delete Account' }}
            </button>
        </section>
    </AccountShell>
</template>

<style scoped>
.section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
    font-weight: 600;
}

.settings-muted,
.settings-label {
    color: #64748b;
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
