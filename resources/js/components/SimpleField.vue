<script setup>
import { computed } from 'vue';
import IconPicker from './IconPicker.vue';

const props = defineProps({
    modelValue: { default: null },
    name:       { type: String, required: true },
    type:       { type: String, default: 'text' },  // text | url | email | number | date | datetime-local | select | textarea | boolean | color | icon
    label:      String,
    required:   Boolean,
    placeholder: String,
    options:    { type: Array, default: () => [] }, // for select: [{value, label}]
    help:       String,
    errors:     { type: Object, default: () => ({}) },
});
defineEmits(['update:modelValue']);

const err = computed(() => props.errors?.[props.name]);
const isCheckbox = computed(() => props.type === 'boolean');
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
