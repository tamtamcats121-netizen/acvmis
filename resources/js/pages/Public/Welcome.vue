<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { useInertiaLoading } from '@/composables/useInertiaLoading';

const currentYear = new Date().getFullYear();
const fallbackLogo = '/images/ac-vmis.logo.png';
const publicNavItems = [
    { id: 'home', label: 'Home' },
    { id: 'how-it-works', label: 'Process Overview' },
    { id: 'about', label: 'About' },
    { id: 'features', label: 'System Services' },
    { id: 'faq', label: 'FAQ' },
    { id: 'contact', label: 'Support' },
];
const howItWorksSteps = [
    'Participate in the appropriate tryout or selection process. A student-athlete may register only after the coach confirms official inclusion in the varsity pool.',
    'Create your student-athlete account using the required personal and school information.',
    'Submit the required supporting documents during registration.',
    'Wait for administrative review and account approval before using restricted system features.',
    'After approval, use the system regularly for schedules, attendance confirmation, and academic updates.',
];
const aboutStoryCards = [
    {
        title: 'Asian College Heritage',
        paragraphs: [
            'Asian College traces its roots to the Asian Institute of Electronics, founded in 1972 by Dr. Constancio A. Sia and Gloria Durano Sia with the vision of providing accessible and quality education in technology and professional fields. From its first batch of students in 1973, the institution continued to grow into a student-centered and industry-relevant college committed to developing future leaders.',
        ],
    },
    {
        title: 'Dumaguete Campus',
        paragraphs: [
            'The Dumaguete campus provides a supportive learning environment with classrooms, a library, gymnasium, soccer field, laboratories, and other spaces that support both academic and extracurricular development.',
        ],
    },
    {
        title: 'Varsity Management System',
        paragraphs: [
            "The Asian College Varsity Management Information System supports this commitment by helping administrators, coaches, and student-athletes manage varsity records, schedules, attendance, academic compliance, training requirements, submitted documents, and communication in one secure system. Through AC-VMIS, varsity management becomes more organized, transparent, and efficient while supporting the school's goal of developing leaders in I.T. and management.",
            'AC-VMIS is a centralized student-athlete management system designed to help schools organize team operations, academic compliance, schedules, attendance, and document tracking in one secure platform. It supports administrators, coaches, and student-athletes by making key processes more efficient, transparent, and easier to manage.',
            'The system helps institutions streamline varsity management by connecting roster oversight, academic submissions, attendance monitoring, and team coordination into a single digital workspace.',
        ],
    },
];
const aboutHighlights = [
    {
        title: 'Vision',
        description:
            'To be a transformative educational institution committed to the success of its graduates through quality instruction, relevant research, and strong community engagement.',
    },
    {
        title: 'Mission',
        description: 'To educate and develop globally competitive future leaders.',
    },
    {
        title: 'Core Values',
        items: ['Academic Excellence', 'Integrity', 'Self-Leadership'],
    },
    {
        title: 'Tagline',
        description: 'Developing Leaders in I.T. and Management',
    },
];
const featureCards = [
    {
        title: 'Account Registration and Approval',
        description: 'Supports account creation, submission of required documents, and administrative approval before full system access is granted.',
    },
    {
        title: 'Team Management',
        description: 'Organizes student-athletes and coaches under the correct team roster for proper record management.',
    },
    {
        title: 'Schedule Management',
        description: 'Publishes practices, competitions, and other approved team activities with role-appropriate schedule updates.',
    },
    {
        title: 'Attendance Confirmation',
        description: 'Maintains session-based attendance records through approved confirmation workflows for student-athletes, coaches, and administrators.',
    },
    {
        title: 'Training Requirements',
        description: 'Allows coaches to assign schedule-based training instructions to selected student-athletes and maintain printable requirement records.',
    },
    {
        title: 'Academic Eligibility',
        description: 'Tracks academic document submissions, review outcomes, and eligibility decisions for each student-athlete.',
    },
    {
        title: 'Announcements & Alerts',
        description: 'Provides official notices regarding schedules, approvals, reminders, and other varsity-related updates.',
    },
    {
        title: 'Email Notifications',
        description: 'Delivers important account, approval, and process updates through official email communication.',
    },
    {
        title: 'Reports & Printing',
        description: 'Produces printable records for attendance, academic documentation, and schedule-based training requirements.',
    },
];
const faqs = [
    {
        question: 'How do I register as a student-athlete?',
        answer: 'Select Register, complete the student-athlete registration form, and submit the required information and supporting documents.',
    },
    {
        question: 'How long does account approval take?',
        answer: 'Approval time depends on the volume of applications under review. Please ensure that all required documents are complete to avoid delays.',
    },
    {
        question: 'How are coach accounts created?',
        answer: 'Coach accounts are created by an administrator, and onboarding instructions are sent through the registered email address.',
    },
    {
        question: 'Where do I check schedules and attendance?',
        answer: 'After signing in, open the schedule page to review sessions and confirm your attendance records.',
    },
    {
        question: 'How is attendance recorded?',
        answer: 'Attendance is recorded for each scheduled session so that student-athletes, coaches, and administrators can review attendance history accurately.',
    },
    {
        question: 'Why should I keep my schedule and attendance records updated?',
        answer: 'Accurate schedule and attendance records help the varsity program coordinate activities, confirm participation, and support academic follow-up when needed.',
    },
    {
        question: 'How does Academic Eligibility work?',
        answer: 'Student-athletes submit the required academic documents for each review period, and authorized personnel record the eligibility result.',
    },
    {
        question: 'Where can I ask for help?',
        answer: 'Use the Support section to access official contact information, office assistance, and communication channels for system concerns.',
    },
];
const prefersReducedMotion = ref(false);
let motionQuery: MediaQueryList | null = null;
let revealObserver: IntersectionObserver | null = null;
const { isLoading } = useInertiaLoading();
const mobileMenuOpen = ref(false);
const selectedFeatureIndex = ref(0);
const openFaqIndex = ref<number | null>(null);

function scrollToSection(id: string) {
    const behavior: ScrollBehavior = prefersReducedMotion.value ? 'auto' : 'smooth';
    const nextHash = id === 'home' ? '' : `#${id}`;

    if (id === 'home') {
        window.scrollTo({ top: 0, behavior });
    } else {
        document.getElementById(id)?.scrollIntoView({ behavior, block: 'start' });
    }

    const nextUrl = `${window.location.pathname}${window.location.search}${nextHash}`;
    window.history.replaceState(window.history.state, '', nextUrl);
}

function handleSectionNavigation(id: string) {
    mobileMenuOpen.value = false;
    window.requestAnimationFrame(() => scrollToSection(id));
}

function toggleFaq(index: number) {
    openFaqIndex.value = openFaqIndex.value === index ? null : index;
}

function syncSectionFromHash() {
    const hash = window.location.hash.replace(/^#/, '');

    if (!hash) {
        return;
    }

    window.setTimeout(() => scrollToSection(hash), 80);
}

function toLogin() {
    router.visit('Login');
}

function toRegister() {
    router.visit('/Register');
}

function onSealError(event: Event) {
    const target = event.target as HTMLImageElement | null;

    if (!target || target.src.endsWith(fallbackLogo)) {
        return;
    }

    target.src = fallbackLogo;
}

function onMotionPreferenceChange(event: MediaQueryListEvent) {
    prefersReducedMotion.value = event.matches;
}

onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    prefersReducedMotion.value = motionQuery.matches;

    motionQuery.addEventListener('change', onMotionPreferenceChange);

    const revealTargets = document.querySelectorAll<HTMLElement>('.welcome-reveal');
    if (!prefersReducedMotion.value && revealTargets.length) {
        revealObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                    }
                });
            },
            { threshold: 0.14, rootMargin: '0px 0px -48px 0px' },
        );

        revealTargets.forEach((target) => revealObserver?.observe(target));
    } else {
        revealTargets.forEach((target) => target.classList.add('is-visible'));
    }

    syncSectionFromHash();
    window.addEventListener('hashchange', syncSectionFromHash);
});

onBeforeUnmount(() => {
    motionQuery?.removeEventListener('change', onMotionPreferenceChange);
    revealObserver?.disconnect();
    window.removeEventListener('hashchange', syncSectionFromHash);
    document.body.style.overflow = '';
});

watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});
</script>

