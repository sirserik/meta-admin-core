<script setup>
import { computed, ref } from 'vue';
import IconPicker from './IconPicker.vue';

const props = defineProps({
    modelValue: { default: null },
    name:       { type: String, required: true },
    // text | url | email | number | date | datetime-local | select | textarea
    // | boolean | color | icon | image | file
    type:       { type: String, default: 'text' },
    // Куда грузить файл для типов image/file. Совпадает с BlockDataEditor,
    // чтобы у ресурсов и блоков был один и тот же способ загрузки.
    uploadUrl:     { type: String, default: '/admin/upload/image' },
    fileUploadUrl: { type: String, default: '/admin/upload/file' },
    label:      String,
    required:   Boolean,
    placeholder: String,
    options:    { type: Array, default: () => [] }, // for select: [{value, label}]
    help:       String,
    errors:     { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);

const err = computed(() => props.errors?.[props.name]);
const isCheckbox = computed(() => props.type === 'boolean');

const uploading = ref(false);
const uploadError = ref('');

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

/**
 * Загрузка файла для полей image/file.
 *
 * Раньше `type: 'image'` в ресурсе молча падал в ветку «обычный input», и
 * браузер рендерил `<input type="image">` — кнопку-картинку вместо поля.
 * Редактор блоков умел загрузку давно; здесь ровно тот же эндпоинт.
 */
async function upload(event, url) {
    const file = event.target.files?.[0];
    if (!file) return;

    uploading.value = true;
    uploadError.value = '';
    try {
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: fd,
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (!data?.url) throw new Error('сервер не вернул ссылку на файл');
        emit('update:modelValue', data.url);
    } catch (e) {
        uploadError.value = 'Не удалось загрузить: ' + e.message;
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" v-if="!isCheckbox && label">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <!-- Select -->
        <select v-if="type === 'select'"
            :value="modelValue"
            @change="$emit('update:modelValue', $event.target.value)"
            :required="required"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-red-500"
            :class="err ? 'border-red-500' : ''">
            <option v-if="!required" value="">—</option>
            <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>

        <!-- Textarea -->
        <textarea v-else-if="type === 'textarea'"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :placeholder="placeholder"
            :required="required"
            rows="4"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-red-500"
            :class="err ? 'border-red-500' : ''"></textarea>

        <!-- Boolean (checkbox) -->
        <label v-else-if="isCheckbox" class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox"
                :checked="!!modelValue"
                @change="$emit('update:modelValue', $event.target.checked)"
                class="w-5 h-5 rounded text-red-600">
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ label }}</span>
        </label>

        <!-- Color -->
        <div v-else-if="type === 'color'" class="flex gap-2">
            <input type="color"
                :value="modelValue || '#000000'"
                @input="$emit('update:modelValue', $event.target.value)"
                class="w-12 h-10 rounded cursor-pointer border border-gray-300 dark:border-gray-600">
            <input type="text"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm font-mono">
        </div>

        <!-- Картинка: путь + загрузка + превью -->
        <div v-else-if="type === 'image' || type === 'file'" class="space-y-1.5">
            <div class="flex items-center gap-2">
                <input type="text"
                    :value="modelValue"
                    @input="$emit('update:modelValue', $event.target.value)"
                    :placeholder="placeholder || 'URL или путь'"
                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                    :class="err ? 'border-red-500' : ''">
                <label class="flex-shrink-0 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg text-sm cursor-pointer text-gray-700 dark:text-gray-200">
                    <i class="fas fa-upload text-xs mr-1"></i>{{ uploading ? 'Загрузка…' : 'Загрузить' }}
                    <input type="file"
                        :accept="type === 'image' ? 'image/*' : undefined"
                        class="hidden"
                        :disabled="uploading"
                        @change="upload($event, type === 'image' ? uploadUrl : fileUploadUrl)">
                </label>
            </div>
            <img v-if="type === 'image' && modelValue" :src="modelValue"
                class="h-16 w-28 object-contain rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
            <p v-if="uploadError" class="text-xs text-red-500">{{ uploadError }}</p>
        </div>

        <!-- Icon picker (FontAwesome) -->
        <IconPicker v-else-if="type === 'icon'"
            :model-value="modelValue"
            :placeholder="placeholder || 'fa-home'"
            @update:model-value="$emit('update:modelValue', $event)" />

        <!-- Default: text-like input -->
        <input v-else
            :type="type"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :placeholder="placeholder"
            :required="required"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-red-500"
            :class="err ? 'border-red-500' : ''"
            :inputmode="type === 'number' ? 'numeric' : undefined">

        <p v-if="help" class="mt-1 text-xs text-gray-400">{{ help }}</p>
        <p v-if="err" class="mt-1 text-xs text-red-500">{{ err }}</p>
    </div>
</template>
