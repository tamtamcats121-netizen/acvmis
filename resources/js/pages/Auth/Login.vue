<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import PublicLayout from '@/components/Public/PublicLayout.vue';
import FieldError from '@/components/ui/form/FieldError.vue';
import FormAlert from '@/components/ui/form/FormAlert.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';

const email = ref('');
const password = ref('');
const page = usePage();
const error = ref(String((page.props as any)?.errors?.message ?? (page.props as any)?.flash?.error ?? ''));
const fieldErrors = reactive({
    email: '',
    password: '',
});
const isSubmitting = ref(false);
const showPassword = ref(false);
const flashSuccess = ref(String((page.props as any)?.flash?.success ?? ''));

function pickFirstError(errors: Record<string, unknown>) {
    const preferredOrder = ['message', 'email', 'password', 'error'];
    for (const key of preferredOrder) {
        const raw = errors[key];
        const message = Array.isArray(raw) ? raw[0] : raw;
        if (typeof message === 'string' && message.trim()) {
            return message;
        }
    }

    for (const value of Object.values(errors)) {
        const message = Array.isArray(value) ? value[0] : value;
        if (typeof message === 'string' && message.trim()) {
            return message;
        }
    }

    return '';
}

function toRegister() {
    router.visit('/Register');
}
function toForgotPassword() {
    router.visit('/forgot-password');
}
function login() {
    if (isSubmitting.value) return;
    error.value = '';
    fieldErrors.email = '';
    fieldErrors.password = '';

    if (!email.value) {
        fieldErrors.email = 'Email is required.';
    }

    if (!password.value) {
        fieldErrors.password = 'Password is required.';
    }

    if (fieldErrors.email || fieldErrors.password) {
        error.value = 'Please complete the required fields.';
        return;
    }

    router.post(
        '/login',
        {
            email: email.value,
            password: password.value,
        },
        {
            onStart: () => {
                isSubmitting.value = true;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
            onError: (e: any) => {
                const payload = (e && typeof e === 'object' ? e : {}) as Record<string, unknown>;
                const resolved = pickFirstError(payload);
                if (typeof payload.email === 'string') {
                    fieldErrors.email = payload.email;
                }
                if (typeof payload.password === 'string') {
                    fieldErrors.password = payload.password;
                }
                error.value = resolved || 'Sign-in could not be completed. Please verify your credentials and try again.';
            },
        },
    );
}
</script>

<template>
    <PublicLayout title="Sign In" page-title="Sign In" page-description="Sign in to access your varsity information and system services.">
        <section class="login-shell">
            <div class="login-grid">
                <section class="public-card login-copy">
                    <p class="copy-kicker">Account Access</p>
                    <h1>Welcome</h1>
                    <p>Sign in to access schedules, attendance, performance records, academic updates, and varsity announcements.</p>
                </section>

                <section class="public-card login-card">
                    <h2>Sign In</h2>

                    <form @submit.prevent="login" class="login-form">
                        <FormAlert tone="success" :message="flashSuccess" />
                        <FormAlert tone="error" :message="error" />

                        <div class="form-stack">
                            <div>
                                <input
                                    v-model="email"
                                    type="email"
                                    placeholder="Email"
                                    :class="['field-input', { 'is-error': !!fieldErrors.email }]"
                                    :aria-invalid="fieldErrors.email ? 'true' : 'false'"
                                    aria-describedby="login-email-error"
                                />
                                <FieldError id="login-email-error" :message="fieldErrors.email" />
                            </div>

                            <div class="password-field">
                                <div class="password-field__control">
                                    <input
                                        v-model="password"
                                        :type="showPassword ? 'text' : 'password'"
                                        placeholder="Password"
                                        :class="['field-input', 'pr-10', { 'is-error': !!fieldErrors.password }]"
                                        :aria-invalid="fieldErrors.password ? 'true' : 'false'"
                                        aria-describedby="login-password-error"
                                    />
                                    <button
                                        type="button"
                                        class="toggle-eye absolute top-1/2 right-3 -translate-y-1/2"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        @click="showPassword = !showPassword"
                                    >
                                        <svg
                                            v-if="showPassword"
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
                                        <svg
                                            v-else
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            aria-hidden="true"
                                        >
                                            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </button>
                                </div>
                                <FieldError id="login-password-error" :message="fieldErrors.password" />
                            </div>

                            <button type="button" class="forgot-link" @click="toForgotPassword" :disabled="isSubmitting">
                                Forgot your password?
                            </button>
                        </div>

                        <button type="submit" class="login-btn" :disabled="isSubmitting">
                            <span class="inline-flex items-center gap-2">
                                <Spinner v-if="isSubmitting" class="h-4 w-4 text-[#034485]" />
                                {{ isSubmitting ? 'Signing in...' : 'Sign In' }}
                            </span>
                        </button>

                        <p class="register-note">
                            Need to create an account?
                            <button type="button" @click="toRegister" class="register-link" :disabled="isSubmitting">Register</button>
                        </p>
                    </form>
                </section>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

.login-shell {
    padding: 1.4rem 0 2.2rem;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
}

.login-grid {
    width: 100%;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 1.25rem;
    align-items: center;
}

.login-copy {
    display: grid;
    gap: 0.75rem;
}

.copy-kicker {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 700;
}

.login-copy h1 {
    margin-top: 0.2rem;
    font-size: 2rem;
    line-height: 1.1;
    color: #ffffff;
    font-weight: 800;
}

.login-copy p {
    margin-top: 0.35rem;
    color: rgba(255, 255, 255, 0.86);
    line-height: 1.65;
    max-width: 56ch;
}

.login-card {
    display: grid;
    gap: 0.75rem;
    min-width: 0;
}

.login-card h2 {
    font-size: 1.35rem;
    color: #ffffff;
    font-weight: 800;
}

.form-stack {
    margin-top: 0.4rem;
    display: grid;
    gap: 0.65rem;
}

.password-field {
    display: grid;
    gap: 0.35rem;
}

.password-field__control {
    position: relative;
}

.field-input {
    width: 100%;
    border-radius: 12px;
    border: 1px solid rgba(3, 68, 133, 0.25);
    background: #ffffff;
    color: #0b1b2b;
    padding: 0.7rem 0.85rem;
    font-size: 0.98rem;
}

.field-input.is-error {
    border-color: #dc2626;
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2);
}