<template>
    <Head title="Welcome" />

    <div class="page min-h-screen">
        <header class="site-header px-3 py-0 sm:px-4 lg:px-6">
            <div class="nav-shell mx-auto max-w-6xl">
                <button type="button" class="mobile-menu-toggle" aria-label="Open menu" @click="mobileMenuOpen = true">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="header-desktop">
                    <div class="header-actions-desktop">
                        <button @click="toLogin" class="header-link header-link-outline" :disabled="isLoading">Sign In</button>
                        <button @click="toRegister" class="header-link header-link-outline" :disabled="isLoading">Register</button>
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

                    <nav class="header-links-desktop" aria-label="Public pages">
                        <button v-for="item in publicNavItems" :key="item.id" type="button" class="header-link" @click="handleSectionNavigation(item.id)">
                            {{ item.label }}
                        </button>
                    </nav>
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
            </div>
            <nav class="mobile-menu-links">
                <button
                    v-for="item in publicNavItems"
                    :key="item.id"
                    type="button"
                    class="mobile-menu-link"
                    @click="handleSectionNavigation(item.id)"
                >
                    {{ item.label }}
                </button>
            </nav>
        </aside>

        <main class="pb-10">
            <section id="home" class="image-strip-hero public-anchor-section" aria-label="Sports highlights">
                <div class="image-strip">
                    <div class="strip-col strip-col-1" aria-hidden="true"></div>
                    <div class="strip-col strip-col-2" aria-hidden="true"></div>
                    <div class="strip-col strip-col-3" aria-hidden="true"></div>
                </div>

                <div class="strip-overlay">
                    <div class="strip-overlay-inner">
                        <span class="strip-kicker">Asian College Varsity Management Information System</span>
                        <h1>Official Varsity Management in One Institutional Platform</h1>
                        <p>
                            AC-VMIS provides student-athletes, coaches, and administrators with a centralized system for schedules, attendance
                            confirmation, academic requirements, and official updates.
                        </p>
                    </div>
                </div>
            </section>

            <div class="hero-divider" aria-hidden="true"></div>

            <section id="how-it-works" class="section-shell welcome-reveal public-anchor-section info-section how-it-works-section">
                <div class="info-panel how-it-works-panel mx-auto max-w-6xl">
                    <div class="info-intro how-it-works-intro">
                        <p class="how-it-works-title-wrap">
                            <span class="how-it-works-title">Process Overview</span>
                        </p>
                        <h2><span class="title-chip title-chip-blue">A clear step-by-step process from selection to regular system use.</span></h2>
                        <p class="section-copy">The system follows a structured process so that first-time users can complete each requirement with confidence.</p>
                    </div>

                    <div class="steps-grid how-it-works-grid">
                        <article
                            v-for="(step, index) in howItWorksSteps"
                            :key="step"
                            class="info-card step-card how-it-works-card"
                            :style="{ transitionDelay: `${index * 90}ms` }"
                        >
                            <span class="step-number">{{ index + 1 }}</span>
                            <p>{{ step }}</p>
                        </article>
                    </div>

                    <article class="info-card info-card-wide how-it-works-summary">
                        <h3>After Approval</h3>
                        <p>
                            Once approved, sign in and use the pages assigned to your role. Student-athletes remain connected to their official team roster so
                            schedules, attendance records, and updates remain properly organized.
                        </p>
                    </article>
                </div>
            </section>

            <section class="role-strip-wrap section-shell welcome-reveal">
                <div class="role-strip mx-auto max-w-6xl">
                        <article class="role-card role-card-left">
                        <div class="role-icon student-icon" aria-hidden="true"></div>
                        <div>
                            <h3>Student-Athletes</h3>
                            <p>Review official schedules, confirm attendance, submit academic requirements, and check assigned training instructions.</p>
                        </div>
                    </article>
                    <div class="coach-card-wrap">
                        <article class="role-card role-card-right">
                            <div class="role-icon coach-icon" aria-hidden="true"></div>
                            <div>
                            <h3>Coaches</h3>
                            <p>Manage team schedules, document attendance, assign training requirements, and oversee roster and compliance records.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mobile-first-wrap section-shell welcome-reveal">
                <div class="mobile-first mx-auto max-w-6xl">
                    <p class="mobile-first-kicker"><span class="title-chip">Mobile Access</span></p>
                    <h2><span class="title-chip title-chip-blue">Designed for convenient use on mobile devices during training and competition days.</span></h2>
                    <div class="mobile-first-media" aria-hidden="true"></div>
                    <p>
                        Access AC-VMIS from your mobile browser to review sessions, confirm attendance, and submit academic requirements. The
                        same workflow is also available on tablet and desktop devices.
                    </p>
                </div>
            </section>

            <div class="full-divider mobile-divider" aria-hidden="true"></div>

            <section class="pathway-wrap section-shell welcome-reveal">
                <div class="pathway-grid mx-auto max-w-6xl">
                    <div class="departments-showcase">
                    <p class="pathway-kicker"><span class="title-chip">Academic Coverage</span></p>
                    <h2><span class="title-chip">Supports varsity management from Senior High School to the college level in one unified platform.</span></h2>

                        <div class="department-logos" role="list" aria-label="Asian College Departments">
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img src="/images/shs.png" alt="Senior High School Seal" class="dept-logo" @error="onSealError" />
                                <div class="dept-tooltip">Senior High School</div>
                            </div>
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img src="/images/ba.png" alt="College of Business Administration Seal" class="dept-logo" @error="onSealError" />
                                <div class="dept-tooltip">College of Business Administration</div>
                            </div>
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img
                                    src="/images/cs.png"
                                    alt="College of Computer Studies and Engineering Seal"
                                    class="dept-logo"
                                    @error="onSealError"
                                />
                                <div class="dept-tooltip">College of Computer Studies and Engineering</div>
                            </div>
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img src="/images/dp.png" alt="Diploma Program Department Seal" class="dept-logo" @error="onSealError" />
                                <div class="dept-tooltip">Diploma Program Department</div>
                            </div>
                            <div class="dept-item" role="listitem" tabindex="0">
                                <img src="/images/hm.png" alt="Hospitality and Tourism Management Seal" class="dept-logo" @error="onSealError" />
                                <div class="dept-tooltip">Hospitality and Tourism Management</div>
                            </div>
                        </div>

                        <p class="departments-desc">
                            Student-athletes from Senior High School through college use the same platform, helping the institution maintain clear schedules,
                            attendance records and academic status in one place.
                        </p>
                    </div>
                </div>

                <div class="pathway-footer mx-auto max-w-6xl">
                    <div class="pathway-footer-inner">
                        <div class="pathway-divider" aria-hidden="true"></div>
                        <div class="pathway-note">
                            <h3><span class="title-chip title-chip-blue">Eligibility Overview</span></h3>
                            <div class="eligibility-media" aria-hidden="true"></div>
                            <p>Required documents, academic standing, and attendance expectations are recorded in one system.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="about" class="section-shell welcome-reveal public-anchor-section info-section">
                <div class="about-section mx-auto max-w-6xl">
                    <div class="about-hero">
                        <div class="about-intro">
                            <p class="section-kicker"><span class="title-chip">About Asian College and AC-VMIS</span></p>
                            <h2>
                                <span class="title-chip title-chip-blue">A school-centered varsity system built around education, leadership, and organized team management.</span>
                            </h2>
                        </div>
                    </div>

                    <div class="about-story-grid">
                        <article
                            v-for="(item, index) in aboutStoryCards"
                            :key="item.title"
                            class="about-story-card"
                            :class="{ 'about-story-card-wide': index === 2 }"
                            :style="{ transitionDelay: `${index * 110}ms` }"
                        >
                            <h3>{{ item.title }}</h3>
                            <div class="about-story-copy">
                                <p v-for="paragraph in item.paragraphs" :key="paragraph">{{ paragraph }}</p>
                            </div>
                        </article>
                    </div>

                    <div class="about-grid">
                        <article
                            v-for="(item, index) in aboutHighlights"
                            :key="item.title"
                            class="about-card"
                            :style="{ transitionDelay: `${index * 100}ms` }"
                        >
                            <h3>{{ item.title }}</h3>
                            <p v-if="item.description">{{ item.description }}</p>
                            <ul v-else class="about-list">
                                <li v-for="entry in item.items" :key="entry">{{ entry }}</li>
                            </ul>
                        </article>
                    </div>
                </div>
            </section>

            <section id="features" class="features-wrap welcome-reveal public-anchor-section">
                <div class="section-shell features-minimal mx-auto max-w-6xl">
                    <p class="features-kicker"><span class="title-chip">System Services</span></p>
                    <p class="features-copy">
                        Each service is designed to support routine varsity responsibilities through one coordinated system for scheduling, documentation, communication, and reporting.
                    </p>

                    <div class="features-interactive-shell">
                        <div class="feature-title-list" role="tablist" aria-label="Core features">
                            <button
                                v-for="(feature, index) in featureCards"
                                :key="feature.title"
                                type="button"
                                class="feature-title-button"
                                :class="{ 'is-active': selectedFeatureIndex === index }"
                                :aria-selected="selectedFeatureIndex === index"
                                :tabindex="selectedFeatureIndex === index ? 0 : -1"
                                @click="selectedFeatureIndex = index"
                            >
                                <span class="feature-title-index">{{ String(index + 1).padStart(2, '0') }}</span>
                                <span class="feature-title-text">{{ feature.title }}</span>
                            </button>
                        </div>

                        <div class="feature-detail-stage">
                            <Transition name="feature-detail" mode="out-in">
                                <article
                                    :key="featureCards[selectedFeatureIndex].title"
                                    class="feature-detail-card"
                                    role="tabpanel"
                                    :aria-label="featureCards[selectedFeatureIndex].title"
                                >
                                    <p class="feature-detail-kicker">Selected Service</p>
                                    <h3>{{ featureCards[selectedFeatureIndex].title }}</h3>
                                    <p>{{ featureCards[selectedFeatureIndex].description }}</p>
                                </article>
                            </Transition>
                        </div>
                    </div>
                </div>
            </section>

            <section id="faq" class="section-shell welcome-reveal public-anchor-section info-section">
                <div class="faq-section mx-auto max-w-4xl">
                    <div class="info-intro faq-intro">
                        <p class="section-kicker"><span class="title-chip">Frequently Asked Questions</span></p>
                        <h2><span class="title-chip title-chip-blue">Guidance for student-athletes, coaches, and first-time users.</span></h2>
                    </div>

                    <div class="faq-accordion">
                        <article v-for="(item, index) in faqs" :key="item.question" class="faq-item" :class="{ 'is-open': openFaqIndex === index }">
                            <button type="button" class="faq-trigger" :aria-expanded="openFaqIndex === index" @click="toggleFaq(index)">
                                <span class="faq-question">{{ item.question }}</span>
                                <span class="faq-chevron" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25">
                                        <path d="M6 9l6 6 6-6" />
                                    </svg>
                                </span>
                            </button>

                            <div class="faq-answer-shell">
                                <div class="faq-answer-body">
                                    <p>{{ item.answer }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="register-cta-wrap section-shell welcome-reveal">
                <div class="register-cta mx-auto max-w-6xl">
                    <p class="cta-kicker"><span class="title-chip">Account Access</span></p>
                    <h2><span class="register-cta-heading">Begin the registration process and complete the required steps for system access.</span></h2>
                    <p>
                        Student-athletes may register, submit the required documents, and wait for administrative approval. Coach accounts are created by an administrator.
                    </p>

                    <div class="cta-actions">
                        <button @click="toRegister" class="btn-fill">Register</button>
                        <button @click="toLogin" class="btn-outline">I Already Have an Account</button>
                    </div>
                </div>
            </section>

            <section id="contact" class="section-shell welcome-reveal public-anchor-section info-section">
                <div class="info-panel mx-auto max-w-6xl">
                    <div class="info-intro">
                        <p class="section-kicker"><span class="title-chip">Support</span></p>
                        <h2><span class="title-chip title-chip-blue">Official support channels for account, access, and system concerns.</span></h2>
                    </div>

                    <div class="support-hub-grid">
                        <article class="support-card support-primary-card">
                            <p class="support-card-kicker">Support Services</p>
                            <h3>Contact and Assistance</h3>
                            <p class="support-card-copy">For faster assistance, include your full name, user role, and a clear description of the concern.</p>

                            <p class="support-best-chip">Recommended contact method: Send an email first, then call during office hours when necessary.</p>

                            <div class="support-contact-list">
                                <p class="support-contact-item">
                                    <span class="support-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="5" width="18" height="14" rx="2" />
                                            <path d="M3 7l9 6 9-6" />
                                        </svg>
                                    </span>
                                    <span>varsity.support@asiancollege.edu.ph</span>
                                </p>
                                <p class="support-contact-item">
                                    <span class="support-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path
                                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.34 1.77.66 2.6a2 2 0 0 1-.45 2.11L8 9.9a16 16 0 0 0 6.1 6.1l1.47-1.32a2 2 0 0 1 2.11-.45c.83.32 1.7.54 2.6.66A2 2 0 0 1 22 16.92z"
                                            />
                                        </svg>
                                    </span>
                                    <span>+63 000 000 0000</span>
                                </p>
                                <p class="support-contact-item">
                                    <span class="support-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2l8 4v6c0 5-3.4 9.4-8 10-4.6-.6-8-5-8-10V6l8-4z" />
                                            <path d="M9.5 12.5l1.8 1.8 3.2-3.2" />
                                        </svg>
                                    </span>
                                    <span>Student Affairs and Sports Development Office</span>
                                </p>
                            </div>

                            <div class="support-cta-row">
                                <a href="mailto:varsity.support@asiancollege.edu.ph" class="support-btn support-btn-fill">Send Email</a>
                                <a href="tel:+630000000000" class="support-btn support-btn-outline">Call Office</a>
                            </div>
                        </article>

                        <div class="support-hub-side">
                            <article class="support-card support-side-card support-form-card">
                                <h3>Submit an Inquiry</h3>
                                <form class="support-form" @submit.prevent>
                                    <input type="text" class="support-input" placeholder="Full name" />
                                    <input type="email" class="support-input" placeholder="Email address" />
                                    <select class="support-input support-select">
                                        <option>Account Access</option>
                                        <option>Registration</option>
                                        <option>Schedule Concern</option>
                                        <option>Other Concern</option>
                                    </select>
                                    <textarea class="support-input support-textarea" rows="3" placeholder="Provide a brief summary of your concern."></textarea>
                                    <button type="submit" class="support-btn support-btn-fill support-submit">Submit Inquiry</button>
                                </form>
                            </article>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="site-footer section-shell relative z-10">
            <div class="footer-shell mx-auto max-w-6xl">
                <div class="footer-grid">
                    <section class="footer-col footer-col-brand">
                        <p class="footer-brand">Asian College Varsity Management Information System</p>
                        <p class="footer-copy">
                            An institutional platform for managing schedules, attendance records, academic eligibility, and official varsity communications.
                        </p>
                        <p class="footer-contact">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="contact-icon" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="M3 7l9 6 9-6" />
                            </svg>
                            <span>varsity.support@asiancollege.edu.ph</span>
                        </p>
                        <p class="footer-contact">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="contact-icon" aria-hidden="true">
                                <path
                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.34 1.77.66 2.6a2 2 0 0 1-.45 2.11L8 9.9a16 16 0 0 0 6.1 6.1l1.47-1.32a2 2 0 0 1 2.11-.45c.83.32 1.7.54 2.6.66A2 2 0 0 1 22 16.92z"
                                />
                            </svg>
                            <span>+63 000 000 0000</span>
                        </p>
                        <div class="footer-socials" aria-label="Social Links">
                            <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="Facebook">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="social-icon" aria-hidden="true">
                                    <path d="M15 3h-2a4 4 0 0 0-4 4v2H7v4h2v8h4v-8h2.5l.5-4H13V7a1 1 0 0 1 1-1h2V3z" />
                                </svg>
                            </a>
                        </div>
                    </section>

                    <nav class="footer-col" aria-label="Public Pages">
                        <p class="footer-heading"><span class="title-chip">Public Pages</span></p>
                        <div class="footer-link-list">
                            <button
                                v-for="item in publicNavItems"
                                :key="item.id"
                                type="button"
                                class="footer-link footer-link-btn"
                                @click="handleSectionNavigation(item.id)"
                            >
                                {{ item.label }}
                            </button>
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
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

.page {
    --brand-blue: var(--blue-light-primary);
    --brand-line: rgba(3, 68, 133, 0.45);
    --brand-line-soft: rgba(3, 68, 133, 0.28);
    --page-bg: var(--blue-light-bg);
    --page-surface: var(--blue-light-surface);
    --page-surface-alt: var(--blue-light-surface-alt);
    --page-text: var(--blue-light-text);
    --page-text-muted: var(--blue-light-text-muted);
    --page-header-bg: rgba(247, 251, 255, 0.92);
    --page-accent: var(--blue-light-primary);
    --page-accent-strong: var(--blue-light-primary-strong);
    --page-accent-soft: #93c5fd;
    --page-btn-text: #ffffff;
    --page-hover-bg: rgba(3, 68, 133, 0.1);
    --page-card-shadow: rgba(3, 68, 133, 0.18);
    --page-card-shadow-strong: rgba(3, 68, 133, 0.28);
    --page-hero-overlay-strong: rgba(3, 20, 40, 0.68);
    --page-hero-overlay-mid: rgba(3, 20, 40, 0.52);
    --page-hero-overlay-soft: rgba(3, 20, 40, 0.38);
    --page-hero-glow-1: rgba(255, 255, 255, 0.2);
    --page-hero-glow-2: rgba(147, 197, 253, 0.2);
    --page-hero-glow-3: rgba(14, 116, 253, 0.16);
    --feature-bg-1: #0b2f5f;
    --feature-bg-2: #0f3b73;
    --feature-bg-3: #145aa6;
    --feature-bg-4: #1b6ec2;
    --title-chip-bg: #ffffff;
    --title-chip-text: #0b1b2b;
    --feature-glow-1: rgba(96, 165, 250, 0.35);
    --feature-glow-2: rgba(3, 20, 40, 0.45);
    --feature-card-bg: var(--page-surface);
    --feature-card-text: var(--page-text-muted);
    --feature-card-title: var(--page-text);
    --space-page-x: clamp(1rem, 3.2vw, 2.5rem);
    --space-section-y: clamp(1.6rem, 4.5vw, 3.6rem);
    --title-xl: clamp(1.7rem, 3.4vw, 2.6rem);
    --title-lg: clamp(1.35rem, 2.6vw, 1.9rem);
    --body-md: clamp(0.95rem, 1.6vw, 1.05rem);
    background: var(--page-bg);
    color: var(--page-text);
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    font-size: 1rem;
    line-height: 1.6;
    overflow-x: hidden;
}

.welcome-reveal {
    opacity: 0;
    transform: translateY(14px);
    transition:
        opacity 420ms cubic-bezier(0.22, 1, 0.36, 1),
        transform 420ms cubic-bezier(0.22, 1, 0.36, 1);
}

.welcome-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.public-anchor-section {
    scroll-margin-top: 6.5rem;
}

.section-shell {
    padding-left: var(--space-page-x);
    padding-right: var(--space-page-x);
}

.brand {
    font-family: inherit;
    font-weight: 800;
}

.text-slate-500,
.text-slate-600,
.text-slate-700 {
    color: var(--page-text-muted);
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
}

.logo-triangle path {
    fill: #ffffff;
}

.logo-inside-triangle {
    position: absolute;
    top: 23px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 60px;
    border-radius: 999px;
    background: #fff;
    padding: 3px;
    object-fit: contain;
    box-shadow: none;
}

.site-header {
    position: sticky;
    top: 0;
    z-index: 35;
    background: #ffffff;
    border-bottom: none;
    backdrop-filter: blur(2px);
    overflow: visible;
}

.nav-shell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 66px;
    padding: 2px 14px 1px;
    overflow: visible;
}

