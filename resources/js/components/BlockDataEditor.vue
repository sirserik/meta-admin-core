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
    for (const f of (field.item_fields || [])) blank[f.key] = (f.type === 'images' ? [] : '');
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
// Top-level translatable field: flip one locale on data[key]'s {ru,kk,en} map.
function setTField(key, locale, value) {
    const cur = data.value[key];
    const existing = (cur && typeof cur === 'object') ? cur : { ru: '', kk: '', en: '' };
    setField(key, { ...existing, [locale]: value });
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

// ===== type: 'images' — список картинок внутри записи массива =====
// Хранится как массив строк (URL или относительный путь). Загрузка —
// сразу несколько файлов, порядок правится стрелками.
function rowImages(field, i, subKey) {
    const v = getArray(field.key)[i]?.[subKey];
    if (Array.isArray(v)) return v;
    return (typeof v === 'string' && v) ? [v] : [];
}
function addRowImages(field, i, subKey, urls) {
    updateRow(field, i, subKey, [...rowImages(field, i, subKey), ...urls]);
}
function removeRowImage(field, i, subKey, pi) {
    const imgs = rowImages(field, i, subKey).slice();
    imgs.splice(pi, 1);
    updateRow(field, i, subKey, imgs);
}
function moveRowImage(field, i, subKey, pi, dir) {
    const imgs = rowImages(field, i, subKey).slice();
    const pj = pi + dir;
    if (pj < 0 || pj >= imgs.length) return;
    [imgs[pi], imgs[pj]] = [imgs[pj], imgs[pi]];
    updateRow(field, i, subKey, imgs);
}
async function pickImages(e, onAdd) {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;
    const urls = [];
    try {
        for (const file of files) {
            const { url } = await doUpload(props.uploadUrl, file);
            urls.push(url);
        }
    } catch (err) { alert('Ошибка загрузки: ' + err.message); }
    finally { e.target.value = ''; }
    if (urls.length) onAdd(urls);
}
// Превью: абсолютные URL/пути как есть, «голый» относительный путь —
// через префикс медиа (legacy-сайты хранят 'dir/file.jpg' → /media/…).
function previewSrc(url) {
    if (typeof url !== 'string' || !url) return '';
    if (/^(https?:)?\/\//.test(url) || url.startsWith('/')) return thumbSrc(url);
    return thumbSrc('/media/' + url);
}

// ===== Поворот загруженной картинки =====
// Крутится на сервере на месте (значение поля/URL не меняется),
// поэтому превью обновляем локальным cache-buster'ом.
const imgBust = ref({});
const isRotatableImage = (url) => typeof url === 'string' && /\.(jpe?g|png|gif|webp)$/i.test((url.split('?')[0] || ''));
const thumbSrc = (url) => url + (imgBust.value[url] ? (url.includes('?') ? '&' : '?') + 'v=' + imgBust.value[url] : '');
async function rotateImage(url) {
    if (!url) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch('/admin/upload/rotate-image', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ path: url, degrees: 90 }),
        });
        const data = await res.json();
        if (data.success) imgBust.value = { ...imgBust.value, [url]: Date.now() };
        else alert(data.message || 'Не удалось повернуть изображение');
    } catch { alert('Не удалось повернуть изображение'); }
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

// ===== Ключи data, которых нет в схеме типа =====
//
// Form.vue при наличии схемы прячет сырой JSON, поэтому всё, что схема не
// объявила, раньше было не видно и правилось только через tinker (на ETU
// таких ключей набралось под сотню: badge, stats, button1_text, benefits…).
// Достраиваем виджет по форме самого значения — схема остаётся источником
// правды для порядка и подписей, а «хвост» больше не теряется.

const LONG_TEXT = 90;

const isI18nMap = (v) => v !== null && typeof v === 'object' && !Array.isArray(v)
    && Object.keys(v).length > 0 && Object.keys(v).every(k => props.locales.includes(k));

/** Виджет для скалярного значения; null — форма не скалярная. */
function inferScalar(key, v) {
    if (v === null || v === undefined) return { key, type: 'text' };
    if (typeof v === 'boolean') return { key, type: 'checkbox' };
    if (typeof v === 'number') return { key, type: 'number' };
    if (typeof v === 'string') return { key, type: v.length > LONG_TEXT ? 'textarea' : 'text' };
    if (isI18nMap(v)) {
        const long = Object.values(v).some(s => typeof s === 'string' && s.length > LONG_TEXT);
        return { key, type: long ? 'translatable_textarea' : 'translatable' };
    }
    return null;
}

