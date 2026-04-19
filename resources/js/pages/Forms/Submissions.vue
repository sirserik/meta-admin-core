<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const props = defineProps({
    title:       String,
    form:        Object,
    submissions: Array,
});
defineOptions({ layout: AdminLayout });

const expanded = ref(null);
function toggle(id) { expanded.value = expanded.value === id ? null : id; }
function setStatus(s, status) {
    router.patch(`/admin/forms/${props.form.id}/submissions/${s.id}`, { status }, { preserveScroll: true });
}
function destroy(s) {
    if (!confirm('Удалить заявку?')) return;
    router.delete(`/admin/forms/${props.form.id}/submissions/${s.id}`, { preserveScroll: true });
}

function labelFor(fieldName) {
    return (props.form.fields.find(f => f.name === fieldName) || {}).label || fieldName;
}

const STATUS_LABELS = { new: 'Новая', read: 'Просмотрена', replied: 'Отвечено', spam: 'Спам' };
const STATUS_COLORS = {
    new:     'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    read:    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    replied: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    spam:    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
};
</script>

<template>
    <div class="max-w-5xl mx-auto p-6 space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Заявки: {{ form.name }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ submissions.length }} шт. · {{ form.fields.length }} пол{{ form.fields.length === 1 ? 'е' : 'ей' }}
                </p>
            </div>
            <div class="flex gap-2">
                <a :href="`/admin/forms/${form.id}/submissions/export`"
                   class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm">
                    <i class="fas fa-download mr-1"></i>CSV
                </a>
                <Link :href="`/admin/forms/${form.id}/edit`"
                      class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm">
                    Редактировать форму
                </Link>
                <Link href="/admin/forms"
                      class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm">
                    ← К списку
                </Link>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div v-if="submissions.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                Ещё ни одной заявки.
            </div>
            <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                <li v-for="s in submissions" :key="s.id" class="px-5 py-4">
                    <div class="flex items-center gap-4">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="STATUS_COLORS[s.status] || STATUS_COLORS.new">
                            {{ STATUS_LABELS[s.status] || s.status }}
                        </span>
                        <div class="flex-1 min-w-0 text-sm">
                            <div class="text-gray-900 dark:text-white">
                                <span class="font-medium">#{{ s.id }}</span>
                                <span class="text-gray-500 dark:text-gray-400"> · {{ s.created_at }}</span>
                                <span v-if="s.ip_address" class="ml-2 text-xs text-gray-400 font-mono">{{ s.ip_address }}</span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                <template v-for="(v, k) in s.data" :key="k">
                                    <span class="mr-3"><span class="text-gray-400">{{ labelFor(k) }}:</span> {{ Array.isArray(v) ? v.join(', ') : v }}</span>
                                </template>
                            </div>
                        </div>
                        <select :value="s.status" @change="e => setStatus(s, e.target.value)"
                                class="text-xs px-2 py-1 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md text-gray-700 dark:text-gray-300">
                            <option v-for="(l, v) in STATUS_LABELS" :key="v" :value="v">{{ l }}</option>
                        </select>
                        <button @click="toggle(s.id)"
                                class="px-2 py-1 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            {{ expanded === s.id ? 'Скрыть' : 'Показать' }}
                        </button>
                        <button @click="destroy(s)"
                                class="px-2 py-1 text-xs rounded-md text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div v-if="expanded === s.id"
                         class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-sm space-y-2">
                        <div v-for="(v, k) in s.data" :key="k">
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ labelFor(k) }}</span>
                            <div class="text-gray-900 dark:text-white">{{ Array.isArray(v) ? v.join(', ') : (v || '—') }}</div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
