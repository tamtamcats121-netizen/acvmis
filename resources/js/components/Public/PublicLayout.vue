<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

import { useInertiaLoading } from '@/composables/useInertiaLoading';

const props = withDefaults(
    defineProps<{
        title?: string;
        pageTitle?: string;
        pageDescription?: string;
    }>(),
    {
        title: '',
        pageTitle: '',
        pageDescription: '',
    },
);

const { isLoading } = useInertiaLoading();
const page = usePage();
const layoutRef = ref<HTMLElement | null>(null);
const currentYear = new Date().getFullYear();
const publicSectionLinks = [
    { id: 'home', label: 'Home', title: 'Overview of AC-VMIS' },
    { id: 'how-it-works', label: 'Process Overview', title: 'Step-by-step guidance for using the platform' },
    { id: 'about', label: 'About', title: 'Overview of AC-VMIS and its intended users' },
    { id: 'features', label: 'System Services', title: 'Main AC-VMIS functions and services' },
    { id: 'faq', label: 'FAQ', title: 'Answers to common questions' },
    { id: 'contact', label: 'Support', title: 'Official contact and support information' },
];
const isAuthed = computed(() => Boolean(page.props.auth?.user));
const userRole = computed(() => String(page.props.auth?.user?.role ?? ''));
const isLoginPage = computed(() => page.component === 'Auth/Login' || page.url.toLowerCase().includes('/login'));
const isRegisterPage = computed(() => page.component.includes('Register') || page.url.toLowerCase().includes('/register'));
const isAuthPage = computed(() => isLoginPage.value || isRegisterPage.value);
const isStatusPage = computed(() => page.component.startsWith('Status/') || page.component.toLowerCase().includes('status/'));
const currentPath = computed(() => {
    const path = page.url.split('?')[0].toLowerCase();
    if (path.length > 1 && path.endsWith('/')) {
        return path.slice(0, -1);
    }
    return path;
});
const mobileMenuOpen = ref(false);

function sectionHref(id: string) {
    return id === 'home' ? '/' : `/#${id}`;
}

function toLogin() {
    router.visit('Login');
}

function toRegister() {
    router.visit('/Register');
}

const dashboardPath = computed(() => {
    const role = userRole.value;
    if (role === 'admin') return '/AdminDashboard';
    if (role === 'coach') return '/coach/dashboard';
    if (role === 'student-athlete' || role === 'student') return '/StudentAthleteDashboard';
    return '/Login';
});

function revealContent() {
    const root = layoutRef.value;
    if (!root) return;
    root.querySelectorAll<HTMLElement>('.reveal').forEach((el) => el.classList.add('is-visible'));
}

onMounted(async () => {
    await nextTick();
    revealContent();
});

watch(
    () => page.url,
    async () => {
        await nextTick();
        revealContent();
        mobileMenuOpen.value = false;
    },
);

watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});
</script>