.mobile-brand {
    display: none;
    align-items: center;
    gap: 0.5rem;
}

.mobile-notch {
    display: none;
}

.mobile-brand-logo {
    width: 44px;
    height: 44px;
    border-radius: 999px;
    background: #ffffff;
    padding: 4px;
    border: 1px solid var(--brand-line);
    object-fit: contain;
}

.header-actions {
    display: flex;
    flex-wrap: wrap;
}

.mobile-menu-toggle {
    display: none;
    flex-direction: column;
    gap: 5px;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid var(--brand-line);
    background: #ffffff;
}

.mobile-menu-toggle span {
    width: 18px;
    height: 2px;
    background: var(--brand-blue);
    border-radius: 999px;
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

.header-links-desktop {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
    flex: 1 1 auto;
    min-width: 0;
}

.header-link {
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    padding: 6px 14px;
    border: none;
    border-radius: 999px;
    background: var(--brand-blue);
    line-height: 1.35;
    text-align: center;
    white-space: nowrap;
    cursor: pointer;
}

.header-link-outline {
    color: var(--brand-blue);
    background: #ffffff;
    border: 1px solid rgba(3, 68, 133, 0.42);
}

.header-link:hover {
    color: #ffffff;
    background: var(--page-accent-strong);
}

.header-link-outline:hover {
    color: var(--brand-blue);
    background: rgba(3, 68, 133, 0.08);
}

.header-actions-desktop .header-link {
    color: var(--brand-blue);
    background: #ffffff;
    border: 1px solid rgba(3, 68, 133, 0.42);
}

.header-actions-desktop .header-link:hover {
    color: var(--brand-blue);
    background: rgba(3, 68, 133, 0.08);
}

.header-actions-desktop .header-link:active,
.header-actions-desktop .header-link.is-active {
    color: var(--brand-blue);
    background: rgba(147, 197, 253, 0.55);
    border-color: rgba(59, 130, 246, 0.55);
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
    background: #ffffff;
    border-right: 1px solid var(--brand-line-soft);
    padding: 1.2rem 1rem;
    z-index: 70;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transform: translateX(-100%);
    transition: transform 220ms ease;
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
    font-size: 0.9rem;
    font-weight: 700;
    color: #0b1b2b;
}

.mobile-menu-close {
    border: 1px solid var(--brand-line);
    background: #ffffff;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.75rem;
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
    display: block;
    width: 100%;
    padding: 0.6rem 0.75rem;
    border-radius: 999px;
    border: 1px solid var(--brand-line-soft);
    color: var(--brand-blue);
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    text-align: left;
    background: #ffffff;
    transition:
        background 150ms ease,
        border-color 150ms ease;
}

.mobile-menu-link:hover {
    background: rgba(3, 68, 133, 0.08);
    border-color: var(--brand-blue);
}

.btn-fill,
.btn-outline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    min-height: 38px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: var(--brand-blue);
    background: #ffffff;
    border: 1px solid var(--brand-blue);
    text-align: center;
}

