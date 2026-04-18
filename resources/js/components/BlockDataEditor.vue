<script setup>
/**
 * Schema-driven editor for a PageBlock's `data` JSON field. Renders
 * proper form inputs (text / textarea / image-url / file-upload /
 * array-of-records) instead of a raw JSON textarea.
 *
 * Schema shape — see Meta\AdminCore\Contracts\BlockCatalog::blockSchema().
 *
 * State model: the editor is fully controlled by its `modelValue`
 * prop (a JSON string). Every input writes back via `update:modelValue`.
 * No internal `ref()` / `reactive()` — avoids a chunk-splitting bug
 * where dynamic-key v-model binding against a ref-wrapped object
 * rendered existing values as empty.
 */
import { computed, ref } from 'vue';
import FilePreviewModal from './FilePreviewModal.vue';

const props = defineProps({
    modelValue:    { type: String, default: '{}' },
    schema:        { type: Object, default: null },
    uploadUrl:     { type: String, default: '/admin/upload/image' },
    fileUploadUrl: { type: String, default: '/admin/upload/file' },
    locales:       { type: Array, default: () => ['ru', 'kk', 'en'] },
});
const emit = defineEmits(['update:modelValue']);

// Locale currently selected for translatable fields. Shared across all
// such fields in the block (matches the outer block form's pattern).
const activeLocale = ref('ru');

const data = computed(() => {
    try {
        const parsed = JSON.parse(props.modelValue || '{}');
        return (parsed && typeof parsed === 'object') ? parsed : {};
    } catch {
        return {};
    }
});

function commit(next) {
    emit('update:modelValue', JSON.stringify(next, null, 2));
}

function setField(key, value) {
    commit({ ...data.value, [key]: value });
}

function setArray(key, arr) {
    commit({ ...data.value, [key]: arr });
}

function getArray(key) {
    const arr = data.value[key];
    return Array.isArray(arr) ? arr : [];
}

function addItem(field) {
    const blank = {};
    for (const f of (field.item_fields || [])) blank[f.key] = '';
    setArray(field.key, [...getArray(field.key), blank]);
}

function removeItem(field, i) {
    if (!confirm('Удалить запись?')) return;
    const arr = getArray(field.key).slice();
    arr.splice(i, 1);
    setArray(field.key, arr);
}

function moveItem(field, i, dir) {
    const arr = getArray(field.key).slice();
    const j = i + dir;
    if (j < 0 || j >= arr.length) return;
    [arr[i], arr[j]] = [arr[j], arr[i]];
    setArray(field.key, arr);
}

function updateRow(field, i, subKey, value, extras = {}) {
    const arr = getArray(field.key).slice();
    arr[i] = { ...arr[i], [subKey]: value, ...extras };
    setArray(field.key, arr);
}

// Translatable helpers — value is an object like {ru:"…",kk:"…",en:"…"}.
// We store it at the row's sub-key and flip one locale at a time.
function tVal(obj, key, locale) {
    const v = obj?.[key];
    if (!v) return '';
    if (typeof v === 'string') return locale === 'ru' ? v : '';
    return v[locale] ?? '';
}
function setTRow(field, i, subKey, locale, value) {
    const arr = getArray(field.key).slice();
    const row = { ...arr[i] };
    const existing = (row[subKey] && typeof row[subKey] === 'object') ? row[subKey] : { ru: '', kk: '', en: '' };
    row[subKey] = { ...existing, [locale]: value };
    arr[i] = row;
    setArray(field.key, arr);
}

// ===== Uploads =====

async function doUpload(url, file) {
    const fd = new FormData();
    fd.append('file', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fd.append('_token', csrf);
    const res = await fetch(url, {
        method: 'POST',
        body: fd,
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.url) throw new Error('No URL in response');
    return json;
}

async function pickImage(e, onSet) {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
        const { url } = await doUpload(props.uploadUrl, file);
        onSet(url);
    } catch (err) { alert('Ошибка загрузки: ' + err.message); }
    finally { e.target.value = ''; }
}

async function pickFile(e, onSet) {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
        const res = await doUpload(props.fileUploadUrl, file);
        onSet(res);
    } catch (err) { alert('Ошибка загрузки: ' + err.message); }
    finally { e.target.value = ''; }
}