<template>
    <Head :title="props.title || props.pageTitle || 'AC-VMIS'" />

    <div class="public-layout public-page" ref="layoutRef">
        <header class="site-header px-3 py-1 sm:px-4 lg:px-6">
            <div class="nav-shell mx-auto max-w-6xl">
                <button type="button" class="mobile-menu-toggle" aria-label="Open menu" @click="mobileMenuOpen = true">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="header-desktop">
                    <div class="header-actions-desktop">
                        <template v-if="!isStatusPage">
                            <button v-if="isAuthed" @click="router.visit(dashboardPath)" class="header-link header-link-outline" :disabled="isLoading">
                                Back to Dashboard
                            </button>
                            <template v-else>
                                <button @click="toLogin" class="header-link header-link-outline" :class="{ 'is-active': isLoginPage }" :disabled="isLoading">
                                    Sign In
                                </button>
                                <button @click="toRegister" class="header-link" :class="{ 'is-active': isRegisterPage }" :disabled="isLoading">
                                    Register
                                </button>
                            </template>
                        </template>
                    </div>

                    <div class="header-logo-slot" aria-hidden="true">
                        <div class="corner-badge">
                            <svg class="logo-triangle" viewBox="0 0 260 130" aria-hidden="true" focusable="false">
                                <path
                                    d="M46 12H214
                                       Q222 12 226 18
                                       L178 124
                                       Q172 128 164 128
                                       H96
                                       Q88 128 82 124
                                       L34 18
                                       Q38 12 46 12Z"
                                />
                            </svg>
                            <img src="/images/ac-vmis.logo.png" alt="AC-VMIS Logo" class="logo-inside-triangle" />
                        </div>
                    </div>

                    <nav v-if="!isStatusPage" class="header-links-desktop" aria-label="Public pages">
                        <Link
                            v-for="item in publicSectionLinks"
                            :key="item.id"
                            :href="sectionHref(item.id)"
                            class="header-link"
                            :class="{ 'is-active': item.id === 'home' && (currentPath === '/' || currentPath === '') }"
                            :title="item.title"
                            :aria-label="`${item.label}: ${item.title}`"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                    <div v-else class="header-system-title" aria-label="System Title">Asian College Varsity Management Information System</div>
                </div>

                <div class="mobile-notch" aria-hidden="true">
                    <div class="header-logo-slot mobile-header-logo-slot">
                        <div class="corner-badge">
                            <svg class="logo-triangle" viewBox="0 0 260 130" aria-hidden="true" focusable="false">
                                <path
                                    d="M46 12H214
                                       Q222 12 226 18
                                       L178 124
                                       Q172 128 164 128
                                       H96
                                       Q88 128 82 124
                                       L34 18
                                       Q38 12 46 12Z"
                                />
                            </svg>
                            <img src="/images/ac-vmis.logo.png" alt="AC-VMIS Logo" class="logo-inside-triangle" />
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div v-if="mobileMenuOpen" class="mobile-menu-overlay" @click="mobileMenuOpen = false"></div>
        <aside class="mobile-menu" :class="{ 'is-open': mobileMenuOpen }" aria-label="Mobile menu">
            <div class="mobile-menu-header">
                <span class="mobile-menu-title">Menu</span>
                <button type="button" class="mobile-menu-close" @click="mobileMenuOpen = false">Close</button>
            </div>
            <div class="mobile-menu-actions">
                <template v-if="isAuthed">
                    <button
                        @click="
                            router.visit(dashboardPath);
                            mobileMenuOpen = false;
                        "
                        class="btn-outline w-full"
                    >
                        Back to Dashboard
                    </button>
                </template>
                <template v-else>
                    <button
                        @click="
                            toLogin();
                            mobileMenuOpen = false;
                        "
                        class="btn-outline w-full"
                    >
                        Sign In
                    </button>
                    <button
                        @click="
                            toRegister();
                            mobileMenuOpen = false;
                        "
                        class="btn-fill w-full"
                    >
                        Register
                    </button>
                </template>
            </div>
            <nav class="mobile-menu-links">
                <template v-if="isStatusPage">
                    <Link href="/" class="mobile-menu-link" @click="mobileMenuOpen = false">Home</Link>
                    <Link href="/Login" class="mobile-menu-link" @click="mobileMenuOpen = false">Sign In</Link>
                    <Link href="/Register" class="mobile-menu-link" @click="mobileMenuOpen = false">Register</Link>
                </template>
                <template v-else>
                    <Link
                        v-for="item in publicSectionLinks"
                        :key="item.id"
                        :href="sectionHref(item.id)"
                        class="mobile-menu-link"
                        @click="mobileMenuOpen = false"
                    >
                        {{ item.label }}
                    </Link>
                </template>
            </nav>
        </aside>

        <main class="public-body">
            <div class="public-content">
                <header v-if="props.pageTitle || props.pageDescription" class="page-intro">
                    
                    <h1 v-if="props.pageTitle" class="page-title">{{ props.pageTitle }}</h1>
                    <p v-if="props.pageDescription" class="page-desc">{{ props.pageDescription }}</p>
                </header>
                <slot />
            </div>
        </main>

        <footer class="site-footer relative z-10 px-4 pb-5 sm:px-6 lg:px-10" :class="{ 'site-footer-auth': isAuthPage, 'site-footer-status': isStatusPage }">
            <div class="footer-shell mx-auto max-w-6xl">
                <div class="footer-grid">
                    <section class="footer-col footer-col-brand">
                        <p class="footer-brand">Asian College Varsity Management Information System</p>
                        <p class="footer-copy">
                            One platform for student-athletes and coaches to handle schedules, attendance, academic eligibility, and
                            announcements.
                        </p>
                        <p class="footer-contact">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="contact-icon" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="M3 7l9 6 9-6" />
                            </svg>
                            <span>varsity.support@asiancollege.edu.ph</span>
                        </p>
                    </section>

                    <nav class="footer-col" aria-label="Public Pages">
                        <p class="footer-heading"><span class="title-chip">Public Pages</span></p>
                        <div class="footer-link-list">
                            <Link
                                v-for="item in publicSectionLinks"
                                :key="item.id"
                                :href="sectionHref(item.id)"
                                class="footer-link"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </nav>

                    <nav class="footer-col" aria-label="Access Links">
                        <p class="footer-heading"><span class="title-chip">Access</span></p>
                        <div class="footer-link-list">
                            <button @click="toRegister" class="footer-link footer-link-btn">Register</button>
                            <button @click="toLogin" class="footer-link footer-link-btn">Sign In</button>
                        </div>
                    </nav>

                </div>

                <div class="footer-bottom-row">
                    <p>© {{ currentYear }} Asian College</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.public-layout {
    --brand-blue: var(--blue-light-primary);
    --page-accent-strong: var(--blue-light-primary-strong);
    --page-accent-soft: color-mix(in srgb, var(--color-brand) 14%, white);
    --public-header-surface: transparent;
    --public-panel-border: color-mix(in srgb, var(--color-brand) 22%, white);
    --public-panel-border-strong: color-mix(in srgb, var(--color-brand) 34%, white);
    --public-panel-shadow: 0 18px 40px -28px rgba(8, 28, 52, 0.34);
    --public-footer-bg: linear-gradient(90deg, rgba(3, 68, 133, 0.95), rgba(3, 68, 133, 0.82)), url('/images/footer-athletes.png');
    --footer-text-strong: rgba(255, 255, 255, 0.96);
    --footer-text-soft: rgba(255, 255, 255, 0.78);
    font-family: Poppins, 'Segoe UI', sans-serif;
}

