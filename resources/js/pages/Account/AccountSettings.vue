<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const page = usePage();
const currentEmail = computed(() => String(page.props.auth?.user?.email ?? ''));
const mustChangePassword = computed(() => Boolean(page.props.auth?.user?.must_change_password));
const role = computed(() => normalizeWorkspaceRole((page.props as any)?.auth?.user?.role));

const emailForm = useForm({
    email: currentEmail.value,
});

const deleteForm = useForm({});

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

        <form id="settings-account" @submit.prevent="submitPassword" class="account-card space-y-3 rounded-2xl border border-[#034485]/40 bg-white p-5" :style="cardMotion(mustChangePassword ? 2 : 1)">
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
                <div class="relative">
                    <input
                        v-model="passwordForm.current_password"
                        :type="showCurrentPassword ? 'text' : 'password'"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 pr-10"
                    />
                    <button
                        type="button"
                        class="settings-icon absolute top-1/2 right-3 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                        :aria-label="showCurrentPassword ? 'Hide password' : 'Show password'"
                        @click="showCurrentPassword = !showCurrentPassword"
                    >
                        <svg
                            v-if="showCurrentPassword"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M3 3l18 18" />
                            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                            <path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c5 0 9.3 3.1 11 7-0.6 1.4-1.6 2.8-2.8 3.9" />
                            <path d="M6.7 6.7C4.7 8.1 3.3 10 2 12c1.6 3.9 6 7 10 7 1.4 0 2.8-0.3 4.1-0.9" />
                        </svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </div>
                <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.current_password }}</p>
            </div>

            <div>
                <label class="settings-label text-sm text-slate-500">New Password</label>
                <div class="relative">
                    <input
                        v-model="passwordForm.new_password"
                        :type="showNewPassword ? 'text' : 'password'"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 pr-10"
                    />
                    <button
                        type="button"
                        class="settings-icon absolute top-1/2 right-3 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                        :aria-label="showNewPassword ? 'Hide password' : 'Show password'"
                        @click="showNewPassword = !showNewPassword"
                    >
                        <svg
                            v-if="showNewPassword"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M3 3l18 18" />
                            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                            <path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c5 0 9.3 3.1 11 7-0.6 1.4-1.6 2.8-2.8 3.9" />
                            <path d="M6.7 6.7C4.7 8.1 3.3 10 2 12c1.6 3.9 6 7 10 7 1.4 0 2.8-0.3 4.1-0.9" />
                        </svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </div>
                <p v-if="passwordForm.errors.new_password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.new_password }}</p>
            </div>

            <div>
                <label class="settings-label text-sm text-slate-500">Confirm New Password</label>
                <div class="relative">
                    <input
                        v-model="passwordForm.new_password_confirmation"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 pr-10"
                    />
                    <button
                        type="button"
                        class="settings-icon absolute top-1/2 right-3 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                        :aria-label="showConfirmPassword ? 'Hide password' : 'Show password'"
                        @click="showConfirmPassword = !showConfirmPassword"
                    >
                        <svg
                            v-if="showConfirmPassword"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M3 3l18 18" />
                            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                            <path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c5 0 9.3 3.1 11 7-0.6 1.4-1.6 2.8-2.8 3.9" />
                            <path d="M6.7 6.7C4.7 8.1 3.3 10 2 12c1.6 3.9 6 7 10 7 1.4 0 2.8-0.3 4.1-0.9" />
                        </svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </div>
                <p v-if="passwordForm.errors.new_password_confirmation" class="mt-1 text-xs text-red-600">
                    {{ passwordForm.errors.new_password_confirmation }}
                </p>
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
            :style="cardMotion(2)"
        >
            <h2 class="section-title">Account Email</h2>
            <p class="settings-muted text-xs text-slate-500">Update the email address tied to your account.</p>
            <div>
                <label class="settings-label text-sm text-slate-500">Email Address</label>
                <input v-model="emailForm.email" type="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="emailForm.errors.email" class="mt-1 text-xs text-red-600">{{ emailForm.errors.email }}</p>
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

        <section v-if="!mustChangePassword" class="account-card mt-4 space-y-3 rounded-2xl border border-red-200 bg-red-50 p-5" :style="cardMotion(3)">
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
.settings-label,
.settings-icon {
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
