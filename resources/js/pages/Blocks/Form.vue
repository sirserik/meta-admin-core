<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';
import LocaleTabs from '@admin-core/components/LocaleTabs.vue';
import TranslatableField from '@admin-core/components/TranslatableField.vue';
import BlockDataEditor from '@admin-core/components/BlockDataEditor.vue';
import { useDraftAutosave } from '@admin-core/composables/useDraftAutosave.js';

const props = defineProps({
    title: String,
    item: Object,
    pagesGrouped: Object,
    typesByCategory: Object,
    typesFlat: Array,
    schemas: { type: Object, default: () => ({}) },
    statuses: Array,
    locales: Array,
    isEdit: Boolean,
    existingKeysByPage: { type: Object, default: () => ({}) },
});

const activeLocale = ref('ru');
const activeTab = ref('content');
const showTypePicker = ref(false);
const typeSearch = ref('');
const activeCategory = ref('__all__');
const manualKey = ref(false); // по умолчанию block_key генерится сам

// `data` and `settings` collide with Inertia's useForm() reserved API
// (`form.data()` returns the payload). Use distinct local names and
// transform to the backend-expected field names on submit.
const form = useForm({
    page_name:    props.item.page_name  ?? '',
    block_key:    props.item.block_key  ?? '',
    block_type:   props.item.block_type ?? 'content',
    sort_order:   props.item.sort_order ?? 0,
    is_active:    props.item.is_active  ?? true,
    status:       props.item.status     ?? 'draft',
    publish_at:   props.item.publish_at   ?? '',
    unpublish_at: props.item.unpublish_at ?? '',
    title:        { ...props.item.title },
    subtitle:     { ...props.item.subtitle },
    content:      { ...props.item.content },
    blockData:    props.item.data     ?? '{}',
    blockSettings:props.item.settings ?? '{}',
});
form.transform((d) => ({
    ...d,
    data:     d.blockData,
    settings: d.blockSettings,
}));

// Debounced localStorage autosave. Key is stable per block so switching
// between edits of different blocks keeps separate drafts. For a brand-
// new block ("create" form), falls back to a nonce so multiple open
// tabs don't step on each other.
const draftKey = `block:${props.item.id ?? 'new-' + (props.item.page_name || 'unassigned')}`;
const {
    savedAt: draftSavedAt,
    restorePrompt,
    discard: discardDraft,
    acceptRestore,
    declineRestore,
} = useDraftAutosave(form, {
    key: draftKey,
    reference_updated_at: props.item.updated_at ?? null,
    debounce: 1500,
});
const draftSavedLabel = computed(() => {
    if (!draftSavedAt.value) return '';
    try { return new Date(draftSavedAt.value).toLocaleTimeString(); } catch { return ''; }
});

// ---------- Live preview ----------
// Split-screen iframe pointing at the public page. State persists in
// localStorage so editors keep the same layout across sessions.
const previewEnabled = ref(false);
try { previewEnabled.value = localStorage.getItem('admin-core:preview-split') === '1'; } catch {}
watch(previewEnabled, (v) => { try { localStorage.setItem('admin-core:preview-split', v ? '1' : '0'); } catch {} });

const previewIframe = ref(null);
const previewUrl = computed(() => {
    const page = form.page_name || props.item.page_name;
    if (!page || page === 'header' || page === 'footer' || page === 'menu') return '';
    // home → '/', anything else → '/{slug}'. Add cache-buster on save.
    const base = page === 'home' ? '/' : ('/' + page);
    return base + '?_preview=' + (previewBuster.value || 0);
});
const previewBuster = ref(0);

// Refresh the iframe when a save succeeds — that way preview always
// mirrors what's in the DB. Per-keystroke re-render would require
// client-side rendering of blocks, which isn't practical for a
// generic package (consumer Blade templates are the source of truth).
watch(() => form.wasSuccessful, (ok) => { if (ok) previewBuster.value = Date.now(); });
function refreshPreview() { previewBuster.value = Date.now(); }

const dataError = ref('');
const settingsError = ref('');

const currentTypeInfo = computed(() =>
    props.typesFlat.find(t => t.key === form.block_type)
    || { key: form.block_type, label: form.block_type, description: '', icon: 'fa-puzzle-piece' }
);

