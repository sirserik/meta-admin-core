<script setup>
/**
 * Schema-driven editor for a PageBlock's `data` JSON field. Renders
 * proper form inputs (text / textarea / image-url / array-of-records)
 * instead of a raw JSON textarea.
 *
 * Schema shape — see Meta\AdminCore\Contracts\BlockCatalog::blockSchema().
 *
 * The editor keeps its value in `modelValue` as a JSON STRING (same
 * contract as the old textarea) so the surrounding form doesn't need
 * to change — it just emits on every field edit.
 */
import { reactive, watch } from 'vue';

const props = defineProps({
    modelValue:    { type: String, default: '{}' },
    schema:        { type: Object, default: null },
    uploadUrl:     { type: String, default: '/admin/upload/image' },
    fileUploadUrl: { type: String, default: '/admin/upload/file' },
});
const emit = defineEmits(['update:modelValue']);

function parse(json) {
    try {
        const parsed = JSON.parse(json || '{}');
        return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch {
        return {};
    }
}

// `reactive()` (not `ref()`) so v-model="data[field.key]" picks up
// dynamic keys correctly — nested property access on a ref-wrapped
// object was rendering empty inputs for existing values.
const data = reactive(parse(props.modelValue));

// Emit JSON string up to the parent whenever any key changes.
watch(data, () => {
    emit('update:modelValue', JSON.stringify(data, null, 2));
}, { deep: true });

// Parent reset (e.g. user switched block type) → replace our state.
watch(() => props.modelValue, (v) => {
    const incoming = parse(v);
    const current = JSON.stringify(data);
    if (JSON.stringify(incoming) === current) return;
    // Wipe + re-hydrate so reactivity catches all changes.
    for (const k of Object.keys(data)) delete data[k];
    Object.assign(data, incoming);
});

// ===== Array helpers =====

function ensureArray(key) {
    if (!Array.isArray(data[key])) data[key] = [];
    return data[key];
}

function addItem(field) {
    const arr = ensureArray(field.key);
    const blank = {};
    for (const f of (field.item_fields || [])) blank[f.key] = '';
    arr.push(blank);
}

function removeItem(field, i) {
    if (!confirm('Удалить запись?')) return;
    ensureArray(field.key).splice(i, 1);
}

function moveItem(field, i, dir) {
    const arr = ensureArray(field.key);
    const j = i + dir;
    if (j < 0 || j >= arr.length) return;
    const tmp = arr[i];
    arr[i] = arr[j];
    arr[j] = tmp;
}

// ===== Image upload for `image` fields =====

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

async function onImagePick(e, onSet) {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
        const { url } = await doUpload(props.uploadUrl, file);
        onSet(url);
    } catch (err) {
        alert('Ошибка загрузки: ' + err.message);
    } finally {
        e.target.value = '';
    }
}

async function onFilePick(e, onSet) {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
        const res = await doUpload(props.fileUploadUrl, file);
        // Pass the full metadata (url, filename, size, ext) so the
        // consumer can choose to display the download label.
        onSet(res);
    } catch (err) {
        alert('Ошибка загрузки: ' + err.message);
    } finally {
        e.target.value = '';
    }
}

function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

// Backwards-compat: if schema item is a scalar top-level field (not
// an array), we still need to round-trip through data[field.key].
</script>

