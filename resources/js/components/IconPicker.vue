<script setup>
import { ref, computed } from 'vue';

/**
 * Free-text FontAwesome icon input with a searchable grid picker.
 * v-model is the icon name *without* the fas/far/fab prefix (e.g. "fa-home").
 * The preview strips/adds the fa- prefix as needed.
 *
 * Icons are curated to the common 200 — users can still type any FA name
 * manually for cases not in the grid.
 */
const props = defineProps({
    modelValue: { default: '' },
    placeholder: { type: String, default: 'fa-home' },
});
const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');

// Normalised icon class for preview: accepts 'fa-home', 'home', 'fas fa-home'.
const previewClass = computed(() => {
    const v = (props.modelValue || '').trim();
    if (!v) return '';
    if (v.includes(' ')) return v;            // full class supplied
    return 'fas ' + (v.startsWith('fa-') ? v : 'fa-' + v);
});

// Curated FA 6 free icons, grouped. Users see these in the grid; typing
// still accepts anything.
const iconGroups = {
    'Общее': [
        'home', 'house', 'user', 'users', 'cog', 'gear', 'gauge-high', 'bell',
        'envelope', 'phone', 'calendar', 'clock', 'star', 'heart', 'flag',
        'bookmark', 'tag', 'tags', 'link', 'lock', 'unlock', 'key', 'eye',
        'eye-slash', 'magnifying-glass', 'search', 'bars',
    ],
    'Навигация': [
        'arrow-left', 'arrow-right', 'arrow-up', 'arrow-down',
        'chevron-left', 'chevron-right', 'chevron-up', 'chevron-down',
        'angle-left', 'angle-right', 'xmark', 'plus', 'minus', 'check',
    ],
    'Контент': [
        'newspaper', 'file', 'file-lines', 'file-pdf', 'file-word',
        'file-excel', 'folder', 'folder-open', 'image', 'images', 'video',
        'music', 'book', 'book-open', 'graduation-cap', 'chalkboard',
        'chalkboard-user', 'comment', 'comments', 'bullhorn', 'flask',
        'microscope', 'palette', 'brush', 'pen', 'pen-to-square',
    ],
    'Образование': [
        'school', 'user-tie', 'user-graduate', 'user-group',
        'book-open-reader', 'book-bookmark', 'calculator', 'ruler',
        'compass', 'globe', 'earth-americas', 'briefcase', 'diagram-project',
    ],
    'Социальное': [
        'address-book', 'address-card', 'at', 'handshake', 'gift',
        'thumbs-up', 'thumbs-down', 'trophy', 'medal', 'award',
        'hands-helping', 'people-group', 'user-plus', 'share',
    ],
    'Действия': [
        'save', 'download', 'upload', 'print', 'copy', 'cut', 'paste',
        'undo', 'redo', 'trash', 'trash-can', 'recycle', 'power-off',
        'sign-in-alt', 'sign-out-alt', 'filter', 'sort', 'sync',
        'refresh', 'broom', 'database', 'floppy-disk', 'edit',
    ],
    'Бизнес': [
        'chart-line', 'chart-bar', 'chart-pie', 'chart-simple', 'dollar-sign',
        'coins', 'money-bill', 'credit-card', 'cart-shopping', 'shopping-cart',
        'truck', 'box', 'boxes-stacked', 'warehouse', 'building',
        'building-columns', 'landmark',
    ],
    'Медиа / UI': [
        'cubes', 'cube', 'grid', 'th', 'th-list', 'list', 'list-ul',
        'list-ol', 'table', 'columns', 'photo-film', 'camera',
        'sliders', 'toggle-on', 'toggle-off', 'folder-tree', 'sitemap',
    ],
    'Статус / Инфо': [
        'circle-info', 'circle-check', 'circle-exclamation', 'circle-xmark',
        'circle-question', 'triangle-exclamation', 'shield', 'shield-halved',
        'bullseye', 'bolt', 'fire', 'lightbulb', 'clipboard', 'inbox',
        'clock-rotate-left',
    ],
};

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return iconGroups;
    const out = {};
    for (const [g, arr] of Object.entries(iconGroups)) {
        const match = arr.filter((n) => n.toLowerCase().includes(q));
        if (match.length) out[g] = match;
    }
    return out;
});

function pick(name) {
    // Save with fa- prefix (matches historical storage convention).
    const v = name.startsWith('fa-') ? name : 'fa-' + name;
    emit('update:modelValue', v);
    open.value = false;
    search.value = '';
}
</script>

<template>
    <div class="relative">
        <!-- Text input + preview + picker toggle -->
        <div class="flex gap-2">
            <div class="relative flex-1">
                <i v-if="previewClass"
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"
                    :class="previewClass"></i>
                <input type="text"
                    :value="modelValue"
                    @input="$emit('update:modelValue', $event.target.value)"
                    :placeholder="placeholder"
                    class="w-full py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm font-mono"
                    :class="previewClass ? 'pl-9 pr-3' : 'px-3'">
            </div>
            <button type="button" @click="open = !open"
                class="px-3 py-2 border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm flex items-center gap-2">
                <i class="fas fa-icons"></i>
                <span class="hidden sm:inline">Выбрать</span>
            </button>
        </div>

        <!-- Dropdown picker -->
        <Teleport to="body">
            <div v-if="open"
                @click.self="open = false"
                class="fixed inset-0 z-50 bg-black/30 flex items-start justify-center pt-16 px-4">
                <div class="w-full max-w-2xl bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 max-h-[80vh] flex flex-col">
                    <!-- Header -->
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                        <div class="relative flex-1">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input v-model="search" type="text"
                                placeholder="Поиск иконки (например, home, user, calendar)"
                                class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white rounded-lg text-sm"
                                autofocus>
                        </div>
                        <button type="button" @click="open = false"
                            class="w-9 h-9 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>

                    <!-- Grid -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-5">
                        <div v-for="(icons, group) in filtered" :key="group">
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                {{ group }}
                            </h4>
                            <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-10 gap-2">
                                <button v-for="name in icons" :key="name"
                                    type="button" @click="pick(name)"
                                    :title="'fa-' + name"
                                    class="aspect-square border border-transparent hover:border-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg flex flex-col items-center justify-center gap-1 text-gray-600 dark:text-gray-300 hover:text-red-600 transition-colors group">
                                    <i class="fas text-lg" :class="'fa-' + name"></i>
                                    <span class="text-[9px] font-mono truncate max-w-full px-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        {{ name }}
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div v-if="!Object.keys(filtered).length" class="text-center py-10 text-gray-400">
                            <i class="fas fa-magnifying-glass text-2xl mb-2 opacity-40"></i>
                            <p class="text-sm">Ничего не нашлось. Можно ввести название вручную — принимается любой FA-класс.</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 flex items-center justify-between">
                        <span>
                            {{ Object.values(filtered).reduce((n, a) => n + a.length, 0) }} иконок
                        </span>
                        <a href="https://fontawesome.com/search?o=r&m=free" target="_blank"
                            class="text-red-600 hover:underline inline-flex items-center gap-1">
                            Полный каталог FA
                            <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