.public-body {
    background: linear-gradient(180deg, #1d4f8f 0%, var(--brand-blue) 100%);
    padding: 0 1.1rem 2.6rem;
}

.public-content {
    max-width: 1140px;
    margin: 0 auto;
    display: grid;
    gap: 1.25rem;
}

.page-intro {
    padding: 1.2rem 0 0.4rem;
    color: var(--footer-text-strong);
    display: grid;
    gap: 0.45rem;
}

.page-title {
    margin: 0;
    font-size: var(--text-2xl);
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--footer-text-strong);
}

.page-desc {
    margin: 0;
    max-width: 60ch;
    color: rgba(255, 255, 255, 0.82);
    font-size: var(--text-base);
    line-height: 1.6;
}

:deep(.public-card) {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.38);
    border-radius: var(--radius-xl);
    padding: 1.4rem 1.6rem;
    box-shadow: var(--public-panel-shadow);
    backdrop-filter: blur(12px);
}

:deep(.public-card p) {
    color: rgba(255, 255, 255, 0.92);
}

:deep(.public-card ul),
:deep(.public-card ol) {
    margin: 0;
    padding-left: 1.1rem;
}

:deep(.public-card li) {
    color: rgba(255, 255, 255, 0.9);
}

:deep(.section-title) {
    display: inline-block;
    padding: 0.22rem 0.7rem;
    border-radius: var(--radius-full);
    background: rgba(255, 255, 255, 0.08);
    color: var(--footer-text-strong);
    font-weight: 700;
    font-size: var(--text-sm);
    margin: 0 0 0.65rem;
    border: 1px solid rgba(255, 255, 255, 0.34);
}