// Current block type's data schema (from BlockCatalog). When present,
// Данные tab shows the visual editor; otherwise falls back to JSON.
//
// Resolution order:
//   1. data.type (for `content` blocks that internally split by
//      `data.type` — clubs-grid, text-card, etc.)
//   2. block_type (canonical case)
//
// This lets a single `block_type='content'` row carry several
// distinct shapes and still get a proper visual editor by registering
// schemas under the `data.type` key.
const currentSchema = computed(() => {
    let dataType = null;
    try {
        const parsed = typeof form.blockData === 'string' ? JSON.parse(form.blockData) : form.blockData;
        if (parsed && typeof parsed === 'object' && typeof parsed.type === 'string') {
            dataType = parsed.type;
        }
    } catch (e) { /* invalid JSON yet — schema can't be picked, fall through */ }
    return (dataType && props.schemas[dataType]) || props.schemas[form.block_type] || null;
});

function isPageInCatalog(slug) {
    for (const group in (props.pagesGrouped || {})) {
        if (Object.prototype.hasOwnProperty.call(props.pagesGrouped[group] || {}, slug)) return true;
    }
    return false;
}

function validateJson(v) {
    if (!v || !v.trim()) return '';
    try { JSON.parse(v); return ''; } catch (e) { return 'Невалидный JSON: ' + e.message; }
}
function submit() {
    dataError.value = validateJson(form.blockData);
    settingsError.value = validateJson(form.blockSettings);
    if (dataError.value || settingsError.value) return;
    if (props.isEdit) form.put(`/admin/blocks/${props.item.id}`);
    else form.post('/admin/blocks');
}
function destroy() {
    if (!confirm(`Удалить блок «${props.item.block_key}»?`)) return;
    router.delete(`/admin/blocks/${props.item.id}`);
}
function chooseType(type) {
    form.block_type = type.key;
    showTypePicker.value = false;
    typeSearch.value = '';
    // При выборе нового типа — перегенерируем ключ, если он не редактировался вручную.
    if (!manualKey.value) form.block_key = generateKey();
}

function generateKey() {
    const base = (form.block_type || 'block').toLowerCase().replace(/[^a-z0-9_]/g, '_');
    const existing = props.existingKeysByPage[form.page_name] || [];
    // В режиме edit — исключаем собственный ключ, чтобы не считать коллизией.
    const mine = props.isEdit ? (props.item.block_key || '') : '';
    const used = new Set(existing.filter(k => k !== mine));
    if (!used.has(base)) return base;
    let i = 2;
    while (used.has(`${base}_${i}`)) i++;
    return `${base}_${i}`;
}

// Авто-перегенерация при смене страницы или типа — только если пользователь не трогал ключ руками.
watch(() => [form.page_name, form.block_type], () => {
    if (!manualKey.value && !props.isEdit) form.block_key = generateKey();
});

// На открытии create-формы если ключ пуст — сгенерить сразу.
if (!props.isEdit && !form.block_key && form.page_name) {
    form.block_key = generateKey();
}

// Список категорий для сайдбара: __all__ + все категории из typesByCategory.
const categories = computed(() => {
    const cats = Object.keys(props.typesByCategory || {});
    return [
        { key: '__all__', label: 'Все типы', icon: 'fa-th-large', count: props.typesFlat.length },
        ...cats.map(name => ({
            key: name,
            label: name,
            icon: categoryIcon(name),
            count: props.typesByCategory[name]?.length || 0,
        })),
    ];
});

function categoryIcon(name) {
    const map = {
        'Hero': 'fa-flag',
        'Контент': 'fa-paragraph',
        'Сетки': 'fa-table-cells',
        'CTA': 'fa-bullhorn',
        'Медиа': 'fa-images',
        'Образование': 'fa-graduation-cap',
        'Люди': 'fa-people-group',
        'Повседневные': 'fa-cube',
        'Студенты': 'fa-user-graduate',
        'Инфраструктура': 'fa-building',
        'GDC': 'fa-leaf',
        'Системные': 'fa-gear',
        'Устаревшие': 'fa-box-archive',
    };
    return map[name] || 'fa-folder';
}

// Отфильтрованный список с учётом поиска и выбранной категории.
const filteredTypes = computed(() => {
    const q = typeSearch.value.trim().toLowerCase();
    let list = props.typesFlat.slice();

    if (activeCategory.value !== '__all__' && !q) {
        list = list.filter(t => (t.category || 'Другое') === activeCategory.value);
    }
    if (q) {
        list = list.filter(t =>
            t.key.toLowerCase().includes(q)
            || (t.label || '').toLowerCase().includes(q)
            || (t.description || '').toLowerCase().includes(q)
            || (t.category || '').toLowerCase().includes(q)
        );
    }
    return list;
});

