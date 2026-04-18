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
import { computed } from 'vue';

const props = defineProps({
    modelValue:    { type: String, default: '{}' },
    schema:        { type: Object, default: null },
    uploadUrl:     { type: String, default: '/admin/upload/image' },
    fileUploadUrl: { type: String, default: '/admin/upload/file' },
});
const emit = defineEmits(['update:modelValue']);

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

                <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                    <div v-for="(row, i) in getArray(field.key)" :key="i" class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-mono text-gray-400">#{{ i + 1 }}</span>
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

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div v-for="sub in field.item_fields" :key="sub.key" :class="sub.type === 'textarea' ? 'sm:col-span-2' : ''">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ sub.label }}</label>

                                <textarea v-if="sub.type === 'textarea'" rows="2"
                                    :value="row[sub.key] ?? ''"
                                    @input="e => updateRow(field, i, sub.key, e.target.value)"
                                    class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>

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

                                <div v-else-if="sub.type === 'file'" class="flex items-center gap-2">
                                    <input type="text" placeholder="URL файла"
                                        :value="row[sub.key] ?? ''"
                                        @input="e => updateRow(field, i, sub.key, e.target.value)"
                                        class="flex-1 px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                    <label class="flex-shrink-0 px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-md text-sm cursor-pointer text-gray-700 dark:text-gray-200" title="PDF, DOC, XLS, ZIP…">
                                        <i class="fas fa-file-arrow-up text-xs"></i>
                                        <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.rtf,.csv" class="hidden"
                                            @change="pickFile($event, res => updateRow(field, i, sub.key, res.url, {
                                                filename: res.filename,
                                                size: res.size,
                                                ext: res.ext,
                                            }))">
                                    </label>
                                </div>

                                <input v-else :type="sub.type === 'url' ? 'url' : sub.type === 'number' ? 'number' : 'text'"
                                    :value="row[sub.key] ?? ''"
                                    @input="e => updateRow(field, i, sub.key, e.target.value)"
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
    </div>
</template>