.btn-fill {
    color: var(--brand-blue);
    border-color: var(--brand-blue);
    background: #ffffff;
}

.btn-outline {
    color: var(--brand-blue);
    border-color: var(--brand-blue);
    background: #ffffff;
}

.btn-fill:disabled,
.btn-outline:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.image-strip-hero {
    position: relative;
    min-height: 72vh;
    border-top: 1px solid var(--brand-line);
    border-bottom: 1px solid var(--brand-line);
    overflow: hidden;
    background: #0f172a;
    margin: 0 clamp(0.75rem, 2.6vw, 1.5rem);
    border-radius: 18px;
    scrollbar-width: none;
}

.image-strip-hero::-webkit-scrollbar {
    display: none;
}

.hero-divider {
    height: 1px;
    background: rgba(3, 68, 133, 0.45);
    width: 50%;
    margin-left: auto;
    margin-right: auto;
    margin-top: 0.85rem;
    margin-bottom: 1.1rem;
}

.image-strip {
    display: flex;
    width: 100%;
    height: 100%;
    min-height: 72vh;
}

.strip-col {
    flex: 1 1 0%;
    min-height: 72vh;
    background-size: cover;
    background-position: center;
    filter: saturate(1.05) contrast(1.02);
    transform: scale(1.04);
    opacity: 0.82;
    animation: heroStripReveal 900ms ease forwards;
    will-change: transform, opacity;
}

.strip-col-1 {
    background-image: url('/images/hero-basketball.webp');
    animation-delay: 60ms;
}

.strip-col-2 {
    background-image: url('/images/hero-volleyball.webp');
    animation-delay: 120ms;
}

.strip-col-3 {
    background-image: url('/images/hero-soccer.webp');
    animation-delay: 180ms;
}

.strip-overlay {
    position: absolute;
    inset: 0;
    display: grid;
    align-items: center;
    padding: clamp(1.6rem, 4vw, 2.6rem) clamp(1rem, 3vw, 1.75rem);
    background: linear-gradient(90deg, rgba(3, 20, 40, 0.75) 0%, rgba(3, 20, 40, 0.2) 60%, rgba(3, 20, 40, 0.05) 100%);
}

.strip-overlay-inner {
    max-width: min(100%, 560px);
    color: #fff;
    opacity: 0;
    transform: translateY(12px);
    animation: heroTextReveal 700ms ease forwards;
    animation-delay: 260ms;
    will-change: transform, opacity;
}

.strip-kicker {
    display: inline-flex;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-weight: 700;
}

.strip-overlay-inner h1 {
    margin: 0.9rem 0 0.6rem;
    font-size: var(--title-xl);
    line-height: 1.15;
    font-weight: 800;
}

.strip-overlay-inner p {
    margin: 0;
    font-size: var(--body-md);
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.88);
}

.strip-version {
    margin-top: 1rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 700;
}

@keyframes heroStripReveal {
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes heroTextReveal {
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .strip-col,
    .strip-overlay-inner {
        animation: none;
        transform: none;
        opacity: 1;
    }
}

.title-chip {
    display: inline-block;
    padding: 0.22rem 0.7rem;
    border-radius: 999px;
    background: var(--title-chip-bg);
    color: var(--title-chip-text);
    line-height: 1.2;
    -webkit-box-decoration-break: clone;
    box-decoration-break: clone;
}

.title-chip-blue {
    background: var(--brand-blue);
    color: #ffffff;
}

.role-strip-wrap {
    margin-top: 1rem;
    min-height: auto;
}

.role-strip {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 2rem;
    width: 100%;
}

.coach-card-wrap {
    display: flex;
    align-items: flex-start;
    margin-top: 2.4rem;
    margin-left: auto;
    flex: 1;
}

.role-card {
    display: flex;
    gap: 0.85rem;
    padding: clamp(1rem, 2.6vw, 1.35rem) clamp(1.1rem, 3vw, 1.6rem);
    align-items: flex-start;
    justify-content: flex-start;
    min-height: 0;
    background: #0b2f5f;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 18px;
    box-shadow: 0 14px 26px -20px rgba(3, 20, 40, 0.6);
    transition:
        transform 180ms cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 180ms cubic-bezier(0.22, 1, 0.36, 1);
    flex: 1;
    max-width: 520px;
}

.role-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px var(--page-card-shadow);
}

.role-card-right {
    margin-top: 0;
}

.role-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.08);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
}

.role-icon::before,
.role-icon::after {
    content: '';
    position: absolute;
}

.student-icon::before {
    width: 14px;
    height: 14px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 999px;
    top: 6px;
    left: 11px;
}

