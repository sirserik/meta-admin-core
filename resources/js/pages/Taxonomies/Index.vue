<script setup>
import { ref, watch } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const props = defineProps({
    title:       String,
    types:       Array,
    activeType:  String,
    terms:       Array,
    locales:     Array,
});

defineOptions({ layout: AdminLayout });

const newTypeInput = ref('');
const editing = ref(null);

const form = useForm({
    type:               props.activeType,
    slug:               '',
    label:              '',
    sort_order:         0,
    label_translations: {},
});

watch(() => props.activeType, (t) => { form.type = t; });

function resetForm(extra = {}) {
    form.reset();
    form.type = props.activeType;
    form.sort_order = 0;
    form.label_translations = {};
    Object.assign(form, extra);
    editing.value = null;
}

function edit(term) {
    editing.value = term.id;
    form.type       = term.type;
    form.slug       = term.slug;
    form.label      = term.label;
    form.sort_order = term.sort_order ?? 0;
    form.label_translations = { ...(term.label_translations ?? {}) };
}

function save() {
    if (editing.value) {
        form.put(`/admin/taxonomies/${editing.value}`, { preserveScroll: true, onSuccess: () => resetForm() });
    } else {
        form.post('/admin/taxonomies', { preserveScroll: true, onSuccess: () => resetForm() });
    }
}

function destroy(term) {
    if (!confirm(`Удалить термин «${term.label}»?`)) return;
    router.delete(`/admin/taxonomies/${term.id}`, { preserveScroll: true });
}

function switchType(t) {
    router.visit(`/admin/taxonomies?type=${encodeURIComponent(t)}`);
}

function createType() {
    const t = newTypeInput.value.trim();
    if (!t) return;
    router.visit(`/admin/taxonomies?type=${encodeURIComponent(t)}`);
    newTypeInput.value = '';
}
</script>

<template>
    <div class="max-w-5xl mx-auto p-6 space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Словари</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Теги, категории, аудитории — любые классификаторы, подключаемые к контенту через trait Taxable.
                </p>
            </div>
        </div>

        <!-- Type switcher -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
            <div class="flex flex-wrap gap-2 items-center">
                <button v-for="t in types" :key="t" @click="switchType(t)"
                        class="px-3 py-1.5 text-sm rounded-md font-medium transition"
                        :class="t === activeType
                            ? 'bg-red-600 text-white'
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'">
                    {{ t }}
                </button>
                <div class="flex items-center gap-1 ml-auto">
                    <input v-model="newTypeInput" @keyup.enter="createType" placeholder="+ новый словарь"
                           class="px-2 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white w-44">
                    <button @click="createType"
                            class="px-3 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                        Добавить
                    </button>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ editing ? 'Редактирование термина' : `Новый термин в «${activeType}»` }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Название</label>
                    <input v-model="form.label" type="text"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                    <p v-if="form.errors.label" class="mt-1 text-xs text-red-500">{{ form.errors.label }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Slug <span class="text-xs text-gray-500 font-normal">(опционально)</span>
                    </label>
                    <input v-model="form.slug" type="text" placeholder="auto"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div v-for="loc in locales" :key="loc">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 uppercase tracking-wider">
                        Название ({{ loc }})
                    </label>
                    <input v-model="form.label_translations[loc]" type="text"
                           class="w-full px-3 py-1.5 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md text-sm">
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 uppercase tracking-wider">Сортировка</label>
                    <input v-model.number="form.sort_order" type="number"
                           class="w-24 px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md text-sm">
                </div>
                <div class="ml-auto flex gap-2">
                    <button v-if="editing" @click="resetForm()" class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        Отмена
                    </button>
                    <button @click="save" :disabled="form.processing"
                            class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        {{ editing ? 'Сохранить' : 'Добавить' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- List -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div v-if="terms.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                В словаре «{{ activeType }}» пока нет терминов.
            </div>
            <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                <li v-for="t in terms" :key="t.id"
                    class="px-5 py-3 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 dark:text-white">{{ t.label }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                            {{ t.slug }}
                            <span v-if="t.sort_order" class="ml-2">· порядок {{ t.sort_order }}</span>
                        </div>
                    </div>
                    <button @click="edit(t)"
                            class="px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                        Ред.
                    </button>
                    <button @click="destroy(t)"
                            class="px-2 py-1.5 text-xs rounded-md text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">
                        <i class="fas fa-trash"></i>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
