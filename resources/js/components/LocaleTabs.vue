<script setup>
const props = defineProps({
    modelValue: { type: String, required: true },
    locales: {
        type: Array,
        default: () => [
            { code: 'ru', label: 'Русский', flag: '🇷🇺' },
            { code: 'kk', label: 'Қазақша', flag: '🇰🇿' },
            { code: 'en', label: 'English', flag: '🇬🇧' },
        ],
    },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex items-center gap-1 border-b border-gray-200 dark:border-gray-700 mb-5">
        <button
            v-for="loc in locales"
            :key="loc.code"
            type="button"
            @click="$emit('update:modelValue', loc.code)"
            class="flex items-center gap-2 px-4 py-2.5 border-b-2 font-medium text-sm transition-colors -mb-px"
            :class="modelValue === loc.code
                ? 'border-red-600 text-red-700 dark:text-red-300'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300'"
        >
            <span class="text-base">{{ loc.flag }}</span>
            <span>{{ loc.label }}</span>
            <span v-if="loc.code === 'ru'" class="text-xs text-red-500 font-bold">*</span>
        </button>
    </div>
</template>
