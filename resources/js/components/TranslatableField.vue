<script setup>
import { computed } from 'vue';
import RichTextEditor from './RichTextEditor.vue';

const props = defineProps({
    modelValue: { type: Object, required: true }, // { ru: '', kk: '', en: '' }
    label: { type: String, required: true },
    type: { type: String, default: 'text' }, // text | textarea | editor
    activeLocale: { type: String, required: true },
    errors: { type: Object, default: () => ({}) }, // keyed by `${name}.${locale}`
    name: { type: String, required: true },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

function update(locale, value) {
    emit('update:modelValue', { ...props.modelValue, [locale]: value });
}

const errorKey = computed(() => `${props.name}.${props.activeLocale}`);
const errorText = computed(() => props.errors[errorKey.value] ?? props.errors[`${props.name}_${props.activeLocale}`]);
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
            {{ label }}
            <span v-if="required && activeLocale === 'ru'" class="text-red-500">*</span>
            <span class="ml-2 text-xs text-gray-400 uppercase">{{ activeLocale }}</span>
        </label>

        <!-- Rich text editor (Tiptap) -->
        <RichTextEditor
            v-if="type === 'editor'"
            :model-value="modelValue[activeLocale] ?? ''"
            @update:model-value="(v) => update(activeLocale, v)"
            :placeholder="placeholder"
        />

        <!-- Textarea -->
        <textarea
            v-else-if="type === 'textarea'"
            :value="modelValue[activeLocale] ?? ''"
            @input="update(activeLocale, $event.target.value)"
            :placeholder="placeholder"
            rows="4"
            class="w-full px-4 py-2 border rounded-lg text-gray-900 dark:text-white dark:bg-gray-700 focus:ring-2 focus:ring-red-500 focus:border-transparent"
            :class="errorText ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'"
        ></textarea>

        <!-- Plain text -->
        <input
            v-else
            type="text"
            :value="modelValue[activeLocale] ?? ''"
            @input="update(activeLocale, $event.target.value)"
            :placeholder="placeholder"
            :required="required && activeLocale === 'ru'"
            class="w-full px-4 py-2.5 border rounded-lg text-gray-900 dark:text-white dark:bg-gray-700 focus:ring-2 focus:ring-red-500 focus:border-transparent"
            :class="errorText ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'"
        >

        <p v-if="errorText" class="mt-1 text-sm text-red-500">{{ errorText }}</p>
    </div>
</template>