// ===== Icon picker popup state =====
const iconPickerOpen = ref(null); // object { setter, close }
const ICON_SUGGESTIONS = [
    { icon: 'fas fa-file-alt',    label: 'Документ' },
    { icon: 'fas fa-file-pdf',    label: 'PDF' },
    { icon: 'fas fa-file-word',   label: 'Word' },
    { icon: 'fas fa-file-excel',  label: 'Excel' },
    { icon: 'fas fa-file-powerpoint', label: 'PowerPoint' },
    { icon: 'fas fa-file-archive',    label: 'Архив' },
    { icon: 'fas fa-file-image',  label: 'Картинка' },
    { icon: 'fas fa-file-video',  label: 'Видео' },
    { icon: 'fas fa-folder-open', label: 'Папка' },
    { icon: 'fas fa-link',        label: 'Ссылка' },
    { icon: 'fas fa-book',        label: 'Книга' },
    { icon: 'fas fa-graduation-cap', label: 'Учебный' },
    { icon: 'fas fa-gavel',       label: 'Юридический' },
    { icon: 'fas fa-certificate', label: 'Сертификат' },
    { icon: 'fas fa-award',       label: 'Награда' },
    { icon: 'fas fa-chart-line',  label: 'Отчёт' },
    { icon: 'fas fa-building',    label: 'Организация' },
    { icon: 'fas fa-users',       label: 'Комиссия' },
    { icon: 'fas fa-download',    label: 'Скачать' },
    { icon: 'fas fa-external-link-alt', label: 'Внешняя' },
];

const COLOR_PRESETS = [
    { value: 'red',    label: 'Красный',  hex: '#dc2626' },
    { value: 'blue',   label: 'Синий',    hex: '#2563eb' },
    { value: 'green',  label: 'Зелёный',  hex: '#16a34a' },
    { value: 'gold',   label: 'Золотой',  hex: '#ca8a04' },
    { value: 'purple', label: 'Фиолет.',  hex: '#9333ea' },
    { value: 'orange', label: 'Оранжев.', hex: '#ea580c' },
    { value: 'gray',   label: 'Серый',    hex: '#6b7280' },
];

function colorHex(value) {
    return COLOR_PRESETS.find((c) => c.value === value)?.hex || value || '#9ca3af';
}

// Extract a friendly filename from a URL (strip query + path).
function fileNameFromUrl(url) {
    if (!url) return '';
    try {
        const cleaned = String(url).split('?')[0].split('#')[0];
        const last = cleaned.split('/').filter(Boolean).pop() || '';
        return decodeURIComponent(last);
    } catch { return String(url); }
}

function fileExtension(url) {
    const name = fileNameFromUrl(url);
    const dot = name.lastIndexOf('.');
    return dot >= 0 ? name.slice(dot + 1).toLowerCase() : '';
}

const FILE_ICONS = {
    pdf: { icon: 'fa-file-pdf',        color: '#dc2626' },
    doc: { icon: 'fa-file-word',       color: '#2563eb' },
    docx:{ icon: 'fa-file-word',       color: '#2563eb' },
    xls: { icon: 'fa-file-excel',      color: '#16a34a' },
    xlsx:{ icon: 'fa-file-excel',      color: '#16a34a' },
    ppt: { icon: 'fa-file-powerpoint', color: '#ea580c' },
    pptx:{ icon: 'fa-file-powerpoint', color: '#ea580c' },
    zip: { icon: 'fa-file-archive',    color: '#6b7280' },
    rar: { icon: 'fa-file-archive',    color: '#6b7280' },
    '7z':{ icon: 'fa-file-archive',    color: '#6b7280' },
    jpg: { icon: 'fa-file-image',      color: '#9333ea' },
    jpeg:{ icon: 'fa-file-image',      color: '#9333ea' },
    png: { icon: 'fa-file-image',      color: '#9333ea' },
    gif: { icon: 'fa-file-image',      color: '#9333ea' },
    webp:{ icon: 'fa-file-image',      color: '#9333ea' },
    mp4: { icon: 'fa-file-video',      color: '#0891b2' },
    mp3: { icon: 'fa-file-audio',      color: '#0891b2' },
    txt: { icon: 'fa-file-alt',        color: '#6b7280' },
    rtf: { icon: 'fa-file-alt',        color: '#6b7280' },
    csv: { icon: 'fa-file-csv',        color: '#16a34a' },
};

function fileIcon(url) {
    const ext = fileExtension(url);
    return FILE_ICONS[ext] || { icon: 'fa-file', color: '#6b7280' };
}