function inferField(key, v) {
    const scalar = inferScalar(key, v);
    if (scalar) return { label: key, inferred: true, ...scalar };

    // Массив однородных записей → обычный репитер. Набор подполей собираем
    // по всем записям: в первой строке значение может быть null.
    if (Array.isArray(v) && v.length && v.every(r => r !== null && typeof r === 'object' && !Array.isArray(r))) {
        const sample = {};
        for (const row of v) {
            for (const k of Object.keys(row)) {
                if (!(k in sample) || sample[k] === null || sample[k] === undefined) sample[k] = row[k];
            }
        }
        const subs = Object.entries(sample).map(([k, sv]) => inferScalar(k, sv));
        if (subs.every(Boolean)) {
            return { key, label: key, inferred: true, type: 'array', item_fields: subs.map(s => ({ ...s, label: s.key })) };
        }
    }

    // Всё остальное (вложенные объекты, массивы массивов) — JSON-поле.
    return { key, label: key, inferred: true, type: 'json' };
}

// Виджет обязан подходить форме значения. Схема описывает намерение, но
// данные накапливались годами: там, где схема говорит `text`, а в блоке лежит
// карта {ru,kk,en}, в поле рисовался «[object Object]», и сохранение схлопывало
// перевод в одну строку. Поэтому объявленный тип чиним по фактическому значению.
const TRANSLATABLE_OF = { textarea: 'translatable_textarea', file: 'translatable_file' };
const toTranslatable = (type) => TRANSLATABLE_OF[type] || 'translatable';

function adaptField(field, value) {
    let out = field;

    if (value !== undefined && value !== null) {
        if (field.type === 'array' && !Array.isArray(value)) {
            return { ...inferField(field.key, value), label: field.label };
        }
        if (field.type !== 'array' && typeof value === 'object' && !Array.isArray(value)) {
            out = isI18nMap(value)
                ? (String(field.type).startsWith('translatable') ? field : { ...field, type: toTranslatable(field.type) })
                : { ...inferField(field.key, value), label: field.label };
        }
    }

    // Колонки репитера: тип колонки один на все записи, поэтому переводим её,
    // если карта {ru,kk,en} встретилась хоть в одной строке.
    if (out.type === 'array' && Array.isArray(value) && out.item_fields?.length) {
        const item_fields = out.item_fields.map((sub) => {
            if (String(sub.type).startsWith('translatable')) return sub;
            const anyMap = value.some(r => r !== null && typeof r === 'object' && isI18nMap(r[sub.key]));
            return anyMap ? { ...sub, type: toTranslatable(sub.type) } : sub;
        });
        if (item_fields.some((s, i) => s !== out.item_fields[i])) out = { ...out, item_fields };
    }

    return out;
}

const fields = computed(() => {
    const items = props.schema?.items || [];
    const known = new Set(items.map(f => f.key));
    const declared = items.map(f => adaptField(f, data.value[f.key]));
    const extra = Object.keys(data.value)
        .filter(k => !known.has(k))
        .map(k => inferField(k, data.value[k]));
    return [...declared, ...extra];
});

// ===== type: 'json' — редактирование значения как JSON =====
// Черновик держим отдельно, иначе повторная сериализация на каждый символ
// переформатирует текст и уводит курсор.
const jsonDraft = ref({});
const jsonError = ref({});
const jsonText = (key) => (jsonDraft.value[key] ?? (data.value[key] === undefined ? '' : JSON.stringify(data.value[key], null, 2)));
function setJson(key, raw) {
    jsonDraft.value = { ...jsonDraft.value, [key]: raw };
    if (raw.trim() === '') {
        jsonError.value = { ...jsonError.value, [key]: '' };
        setField(key, null);
        return;
    }
    try {
        const parsed = JSON.parse(raw);
        jsonError.value = { ...jsonError.value, [key]: '' };
        setField(key, parsed);
    } catch (e) {
        jsonError.value = { ...jsonError.value, [key]: e.message };
    }
}
</script>

