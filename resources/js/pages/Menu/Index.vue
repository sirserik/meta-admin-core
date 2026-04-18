<script setup>
import { ref, reactive } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';
import MenuRow from './MenuRow.vue';
import IconPicker from '@admin-core/components/IconPicker.vue';

const props = defineProps({
    title: String,
    items: Array,     // tree
    flat: Array,      // for parent select
    locales: Array,
});

const activeLocale = ref('ru');
const showCreate = ref(false);

const blankForm = () => ({
    parent_id: '',
    slug: '',
    icon: '',
    is_published: true,
    title: { ru: '', kk: '', en: '' },
    url: { ru: '', kk: '', en: '' },
});

const createForm = useForm(blankForm());
function submitCreate() {
    createForm.post('/admin/menu', {
        preserveScroll: true,
        onSuccess: () => { Object.assign(createForm, blankForm()); createForm.reset(); showCreate.value = false; },
    });
}

const editing = reactive({});
function startEdit(item) {
    editing[item.id] = {
        parent_id: item.parent_id ?? '',
        slug: item.slug ?? '',
        icon: item.icon ?? '',
        is_published: !!item.is_published,
        title: { ...item.title },
        url: { ...item.url },
    };
}
function saveEdit(item) {
    router.put(`/admin/menu/${item.id}`, editing[item.id], {
        preserveScroll: true,
        onSuccess: () => { delete editing[item.id]; },
    });
}
function destroy(item) {
    if (!confirm(`Удалить пункт «${item.title.ru || item.slug}»? Подпункты станут корневыми.`)) return;
    router.delete(`/admin/menu/${item.id}`, { preserveScroll: true });
}
function togglePublish(item) {
    router.put(`/admin/menu/${item.id}`, {
        parent_id: item.parent_id,
        slug: item.slug,
        icon: item.icon,
        is_published: !item.is_published,
        title: item.title,
        url: item.url,
    }, { preserveScroll: true });
}

function parentOptions(currentId) {
    // Exclude self and descendants
    const excluded = new Set();
    function walk(id) {
        excluded.add(id);
        for (const f of props.flat) if (f.parent_id === id) walk(f.id);
    }
    if (currentId) walk(currentId);
    return props.flat.filter(f => !excluded.has(f.id));
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" subtitle="Иерархические пункты навигации с per-locale URL">
        <template #actions>
            <div class="flex items-center gap-1 border border-gray-300 dark:border-gray-600 rounded-lg p-1 bg-white dark:bg-gray-700">
                <button v-for="loc in locales" :key="loc" type="button" @click="activeLocale = loc"
                    class="px-3 py-1 rounded text-xs font-medium uppercase transition"
                    :class="activeLocale === loc ? 'bg-red-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600'">
                    {{ loc }}
                </button>
            </div>
            <button @click="showCreate = !showCreate" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas" :class="showCreate ? 'fa-xmark' : 'fa-plus'"></i>
                <span>{{ showCreate ? 'Отмена' : 'Новый пункт' }}</span>
            </button>
        </template>
    </PageHeader>

    <transition enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
        <div v-if="showCreate" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-4">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Новый пункт меню</h3>
            <form @submit.prevent="submitCreate" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Название ({{ activeLocale.toUpperCase() }}) <span v-if="activeLocale === 'ru'" class="text-red-500">*</span></label>
                    <input v-model="createForm.title[activeLocale]" type="text" :required="activeLocale === 'ru'" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">URL ({{ activeLocale.toUpperCase() }})</label>
                    <input v-model="createForm.url[activeLocale]" type="text" placeholder="/about" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Родитель</label>
                    <select v-model="createForm.parent_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                        <option value="">— корень —</option>
                        <option v-for="p in flat" :key="p.id" :value="p.id">{{ p.title }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Иконка</label>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <IconPicker v-model="createForm.icon" placeholder="fa-home" />
                        </div>
                        <button type="submit" :disabled="createForm.processing" class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-4 rounded-lg">
                            <i class="fas" :class="createForm.processing ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </transition>

    <!-- Tree -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <ul v-if="items.length" class="space-y-2">
            <template v-for="item in items" :key="item.id">
                <MenuRow :item="item" :level="0"
                    :locale="activeLocale" :locales="locales"
                    :editing="editing" :flat="flat" :parentOptions="parentOptions"
                    @start-edit="startEdit" @save-edit="saveEdit"
                    @cancel="(id) => delete editing[id]"
                    @destroy="destroy" @toggle="togglePublish" />
            </template>
        </ul>
        <div v-else class="py-10 text-center text-gray-500">
            <i class="fas fa-bars text-4xl mb-2 opacity-30"></i>
            <p>Пункты меню ещё не созданы</p>
        </div>
    </div>
</template>