function isExternalUrl(url) {
    return /^https?:\/\//i.test(url || '');
}

const previewRef = ref(null);
function openPreview(url, filename) {
    if (!url) return;
    previewRef.value?.open(url, filename || '');
}
</script>

<template>
    <div class="space-y-5">
        <!-- Locale picker: only shown if any schema field is translatable. -->
        <div v-if="(schema.items || []).some(f => f.item_fields?.some(s => s.type?.startsWith('translatable')) || (f.type || '').startsWith('translatable'))"
             class="inline-flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5 text-xs">
            <button v-for="loc in locales" :key="loc" type="button" @click="activeLocale = loc"
                class="px-3 py-1.5 rounded-md transition-colors"
                :class="activeLocale === loc
                    ? 'bg-white dark:bg-gray-800 text-red-700 dark:text-red-300 shadow-sm font-medium'
                    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900'">
                {{ loc.toUpperCase() }}
            </button>
        </div>

        <template v-for="field in (schema.items || [])" :key="field.key">
            <!-- Array-of-records -->
            <div v-if="field.type === 'array'" class="border border-gray-200 dark:border-gray-700 rounded-lg">
                <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-900/30 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-layer-group text-gray-400 text-sm"></i>
                        <h4 class="font-medium text-sm text-gray-900 dark:text-white">{{ field.label }}</h4>
                        <span class="text-xs text-gray-400">({{ getArray(field.key).length }})</span>
                    </div>
                    <button type="button" @click="addItem(field)"
                        class="text-xs text-red-600 hover:text-red-700 dark:text-red-400 inline-flex items-center gap-1">
                        <i class="fas fa-plus"></i> Добавить
                    </button>
                </div>

                <div v-if="getArray(field.key).length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                    Пока нет записей. Нажми «Добавить».
                </div>

                <div v-else class="p-3 space-y-3 bg-gray-50 dark:bg-gray-900/30">
                    <div v-for="(row, i) in getArray(field.key)" :key="i"
                        class="bg-white dark:bg-gray-800 rounded-lg border-2 border-gray-200 dark:border-gray-700 p-4 space-y-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono text-gray-400">#{{ i + 1 }}</span>
                                <!-- live preview for known link types -->
                                <span v-if="row.icon || row.color"
                                    class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                    <i :class="row.icon || 'fas fa-circle'" :style="{ color: colorHex(row.color) }"></i>
                                    <span class="truncate max-w-[280px]">{{ tVal(row, 'title', activeLocale) || row.title || '—' }}</span>
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="moveItem(field, i, -1)" :disabled="i === 0"
                                    class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30" title="Вверх">
                                    <i class="fas fa-arrow-up text-xs"></i>
                                </button>
                                <button type="button" @click="moveItem(field, i, 1)" :disabled="i === getArray(field.key).length - 1"
                                    class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30" title="Вниз">
                                    <i class="fas fa-arrow-down text-xs"></i>
                                </button>
                                <button type="button" @click="removeItem(field, i)"
                                    class="p-1.5 text-red-500 hover:text-red-700" title="Удалить">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <template v-for="sub in field.item_fields" :key="sub.key">
                                <div v-if="sub.key === 'icon'">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ sub.label }}</label>
                                    <div class="flex items-center gap-2">
                                        <div class="w-10 h-10 rounded border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                                            <i :class="row[sub.key] || 'fas fa-image'" class="text-gray-600 dark:text-gray-300"></i>
                                        </div>
                                        <input type="text"
                                            :value="row[sub.key] ?? ''"
                                            @input="e => updateRow(field, i, sub.key, e.target.value)"
                                            placeholder="fas fa-file-alt"
                                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white font-mono focus:ring-2 focus:ring-red-500">
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <button v-for="s in ICON_SUGGESTIONS" :key="s.icon" type="button"
                                            @click="updateRow(field, i, sub.key, s.icon)"
                                            :title="s.label"
                                            :class="['w-8 h-8 rounded border flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-red-50 hover:border-red-300',
                                                    row[sub.key] === s.icon ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700']">
                                            <i :class="s.icon" class="text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <div v-else-if="sub.key === 'color'">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ sub.label }}</label>
                                    <div class="flex items-center gap-1.5">
                                        <button v-for="c in COLOR_PRESETS" :key="c.value" type="button"
                                            @click="updateRow(field, i, sub.key, c.value)"
                                            :title="c.label"
                                            :class="['px-3 py-2 rounded-md text-xs font-medium transition border-2',
                                                    row[sub.key] === c.value ? 'border-gray-900 dark:border-white' : 'border-transparent hover:border-gray-300']"
                                            :style="{ background: c.hex + '20', color: c.hex }">
                                            {{ c.label }}
                                        </button>
                                        <input v-if="!COLOR_PRESETS.some(c => c.value === row[sub.key])"
                                            type="text"
                                            :value="row[sub.key] ?? ''"
                                            @input="e => updateRow(field, i, sub.key, e.target.value)"
                                            placeholder="произвольный"
                                            class="flex-1 px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-xs text-gray-900 dark:text-white font-mono">
                                    </div>
                                </div>

                                <div v-else>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ sub.label }}</label>

                                    <textarea v-if="sub.type === 'textarea'" rows="2"
                                        :value="row[sub.key] ?? ''"
                                        @input="e => updateRow(field, i, sub.key, e.target.value)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>

                                <div v-else-if="sub.type === 'image'" class="flex items-center gap-2">
                                    <input type="text" placeholder="URL или путь"
                                        :value="row[sub.key] ?? ''"
                                        @input="e => updateRow(field, i, sub.key, e.target.value)"
                                        class="flex-1 px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                    <label class="flex-shrink-0 px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-sm cursor-pointer text-gray-700 dark:text-gray-200">
                                        <i class="fas fa-upload text-xs"></i>
                                        <input type="file" accept="image/*" class="hidden" @change="pickImage($event, url => updateRow(field, i, sub.key, url))">
                                    </label>
                                </div>

                                <div v-else-if="sub.type === 'file'" class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <input type="text" placeholder="URL файла"
                                            :value="row[sub.key] ?? ''"
                                            @input="e => updateRow(field, i, sub.key, e.target.value)"
                                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                        <label class="flex-shrink-0 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-sm cursor-pointer text-gray-700 dark:text-gray-200" title="PDF, DOC, XLS, ZIP…">
                                            <i class="fas fa-file-arrow-up text-xs"></i>
                                            <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.rtf,.csv" class="hidden"
                                                @change="pickFile($event, res => updateRow(field, i, sub.key, res.url, {
                                                    filename: res.filename,
                                                    size: res.size,
                                                    ext: res.ext,
                                                }))">
                                        </label>
                                    </div>
                                    <button v-if="row[sub.key]" type="button" @click="openPreview(row[sub.key], row.filename)"
                                        class="w-full flex items-center gap-3 p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 hover:bg-white dark:hover:bg-gray-800 hover:border-red-300 transition text-left">
                                        <div class="w-10 h-10 rounded flex items-center justify-center flex-shrink-0"
                                            :style="{ background: fileIcon(row[sub.key]).color + '20', color: fileIcon(row[sub.key]).color }">
                                            <i :class="'fas ' + fileIcon(row[sub.key]).icon"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ fileNameFromUrl(row[sub.key]) }}</div>
                                            <div class="text-xs text-gray-500 flex items-center gap-1.5">
                                                <span class="uppercase">{{ fileExtension(row[sub.key]) || 'file' }}</span>
                                                <span v-if="row.size">· {{ (row.size / 1024).toFixed(1) }} KB</span>
                                                <span v-if="isExternalUrl(row[sub.key])" class="flex items-center gap-1"><i class="fas fa-external-link-alt text-[10px]"></i>внешняя</span>
                                            </div>
                                        </div>
                                        <i class="fas fa-eye text-gray-400 text-xs"></i>
                                    </button>
                                </div>

                                <div v-else-if="sub.type === 'translatable'" class="space-y-1">
                                    <input type="text"
                                        :value="tVal(row, sub.key, activeLocale)"
                                        @input="e => setTRow(field, i, sub.key, activeLocale, e.target.value)"
                                        :placeholder="activeLocale.toUpperCase()"
                                        class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                </div>

                                <div v-else-if="sub.type === 'translatable_textarea'" class="space-y-1">
                                    <textarea rows="2"
                                        :value="tVal(row, sub.key, activeLocale)"
                                        @input="e => setTRow(field, i, sub.key, activeLocale, e.target.value)"
                                        :placeholder="activeLocale.toUpperCase()"
                                        class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>
                                </div>

                                <div v-else-if="sub.type === 'translatable_file'" class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <input type="text"
                                            :value="tVal(row, sub.key, activeLocale)"
                                            @input="e => setTRow(field, i, sub.key, activeLocale, e.target.value)"
                                            :placeholder="'URL ' + activeLocale.toUpperCase()"
                                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                        <label class="flex-shrink-0 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-sm cursor-pointer text-gray-700 dark:text-gray-200" title="PDF, DOC, XLS, ZIP…">
                                            <i class="fas fa-file-arrow-up text-xs"></i>
                                            <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.rtf,.csv" class="hidden"
                                                @change="pickFile($event, res => setTRow(field, i, sub.key, activeLocale, res.url))">
                                        </label>
                                    </div>
                                    <button v-if="tVal(row, sub.key, activeLocale)" type="button"
                                        @click="openPreview(tVal(row, sub.key, activeLocale), tVal(row, 'title', activeLocale))"
                                        class="w-full flex items-center gap-3 p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 hover:bg-white dark:hover:bg-gray-800 hover:border-red-300 transition text-left">
                                        <div class="w-10 h-10 rounded flex items-center justify-center flex-shrink-0"
                                            :style="{ background: fileIcon(tVal(row, sub.key, activeLocale)).color + '20', color: fileIcon(tVal(row, sub.key, activeLocale)).color }">
                                            <i :class="'fas ' + fileIcon(tVal(row, sub.key, activeLocale)).icon"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ fileNameFromUrl(tVal(row, sub.key, activeLocale)) }}</div>
                                            <div class="text-xs text-gray-500 flex items-center gap-1.5">
                                                <span class="uppercase">{{ fileExtension(tVal(row, sub.key, activeLocale)) || 'file' }}</span>
                                                <span v-if="isExternalUrl(tVal(row, sub.key, activeLocale))" class="flex items-center gap-1"><i class="fas fa-external-link-alt text-[10px]"></i>внешняя</span>
                                                <span class="ml-1 px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-[10px]">{{ activeLocale.toUpperCase() }}</span>
                                            </div>
                                        </div>
                                        <i class="fas fa-eye text-gray-400 text-xs"></i>
                                    </button>
                                </div>

                                    <input v-else :type="sub.type === 'url' ? 'url' : sub.type === 'number' ? 'number' : 'text'"
                                        :value="row[sub.key] ?? ''"
                                        @input="e => updateRow(field, i, sub.key, e.target.value)"
                                        :placeholder="sub.placeholder || ''"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scalar top-level fields -->
            <div v-else>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ field.label }}</label>

                <textarea v-if="field.type === 'textarea'" rows="3"
                    :value="data[field.key] ?? ''"
                    @input="e => setField(field.key, e.target.value)"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>

                <div v-else-if="field.type === 'image'" class="flex items-center gap-2">
                    <input type="text" placeholder="URL или путь"
                        :value="data[field.key] ?? ''"
                        @input="e => setField(field.key, e.target.value)"
                        class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                    <label class="flex-shrink-0 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg text-sm cursor-pointer text-gray-700 dark:text-gray-200">
                        <i class="fas fa-upload text-xs mr-1"></i>Загрузить
                        <input type="file" accept="image/*" class="hidden" @change="pickImage($event, url => setField(field.key, url))">
                    </label>
                </div>

                <div v-else-if="field.type === 'file'" class="flex items-center gap-2">
                    <input type="text" placeholder="URL файла"
                        :value="data[field.key] ?? ''"
                        @input="e => setField(field.key, e.target.value)"
                        class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                    <label class="flex-shrink-0 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg text-sm cursor-pointer text-gray-700 dark:text-gray-200" title="PDF, DOC, XLS, ZIP…">
                        <i class="fas fa-file-arrow-up text-xs mr-1"></i>Файл
                        <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.rtf,.csv" class="hidden" @change="pickFile($event, res => setField(field.key, res.url))">
                    </label>
                </div>

                <input v-else :type="field.type === 'url' ? 'url' : field.type === 'number' ? 'number' : 'text'"
                    :value="data[field.key] ?? ''"
                    @input="e => setField(field.key, e.target.value)"
                    :placeholder="field.placeholder || ''"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
            </div>
        </template>

        <!-- Inline viewer (PDF / image / video / fallback). -->
        <FilePreviewModal ref="previewRef" />
    </div>
</template>
