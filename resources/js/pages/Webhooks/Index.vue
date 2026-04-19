<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const props = defineProps({
    title:       String,
    webhooks:    Array,
    knownEvents: Array,
});

defineOptions({ layout: AdminLayout });

const editing = ref(null);
const form = useForm({
    label:     '',
    url:       '',
    events:    [],
    secret:    '',
    is_active: true,
});

function newHook() {
    editing.value = 'new';
    form.reset();
    form.events = [];
    form.is_active = true;
}
function edit(hook) {
    editing.value = hook.id;
    form.label     = hook.label;
    form.url       = hook.url;
    form.events    = [...hook.events];
    form.secret    = '';
    form.is_active = hook.is_active;
}
function cancel() {
    editing.value = null;
    form.reset();
}
function save() {
    if (editing.value === 'new') {
        form.post('/admin/webhooks', {
            preserveScroll: true,
            onSuccess: () => cancel(),
        });
    } else {
        form.put(`/admin/webhooks/${editing.value}`, {
            preserveScroll: true,
            onSuccess: () => cancel(),
        });
    }
}
function destroy(hook) {
    if (!confirm(`Удалить webhook «${hook.label}»?`)) return;
    router.delete(`/admin/webhooks/${hook.id}`, { preserveScroll: true });
}
function test(hook) {
    router.post(`/admin/webhooks/${hook.id}/test`, {}, { preserveScroll: true });
}
function toggleEvent(name) {
    const i = form.events.indexOf(name);
    if (i >= 0) form.events.splice(i, 1);
    else form.events.push(name);
}

const eventsByTable = computed(() => {
    const map = {};
    for (const ev of props.knownEvents) {
        const table = ev.name.split('.')[0];
        if (!map[table]) map[table] = [];
        map[table].push(ev);
    }
    return Object.entries(map).map(([table, events]) => ({ table, events }));
});
</script>

<template>
    <div class="max-w-5xl mx-auto p-6 space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Webhooks</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    HTTP-коллбэки на события CRUD. Каждый запрос подписан HMAC-SHA256 по секрету.
                </p>
            </div>
            <button v-if="!editing" @click="newHook"
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + Новый webhook
            </button>
        </div>

        <div v-if="editing"
             class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ editing === 'new' ? 'Новый webhook' : 'Редактирование webhook' }}
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Название</label>
                    <input v-model="form.label" type="text"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                    <p v-if="form.errors.label" class="mt-1 text-xs text-red-500">{{ form.errors.label }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL</label>
                    <input v-model="form.url" type="url" placeholder="https://example.com/hook"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                    <p v-if="form.errors.url" class="mt-1 text-xs text-red-500">{{ form.errors.url }}</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">События</label>
                <div class="space-y-3 max-h-72 overflow-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                    <div v-for="g in eventsByTable" :key="g.table">
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider mb-1">{{ g.table }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <label v-for="ev in g.events" :key="ev.name" class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" :checked="form.events.includes(ev.name)"
                                       @change="toggleEvent(ev.name)"
                                       class="w-4 h-4 rounded text-red-600">
                                <span class="font-mono text-xs">{{ ev.name }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <p v-if="form.errors.events" class="mt-1 text-xs text-red-500">{{ form.errors.events }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        HMAC-секрет
                        <span class="text-xs font-normal text-gray-500">(оставь пустым, чтобы не менять)</span>
                    </label>
                    <input v-model="form.secret" type="text"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg font-mono text-xs">
                </div>
                <label class="flex items-center gap-2 cursor-pointer pb-2">
                    <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded text-red-600">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Активен</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3">
                <button @click="cancel" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-sm">
                    Отмена
                </button>
                <button @click="save" :disabled="form.processing"
                        class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                    Сохранить
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div v-if="webhooks.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                Ни одного webhook'а ещё не добавлено.
            </div>
            <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                <li v-for="h in webhooks" :key="h.id" class="p-4 flex items-center gap-4">
                    <span class="w-2 h-2 rounded-full" :class="h.is_active ? 'bg-green-500' : 'bg-gray-400'"></span>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 dark:text-white truncate">{{ h.label }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate font-mono">{{ h.url }}</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <span v-for="ev in h.events" :key="ev"
                                  class="inline-block text-[10px] px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-mono">
                                {{ ev }}
                            </span>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        <template v-if="h.last_fired_at">
                            <i class="fas fa-circle-check text-green-500"></i>
                            {{ h.last_fired_at }}
                        </template>
                        <template v-else>ни разу</template>
                    </div>
                    <button @click="test(h)" title="Отправить тестовое событие"
                            class="px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                        Тест
                    </button>
                    <button @click="edit(h)"
                            class="px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                        Ред.
                    </button>
                    <button @click="destroy(h)" title="Удалить"
                            class="px-2 py-1.5 text-xs rounded-md text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">
                        <i class="fas fa-trash"></i>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
