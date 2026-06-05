<script setup lang="ts">
import { router } from '@inertiajs/vue3'

import PublicLayout from '@/components/Public/PublicLayout.vue'

function goHome() {
    router.visit('/')
}

function goLogin() {
    router.visit('/Login')
}
</script>

<template>
    <PublicLayout title="Account Pending Approval">
        <div class="status-shell">
            <section class="public-card status-card">
                <p class="status-kicker">Registration Submitted</p>
                <h2>Registration Submitted Successfully</h2>
                <p class="status-text">
                    Your registration is currently <span class="status-pending">pending review</span>.
                    The coach assigned to your selected sport will review your submitted information. You may sign in after your account has been approved.
                </p>

                <div class="status-actions">
                    <button @click="goHome" class="status-btn-fill">Return to Home</button>
                    <button @click="goLogin" class="status-btn-outline">Go to Sign In</button>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>

<style scoped>
.status-shell {
    min-height: 52vh;
    display: grid;
    place-items: center;
}

.status-card {
    width: min(100%, 620px);
    text-align: center;
    display: grid;
    gap: 0.5rem;
    padding: clamp(1.25rem, 3vw, 2rem);
}

.status-kicker {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 700;
}

.status-card h2 {
    margin-top: 0.45rem;
    color: #ffffff;
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.2;
}

.status-text {
    margin-top: 0.75rem;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.6;
}

.status-pending {
    color: #fde68a;
    font-weight: 700;
}

.status-actions {
    margin-top: 1rem;
    display: flex;
    justify-content: center;
    align-items: stretch;
    gap: 0.6rem;
    flex-direction: column;
}

.status-btn-fill,
.status-btn-outline {
    border-radius: 10px;
    padding: 0.62rem 0.95rem;
    font-weight: 700;
    font-size: 0.9rem;
    width: 100%;
}

.status-btn-fill {
    border: 1px solid #ffffff;
    background: #ffffff;
    color: #034485;
}

.status-btn-outline {
    border: 1px solid rgba(255, 255, 255, 0.6);
    background: transparent;
    color: #ffffff;
}

@media (min-width: 640px) {
    .status-actions {
        flex-direction: row;
        flex-wrap: wrap;
    }

    .status-btn-fill,
    .status-btn-outline {
        width: auto;
    }
}
</style>