.field-input:focus {
    outline: none;
    border-color: rgba(3, 68, 133, 0.45);
    box-shadow: 0 0 0 2px rgba(3, 68, 133, 0.15);
}

.forgot-link {
    width: fit-content;
    border: none;
    background: transparent;
    color: #ffffff;
    font-weight: 600;
    font-size: 0.88rem;
    padding: 0;
    cursor: pointer;
}

.forgot-link:hover {
    text-decoration: underline;
}

.login-btn {
    width: 100%;
    margin-top: 0.85rem;
    border-radius: 999px;
    border: 1px solid #ffffff;
    background: #ffffff;
    color: #034485;
    font-weight: 700;
    padding: 0.7rem 1rem;
}

.register-note {
    margin-top: 0.75rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.9rem;
    line-height: 1.6;
}

.register-link {
    border: none;
    background: none;
    color: #ffffff;
    font-weight: 700;
    margin-left: 0.3rem;
    cursor: pointer;
}

.register-link:hover {
    text-decoration: underline;
}

.toggle-eye {
    color: rgba(3, 68, 133, 0.65);
}

@media (max-width: 900px) {
    .login-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

@media (max-width: 640px) {
    .login-shell {
        padding: 0.85rem 0 1.8rem;
    }

    .login-copy,
    .login-card {
        padding-inline: 1.15rem;
    }

    .login-copy h1 {
        font-size: 1.6rem;
    }
}
</style>
