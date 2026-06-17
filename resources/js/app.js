import './bootstrap';
import { syncCsrfToken } from './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { createPinia } from 'pinia';

const appName = import.meta.env.VITE_APP_NAME || 'VA QLDA';

router.on('invalid', (event) => {
    const status = event.detail.response?.status;
    if (status === 419) {
        event.preventDefault();
        window.location.reload();
    }
});

router.on('success', (event) => {
    const token = event.detail.page?.props?.csrf_token;
    syncCsrfToken(token);
});

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue', { eager: false }),
        ),
    setup({ el, App, props, plugin }) {
        syncCsrfToken(props.initialPage?.props?.csrf_token);
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(createPinia())
            .mount(el);
    },
    progress: {
        color: '#9A0036',
    },
});