function onPickerOpen() {
    typeSearch.value = '';
    activeCategory.value = '__all__';
    setTimeout(() => document.getElementById('type-picker-search')?.focus(), 50);
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title">
        <template #actions>
            <Link href="/admin/blocks" class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i><span>К списку</span>
            </Link>
            <button v-if="isEdit" @click="destroy" class="inline-flex items-center gap-2 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 hover:bg-red-100 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-trash"></i><span>Удалить</span>
            </button>
        </template>
    </PageHeader>

    <form @submit.prevent="submit" class="space-y-6">
        <!-- Draft-restore prompt: shown once on mount if localStorage
             holds a newer unsaved version than what's in the DB. -->
        <div v-if="restorePrompt"
             class="flex items-center gap-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700/40 rounded-lg px-4 py-3">
            <i class="fas fa-clock-rotate-left text-amber-600 dark:text-amber-300"></i>
            <div class="flex-1 text-sm text-amber-900 dark:text-amber-200">
                У вас есть несохранённая версия от
                <strong>{{ new Date(restorePrompt.savedAt).toLocaleString() }}</strong>.
                Восстановить?
            </div>
            <button type="button" @click="acceptRestore"
                    class="px-3 py-1.5 text-sm rounded-md bg-amber-600 hover:bg-amber-700 text-white font-medium">
                Восстановить
            </button>
            <button type="button" @click="declineRestore"
                    class="px-3 py-1.5 text-sm rounded-md border border-amber-400 dark:border-amber-600 text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900/50">
                Отбросить
            </button>
        </div>

        <div class="flex items-center gap-1 border-b border-gray-200 dark:border-gray-700">
            <button v-for="(t, val) in { content: 'Контент', data: 'Данные', settings: 'Настройки' }"
                :key="val" type="button" @click="activeTab = val"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
                :class="activeTab === val
                    ? 'border-red-600 text-red-700 dark:text-red-300'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-200'">
                {{ t }}
            </button>
            <button v-if="isEdit && previewUrl" type="button" @click="previewEnabled = !previewEnabled"
                class="ml-auto px-3 py-1.5 text-xs rounded-md font-medium inline-flex items-center gap-2"
                :class="previewEnabled
                    ? 'bg-red-600 text-white hover:bg-red-700'
                    : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
                <i class="fas fa-eye"></i>
                {{ previewEnabled ? 'Скрыть предпросмотр' : 'Предпросмотр' }}
            </button>
        </div>

        <!-- Live preview sidebar (iframe). Refreshes on save; consumer
             Blade is the source of truth so no client-side render. -->
        <div v-if="previewEnabled && previewUrl"
             class="sticky top-16 z-10 -mx-4 lg:mx-0 lg:grid lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:gap-4">
            <div class="hidden lg:block"></div>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                    <i class="fas fa-globe text-gray-400 text-xs"></i>
                    <code class="flex-1 text-xs text-gray-600 dark:text-gray-400 truncate font-mono">{{ previewUrl }}</code>
                    <button type="button" @click="refreshPreview" title="Обновить"
                            class="p-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <i class="fas fa-rotate"></i>
                    </button>
                    <a :href="previewUrl" target="_blank" rel="noopener" title="Открыть в новой вкладке"
                       class="p-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
                <iframe ref="previewIframe" :src="previewUrl" class="w-full bg-white" style="height: 70vh;"></iframe>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div v-show="activeTab === 'content'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <LocaleTabs v-model="activeLocale" />
                    <div class="space-y-5">
                        <TranslatableField name="title"    type="text"     label="Заголовок"    :active-locale="activeLocale" :errors="form.errors" v-model="form.title" />
                        <TranslatableField name="subtitle" type="textarea" label="Подзаголовок" :active-locale="activeLocale" :errors="form.errors" v-model="form.subtitle" />
                        <TranslatableField name="content"  type="editor"   label="Содержимое"   :active-locale="activeLocale" :errors="form.errors" v-model="form.content" />
                    </div>
                </div>

                <div v-show="activeTab === 'data'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Данные блока</h3>
                        <span class="text-xs text-gray-400">{{ currentTypeInfo.label }}</span>
                    </div>

                    <!-- Visual schema-driven editor when the type has a schema. -->
                    <BlockDataEditor v-if="currentSchema" :schema="currentSchema" v-model="form.blockData" />

                    <!-- Raw JSON fallback for types without a schema. -->
                    <template v-else>
                        <p class="text-xs text-gray-500">Структурные данные (слайды, ссылки, карточки) в формате JSON. Для типов с готовой схемой здесь отрисовывается визуальный редактор.</p>
                        <textarea v-model="form.blockData" rows="18" spellcheck="false"
                            class="w-full font-mono text-sm px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"
                            :class="dataError ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'"></textarea>
                        <p v-if="dataError" class="text-sm text-red-500">{{ dataError }}</p>
                    </template>
                </div>

                <div v-show="activeTab === 'settings'" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-2">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Настройки отображения (JSON)</h3>
                    <p class="text-xs text-gray-500">Визуальные параметры: фон, цвет текста, CSS-классы.</p>
                    <textarea v-model="form.blockSettings" rows="12" spellcheck="false"
                        class="w-full font-mono text-sm px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"
                        :class="settingsError ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'"></textarea>
                    <p v-if="settingsError" class="text-sm text-red-500">{{ settingsError }}</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Публикация</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Статус</label>
                        <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.is_active" type="checkbox" class="w-5 h-5 rounded text-red-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Активен (показывается на сайте)</span>
                    </label>

                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Опубликовать в
                                <span class="text-xs font-normal text-gray-500">(оставь пустым для «сразу»)</span>
                            </label>
                            <input v-model="form.publish_at" type="datetime-local"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                            <p v-if="form.errors.publish_at" class="mt-1 text-xs text-red-500">{{ form.errors.publish_at }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Снять с публикации в
                                <span class="text-xs font-normal text-gray-500">(опционально)</span>
                            </label>
                            <input v-model="form.unpublish_at" type="datetime-local"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                            <p v-if="form.errors.unpublish_at" class="mt-1 text-xs text-red-500">{{ form.errors.unpublish_at }}</p>
                        </div>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            Статус переключается автоматически, когда наступает указанное время. Требуется запущенный Laravel-scheduler или cron на <code>admin-core:apply-schedule</code>.
                        </p>
                    </div>

                    <button type="submit" :disabled="form.processing" class="w-full bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white py-2.5 rounded-lg font-medium">
                        <i class="fas" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                        {{ isEdit ? 'Сохранить' : 'Создать' }}
                    </button>

                    <a v-if="isEdit" :href="`/admin/blocks/${item.id}/revisions`"
                       class="flex items-center justify-center gap-2 w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <i class="fas fa-clock-rotate-left"></i>
                        История изменений
                    </a>

                    <p v-if="draftSavedLabel"
                       class="flex items-center justify-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <i class="fas fa-circle-check text-green-500"></i>
                        черновик автосохранён в {{ draftSavedLabel }}
                    </p>
                </div>

                <!-- Visual type picker trigger -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-3">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Тип блока</h3>
                    <button type="button" @click="showTypePicker = true; onPickerOpen()"
                        class="w-full flex items-center gap-3 p-3 border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-red-500 rounded-lg text-left transition">
                        <div class="w-10 h-10 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-300 flex items-center justify-center flex-shrink-0">
                            <i :class="'fas ' + currentTypeInfo.icon"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-gray-900 dark:text-white truncate">{{ currentTypeInfo.label }}</div>
                            <div class="text-xs text-gray-500 line-clamp-1">{{ currentTypeInfo.description || 'Нажми для выбора другого типа' }}</div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                </div>

                <!-- Placement -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-3">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Размещение</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Страница <span class="text-red-500">*</span></label>
                        <select v-model="form.page_name" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm"
                            :class="form.errors.page_name ? 'border-red-500' : ''">
                            <option value="">— выберите страницу —</option>
                            <optgroup v-for="(pages, group) in pagesGrouped" :key="group" :label="group">
                                <option v-for="(label, slug) in pages" :key="slug" :value="slug">{{ label }}</option>
                            </optgroup>
                            <!-- Synthetic page_name (e.g. `procurement-6`) — keep block bound
                                 to its current page even if BlockCatalog doesn't list it. -->
                            <option v-if="form.page_name && !isPageInCatalog(form.page_name)"
                                    :value="form.page_name">
                                {{ form.page_name }} (текущая)
                            </option>
                        </select>
                        <p v-if="form.errors.page_name" class="text-xs text-red-500 mt-1">{{ form.errors.page_name }}</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ключ блока</label>
                            <button type="button" @click="manualKey = !manualKey; if (!manualKey) form.block_key = generateKey()"
                                class="text-xs text-gray-500 hover:text-red-600">
                                <i class="fas" :class="manualKey ? 'fa-wand-magic-sparkles' : 'fa-pen-to-square'"></i>
                                {{ manualKey ? 'Сгенерировать' : 'Изменить вручную' }}
                            </button>
                        </div>
                        <input v-if="manualKey" v-model="form.block_key" placeholder="hero, intro, features…"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm font-mono"
                            :class="form.errors.block_key ? 'border-red-500' : ''">
                        <div v-else class="px-3 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 text-gray-600 dark:text-gray-400 rounded-lg text-sm font-mono flex items-center gap-2">
                            <i class="fas fa-wand-magic-sparkles text-gray-400 text-xs"></i>
                            <span>{{ form.block_key || '(будет сгенерирован)' }}</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ manualKey ? 'Уникальный ID блока (только английские буквы, цифры и _)' : 'Генерируется из типа блока. Обычно редактировать не нужно.' }}
                        </p>
                        <p v-if="form.errors.block_key" class="text-xs text-red-500 mt-1">{{ form.errors.block_key }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Порядок на странице</label>
                        <input v-model="form.sort_order" type="number" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                    </div>
                </div>

                <div v-if="isEdit" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-2 text-sm">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Метаданные</h3>
                    <div class="text-gray-600 dark:text-gray-400 space-y-1">
                        <div>ID: <code class="font-mono text-gray-500">{{ item.id }}</code></div>
                        <div>Создан: {{ item.created_at }}</div>
                        <div>Обновлён: {{ item.updated_at }}</div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Type picker modal -->
    <Teleport to="body">
        <div v-if="showTypePicker" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showTypePicker = false" @keydown.esc="showTypePicker = false">
            <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-6xl shadow-2xl flex flex-col" style="max-height: 88vh">
                <!-- Top: title + search + close -->
                <header class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Выбор типа блока</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Выбери визуальный тип — он определяет, какие поля появятся во вкладке «Данные».</p>
                    </div>
                    <div class="relative w-80">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input id="type-picker-search" v-model="typeSearch" type="search"
                            placeholder="Поиск…"
                            class="w-full pl-9 pr-9 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500">
                        <button v-if="typeSearch" @click="typeSearch = ''" type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1">
                            <i class="fas fa-xmark text-xs"></i>
                        </button>
                    </div>
                    <button @click="showTypePicker = false" class="p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-xmark text-xl"></i>
                    </button>
                </header>

                <!-- Body: sidebar (categories) + main (cards) -->
                <div class="flex flex-1 min-h-0">
                    <!-- Sidebar -->
                    <aside class="w-56 flex-shrink-0 border-r border-gray-200 dark:border-gray-700 overflow-y-auto bg-gray-50 dark:bg-gray-900/30 py-3">
                        <nav class="space-y-0.5 px-2">
                            <button v-for="c in categories" :key="c.key" type="button"
                                @click="activeCategory = c.key; typeSearch = ''"
                                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-left transition"
                                :class="(activeCategory === c.key && !typeSearch)
                                    ? 'bg-red-600 text-white font-medium'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700'">
                                <i :class="'fas ' + c.icon" class="w-4 text-center opacity-80"></i>
                                <span class="flex-1 truncate">{{ c.label }}</span>
                                <span class="text-xs opacity-60">{{ c.count }}</span>
                            </button>
                        </nav>
                    </aside>

                    <!-- Main cards area -->
                    <div class="flex-1 overflow-y-auto p-5">
                        <div v-if="typeSearch" class="text-sm text-gray-500 mb-3">
                            Найдено: <strong>{{ filteredTypes.length }}</strong>
                            <button @click="typeSearch = ''" class="ml-2 text-xs text-red-600 hover:text-red-700">Сбросить</button>
                        </div>

                        <div v-if="filteredTypes.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <button v-for="t in filteredTypes" :key="t.key" type="button" @click="chooseType(t)"
                                class="group text-left p-4 rounded-xl border-2 transition hover:border-red-500 hover:shadow-md"
                                :class="t.key === form.block_type
                                    ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800'">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                        :class="t.key === form.block_type
                                            ? 'bg-red-600 text-white'
                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-500 group-hover:bg-red-50 group-hover:text-red-600'">
                                        <i :class="'fas ' + t.icon"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ t.label }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ t.description }}</div>
                                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                                            <code class="text-[11px] text-gray-400 font-mono">{{ t.key }}</code>
                                            <span v-if="typeSearch && t.category" class="text-[10px] uppercase tracking-wider text-gray-400">· {{ t.category }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="t.preview" class="mt-3 p-2 bg-gray-50 dark:bg-gray-900/40 rounded text-center text-xs font-mono text-gray-500">
                                    {{ t.preview }}
                                </div>
                            </button>
                        </div>

                        <div v-else class="py-16 text-center text-gray-500">
                            <i class="fas fa-face-frown text-4xl mb-2 opacity-30"></i>
                            <p v-if="typeSearch">Ничего не найдено по запросу «{{ typeSearch }}»</p>
                            <p v-else>В этой категории нет типов</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