.student-icon::after {
    width: 18px;
    height: 10px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-top: none;
    border-radius: 0 0 10px 10px;
    bottom: 6px;
    left: 9px;
}

.coach-icon::before {
    width: 18px;
    height: 2px;
    background: rgba(255, 255, 255, 0.8);
    transform: rotate(-24deg);
    top: 13px;
    left: 10px;
}

.coach-icon::after {
    width: 8px;
    height: 8px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 999px;
    bottom: 7px;
    left: 15px;
}

.role-card h3 {
    font-size: clamp(1rem, 2.2vw, 1.2rem);
    color: #ffffff;
    font-weight: 700;
}

.role-card p {
    margin-top: 0.3rem;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.5;
}

.mobile-first-wrap {
    padding-top: var(--space-section-y);
}

.mobile-first {
    padding-top: 1rem;
    margin: 1.5rem 0;
}

.mobile-first-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--page-text-muted);
    font-weight: 700;
}

.mobile-first h2 {
    margin-top: 0.4rem;
    font-size: var(--title-lg);
    color: var(--page-text);
    line-height: 1.25;
    font-weight: 800;
}

.mobile-first p {
    margin-top: 0.6rem;
    color: var(--page-text-muted);
    line-height: 1.6;
    max-width: 72ch;
    font-size: var(--body-md);
}

.mobile-first-media {
    margin-top: 0.9rem;
    border-radius: 18px;
    border: 1px solid var(--brand-blue);
    background-image: url('/images/mobile-messaging-modern-communication-technology-online-chatting-sms-texting-modern-leisure-activity-guy-checking-email-inbox-with-smartphone_335657-3526.avif');
    background-size: cover;
    background-position: center;
    position: relative;
    overflow: hidden;
    width: min(240px, 70vw);
    aspect-ratio: 1 / 1;
}

.full-divider {
    width: 100%;
    height: 2px;
    background: var(--brand-line);
    margin-top: 1.25rem;
}

.mobile-divider {
    width: 75%;
    height: 1px;
    background: rgba(3, 68, 133, 0.45);
    margin: 1.6rem 0 0 1.25rem;
}

.pathway-wrap {
    padding-top: clamp(1rem, 2vw, 1.5rem);
}

.pathway-grid {
    display: block;
    border: 1px solid var(--brand-line);
    border-radius: 18px;
    background: var(--brand-blue);
    padding: clamp(1.6rem, 3.6vw, 2.6rem) clamp(1.4rem, 4vw, 2.6rem);
    margin: clamp(2rem, 5vw, 3rem) auto;
    width: min(100%, 980px);
}

.pathway-footer {
    width: min(100%, 980px);
    margin: 1.5rem auto 3.2rem;
}

.pathway-footer-inner {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    padding-right: 2.4rem;
}

.pathway-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--page-text-muted);
    font-weight: 700;
}

.departments-showcase h2 {
    margin-top: 0.4rem;
    font-size: var(--title-lg);
    line-height: 1.25;
    color: #ffffff;
    font-weight: 800;
    text-align: center;
}

.departments-showcase {
    text-align: center;
    color: #ffffff;
}

.department-logos {
    margin-top: 1rem;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: clamp(0.6rem, 2.4vw, 1.05rem);
}

.dept-item {
    position: relative;
    width: 104px;
    height: 104px;
    border-radius: 999px;
    border: 1px solid var(--brand-line);
    background: var(--page-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem;
    box-shadow: 0 10px 20px var(--page-card-shadow);
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        border-color 0.18s ease;
    cursor: default;
}

.dept-item:hover,
.dept-item:focus-visible {
    transform: translateY(-2px);
    border-color: var(--brand-blue);
    box-shadow: 0 14px 24px var(--page-card-shadow-strong);
    outline: none;
}

.dept-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.dept-tooltip {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    width: max-content;
    max-width: 220px;
    padding: 0.4rem 0.55rem;
    border-radius: 9px;
    background: var(--page-accent);
    border: 1px solid rgba(255, 255, 255, 0.8);
    color: #ffffff;
    font-size: 0.74rem;
    line-height: 1.25;
    text-align: center;
    white-space: normal;
    overflow-wrap: anywhere;
    opacity: 0;
    visibility: hidden;
    transition:
        opacity 0.16s ease,
        transform 0.16s ease;
    box-shadow: 0 10px 18px var(--page-card-shadow-strong);
    z-index: 5;
}

.dept-item:hover .dept-tooltip,
.dept-item:focus-visible .dept-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.departments-desc {
    margin: 1rem auto 0;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.6;
    max-width: 76ch;
    text-align: center;
    font-size: var(--body-md);
}

.pathway-divider {
    height: 1px;
    width: 70%;
    margin: 1.4rem 0 1.6rem;
    background: rgba(3, 68, 133, 0.6);
}

.pathway-note {
    max-width: 320px;
    text-align: right;
}

.pathway-note h3 {
    margin: 0;
    font-size: clamp(1.1rem, 2.4vw, 1.3rem);
    font-weight: 700;
    color: #0b1b2b;
}

.pathway-note p {
    margin-top: 0.4rem;
    font-size: var(--body-md);
    line-height: 1.5;
    color: rgba(11, 27, 43, 0.75);
}

.eligibility-media {
    margin-top: 0.85rem;
    width: min(240px, 70vw);
    aspect-ratio: 1 / 1;
    border-radius: 16px;
    border: 1px solid var(--brand-blue);
    background-image: url('/images/checking-inventory-flat-style-design-vector-illustration-stock-illustration_357500-43.avif');
    background-size: cover;
    background-position: center;
}

.features-divider {
    margin-top: 1.5rem;
}

.features-wrap {
    padding: clamp(2rem, 5vw, 3rem) 0;
    background: var(--brand-blue);
    color: #ffffff;
    margin: 0 0.75rem;
    border-radius: 18px;
    overflow: hidden;
    --title-chip-bg: #ffffff;
    --title-chip-text: #0b1b2b;
    background-image: url('/images/Maximizing-Oracle-Apps-Technical-Tips-and-Tricks-for-Developers.webp');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
}

.features-wrap::before {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(3, 20, 40, 0.72);
    z-index: 0;
}

.features-minimal {
    position: relative;
    z-index: 1;
}

.features-minimal {
    display: grid;
    gap: 1rem;
    max-width: 62rem;
}

.features-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.75);
}

.features-minimal h2 {
    margin: 0;
    font-size: var(--title-lg);
    line-height: 1.2;
    font-weight: 800;
    color: #ffffff;
}

.features-copy {
    margin: -0.2rem 0 0;
    max-width: 48rem;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.94rem;
    line-height: 1.6;
}

.features-interactive-shell {
    display: grid;
    grid-template-columns: minmax(220px, 300px) minmax(0, 1fr);
    gap: 1rem;
    align-items: stretch;
}

.feature-title-list {
    display: grid;
    gap: 0.55rem;
}

.feature-title-button {
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    gap: 0.85rem;
    width: 100%;
    padding: 0.78rem 0.9rem;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
    text-align: left;
    cursor: pointer;
    backdrop-filter: blur(4px);
    transition:
        transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 220ms ease,
        background 220ms ease,
        box-shadow 220ms ease;
}

.feature-title-button:hover,
.feature-title-button:focus-visible {
    transform: translateX(3px);
    border-color: rgba(255, 255, 255, 0.4);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 20px 38px -34px rgba(3, 20, 40, 0.78);
    outline: none;
}

.feature-title-button.is-active {
    border-color: rgba(255, 255, 255, 0.58);
    background: rgba(255, 255, 255, 0.18);
    box-shadow: 0 24px 42px -34px rgba(3, 20, 40, 0.82);
}

.feature-title-index {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.2rem;
    height: 2.2rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.28);
    color: rgba(255, 255, 255, 0.88);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.feature-title-button.is-active .feature-title-index {
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.5);
    color: #ffffff;
}

.feature-title-text {
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.4;
}

.feature-detail-stage {
    position: relative;
    min-height: 100%;
}

.feature-detail-card {
    display: grid;
    align-content: center;
    gap: 0.75rem;
    min-height: 100%;
    padding: clamp(1.1rem, 2.8vw, 1.7rem);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background:
        radial-gradient(circle at top right, rgba(147, 197, 253, 0.24), transparent 35%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.08));
    box-shadow: 0 30px 62px -42px rgba(3, 20, 40, 0.8);
    backdrop-filter: blur(8px);
}

.feature-detail-kicker {
    margin: 0;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.7);
}

.feature-detail-card h3 {
    margin: 0;
    font-size: clamp(1.2rem, 2.4vw, 1.75rem);
    line-height: 1.1;
    font-weight: 800;
    color: #ffffff;
}

.feature-detail-card p:last-child {
    margin: 0;
    max-width: 32rem;
    font-size: 0.94rem;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.84);
}

.feature-detail-enter-active,
.feature-detail-leave-active {
    transition:
        opacity 240ms ease,
        transform 240ms cubic-bezier(0.22, 1, 0.36, 1);
}