.title-chip {
    display: inline-block;
    padding: 0.22rem 0.7rem;
    border-radius: var(--radius-full);
    background: var(--title-chip-bg);
    color: var(--title-chip-text);
    line-height: 1.2;
    border: 1px solid var(--title-chip-border);
    -webkit-box-decoration-break: clone;
    box-decoration-break: clone;
}

.site-footer {
    margin-top: 0.75rem;
    padding-top: 0.85rem;
    border-top: 1px solid rgba(255, 255, 255, 0.22);
    background-image: var(--public-footer-bg);
    background-position: center, 72% center;
    background-repeat: no-repeat, no-repeat;
    background-size: cover, cover;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    margin-left: 1.5rem;
    margin-right: 1.5rem;
    overflow: hidden;
    color: var(--footer-text-strong);
    box-shadow: var(--shadow-md);
    --title-chip-bg: rgba(255, 255, 255, 0.1);
    --title-chip-border: rgba(255, 255, 255, 0.18);
    --title-chip-text: var(--footer-text-strong);
}

.footer-shell {
    padding: 0.2rem 0 0;
    color: var(--footer-text-strong);
}

.footer-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) repeat(2, minmax(0, 0.8fr));
    gap: 0.85rem;
}

.footer-col {
    min-width: 0;
}

.footer-col-brand {
    max-width: 42ch;
}

.footer-brand {
    color: var(--footer-text-strong);
    font-size: var(--text-base);
    font-weight: 800;
}

.footer-copy {
    margin-top: 0.35rem;
    color: var(--footer-text-soft);
    line-height: 1.45;
    font-size: 0.82rem;
}

.footer-contact {
    margin-top: 0.3rem;
    color: var(--footer-text-soft);
    font-size: 0.8rem;
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    line-height: 1.5;
}

.contact-icon {
    width: 0.92rem;
    height: 0.92rem;
    color: var(--footer-text-strong);
    flex-shrink: 0;
}

.footer-heading {
    color: var(--footer-text-soft);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: var(--text-xs);
    font-weight: 800;
}

.footer-link-list {
    margin-top: 0.45rem;
    display: grid;
    gap: 0.32rem;
    overflow-wrap: anywhere;
}

.footer-link {
    color: var(--footer-text-soft);
    text-decoration: none;
    font-size: 0.82rem;
    overflow-wrap: anywhere;
}

.footer-link:hover {
    color: var(--footer-text-strong);
}

.footer-link-btn {
    border: none;
    background: none;
    padding: 0;
    text-align: left;
    cursor: pointer;
}

.footer-bottom-row {
    margin-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 0.55rem;
    color: var(--footer-text-soft);
    font-size: var(--text-xs);
}

.site-footer-auth .footer-brand,
.site-footer-auth .footer-copy,
.site-footer-auth .footer-contact,
.site-footer-auth .contact-icon,
.site-footer-auth .footer-heading,
.site-footer-auth .footer-link,
.site-footer-auth .footer-bottom-row,
.site-footer-auth .title-chip,
.site-footer-status .footer-brand,
.site-footer-status .footer-copy,
.site-footer-status .footer-contact,
.site-footer-status .contact-icon,
.site-footer-status .footer-heading,
.site-footer-status .footer-link,
.site-footer-status .footer-bottom-row,
.site-footer-status .title-chip {
    color: #ffffff !important;
}

.site-footer-auth .footer-link:hover,
.site-footer-status .footer-link:hover {
    color: #ffffff !important;
}

.site-footer-auth .title-chip,
.site-footer-status .title-chip {
    background: rgba(255, 255, 255, 0.12) !important;
    border-color: rgba(255, 255, 255, 0.24) !important;
}

.site-footer-auth .footer-bottom-row p,
.site-footer-auth .footer-contact span,
.site-footer-status .footer-bottom-row p,
.site-footer-status .footer-contact span,
.site-footer-status .footer-link-list,
.site-footer-status .footer-link-list *,
.site-footer-status .footer-shell,
.site-footer-status .footer-shell * {
    color: #ffffff !important;
}

