<script setup>
import { ref, watch, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    groups: Object,   // { groupName: [...settings] }
    allGroups: Array, // ['general','seo',...]
    filters: Object,
    locales: Array,
});

const search = ref(props.filters.search ?? '');
const group = ref(props.filters.group ?? '');
const activeLocale = ref('ru');

let tm = null;
watch(search, (v) => { clearTimeout(tm); tm = setTimeout(() => apply({ search: v, group: group.value }), 300); });
watch(group, (v) => apply({ search: search.value, group: v }));

function apply(params) {
    router.get('/admin/settings',
        Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null)),
        { preserveState: true, preserveScroll: true, replace: true });
}

const saving = reactive({});
const saved = reactive({});

function save(setting) {
    saving[setting.id] = true;
    router.patch(`/admin/settings/${setting.id}`, { value: setting.value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            saving[setting.id] = false;
            saved[setting.id] = true;
            setTimeout(() => (saved[setting.id] = false), 1500);
        },
        onError: () => { saving[setting.id] = false; },
    });
}

function groupLabel(name) {
    const map = {
        general:       'Общие',
        seo:           'SEO',
        contacts:      'Контакты',
        social:        'Социальные сети',
        rector:        'Ректор',
        admission:     'Приёмная комиссия',
        admissions:    'Приёмная комиссия',
        footer:        'Подвал',
        home:          'Главная',
        header:        'Шапка',
        about:         'О университете',
        career:        'Карьера',
        programs:      'Программы',
        programs_page: 'Страница «Программы»',
        schools_page:  'Страница «Школы»',
        site:          'Сайт',
        ui:            'Интерфейс',
    };
    return map[name] || name.charAt(0).toUpperCase() + name.slice(1);
}

// Словарь частых слов в ключах настроек — используется для автогенерации
// человечного лейбла, когда у setting'а нет description.
const WORD_MAP = {
    title: 'Заголовок',
    subtitle: 'Подзаголовок',
    text: 'Текст',
    description: 'Описание',
    label: 'Подпись',
    name: 'Название',
    mission: 'Миссия',
    vision: 'Видение',
    history: 'История',
    about: 'О нас',
    hero: 'Hero',
    stat: 'Статистика',
    stats: 'Статистика',
    years: 'Лет',
    graduates: 'Выпускники',
    employment: 'Трудоустройство',
    partners: 'Партнёры',
    programs: 'Программы',
    schools: 'Школы',
    teachers: 'Преподаватели',
    students: 'Студенты',
    phone: 'Телефон',
    email: 'Email',
    address: 'Адрес',
    contact: 'Контакты',
    contacts: 'Контакты',
    footer: 'Подвал',
    header: 'Шапка',
    button: 'Кнопка',
    cta: 'CTA',
    link: 'Ссылка',
    url: 'URL',
    admission: 'Приёмная',
    admissions: 'Приёмная',
    apply: 'Подать',
    learn: 'Узнать',
    more: 'больше',
    main: 'Главная',
    home: 'Главная',
    rector: 'Ректор',
    message: 'Сообщение',
    slogan: 'Слоган',
    info: 'Информация',
    page: 'Страница',
    social: 'Соцсети',
    facebook: 'Facebook',
    instagram: 'Instagram',
    youtube: 'YouTube',
    linkedin: 'LinkedIn',
    telegram: 'Telegram',
    whatsapp: 'WhatsApp',
    copyright: 'Копирайт',
    seo: 'SEO',
    keywords: 'Ключевые слова',
    meta: 'Мета',
    logo: 'Логотип',
    welcome: 'Приветствие',
    news: 'Новости',
    articles: 'Статьи',
};

function keyLabel(key) {
    if (!key) return '';
    const parts = String(key).split(/[_\s]+/).filter(Boolean);
    const words = parts.map((p, i) => {
        const lower = p.toLowerCase();
        const mapped = WORD_MAP[lower];
        if (mapped) return i === 0 ? mapped : mapped.toLowerCase();
        // unknown word — return as-is (could be abbreviation like IT, HR)
        return p.length <= 3 ? p.toUpperCase() : p.charAt(0).toUpperCase() + p.slice(1);
    });
    return words.join(' ');
}

function typeLabel(type) {
    const map = {
        text:     'текст',
        textarea: 'длинный текст',
        json:     'JSON',
        image:    'изображение',
        color:    'цвет',
        url:      'URL',
        email:    'email',
        boolean:  'да/нет',
        number:   'число',
    };
    return map[type] || type || 'text';
}

function isLongValue(v) {
    return (v || '').length > 80 || /\n/.test(v || '');
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" subtitle="Тексты, контакты и SEO-поля сайта — per-locale" />

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 flex flex-col md:flex-row gap-3">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input v-model="search" type="search" placeholder="Поиск по ключу или описанию…"
                class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500">
        </div>
        <select v-model="group" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все группы</option>
            <option v-for="g in allGroups" :key="g" :value="g">{{ groupLabel(g) }}</option>
        </select>
        <div class="flex items-center gap-1 border border-gray-300 dark:border-gray-600 rounded-lg p-1 bg-white dark:bg-gray-700">
            <button v-for="loc in locales" :key="loc" type="button" @click="activeLocale = loc"
                class="px-3 py-1 rounded text-xs font-medium uppercase transition"
                :class="activeLocale === loc
                    ? 'bg-red-600 text-white'
                    : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600'">
                {{ loc }}
            </button>
        </div>
    </div>

    <div v-if="Object.keys(groups).length === 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center text-gray-500">
        <i class="fas fa-sliders text-4xl mb-2 opacity-30"></i>
        <p>Настроек не найдено</p>
    </div>

    <div class="space-y-6">
        <section v-for="(items, groupName) in groups" :key="groupName"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <header class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                <h2 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="fas fa-folder text-gray-400 text-sm"></i>
                    <span>{{ groupLabel(groupName) }}</span>
                    <span class="text-xs text-gray-400 font-normal">({{ items.length }})</span>
                </h2>
            </header>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <div v-for="setting in items" :key="setting.id" class="px-6 py-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                    <div class="md:col-span-1">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ setting.description || keyLabel(setting.key) }}
                        </div>
                        <div class="text-xs text-gray-400 font-mono mt-0.5 truncate" :title="setting.key">{{ setting.key }}</div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex gap-2 items-start">
                            <textarea v-if="isLongValue(setting.value[activeLocale])"
                                v-model="setting.value[activeLocale]"
                                rows="3"
                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                                :placeholder="activeLocale !== 'ru' ? (setting.value.ru || '') : ''"
                            ></textarea>
                            <input v-else
                                v-model="setting.value[activeLocale]"
                                type="text"
                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                                :placeholder="activeLocale !== 'ru' ? (setting.value.ru || '') : ''"
                            >
                            <button type="button" @click="save(setting)" :disabled="saving[setting.id]"
                                class="px-3 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg text-sm flex-shrink-0">
                                <i v-if="saving[setting.id]" class="fas fa-spinner fa-spin"></i>
                                <i v-else-if="saved[setting.id]" class="fas fa-check"></i>
                                <i v-else class="fas fa-save"></i>
                            </button>
                        </div>
                        <div class="mt-1 text-xs text-gray-400 flex items-center gap-3">
                            <span>Тип: <span class="font-mono">{{ typeLabel(setting.type) }}</span></span>
                            <span v-if="activeLocale !== 'ru' && !setting.value[activeLocale]" class="text-amber-600">
                                <i class="fas fa-triangle-exclamation"></i> Нет перевода — используется RU
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