.feature-detail-enter-from {
    opacity: 0;
    transform: translateY(18px);
}

.feature-detail-leave-to {
    opacity: 0;
    transform: translateY(-14px);
}

.about-section {
    display: grid;
    gap: 1.25rem;
}

.about-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1rem;
    align-items: stretch;
}

.about-intro {
    display: grid;
    gap: 0.5rem;
    justify-items: center;
    max-width: 58rem;
    margin: 0 auto;
    text-align: center;
}

.about-spotlight {
    position: relative;
    overflow: hidden;
    display: grid;
    align-content: end;
    gap: 0.7rem;
    min-height: 100%;
    padding: 1.25rem 1.2rem;
    border-radius: 24px;
    background:
        radial-gradient(circle at top right, rgba(147, 197, 253, 0.34), transparent 38%),
        linear-gradient(135deg, rgba(3, 68, 133, 0.98), rgba(27, 110, 194, 0.92));
    box-shadow: 0 28px 54px -40px rgba(3, 20, 40, 0.8);
    color: #ffffff;
}

.about-spotlight::before {
    content: '';
    position: absolute;
    inset: auto -18% -42% auto;
    width: 12rem;
    height: 12rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    filter: blur(4px);
}

.about-spotlight-label,
.about-spotlight-copy {
    position: relative;
    z-index: 1;
    margin: 0;
}

.about-spotlight-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.75);
}

.about-spotlight-copy {
    font-size: 1rem;
    line-height: 1.75;
    font-weight: 600;
}

.info-section {
    padding-top: clamp(1.5rem, 4vw, 2.6rem);
}

.how-it-works-section {
    padding-top: clamp(1.8rem, 4.8vw, 3rem);
}

.info-panel {
    display: grid;
    gap: 1.2rem;
}

.how-it-works-panel {
    justify-items: center;
    gap: 1.5rem;
}

.info-intro {
    display: grid;
    gap: 0.45rem;
    max-width: 72ch;
}

.how-it-works-intro {
    text-align: center;
    max-width: 56rem;
}

.how-it-works-title-wrap {
    margin: 0;
    display: flex;
    justify-content: center;
}

.how-it-works-title {
    display: inline-block;
    font-size: clamp(2.8rem, 10vw, 6.2rem);
    line-height: 0.95;
    font-weight: 900;
    letter-spacing: -0.05em;
    text-transform: uppercase;
    color: var(--brand-blue);
    text-shadow:
        0 10px 24px rgba(147, 197, 253, 0.4),
        0 20px 44px rgba(3, 68, 133, 0.18);
    transform: translateY(18px) scale(0.94);
    opacity: 0;
}

.how-it-works-section.is-visible .how-it-works-title {
    animation: howItWorksTitleReveal 900ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.how-it-works-title::after {
    content: '';
    display: block;
    width: 100%;
    height: 0.38rem;
    margin-top: 0.45rem;
    border-radius: 999px;
    background: linear-gradient(90deg, rgba(3, 68, 133, 0.12), rgba(3, 68, 133, 0.95), rgba(147, 197, 253, 0.65));
    transform-origin: center;
    transform: scaleX(0.12);
    opacity: 0;
    animation: howItWorksUnderline 900ms cubic-bezier(0.22, 1, 0.36, 1) 180ms forwards;
}

.section-kicker {
    margin: 0;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--page-text-muted);
    font-weight: 700;
}

.info-intro h2 {
    margin: 0;
    font-size: var(--title-lg);
    line-height: 1.25;
    color: var(--page-text);
    font-weight: 800;
}

.section-copy {
    margin: 0;
    color: var(--page-text-muted);
    font-size: var(--body-md);
}

.about-story-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    align-items: stretch;
}

.about-story-card {
    position: relative;
    display: grid;
    gap: 0.75rem;
    min-height: 100%;
    padding: clamp(1.1rem, 2.5vw, 1.45rem);
    border-radius: 18px;
    border: 1px solid rgba(3, 68, 133, 0.16);
    background: #ffffff;
    box-shadow: 0 24px 46px -38px rgba(3, 68, 133, 0.34);
    text-align: center;
    transition:
        transform 260ms cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 260ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 260ms ease,
        opacity 420ms ease;
}

.about-story-card:hover {
    transform: translateY(-5px);
    border-color: rgba(3, 68, 133, 0.3);
    box-shadow: 0 30px 54px -38px rgba(3, 68, 133, 0.42);
}

.about-story-card-wide {
    grid-column: 1 / -1;
    max-width: 58rem;
    justify-self: center;
}

.about-story-card h3 {
    margin: 0;
    color: var(--page-text);
    font-size: 1.12rem;
    line-height: 1.28;
    font-weight: 800;
}

.about-story-copy {
    display: grid;
    gap: 0.8rem;
}

.about-story-copy p {
    margin: 0;
    color: var(--page-text-muted);
    font-size: 0.97rem;
    line-height: 1.78;
}

.info-grid,
.steps-grid {
    display: grid;
    gap: 1rem;
}

.info-grid-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.info-grid-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.about-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: clamp(1.5rem, 4vw, 2.75rem);
}

.about-card {
    position: relative;
    display: grid;
    gap: 0.8rem;
    min-height: 100%;
    padding: 1.2rem 1.15rem;
    border-radius: 22px;
    border: 1px solid rgba(3, 68, 133, 0.16);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(244, 249, 255, 0.98)), var(--page-surface);
    box-shadow: 0 24px 46px -38px rgba(3, 68, 133, 0.34);
    transition:
        transform 260ms cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 260ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 260ms ease,
        opacity 420ms ease;
}

.about-card:hover {
    transform: translateY(-6px);
    border-color: rgba(3, 68, 133, 0.28);
    box-shadow: 0 30px 54px -38px rgba(3, 68, 133, 0.42);
}

.about-card h3 {
    margin: 0;
    font-size: 1.06rem;
    line-height: 1.28;
    color: var(--page-text);
    font-weight: 800;
}

.about-card p {
    margin: 0;
    color: var(--page-text-muted);
    font-size: 0.97rem;
    line-height: 1.72;
}

.about-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 0.7rem;
}

.about-list li {
    position: relative;
    padding-left: 1.3rem;
    color: var(--page-text-muted);
    line-height: 1.6;
}

.about-list li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.52rem;
    width: 0.48rem;
    height: 0.48rem;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--brand-blue), var(--page-accent-soft));
    box-shadow: 0 0 0 6px rgba(147, 197, 253, 0.18);
}

.about-section.welcome-reveal .about-spotlight,
.about-section.welcome-reveal .about-story-card,
.about-section.welcome-reveal .about-card {
    opacity: 0;
    transform: translateY(18px);
}

.about-section.is-visible .about-spotlight,
.about-section.is-visible .about-story-card,
.about-section.is-visible .about-card {
    opacity: 1;
    transform: translateY(0);
}

.about-section.is-visible .about-spotlight {
    transition:
        opacity 480ms ease,
        transform 480ms cubic-bezier(0.22, 1, 0.36, 1);
}

.about-section.is-visible .about-card {
    transition:
        opacity 520ms ease,
        transform 520ms cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 260ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 260ms ease;
}

.about-section.is-visible .about-story-card {
    transition:
        opacity 520ms ease,
        transform 520ms cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 260ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 260ms ease;
}

.faq-section {
    display: grid;
    gap: 1rem;
}

.faq-intro {
    justify-items: center;
    text-align: center;
    max-width: 42rem;
    margin: 0 auto;
}

.faq-accordion {
    display: grid;
    gap: 0.8rem;
}

.faq-item {
    border-radius: 22px;
    border: 1px solid rgba(3, 68, 133, 0.14);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(244, 249, 255, 0.98));
    box-shadow: 0 18px 40px -34px rgba(3, 68, 133, 0.28);
    overflow: hidden;
    transition:
        border-color 220ms ease,
        box-shadow 220ms ease,
        transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.faq-item:hover {
    transform: translateY(-2px);
    border-color: rgba(3, 68, 133, 0.24);
    box-shadow: 0 24px 44px -34px rgba(3, 68, 133, 0.34);
}

.faq-item.is-open {
    border-color: rgba(3, 68, 133, 0.32);
    box-shadow: 0 28px 48px -34px rgba(3, 68, 133, 0.38);
}

.faq-trigger {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border: none;
    background: transparent;
    text-align: left;
    cursor: pointer;
    transition: background 200ms ease;
}

.faq-trigger:hover,
.faq-trigger:focus-visible {
    background: rgba(147, 197, 253, 0.12);
    outline: none;
}

.faq-item.is-open .faq-trigger {
    background: rgba(147, 197, 253, 0.14);
}

.faq-question {
    font-size: 1rem;
    line-height: 1.45;
    font-weight: 700;
    color: var(--page-text);
}

.faq-chevron {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    flex-shrink: 0;
    border-radius: 999px;
    color: var(--brand-blue);
    background: rgba(3, 68, 133, 0.08);
    transition:
        transform 240ms cubic-bezier(0.22, 1, 0.36, 1),
        background 200ms ease,
        color 200ms ease;
}

