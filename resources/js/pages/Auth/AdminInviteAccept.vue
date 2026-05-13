<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Password from 'primevue/password';

import PublicLayout from '@/components/Public/PublicLayout.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';

const props = defineProps<{
    email: string;
    token: string;
    expiresAt: string;
}>();

const form = useForm({
    email: props.email ?? '',
    token: props.token ?? '',
    first_name: '',
    middle_name: '',
    last_name: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/admin/invite/accept', {
        preserveScroll: true,
    });
}

function toLogin() {
    router.visit('/Login');
}
</script>

<template>
    <PublicLayout title="Admin Invitation" page-title="Admin Invitation" page-description="Complete your administrator setup for AC-VMIS.">
        <section class="login-shell">
            <div class="login-grid">
                <section class="public-card login-copy">
                    <p class="copy-kicker">Administrator Access</p>
                    <h1>Complete Administrator Account Setup</h1>
                    <p>Use this invitation to create your administrator account for AC-VMIS. This link may be used once and will expire on {{ expiresAt }}.</p>
                </section>

                <section class="public-card login-card">
                    <h2>Set Your Details</h2>

                    <form @submit.prevent="submit" class="login-form">
                        <div class="form-stack">
                            <InputText v-model="form.email" type="email" class="field-input" readonly />
                            <InputText v-model="form.first_name" type="text" class="field-input" placeholder="First name" />
                            <Message v-if="form.errors.first_name" severity="error" size="small" variant="simple">{{ form.errors.first_name }}</Message>

                            <InputText v-model="form.middle_name" type="text" class="field-input" placeholder="Middle name (optional)" />
                            <Message v-if="form.errors.middle_name" severity="error" size="small" variant="simple">{{ form.errors.middle_name }}</Message>

                            <InputText v-model="form.last_name" type="text" class="field-input" placeholder="Last name" />
                            <Message v-if="form.errors.last_name" severity="error" size="small" variant="simple">{{ form.errors.last_name }}</Message>

                            <Password
                                v-model="form.password"
                                toggleMask
                                :feedback="false"
                                placeholder="New password"
                                inputClass="field-input w-full"
                                :invalid="!!form.errors.password"
                                class="w-full"
                            />
                            <Message v-if="form.errors.password" severity="error" size="small" variant="simple">{{ form.errors.password }}</Message>

                            <Password
                                v-model="form.password_confirmation"
                                toggleMask
                                :feedback="false"
                                placeholder="Confirm new password"
                                inputClass="field-input w-full"
                                :invalid="!!form.errors.password_confirmation"
                                class="w-full"
                            />
                            <Message v-if="form.errors.password_confirmation" severity="error" size="small" variant="simple">{{ form.errors.password_confirmation }}</Message>
                        </div>

                        <button type="submit" class="login-btn mt-3" :disabled="form.processing">
                            <span class="inline-flex items-center gap-2">
                                <Spinner v-if="form.processing" class="spinner-mark h-4 w-4" />
                                {{ form.processing ? 'Creating account...' : 'Create Administrator Account' }}
                            </span>
                        </button>

                        <p class="register-note">
                            Return to account access:
                            <button type="button" @click="toLogin" class="register-link">Go to Sign In</button>
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

.toggle-eye {
    color: color-mix(in srgb, var(--color-brand) 72%, white);
}

.field-hint {
    color: rgba(255, 255, 255, 0.82);
    font-size: var(--text-xs);
    line-height: 1.5;
}

.spinner-mark {
    color: var(--color-brand);
}

@media (max-width: 900px) {
    .login-shell {
        padding: 1rem 0 2rem;
    }

    .login-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

@media (max-width: 640px) {
    .login-shell {
        padding: 0.85rem 0 1.8rem;
    }

    .login-copy h1 {
        font-size: 1.6rem;
    }

    .login-copy,
    .login-card {
        padding-inline: 1.15rem;
    }
}
</style>
