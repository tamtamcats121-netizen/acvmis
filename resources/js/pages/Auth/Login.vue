<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Password from 'primevue/password';
import { reactive, ref } from 'vue';

import PublicLayout from '@/components/Public/PublicLayout.vue';
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
                    <p>Sign in to access schedules, attendance, academic updates, and varsity announcements.</p>
                </section>

                <section class="public-card login-card">
                    <h2>Sign In</h2>

                    <form @submit.prevent="login" class="login-form">
                        <FormAlert tone="success" :message="flashSuccess" />
                        <FormAlert tone="error" :message="error" />

                        <div class="form-stack">
                            <div>
                                <InputText
                                    v-model="email"
                                    type="email"
                                    placeholder="Email"
                                    :class="['field-input', { 'p-invalid': !!fieldErrors.email }]"
                                />
                                <Message v-if="fieldErrors.email" severity="error" size="small" variant="simple" class="mt-1">
                                    {{ fieldErrors.email }}
                                </Message>
                            </div>

                            <div class="password-field">
                                <Password
                                    v-model="password"
                                    toggleMask
                                    :feedback="false"
                                    placeholder="Password"
                                    inputClass="field-input w-full"
                                    :invalid="!!fieldErrors.password"
                                    class="w-full"
                                />
                                <Message v-if="fieldErrors.password" severity="error" size="small" variant="simple" class="mt-1">
                                    {{ fieldErrors.password }}
                                </Message>
                            </div>

                            <button type="button" class="forgot-link" @click="toForgotPassword" :disabled="isSubmitting">
                                Forgot your password?
                            </button>
                        </div>

                        <button type="submit" class="login-btn" :disabled="isSubmitting">
                            <span class="inline-flex items-center gap-2">
                                <Spinner v-if="isSubmitting" class="spinner-mark h-4 w-4" />
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
.login-shell {
    padding: 1.4rem 0 2.2rem;
    font-family: Poppins, 'Segoe UI', sans-serif;
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
    font-size: var(--text-xs);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 700;
}

.login-copy h1 {
    margin-top: 0.2rem;
    font-size: var(--text-3xl);
    line-height: 1.1;
    color: rgba(255, 255, 255, 0.96);
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
    border-radius: calc(var(--radius-lg) + 4px);
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.24);
    box-shadow: var(--shadow-sm);
    backdrop-filter: blur(14px);
}

.login-card h2 {
    font-size: var(--text-xl);
    color: rgba(255, 255, 255, 0.96);
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
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-brand) 30%, white);
    background: rgba(255, 255, 255, 0.96);
    color: var(--color-text-primary);
    padding: 0.7rem 0.85rem;
    font-size: var(--text-base);
    box-shadow: var(--shadow-xs);
}

.field-input.is-error {
    border-color: rgba(185, 28, 28, 0.88);
    box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.14);
}

.field-input:focus {
    outline: none;
    border-color: color-mix(in srgb, var(--color-brand) 42%, white);
    box-shadow: 0 0 0 3px rgba(3, 68, 133, 0.14);
}

.forgot-link {
    width: fit-content;
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
    font-size: var(--text-sm);
    padding: 0;
    cursor: pointer;
}

.forgot-link:hover {
    text-decoration: underline;
}

.login-btn {
    width: 100%;
    margin-top: 0.85rem;
    border-radius: var(--radius-full);
    border: 1px solid rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.96);
    color: var(--color-brand);
    font-weight: 700;
    padding: 0.7rem 1rem;
    box-shadow: var(--shadow-sm);
}

.register-note {
    margin-top: 0.75rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.82);
    font-size: var(--text-sm);
    line-height: 1.6;
}

.register-link {
    border: none;
    background: none;
    color: rgba(255, 255, 255, 0.96);
    font-weight: 700;
    margin-left: 0.3rem;
    cursor: pointer;
}

.register-link:hover {
    text-decoration: underline;
}

.toggle-eye {
    color: color-mix(in srgb, var(--color-brand) 72%, white);
}

.spinner-mark {
    color: var(--color-brand);
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