<template>
    <div class="space-y-5">
        <!-- Locale picker: only shown if any schema field is translatable. -->
        <div v-if="fields.some(f => f.item_fields?.some(s => s.type?.startsWith('translatable')) || (f.type || '').startsWith('translatable'))"
             class="inline-flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5 text-xs">
            <button v-for="loc in locales" :key="loc" type="button" @click="activeLocale = loc"
                class="px-3 py-1.5 rounded-md transition-colors"
                :class="activeLocale === loc
                    ? 'bg-white dark:bg-gray-800 text-red-700 dark:text-red-300 shadow-sm font-medium'
                    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900'">
                {{ loc.toUpperCase() }}
            </button>
        </div>

        <template v-for="field in fields" :key="field.key">
            <!-- Array-of-records -->
            <div v-if="field.type === 'array'" class="border border-gray-200 dark:border-gray-700 rounded-lg">
                <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-900/30 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-layer-group text-gray-400 text-sm"></i>
                        <h4 class="font-medium text-sm text-gray-900 dark:text-white">{{ field.label }}</h4>
                        <span class="text-xs text-gray-400">({{ getArray(field.key).length }})</span>
                        <span v-if="field.inferred" class="px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-[10px] text-gray-500 dark:text-gray-400"
                            title="Ключа нет в схеме типа блока — поля собраны по данным">вне схемы</span>
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

                                <div v-else-if="sub.type === 'image'" class="space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <input type="text" placeholder="URL или путь"
                                            :value="row[sub.key] ?? ''"
                                            @input="e => updateRow(field, i, sub.key, e.target.value)"
                                            class="flex-1 px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                        <label class="flex-shrink-0 px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-sm cursor-pointer text-gray-700 dark:text-gray-200">
                                            <i class="fas fa-upload text-xs"></i>
                                            <input type="file" accept="image/*" class="hidden" @change="pickImage($event, url => updateRow(field, i, sub.key, url))">
                                        </label>
                                    </div>
                                    <div v-if="isRotatableImage(row[sub.key])" class="flex items-center gap-2">
                                        <img :src="thumbSrc(row[sub.key])" class="h-12 w-20 object-contain rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                                        <button type="button" @click="rotateImage(row[sub.key])" title="Повернуть на 90° по часовой"
                                            class="px-2.5 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-xs text-gray-700 dark:text-gray-200">
                                            <i class="fas fa-rotate-right"></i>
                                        </button>
                                    </div>
                                </div>

                                <div v-else-if="sub.type === 'images'" class="space-y-2">
                                    <div v-if="rowImages(field, i, sub.key).length" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                        <div v-for="(img, pi) in rowImages(field, i, sub.key)" :key="pi"
                                            class="group relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                                            <img :src="previewSrc(img)" class="w-full h-24 object-cover" loading="lazy">
                                            <div class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 py-1 bg-black/55 opacity-0 group-hover:opacity-100 transition">
                                                <button type="button" @click="moveRowImage(field, i, sub.key, pi, -1)" :disabled="pi === 0"
                                                    class="p-1 text-white/90 hover:text-white disabled:opacity-30" title="Левее">
                                                    <i class="fas fa-arrow-left text-xs"></i>
                                                </button>
                                                <button type="button" @click="moveRowImage(field, i, sub.key, pi, 1)" :disabled="pi === rowImages(field, i, sub.key).length - 1"
                                                    class="p-1 text-white/90 hover:text-white disabled:opacity-30" title="Правее">
                                                    <i class="fas fa-arrow-right text-xs"></i>
                                                </button>
                                                <button v-if="isRotatableImage(img)" type="button" @click="rotateImage(img)"
                                                    class="p-1 text-white/90 hover:text-white" title="Повернуть на 90°">
                                                    <i class="fas fa-rotate-right text-xs"></i>
                                                </button>
                                                <button type="button" @click="removeRowImage(field, i, sub.key, pi)"
                                                    class="p-1 text-red-300 hover:text-red-400" title="Удалить фото">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <label class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-sm cursor-pointer text-gray-700 dark:text-gray-200">
                                        <i class="fas fa-upload text-xs"></i> Добавить фото
                                        <input type="file" accept="image/*" multiple class="hidden"
                                            @change="pickImages($event, urls => addRowImages(field, i, sub.key, urls))">
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

                                <select v-else-if="sub.type === 'select'"
                                    :value="row[sub.key] ?? ''"
                                    @change="e => updateRow(field, i, sub.key, e.target.value)"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                    <option value="">—</option>
                                    <option v-for="opt in (sub.options || [])"
                                        :key="(opt && opt.value !== undefined) ? opt.value : opt"
                                        :value="(opt && opt.value !== undefined) ? opt.value : opt">
                                        {{ (opt && opt.label) ? opt.label : ((opt && opt.value !== undefined) ? opt.value : opt) }}
                                    </option>
                                </select>

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
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ field.label }}
                    <span v-if="field.inferred" class="ml-1.5 px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-[10px] font-normal text-gray-500 dark:text-gray-400"
                        title="Ключа нет в схеме типа блока — поле собрано по данным">вне схемы</span>
                </label>

                <label v-if="field.type === 'checkbox'" class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" :checked="data[field.key] === true"
                        @change="e => setField(field.key, e.target.checked)"
                        class="rounded border-gray-300 dark:border-gray-600 text-red-600 focus:ring-red-500">
                    {{ data[field.key] === true ? 'включено' : 'выключено' }}
                </label>

                <select v-else-if="field.type === 'select'"
                    :value="data[field.key] ?? ''"
                    @change="e => setField(field.key, e.target.value)"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                    <option v-for="opt in (field.options || [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>

                <div v-else-if="field.type === 'json'" class="space-y-1">
                    <textarea rows="6" spellcheck="false"
                        :value="jsonText(field.key)"
                        @input="e => setJson(field.key, e.target.value)"
                        class="w-full px-3 py-2 border rounded-lg text-xs font-mono bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"
                        :class="jsonError[field.key] ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'"></textarea>
                    <p v-if="jsonError[field.key]" class="text-xs text-red-600">Невалидный JSON: {{ jsonError[field.key] }}</p>
                    <p v-else class="text-xs text-gray-400">Вложенная структура — правится как JSON.</p>
                </div>

                <textarea v-else-if="field.type === 'textarea'" rows="3"
                    :value="data[field.key] ?? ''"
                    @input="e => setField(field.key, e.target.value)"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>

                <div v-else-if="field.type === 'image'" class="space-y-1.5">
                    <div class="flex items-center gap-2">
                        <input type="text" placeholder="URL или путь"
                            :value="data[field.key] ?? ''"
                            @input="e => setField(field.key, e.target.value)"
                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                        <label class="flex-shrink-0 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg text-sm cursor-pointer text-gray-700 dark:text-gray-200">
                            <i class="fas fa-upload text-xs mr-1"></i>Загрузить
                            <input type="file" accept="image/*" class="hidden" @change="pickImage($event, url => setField(field.key, url))">
                        </label>
                    </div>
                    <div v-if="isRotatableImage(data[field.key])" class="flex items-center gap-2">
                        <img :src="thumbSrc(data[field.key])" class="h-14 w-24 object-contain rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                        <button type="button" @click="rotateImage(data[field.key])" title="Повернуть на 90° по часовой"
                            class="px-2.5 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-xs text-gray-700 dark:text-gray-200">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
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

                <div v-else-if="field.type === 'translatable'" class="space-y-1">
                    <input type="text"
                        :value="tVal(data, field.key, activeLocale)"
                        @input="e => setTField(field.key, activeLocale, e.target.value)"
                        :placeholder="field.placeholder || activeLocale.toUpperCase()"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                </div>

                <div v-else-if="field.type === 'translatable_textarea'" class="space-y-1">
                    <textarea rows="3"
                        :value="tVal(data, field.key, activeLocale)"
                        @input="e => setTField(field.key, activeLocale, e.target.value)"
                        :placeholder="field.placeholder || activeLocale.toUpperCase()"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>
                </div>

                <div v-else-if="field.type === 'translatable_file'" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <input type="text"
                            :value="tVal(data, field.key, activeLocale)"
                            @input="e => setTField(field.key, activeLocale, e.target.value)"
                            :placeholder="'URL ' + activeLocale.toUpperCase()"
                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                        <label class="flex-shrink-0 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg text-sm cursor-pointer text-gray-700 dark:text-gray-200" title="PDF, DOC, XLS, ZIP…">
                            <i class="fas fa-file-arrow-up text-xs mr-1"></i>{{ activeLocale.toUpperCase() }}
                            <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.rtf,.csv" class="hidden"
                                @change="pickFile($event, res => setTField(field.key, activeLocale, res.url))">
                        </label>
                    </div>
                    <button v-if="tVal(data, field.key, activeLocale)" type="button"
                        @click="openPreview(tVal(data, field.key, activeLocale), field.label)"
                        class="w-full flex items-center gap-3 p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 hover:bg-white dark:hover:bg-gray-800 hover:border-red-300 transition text-left">
                        <div class="w-10 h-10 rounded flex items-center justify-center flex-shrink-0"
                            :style="{ background: fileIcon(tVal(data, field.key, activeLocale)).color + '20', color: fileIcon(tVal(data, field.key, activeLocale)).color }">
                            <i :class="'fas ' + fileIcon(tVal(data, field.key, activeLocale)).icon"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ fileNameFromUrl(tVal(data, field.key, activeLocale)) }}</div>
                            <div class="text-xs text-gray-500 flex items-center gap-1.5">
                                <span class="uppercase">{{ fileExtension(tVal(data, field.key, activeLocale)) || 'file' }}</span>
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-[10px]">{{ activeLocale.toUpperCase() }}</span>
                            </div>
                        </div>
                    </button>
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
