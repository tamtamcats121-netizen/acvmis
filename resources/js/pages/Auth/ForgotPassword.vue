<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import { computed } from 'vue';

import PublicLayout from '@/components/Public/PublicLayout.vue';
import FormAlert from '@/components/ui/form/FormAlert.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';

const page = usePage();

const form = useForm({
    email: '',
});

const status = computed(() => String((page.props as any)?.flash?.success ?? ''));
const generalError = computed(() => String((page.props as any)?.errors?.message ?? ''));

function submit() {
    form.post('/forgot-password', {
        preserveScroll: true,
    });
}

function toLogin() {
    router.visit('/Login');
}
</script>

<template>
    <PublicLayout
        title="Forgot Password"
        page-title="Forgot Password"
        page-description="Enter your email address to request a password reset link."
    >
        <section class="login-shell">
            <div class="login-grid">
                <section class="public-card login-copy">
                    <p class="copy-kicker">Account Recovery</p>
                    <h1>Password Assistance</h1>
                    <p>Enter your registered email address to receive a secure password reset link.</p>
                </section>

                <section class="public-card login-card">
                    <h2>Request Password Reset</h2>

                    <form @submit.prevent="submit" class="login-form">
                        <FormAlert tone="success" :message="status" />
                        <FormAlert tone="error" :message="generalError" />

                        <div class="form-stack">
                            <div>
                                <InputText
                                    v-model="form.email"
                                    type="email"
                                    placeholder="Email"
                                    :class="['field-input', { 'p-invalid': !!form.errors.email }]"
                                />
                                <Message v-if="form.errors.email" severity="error" size="small" variant="simple" class="mt-1">
                                    {{ form.errors.email }}
                                </Message>
                            </div>
                        </div>

                        <button type="submit" class="login-btn mt-3" :disabled="form.processing">
                            <span class="inline-flex items-center gap-2">
                                <Spinner v-if="form.processing" class="spinner-mark h-4 w-4" />
                                {{ form.processing ? 'Sending...' : 'Send Reset Link' }}
                            </span>
                        </button>

                        <p class="register-note">
                            Return to account access:
                            <button type="button" @click="toLogin" class="register-link">Back to Sign In</button>
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

.login-btn {
    width: 100%;
    min-height: 56px;
    border-radius: var(--radius-full);
    font-size: var(--text-base);
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.96);
    color: var(--color-brand);
    box-shadow: var(--shadow-sm);
}

.register-note {
    margin-top: 0.35rem;
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