.site-header {
    position: sticky;
    top: 0;
    z-index: 35;
    background: transparent;
    border-bottom: none;
    overflow: visible;
}

.nav-shell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 66px;
    padding: 2px 14px 1px;
    background: var(--public-header-surface);
    border-radius: 0 0 calc(var(--radius-xl) + 4px) calc(var(--radius-xl) + 4px);
    overflow: visible;
}

.header-desktop {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    width: 100%;
    overflow: visible;
}

.header-actions-desktop {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex: 0 0 auto;
    min-width: 0;
}

.mobile-notch {
    display: none;
}

.mobile-menu-toggle {
    display: none;
    flex-direction: column;
    gap: 5px;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
    border: 1px solid var(--brand-line);
    background: var(--color-surface);
    box-shadow: var(--shadow-xs);
}

.mobile-menu-toggle span {
    width: 18px;
    height: 2px;
    background: var(--brand-blue);
    border-radius: 999px;
}

.header-logo-slot {
    position: relative;
    flex: 0 0 236px;
    width: 236px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 4;
    overflow: visible;
}

.corner-badge {
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 40;
    width: 272px;
    height: 94px;
    pointer-events: none;
}

.logo-triangle {
    position: relative;
    width: 100%;
    height: 100%;
    background: transparent !important;
}

.logo-triangle path {
    fill: var(--color-surface);
}

.logo-inside-triangle {
    position: absolute;
    top: 23px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 60px;
    border-radius: var(--radius-full);
    background: var(--color-surface);
    padding: 3px;
    object-fit: contain;
    border: 1px solid var(--color-border);
    box-shadow: var(--shadow-sm);
}

.header-links-desktop {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
    flex: 1 1 auto;
    min-width: 0;
}

.header-system-title {
    margin-left: auto;
    text-align: right;
    font-size: var(--text-sm);
    font-weight: 700;
    color: var(--brand-blue);
    letter-spacing: 0.02em;
    align-self: flex-end;
}

.header-link {
    color: var(--color-surface);
    font-size: var(--text-sm);
    font-weight: 600;
    text-decoration: none;
    padding: 6px 14px;
    border: none;
    border-radius: var(--radius-full);
    background: var(--brand-blue);
    line-height: 1.35;
    text-align: center;
    white-space: nowrap;
}

.header-link:hover {
    color: var(--color-surface);
    background: var(--page-accent-strong);
}

.header-link-outline {
    color: var(--brand-blue);
    background: transparent;
}

.header-link-outline:hover {
    color: var(--brand-blue);
    background: var(--page-accent-soft);
}

.header-actions-desktop .header-link {
    color: var(--brand-blue);
    background: transparent;
}

.header-actions-desktop .header-link:hover {
    color: var(--brand-blue);
    background: var(--page-accent-soft);
}

.header-actions-desktop .header-link:active,
.header-actions-desktop .header-link.is-active {
    color: var(--brand-blue);
    background: color-mix(in srgb, var(--color-brand) 16%, white);
}

.header-link.is-active {
    background: var(--page-accent-strong);
    color: var(--color-surface);
    border: none;
}

.btn-fill,
.btn-outline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    min-height: 38px;
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: 700;
    color: var(--brand-blue);
    background: var(--color-surface);
    border: 1px solid var(--brand-blue);
    text-align: center;
}

.mobile-menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(3, 20, 40, 0.45);
    z-index: 60;
}

.mobile-menu {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: min(86vw, 340px);
    background: linear-gradient(180deg, var(--color-surface) 0%, var(--color-overlay) 100%);
    border-right: 1px solid var(--color-border);
    padding: 1.2rem 1rem;
    z-index: 70;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transform: translateX(-100%);
    box-shadow: var(--shadow-lg);
    transition: transform var(--transition-slow);
}

.mobile-menu.is-open {
    transform: translateX(0);
}

.mobile-menu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mobile-menu-title {
    font-size: var(--text-base);
    font-weight: 700;
    color: var(--color-text-primary);
}

