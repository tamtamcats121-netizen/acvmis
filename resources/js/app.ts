import '../css/app.css';
import 'primeicons/primeicons.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import Aura from '@primeuix/themes/aura';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PrimeVue from 'primevue/config';
import Toast from 'primevue/toast';
import ToastService from 'primevue/toastservice';
import type { DefineComponent } from 'vue';
import { Transition, createApp, h } from 'vue';

import SessionExpiredToast from '@/components/ui/SessionExpiredToast.vue';
import { showAppToast } from '@/composables/useAppToast';
import { useSessionExpired } from '@/composables/useSessionExpired';
import { initTheme } from '@/composables/useTheme';

const appName = import.meta.env.VITE_APP_NAME || 'ACVMIS';

initTheme();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        router.on('success', (event) => {
            const flash = (event.detail.page.props as any)?.flash ?? {};
            showAppToast(String(flash.login_success ?? ''), 'success');
            showAppToast(String(flash.success ?? ''), 'success');
            showAppToast(String(flash.error ?? ''), 'error');
        });
        const { showSessionExpired } = useSessionExpired();

        const isAuthExpired = (status?: number) => status === 401 || status === 419;

        router.on('error', (event: any) => {
            const status = event?.detail?.response?.status;
            if (isAuthExpired(status)) {
                showSessionExpired();
            }
        });

        router.on('finish', (event: any) => {
            const status = event?.detail?.visit?.response?.status;
            if (isAuthExpired(status)) {
                showSessionExpired();
            }
        });

        if (typeof window !== 'undefined') {
            window.addEventListener('inertia:exception', (event: any) => {
                const status = event?.detail?.response?.status;
                if (isAuthExpired(status)) {
                    showSessionExpired();
                }
            });
        }

        createApp({
            render: () =>
                h('div', { class: 'app-shell' }, [
                    h(
                        Transition,
                        { name: 'route-fade', mode: 'out-in' },
                        {
                            default: () => h(App, { ...props, key: props.initialPage.url }),
                        },
                    ),
                    h(Toast, { position: 'top-right', baseZIndex: 20000 }),
                    h(SessionExpiredToast),
                ]),
        })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: '.theme-dark',
                    },
                },
            })
            .use(ToastService)
            .component('Toast', Toast)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
