/*
 * Admin SPA entry — boots the headless admin from meta/admin-core.
 * All pages, layout, and components come from the package.
 *
 * Site-specific overrides go in resources/js/admin-spa/pages/{Name}/Index.vue —
 * they win over package pages by the bootAdminCore resolver.
 */
import { bootAdminCore } from '@admin-core/admin-spa.js';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const sitePages = import.meta.glob('./admin-spa/pages/**/*.vue');
const corePages = import.meta.glob('../../vendor/meta/admin-core/resources/js/pages/**/*.vue');

bootAdminCore({
    sitePages,
    corePages,
    AdminLayout,
    title: import.meta.env.VITE_ADMIN_TITLE || 'Admin',
});
