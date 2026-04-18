<script setup>
import IconPicker from '@admin-core/components/IconPicker.vue';

const props = defineProps({
    item: Object,
    level: Number,
    locale: String,
    locales: Array,
    editing: Object,
    flat: Array,
    parentOptions: Function,
});
const emit = defineEmits(['startEdit', 'saveEdit', 'cancel', 'destroy', 'toggle']);
</script>

<template>
    <li>
        <div :class="level > 0 ? 'ml-6 border-l-2 border-gray-200 dark:border-gray-700 pl-4' : ''">
            <template v-if="editing[item.id]">
                <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 space-y-2">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                        <input v-model="editing[item.id].title[locale]" :placeholder="`Название (${locale.toUpperCase()})`" class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded text-sm md:col-span-2">
                        <input v-model="editing[item.id].url[locale]" :placeholder="`URL (${locale.toUpperCase()})`" class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded text-sm font-mono md:col-span-2">
                        <select v-model="editing[item.id].parent_id" class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded text-sm">
                            <option value="">— корень —</option>
                            <option v-for="p in parentOptions(item.id)" :key="p.id" :value="p.id">{{ p.title }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-64">
                            <IconPicker v-model="editing[item.id].icon" placeholder="fa-home" />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="editing[item.id].is_published" type="checkbox" class="w-4 h-4 rounded">
                            <span class="text-gray-700 dark:text-gray-300">Опубликован</span>
                        </label>
                        <div class="flex-1"></div>
                        <button @click="emit('saveEdit', item)" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                            <i class="fas fa-check mr-1"></i>Сохранить
                        </button>
                        <button @click="emit('cancel', item.id)" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-sm">Отмена</button>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="flex items-center gap-3 py-2 px-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                    <i v-if="item.icon" :class="'fas ' + item.icon + ' text-gray-400 w-4 text-center'"></i>
                    <i v-else class="fas fa-circle-dot text-gray-300 w-4 text-center text-xs"></i>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-900 dark:text-white">{{ item.title[locale] || item.title.ru || '(без названия)' }}</span>
                            <span v-if="locale !== 'ru' && !item.title[locale]" class="text-xs text-amber-600">RU fallback</span>
                        </div>
                        <div class="text-xs text-gray-500 font-mono truncate">{{ item.url[locale] || item.url.ru || '—' }}</div>
                    </div>
                    <button @click="emit('toggle', item)" class="p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-600 rounded" :title="item.is_published ? 'Скрыть' : 'Показать'">
                        <i class="fas" :class="item.is_published ? 'fa-eye text-green-600' : 'fa-eye-slash text-gray-400'"></i>
                    </button>
                    <button @click="emit('startEdit', item)" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded"><i class="fas fa-pen"></i></button>
                    <button @click="emit('destroy', item)" class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"><i class="fas fa-trash"></i></button>
                </div>
            </template>

            <!-- Children (recursive) -->
            <ul v-if="item.children && item.children.length" class="mt-1 space-y-1">
                <MenuRow v-for="child in item.children" :key="child.id"
                    :item="child" :level="level + 1"
                    :locale="locale" :locales="locales"
                    :editing="editing" :flat="flat" :parentOptions="parentOptions"
                    @start-edit="(i) => emit('startEdit', i)"
                    @save-edit="(i) => emit('saveEdit', i)"
                    @cancel="(id) => emit('cancel', id)"
                    @destroy="(i) => emit('destroy', i)"
                    @toggle="(i) => emit('toggle', i)" />
            </ul>
        </div>
    </li>
</template>
