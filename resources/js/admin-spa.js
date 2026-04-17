/*
 * Admin Core SPA bootstrap.
 *
 * Consumer app entry (resources/js/admin-spa.js) should call bootAdminCore:
 *
 *   import { bootAdminCore } from '@admin-core';
 *   import AdminLayout from '@admin-core/layouts/AdminLayout.vue';
 *
 *   const sitePages = import.meta.glob('./admin-spa/pages/\u002A\u002A/\u002A.vue');
 *   const corePages = import.meta.glob('../../vendor/meta/admin-core/resources/js/pages/\u002A\u002A/\u002A.vue');
 *
 *   bootAdminCore({ sitePages, corePages, AdminLayout });
 *
 * Resolution order: site pages first (so host app can override), then core pages.
 */
import '@fontsource/inter/300.css';
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/inter/800.css';
import '@fortawesome/fontawesome-free/css/all.min.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

export function bootAdminCore({ sitePages = {}, corePages = {}, AdminLayout, title } = {}) {
    if (!AdminLayout) throw new Error('[admin-core] AdminLayout is required');

    return createInertiaApp({
        title: (t) => (t ? `${t} — ${title || 'Admin'}` : title || 'Admin'),

        resolve: async (name) => {
            const sitePath = `./admin-spa/pages/${name}.vue`;
            const corePath = `../../vendor/meta/admin-core/resources/js/pages/${name}.vue`;

            const loader = sitePages[sitePath] || corePages[corePath];
            if (!loader) throw new Error(`[admin-core] Inertia page not found: ${name}`);

            const page = (await loader()).default;
            page.layout = page.layout ?? AdminLayout;
            return page;
        },

        setup({ el, App, props, plugin }) {
            createApp({ render: () => h(App, props) })
                .use(plugin)
                .mount(el);
        },

        progress: { color: '#C41E3A', showSpinner: true },
    });
}

export default bootAdminCore;
