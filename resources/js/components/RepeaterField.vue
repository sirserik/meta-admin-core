<script setup>
/**
 * Повторяющаяся группа полей в форме ресурса.
 *
 * Зачем: у ресурсов до сих пор были только одиночные поля, и любые списки
 * (команда, достижения, ссылки, материалы) приходилось складывать в textarea
 * построчно — «ФИО | роль | фото» — и разбирать на бэкенде. Редактор блоков
 * такой список умеет давно (`type: 'array'` + `item_fields`), а формы
 * ресурсов — нет. Компонент закрывает этот пробел тем же описанием схемы.
 *
 * Значение — массив объектов, поэтому колонка модели должна быть json
 * (или иметь `'поле' => 'array'` в $casts). Пустые строки не сохраняются.
 */
import { computed } from 'vue';
import SimpleField from './SimpleField.vue';

const props = defineProps({
    modelValue: { type: [Array, String, Object], default: () => [] },
    name:        { type: String, required: true },
    label:       String,
    help:        String,
    // [{ key, label, type, options, placeholder, help }] — как в каталоге блоков
    itemFields:  { type: Array, default: () => [] },
    addLabel:    { type: String, default: 'Добавить' },
    errors:      { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

/** Значение может прийти строкой JSON — форма Inertia отдаёт то, что в модели. */
const items = computed(() => {
    const v = props.modelValue;
    if (Array.isArray(v)) return v;
    if (typeof v === 'string' && v.trim().startsWith('[')) {
        try { return JSON.parse(v); } catch { return []; }
    }
    return [];
});

const commit = rows => emit('update:modelValue', rows);

function addItem() {
    const blank = {};
    for (const f of props.itemFields) blank[f.key] = '';
    commit([...items.value, blank]);
}

function removeItem(index) {
    commit(items.value.filter((_, i) => i !== index));
}

function move(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= items.value.length) return;
    const rows = [...items.value];
    [rows[index], rows[target]] = [rows[target], rows[index]];
    commit(rows);
}

function setField(index, key, value) {
    const rows = items.value.map((row, i) => (i === index ? { ...row, [key]: value } : row));
    commit(rows);
}

/** Подпись строки в свёрнутом виде: первое непустое текстовое поле. */
function rowTitle(row, index) {
    for (const f of props.itemFields) {
        const v = row?.[f.key];
        if (typeof v === 'string' && v.trim() !== '' && f.type !== 'image' && f.type !== 'file') {
            return v.length > 60 ? v.slice(0, 60) + '…' : v;
        }
    }
    return `Пункт ${index + 1}`;
}
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ label }}</label>
        <p v-if="help" class="mb-2 text-xs text-gray-400">{{ help }}</p>

        <div class="border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="(row, index) in items" :key="index" class="p-3 sm:p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">{{ rowTitle(row, index) }}</span>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button type="button" @click="move(index, -1)" :disabled="index === 0" title="Выше"
                            class="px-2 py-1 rounded text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30">
                            <i class="fas fa-arrow-up text-xs"></i>
                        </button>
                        <button type="button" @click="move(index, 1)" :disabled="index === items.length - 1" title="Ниже"
                            class="px-2 py-1 rounded text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30">
                            <i class="fas fa-arrow-down text-xs"></i>
                        </button>
                        <button type="button" @click="removeItem(index)" title="Удалить"
                            class="px-2 py-1 rounded text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <SimpleField v-for="f in itemFields" :key="f.key"
                        :name="`${name}.${index}.${f.key}`"
                        :type="f.type || 'text'"
                        :label="f.label"
                        :placeholder="f.placeholder"
                        :options="f.options || []"
                        :help="f.help"
                        :errors="errors"
                        :model-value="row?.[f.key] ?? ''"
                        @update:model-value="v => setField(index, f.key, v)" />
                </div>
            </div>

            <div v-if="!items.length" class="p-4 text-sm text-gray-400">Пока пусто</div>
        </div>

        <button type="button" @click="addItem"
            class="mt-2 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-200">
            <i class="fas fa-plus text-xs mr-1"></i>{{ addLabel }}
        </button>
    </div>
</template>