.mobile-menu-close {
    border: 1px solid var(--brand-line);
    background: var(--color-surface);
    border-radius: var(--radius-full);
    padding: 0.35rem 0.75rem;
    font-size: var(--text-xs);
    font-weight: 700;
    color: var(--brand-blue);
}

.mobile-menu-actions {
    display: grid;
    gap: 0.5rem;
}

.mobile-menu-links {
    display: grid;
    gap: 0.4rem;
}

.mobile-menu-link {
    padding: 0.6rem 0.75rem;
    border-radius: var(--radius-full);
    border: 1px solid var(--public-panel-border);
    color: var(--brand-blue);
    font-weight: 600;
    font-size: var(--text-sm);
    text-decoration: none;
    background: rgba(255, 255, 255, 0.66);
}

.mobile-menu-link:hover {
    background: var(--page-accent-soft);
    border-color: var(--public-panel-border-strong);
}

.btn-fill {
    color: var(--brand-blue);
    border-color: var(--brand-blue);
    background: var(--color-surface);
}

.btn-outline {
    color: var(--brand-blue);
    border-color: var(--brand-blue);
    background: var(--color-surface);
}

.site-header .btn-fill,
.site-header .btn-outline {
    background: var(--color-surface) !important;
    color: var(--brand-blue) !important;
    border-color: var(--brand-blue) !important;
}

.site-header .btn-fill:hover,
.site-header .btn-outline:hover {
    background: var(--page-accent-soft) !important;
    color: var(--brand-blue) !important;
}

.site-header .btn-fill.is-active,
.site-header .btn-outline.is-active {
    box-shadow: inset 0 -2px 0 var(--brand-blue);
}

.btn-fill:disabled,
.btn-outline:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

@media (min-width: 640px) {
    .public-body {
        padding: 0 1.4rem 3rem;
    }

    .footer-bottom-row {
        display: flex;
        justify-content: flex-end;
    }
}

@media (max-width: 768px) {
    .nav-shell {
        justify-content: space-between;
        align-items: center;
        min-height: 45px;
        padding: 2px 14px 0;
    }

    .header-desktop,
    .header-links-desktop {
        display: none;
    }

    .mobile-notch {
        display: flex;
        position: absolute;
        left: 50%;
        bottom: -36px;
        transform: translateX(-50%);
        width: min(236px, 68vw);
        height: 76px;
        align-items: flex-start;
        justify-content: center;
        pointer-events: none;
        overflow: visible;
    }

    .header-system-title {
        display: none;
    }

    .mobile-menu-toggle {
        display: inline-flex;
    }

    .mobile-menu-actions .btn-fill,
    .mobile-menu-actions .btn-outline {
        width: 100%;
    }

    .corner-badge {
        top: 3px;
        width: 226px;
        height: 79px;
    }

    .header-logo-slot {
        flex-basis: 202px;
    }

    .logo-inside-triangle {
        top: 20px;
        width: 50px;
        height: 50px;
        padding: 3px;
    }

    .mobile-header-logo-slot {
        position: relative;
        width: min(236px, 68vw);
        height: 76px;
        left: auto;
        top: auto;
        transform: none;
        flex: 0 0 auto;
    }

    .footer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .public-body {
        padding: 0 1rem 2.4rem;
    }

    .page-intro {
        padding-top: 3.25rem;
    }

    .corner-badge {
        top: 4px;
        width: 212px;
        height: 74px;
    }

    .header-logo-slot {
        flex-basis: 190px;
    }

    .logo-inside-triangle {
        top: 20px;
        width: 44px;
        height: 44px;
        padding: 3px;
    }

    .mobile-header-logo-slot {
        width: min(206px, 66vw);
        height: 70px;
    }

    .site-footer {
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        margin-left: 1rem;
        margin-right: 1rem;
    }

    .footer-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .footer-bottom-row {
        justify-content: flex-start;
    }
}

@media (max-width: 400px) {
    .header-links {
        gap: 4px 8px;
    }

    .header-link {
        font-size: 11px;
    }

    .btn-fill,
    .btn-outline {
        padding: 9px 10px;
        font-size: 13px;
        width: 100%;
    }
}

@keyframes loading-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}
</style>