<template>
    <div class="space-y-5">
        <template v-for="field in (schema.items || [])" :key="field.key">
            <!-- Array-of-records -->
            <div v-if="field.type === 'array'" class="border border-gray-200 dark:border-gray-700 rounded-lg">
                <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-900/30 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-layer-group text-gray-400 text-sm"></i>
                        <h4 class="font-medium text-sm text-gray-900 dark:text-white">{{ field.label }}</h4>
                        <span class="text-xs text-gray-400">({{ (data[field.key] || []).length }})</span>
                    </div>
                    <button type="button" @click="addItem(field)"
                        class="text-xs text-red-600 hover:text-red-700 dark:text-red-400 inline-flex items-center gap-1">
                        <i class="fas fa-plus"></i> Добавить
                    </button>
                </div>

                <div v-if="(data[field.key] || []).length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                    Пока нет записей. Нажми «Добавить».
                </div>

                <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                    <div v-for="(row, i) in data[field.key]" :key="i" class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-mono text-gray-400">#{{ i + 1 }}</span>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="moveItem(field, i, -1)" :disabled="i === 0"
                                    class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30" title="Вверх">
                                    <i class="fas fa-arrow-up text-xs"></i>
                                </button>
                                <button type="button" @click="moveItem(field, i, 1)" :disabled="i === data[field.key].length - 1"
                                    class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30" title="Вниз">
                                    <i class="fas fa-arrow-down text-xs"></i>
                                </button>
                                <button type="button" @click="removeItem(field, i)"
                                    class="p-1.5 text-red-500 hover:text-red-700" title="Удалить">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div v-for="sub in field.item_fields" :key="sub.key" :class="sub.type === 'textarea' ? 'sm:col-span-2' : ''">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ sub.label }}</label>

                                <textarea v-if="sub.type === 'textarea'" v-model="row[sub.key]" rows="2"
                                    class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>

                                <div v-else-if="sub.type === 'image'" class="flex items-center gap-2">
                                    <input v-model="row[sub.key]" type="text" placeholder="URL или путь"
                                        class="flex-1 px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                    <label class="flex-shrink-0 px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-sm cursor-pointer text-gray-700 dark:text-gray-200">
                                        <i class="fas fa-upload text-xs"></i>
                                        <input type="file" accept="image/*" class="hidden" @change="onImagePick($event, url => row[sub.key] = url)">
                                    </label>
                                </div>

                                <div v-else-if="sub.type === 'file'" class="flex items-center gap-2">
                                    <input v-model="row[sub.key]" type="text" placeholder="URL файла"
                                        class="flex-1 px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                    <label class="flex-shrink-0 px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-sm cursor-pointer text-gray-700 dark:text-gray-200" title="PDF, DOC, XLS, ZIP…">
                                        <i class="fas fa-file-arrow-up text-xs"></i>
                                        <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.rtf,.csv" class="hidden"
                                            @change="onFilePick($event, res => {
                                                row[sub.key] = res.url;
                                                if (row.filename === undefined || !row.filename) row.filename = res.filename;
                                                if (row.size === undefined) row.size = res.size;
                                                if (row.ext === undefined) row.ext = res.ext;
                                            })">
                                    </label>
                                </div>

                                <input v-else :type="sub.type === 'url' ? 'url' : sub.type === 'number' ? 'number' : 'text'" v-model="row[sub.key]"
                                    :placeholder="sub.placeholder || ''"
                                    class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scalar top-level fields -->
            <div v-else>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ field.label }}</label>

                <textarea v-if="field.type === 'textarea'" v-model="data[field.key]" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>

                <div v-else-if="field.type === 'image'" class="flex items-center gap-2">
                    <input v-model="data[field.key]" type="text" placeholder="URL или путь"
                        class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                    <label class="flex-shrink-0 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg text-sm cursor-pointer text-gray-700 dark:text-gray-200">
                        <i class="fas fa-upload text-xs mr-1"></i>Загрузить
                        <input type="file" accept="image/*" class="hidden" @change="onImagePick($event, url => data[field.key] = url)">
                    </label>
                </div>

                <div v-else-if="field.type === 'file'" class="flex items-center gap-2">
                    <input v-model="data[field.key]" type="text" placeholder="URL файла"
                        class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                    <label class="flex-shrink-0 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg text-sm cursor-pointer text-gray-700 dark:text-gray-200" title="PDF, DOC, XLS, ZIP…">
                        <i class="fas fa-file-arrow-up text-xs mr-1"></i>Файл
                        <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.rtf,.csv" class="hidden" @change="onFilePick($event, res => data[field.key] = res.url)">
                    </label>
                </div>

                <input v-else :type="field.type === 'url' ? 'url' : field.type === 'number' ? 'number' : 'text'" v-model="data[field.key]"
                    :placeholder="field.placeholder || ''"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
            </div>
        </template>
    </div>
</template>