.faq-chevron svg {
    width: 1rem;
    height: 1rem;
}

.faq-item.is-open .faq-chevron {
    transform: rotate(180deg);
    background: rgba(3, 68, 133, 0.16);
    color: var(--page-accent-strong);
}

.faq-answer-shell {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 260ms cubic-bezier(0.22, 1, 0.36, 1);
}

.faq-item.is-open .faq-answer-shell {
    grid-template-rows: 1fr;
}

.faq-answer-body {
    overflow: hidden;
}

.faq-answer-body p {
    margin: 0;
    padding: 0 1.1rem 1rem;
    color: var(--page-text-muted);
    font-size: 0.95rem;
    line-height: 1.7;
    opacity: 0;
    transform: translateY(-8px);
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}

.faq-item.is-open .faq-answer-body p {
    opacity: 1;
    transform: translateY(0);
}

.steps-grid {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.how-it-works-grid {
    width: 100%;
    grid-template-columns: repeat(auto-fit, minmax(240px, 300px));
    justify-content: center;
    gap: 1.2rem;
}

.info-card {
    display: grid;
    gap: 0.7rem;
    padding: 1.15rem 1.1rem;
    border-radius: 18px;
    border: 1px solid var(--brand-line);
    background: var(--page-surface);
    box-shadow: 0 12px 24px -22px var(--page-card-shadow-strong);
}

.info-card h3 {
    margin: 0;
    font-size: 1rem;
    color: var(--page-text);
    font-weight: 700;
}

.info-card p {
    margin: 0;
    color: var(--page-text-muted);
    font-size: 0.95rem;
}

.info-card-wide {
    max-width: 860px;
}

.support-hub-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
    gap: 1rem;
}

.support-hub-side {
    display: grid;
    gap: 1rem;
}

.support-card {
    display: grid;
    gap: 0.75rem;
    padding: 1.15rem 1.1rem;
    border-radius: 1rem;
    border: 1px solid rgba(3, 68, 133, 0.16);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.97), rgba(244, 249, 255, 0.98));
    box-shadow: 0 16px 34px -30px rgba(3, 68, 133, 0.28);
    transition:
        transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 220ms ease;
}

.support-card:hover {
    transform: translateY(-3px);
    border-color: rgba(3, 68, 133, 0.26);
    box-shadow: 0 22px 42px -32px rgba(3, 68, 133, 0.36);
}

.support-card-kicker {
    margin: 0;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: var(--brand-blue);
    font-weight: 800;
}

.support-card h3 {
    margin: 0;
    font-size: 1.04rem;
    font-weight: 800;
    color: var(--page-text);
}

.support-card-copy,
.support-card p {
    margin: 0;
    color: var(--page-text-muted);
    font-size: 0.92rem;
    line-height: 1.62;
}

.support-best-chip {
    width: fit-content;
    max-width: 100%;
    border-radius: 999px;
    border: 1px solid rgba(3, 68, 133, 0.2);
    background: rgba(219, 234, 254, 0.66);
    color: #0f3f74 !important;
    font-size: 0.79rem !important;
    font-weight: 700;
    padding: 0.34rem 0.62rem;
}

.support-contact-list {
    display: grid;
    gap: 0.55rem;
}

.support-contact-item {
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    gap: 0.55rem;
    margin: 0;
    color: var(--page-text);
    font-size: 0.9rem;
    font-weight: 600;
}

.support-icon {
    width: 1.7rem;
    height: 1.7rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(3, 68, 133, 0.1);
    color: var(--brand-blue);
    flex-shrink: 0;
}

.support-icon svg {
    width: 0.92rem;
    height: 0.92rem;
}

.support-cta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
}

.support-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid transparent;
    font-size: 0.84rem;
    font-weight: 700;
    text-decoration: none;
    padding: 0.52rem 0.9rem;
    transition:
        background 200ms ease,
        color 200ms ease,
        border-color 200ms ease,
        box-shadow 200ms ease;
}

.support-btn-fill {
    background: var(--brand-blue);
    border-color: var(--brand-blue);
    color: #ffffff;
}

.support-btn-fill:hover {
    background: #0d58a3;
    border-color: #0d58a3;
    box-shadow: 0 12px 24px -18px rgba(3, 68, 133, 0.6);
}

.support-btn-outline {
    background: rgba(255, 255, 255, 0.95);
    border-color: rgba(3, 68, 133, 0.36);
    color: var(--brand-blue);
}

.support-btn-outline:hover {
    background: rgba(219, 234, 254, 0.6);
    border-color: rgba(3, 68, 133, 0.55);
}

.support-form {
    display: grid;
    gap: 0.55rem;
}

.support-input {
    width: 100%;
    border-radius: 0.85rem;
    border: 1px solid rgba(3, 68, 133, 0.22);
    background: #ffffff;
    color: var(--page-text);
    font-size: 0.9rem;
    padding: 0.58rem 0.7rem;
    transition:
        border-color 200ms ease,
        box-shadow 200ms ease;
}

.support-input::placeholder {
    color: rgba(15, 23, 42, 0.52);
}

.support-input:focus {
    outline: none;
    border-color: rgba(3, 68, 133, 0.58);
    box-shadow: 0 0 0 2px rgba(3, 68, 133, 0.2);
}

.support-select {
    appearance: none;
    background-image:
        linear-gradient(45deg, transparent 50%, rgba(3, 68, 133, 0.7) 50%),
        linear-gradient(135deg, rgba(3, 68, 133, 0.7) 50%, transparent 50%);
    background-position:
        calc(100% - 16px) calc(50% + 1px),
        calc(100% - 11px) calc(50% + 1px);
    background-size:
        5px 5px,
        5px 5px;
    background-repeat: no-repeat;
}

.support-textarea {
    resize: vertical;
    min-height: 4.4rem;
}

.support-submit {
    justify-self: start;
}

.step-card {
    position: relative;
    padding-top: 1.45rem;
}

.how-it-works-card {
    justify-items: center;
    align-content: start;
    min-height: 18rem;
    padding: 1.75rem 1.35rem 1.45rem;
    text-align: center;
    border-color: rgba(3, 68, 133, 0.22);
    background:
        radial-gradient(circle at top, rgba(147, 197, 253, 0.28), transparent 52%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(236, 246, 255, 0.94));
    box-shadow: 0 26px 54px -36px rgba(3, 68, 133, 0.4);
    transform: translateY(0) scale(1);
    transition:
        transform 240ms cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 240ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 240ms ease,
        background 240ms ease;
}

.how-it-works-card:hover {
    transform: translateY(-8px) scale(1.02);
    border-color: rgba(3, 68, 133, 0.46);
    box-shadow: 0 34px 70px -40px rgba(3, 68, 133, 0.5);
}

.how-it-works-card p {
    font-size: 1.02rem;
    line-height: 1.7;
}

.step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    background: var(--brand-blue);
    color: #ffffff;
    font-size: 1rem;
    font-weight: 800;
    width: 3.4rem;
    height: 3.4rem;
    box-shadow: 0 16px 34px -20px rgba(3, 68, 133, 0.7);
}

.how-it-works-summary {
    width: min(100%, 56rem);
    text-align: center;
    justify-items: center;
    padding: 1.5rem 1.4rem;
    background: linear-gradient(135deg, rgba(3, 68, 133, 0.98), rgba(27, 110, 194, 0.92));
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 24px 54px -38px rgba(3, 20, 40, 0.75);
    transition:
        transform 260ms cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 260ms cubic-bezier(0.22, 1, 0.36, 1);
}

.how-it-works-summary:hover {
    transform: translateY(-4px);
    box-shadow: 0 30px 64px -40px rgba(3, 20, 40, 0.85);
}

.how-it-works-summary h3,
.how-it-works-summary p {
    color: #ffffff;
}

.how-it-works-section.welcome-reveal {
    transition:
        opacity 520ms cubic-bezier(0.22, 1, 0.36, 1),
        transform 520ms cubic-bezier(0.22, 1, 0.36, 1);
}

.how-it-works-section .how-it-works-card,
.how-it-works-section .how-it-works-summary {
    opacity: 0;
    transform: translateY(28px);
}

.how-it-works-section.is-visible .how-it-works-card,
.how-it-works-section.is-visible .how-it-works-summary {
    opacity: 1;
    transform: translateY(0);
}

.how-it-works-section.is-visible .how-it-works-summary {
    transition-delay: 520ms;
}

@keyframes howItWorksTitleReveal {
    0% {
        opacity: 0;
        transform: translateY(18px) scale(0.94);
        letter-spacing: -0.08em;
    }

    65% {
        opacity: 1;
        transform: translateY(-4px) scale(1.02);
    }

    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
        letter-spacing: -0.05em;
    }
}

@keyframes howItWorksUnderline {
    0% {
        opacity: 0;
        transform: scaleX(0.12);
    }

    100% {
        opacity: 1;
        transform: scaleX(1);
    }
}

