<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    socialLinks: Object,
    rector: Object,
    secondaryLogo: Object,
    menuConfig: Array,
    socialNetworks: Array,
});

const activeTab = ref('social');

// --- Social ---
const socialFormInit = () => {
    const obj = {};
    for (const n of props.socialNetworks) {
        obj[`${n.key}_url`]     = props.socialLinks[n.key]?.url ?? '';
        obj[`${n.key}_enabled`] = !!props.socialLinks[n.key]?.enabled;
    }
    return obj;
};
const socialForm = useForm(socialFormInit());
function saveSocial() { socialForm.put('/admin/site-settings/social-media', { preserveScroll: true }); }

// --- Rector ---
const rectorForm = useForm({
    email:          props.rector.email          ?? '',
    phone:          props.rector.phone          ?? '',
    reception_days: props.rector.reception_days ?? '',
    reception_time: props.rector.reception_time ?? '',
});
function saveRector() { rectorForm.put('/admin/site-settings/rector', { preserveScroll: true }); }

// --- Secondary logo ---
const logoForm = useForm({
    image:    null,
    url:      props.secondaryLogo.url ?? '',
    enabled:  !!props.secondaryLogo.enabled,
    remove_image: false,
    _method:  'put',
});
const logoPreview = ref(null);
const existingLogoUrl = ref(props.secondaryLogo.image_url ?? null);
function onLogoChange(e) {
    const f = e.target.files?.[0];
    logoForm.image = f || null;
    logoForm.remove_image = false;
    if (f) { const r = new FileReader(); r.onload = (ev) => (logoPreview.value = ev.target.result); r.readAsDataURL(f); }
    else logoPreview.value = null;
}
function removeLogo() { logoForm.image = null; logoForm.remove_image = true; logoPreview.value = null; existingLogoUrl.value = null; }
function saveLogo() { logoForm.post('/admin/site-settings/secondary-logo', { forceFormData: true, preserveScroll: true }); }

// --- Menu ---
const menuItems = ref(JSON.parse(JSON.stringify(props.menuConfig)));
const menuForm = useForm({ menu: {} });
function saveMenu() {
    menuForm.menu = {};
    for (const item of menuItems.value) {
        menuForm.menu[item.key] = { enabled: !!item.enabled, order: Number(item.order) || 0 };
    }
    menuForm.put('/admin/site-settings/menu', { preserveScroll: true });
}

const tabs = [
    { key: 'social', label: 'Соцсети',       icon: 'fa-share-nodes' },
    { key: 'rector', label: 'Ректор',        icon: 'fa-user-tie' },
    { key: 'logo',   label: 'Второй логотип',icon: 'fa-image' },
    { key: 'menu',   label: 'Главное меню',  icon: 'fa-bars' },
];
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" subtitle="Специализированные настройки верхней навигации и ректората" />

    <div class="flex items-center gap-1 border-b border-gray-200 dark:border-gray-700 mb-6 overflow-x-auto">
        <button v-for="t in tabs" :key="t.key" type="button" @click="activeTab = t.key"
            class="flex items-center gap-2 px-4 py-2.5 border-b-2 font-medium text-sm transition-colors -mb-px whitespace-nowrap"
            :class="activeTab === t.key
                ? 'border-red-600 text-red-700 dark:text-red-300'
                : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-200'">
            <i :class="'fas ' + t.icon"></i>
            <span>{{ t.label }}</span>
        </button>
    </div>

    <!-- Social networks -->
    <form v-show="activeTab === 'social'" @submit.prevent="saveSocial" class="space-y-3 max-w-3xl">
        <div v-for="n in socialNetworks" :key="n.key"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-lg bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center text-gray-700 dark:text-gray-200">
                <i :class="n.icon" class="text-lg"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-medium text-gray-900 dark:text-white mb-1">{{ n.label }}</div>
                <input v-model="socialForm[n.key + '_url']" type="url" placeholder="https://…"
                    class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="socialForm[n.key + '_enabled']" type="checkbox" class="w-5 h-5 rounded text-red-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">Показать</span>
            </label>
        </div>
        <button type="submit" :disabled="socialForm.processing" class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
            <i class="fas" :class="socialForm.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i> Сохранить соцсети
        </button>
    </form>

    <!-- Rector -->
    <form v-show="activeTab === 'rector'" @submit.prevent="saveRector" class="space-y-4 max-w-xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">Контакты ректора</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email <span class="text-red-500">*</span></label>
                <input v-model="rectorForm.email" type="email" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                <p v-if="rectorForm.errors.email" class="text-xs text-red-500 mt-1">{{ rectorForm.errors.email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Телефон</label>
                <input v-model="rectorForm.phone" type="text" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm font-mono">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Дни приёма</label>
                    <input v-model="rectorForm.reception_days" type="text" placeholder="Вторник, Четверг" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Время приёма</label>
                    <input v-model="rectorForm.reception_time" type="text" placeholder="14:00 – 16:00" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                </div>
            </div>
        </div>
        <button type="submit" :disabled="rectorForm.processing" class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
            <i class="fas" :class="rectorForm.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i> Сохранить ректора
        </button>
    </form>

    <!-- Secondary logo -->
    <form v-show="activeTab === 'logo'" @submit.prevent="saveLogo" class="space-y-4 max-w-xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">Второй логотип</h3>
            <p class="text-sm text-gray-500">Показывается рядом с основным логотипом META University (например, логотип партнёра).</p>

            <label class="flex items-center gap-3 cursor-pointer">
                <input v-model="logoForm.enabled" type="checkbox" class="w-5 h-5 rounded text-red-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">Показать на сайте</span>
            </label>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Изображение логотипа</label>
                <div v-if="logoPreview || existingLogoUrl" class="relative inline-block mb-3">
                    <img :src="logoPreview || existingLogoUrl" class="h-20 bg-gray-50 dark:bg-gray-900 rounded-lg p-2 border border-gray-200 dark:border-gray-700">
                    <button type="button" @click="removeLogo" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-600 text-white flex items-center justify-center text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <input type="file" accept="image/*" @change="onLogoChange" class="block text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-red-50 file:text-red-700 hover:file:bg-red-100 dark:file:bg-red-900/30 dark:file:text-red-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ссылка при клике</label>
                <input v-model="logoForm.url" type="url" placeholder="https://partner.example.com" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
            </div>
        </div>
        <button type="submit" :disabled="logoForm.processing" class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
            <i class="fas" :class="logoForm.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i> Сохранить логотип
        </button>
    </form>

    <!-- Menu config -->
    <form v-show="activeTab === 'menu'" @submit.prevent="saveMenu" class="space-y-4 max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <header class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white">Пункты главного меню</h3>
                <span class="text-xs text-gray-500">Показать + порядок</span>
            </header>
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                <li v-for="item in menuItems" :key="item.key" class="px-5 py-3 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 dark:text-white">{{ item.label }}</div>
                        <code class="text-xs text-gray-400 font-mono">{{ item.key }}</code>
                    </div>
                    <input v-model.number="item.order" type="number" min="0" max="100"
                        class="w-20 px-2 py-1 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded text-sm text-center">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="item.enabled" type="checkbox" class="w-5 h-5 rounded text-red-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Показать</span>
                    </label>
                </li>
            </ul>
        </div>
        <button type="submit" :disabled="menuForm.processing" class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
            <i class="fas" :class="menuForm.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i> Сохранить меню
        </button>
    </form>
</template>