.info-list {
    margin: 0;
    padding-left: 1.15rem;
    color: var(--page-text-muted);
    display: grid;
    gap: 0.4rem;
}

.register-cta-wrap {
    padding-top: clamp(1.6rem, 4vw, 2.5rem);
    padding-bottom: 1rem;
}

.register-cta {
    border: none;
    border-radius: 0;
    background: transparent;
    padding: 0;
    text-align: left;
    max-width: 720px;
    transition: none;
}

.register-cta:hover {
    box-shadow: none;
}

.cta-kicker {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--page-text-muted);
    font-weight: 700;
}

.register-cta h2 {
    margin-top: 0.45rem;
    font-size: var(--title-lg);
    line-height: 1.25;
    color: var(--page-text);
    font-weight: 800;
}

.register-cta-heading {
    display: inline;
    padding: 0.22rem 0.7rem;
    border-radius: 999px;
    background: var(--brand-blue);
    color: #ffffff;
    line-height: 1.45;
    white-space: normal;
    -webkit-box-decoration-break: clone;
    box-decoration-break: clone;
}

.register-cta p {
    margin: 0.75rem 0 0;
    color: var(--page-text-muted);
    line-height: 1.6;
    max-width: 60ch;
    font-size: var(--body-md);
}

.cta-actions {
    margin-top: 1rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: clamp(0.5rem, 1.8vw, 0.8rem);
}

.site-footer {
    margin-top: 1rem;
    padding-top: clamp(1rem, 3vw, 1.5rem);
    padding-bottom: 1.3rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    background: #dc2626;
    border-radius: 18px 18px 0 0;
    margin-left: 1.5rem;
    margin-right: 1.5rem;
    overflow: hidden;
    color: #ffffff;
    --title-chip-bg: rgba(255, 255, 255, 0.14);
    --title-chip-text: #ffffff;
}

.footer-shell {
    padding: 0.2rem 0 0;
    color: #ffffff;
}

.footer-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) repeat(2, minmax(0, 0.8fr));
    gap: 1.2rem;
}

.footer-col {
    min-width: 0;
}

.footer-col-brand {
    max-width: 42ch;
}

.footer-brand {
    color: #ffffff;
    font-size: 0.96rem;
    font-weight: 800;
}

.footer-copy {
    margin-top: 0.55rem;
    color: #ffffff;
    line-height: 1.6;
    font-size: 0.92rem;
}

.footer-contact {
    margin-top: 0.45rem;
    color: #ffffff;
    font-size: 0.84rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.contact-icon {
    width: 0.92rem;
    height: 0.92rem;
    color: #ffffff;
    flex-shrink: 0;
}

.social-col {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    padding-top: 1.45rem;
}

.footer-socials {
    margin-top: 0.85rem;
    display: flex;
    gap: 0.5rem;
}

.social-icon-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    color: #ffffff;
    text-decoration: none;
}

.social-icon-link:hover {
    background: rgba(255, 255, 255, 0.15);
}

.social-icon {
    width: 1.05rem;
    height: 1.05rem;
}

.footer-heading {
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.72rem;
    font-weight: 800;
}

.footer-link-list {
    margin-top: 0.65rem;
    display: grid;
    gap: 0.5rem;
    overflow-wrap: anywhere;
}

.footer-link {
    color: #ffffff;
    text-decoration: none;
    font-size: 0.9rem;
    overflow-wrap: anywhere;
}

.footer-link:hover {
    color: #ffffff;
}

.footer-link-btn {
    border: none;
    background: none;
    padding: 0;
    text-align: left;
    cursor: pointer;
}

.footer-bottom-row {
    margin-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 0.75rem;
    color: #ffffff;
    font-size: 0.78rem;
}

@media (min-width: 640px) {
    .hero-divider {
        margin-top: 1rem;
        margin-bottom: 1.2rem;
    }

    .nav-shell {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 4px 18px;
    }

    .header-links {
        justify-content: flex-end;
        margin: 0;
    }

    .footer-bottom-row {
        display: flex;
        justify-content: flex-end;
    }
}

@media (min-width: 1024px) {
    .page {
        font-size: 1.125rem;
    }
}

@media (min-width: 1024px) {
    .hero-divider {
        margin-top: 1.2rem;
        margin-bottom: 1.4rem;
    }
}

@media (max-width: 1024px) {
    .strip-overlay {
        padding: 2rem 1.25rem;
    }

    .strip-overlay-inner {
        max-width: 460px;
    }

    .strip-overlay-inner h1 {
        font-size: 2.05rem;
    }

    .features-minimal h2 {
        font-size: 1.55rem;
    }

    .footer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .info-grid-3 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .features-interactive-shell {
        grid-template-columns: 1fr;
    }

    .about-hero,
    .about-story-grid,
    .about-grid {
        grid-template-columns: 1fr;
    }

    .about-story-card-wide {
        max-width: none;
    }

    .support-hub-grid {
        grid-template-columns: 1fr;
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

    .mobile-menu-toggle {
        display: inline-flex;
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

    .image-strip-hero,
    .image-strip,
    .strip-col {
        min-height: 60vh;
    }

    .strip-overlay-inner h1 {
        font-size: 1.7rem;
    }

    .strip-overlay-inner p {
        font-size: 0.95rem;
    }

    .strip-kicker,
    .strip-version {
        font-size: 0.7rem;
    }

    .info-grid-2,
    .info-grid-3 {
        grid-template-columns: 1fr;
    }

    .feature-title-button {
        padding: 0.9rem 0.95rem;
    }

    .feature-detail-card {
        padding: 1.25rem 1.05rem;
    }

    .about-spotlight,
    .about-card {
        padding: 1.05rem 1rem;
        border-radius: 18px;
    }

    .support-hub-side {
        display: flex;
        flex-direction: column-reverse;
    }

    .support-card {
        padding: 1rem;
        border-radius: 0.95rem;
    }

    .support-submit {
        width: 100%;
        justify-content: center;
    }

    .faq-trigger {
        padding: 0.92rem 0.95rem;
    }

    .faq-answer-body p {
        padding: 0 0.95rem 0.95rem;
    }

    .policy-card {
        padding: 1rem 0.95rem;
        border-radius: 16px;
    }

    .policy-chip-list li {
        width: 100%;
    }
}

@media (min-width: 900px) {
    .role-strip {
        flex-direction: row;
    }

    .role-card + .role-card {
        border-top: none;
        border-left: none;
    }

    .pathway-grid {
        padding: 1.25rem;
    }
}

@media (max-width: 640px) {
    .image-strip-hero {
        margin: 0 1rem;
        border-radius: 16px;
    }

    .features-wrap {
        margin: 0 1rem;
        border-radius: 16px;
    }

    .site-footer {
        border-radius: 16px 16px 0 0;
        margin-left: 1rem;
        margin-right: 1rem;
    }

    .mobile-divider {
        margin-left: 1.5rem;
    }

    .hero-divider {
        margin-top: 0.9rem;
        margin-bottom: 1.1rem;
    }
    .corner-badge {
        top: 4px;
        width: 212px;
        height: 74px;
    }

    .header-logo-slot {
        flex-basis: 190px;
    }

    .nav-shell {
        border-radius: 20px;
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

    .dept-item {
        width: 88px;
        height: 88px;
    }

    .pathway-divider {
        width: 85%;
        margin: 1.2rem auto 1.4rem 0;
    }

    .pathway-note {
        margin-left: 0;
        text-align: left;
        max-width: none;
    }

    .pathway-footer-inner {
        align-items: flex-start;
        padding-right: 0;
        padding-left: 1rem;
    }

    .feature-list {
        grid-template-columns: 1fr;
    }

    .features-minimal h2 {
        font-size: 1.4rem;
    }

    .footer-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .footer-bottom-row {
        justify-content: flex-start;
    }

    .register-cta h2 {
        font-size: 1.25rem;
    }

    .role-strip {
        flex-direction: column;
        gap: 1rem;
    }

    .coach-card-wrap {
        margin-top: 0;
    }

    .image-strip-hero {
        overflow-x: auto;
    }

    .image-strip {
        min-width: 900px;
    }

    .strip-overlay {
        padding: 1.6rem 1rem;
        background: linear-gradient(180deg, rgba(3, 20, 40, 0.85) 0%, rgba(3, 20, 40, 0.35) 70%, rgba(3, 20, 40, 0.1) 100%);
    }
}

@media (max-width: 480px) {
    .strip-overlay-inner h1 {
        font-size: 1.5rem;
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

    .strip-overlay-inner p,
    .departments-desc,
    .register-cta p {
        font-size: 0.9rem;
    }

    .feature-chip {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .welcome-reveal,
    .features-wrap,
    .image-strip,
    .role-card,
    .register-cta,
    .dept-item,
    .fade-title-enter-active,
    .fade-title-leave-active {
        animation: none !important;
        transition: none !important;
        transform: none !important;
    }

    .welcome-reveal {
        opacity: 1 !important;
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
