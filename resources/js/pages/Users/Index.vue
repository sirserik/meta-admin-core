<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    users: Array,
    roles: Array,
    filters: Object,
    currentUserId: Number,
});

const search = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? '');
let tm = null;
watch(search, (v) => { clearTimeout(tm); tm = setTimeout(() => apply({ search: v, role: roleFilter.value }), 300); });
watch(roleFilter, (v) => apply({ search: search.value, role: v }));

function apply(params) {
    router.get('/admin/users',
        Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null)),
        { preserveState: true, preserveScroll: true, replace: true });
}

const showCreate = ref(false);
const createForm = useForm({ name: '', email: '', password: '', role: 'editor' });
function submitCreate() {
    createForm.post('/admin/users', {
        preserveScroll: true,
        onSuccess: () => { createForm.reset(); showCreate.value = false; },
    });
}

function changeRole(user, role) {
    router.patch(`/admin/users/${user.id}/role`, { role }, { preserveScroll: true });
}
function destroy(user) {
    if (user.id === props.currentUserId) { alert('Нельзя удалить самого себя'); return; }
    if (!confirm(`Удалить пользователя «${user.name}»?`)) return;
    router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
}

function roleBadge(role) {
    const map = {
        admin:  'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300',
        editor: 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
        author: 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    };
    return map[role] || 'bg-gray-100 dark:bg-gray-700 text-gray-600';
}
function avatar(user) {
    const [a = '', b = ''] = (user.name || '?').trim().split(/\s+/);
    return ((a[0] || '') + (b[0] || '')).toUpperCase() || '?';
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" :subtitle="`Всего: ${users.length}`">
        <template #actions>
            <button @click="showCreate = !showCreate" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas" :class="showCreate ? 'fa-xmark' : 'fa-plus'"></i>
                <span>{{ showCreate ? 'Отмена' : 'Добавить пользователя' }}</span>
            </button>
        </template>
    </PageHeader>

    <!-- Create form -->
    <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
        <div v-if="showCreate" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-4">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Новый пользователь</h3>
            <form @submit.prevent="submitCreate" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Имя</label>
                    <input v-model="createForm.name" type="text" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm"
                        :class="createForm.errors.name ? 'border-red-500' : ''">
                    <p v-if="createForm.errors.name" class="text-xs text-red-500 mt-1">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Email</label>
                    <input v-model="createForm.email" type="email" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm"
                        :class="createForm.errors.email ? 'border-red-500' : ''">
                    <p v-if="createForm.errors.email" class="text-xs text-red-500 mt-1">{{ createForm.errors.email }}</p>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Пароль</label>
                    <input v-model="createForm.password" type="password" required minlength="8" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm"
                        :class="createForm.errors.password ? 'border-red-500' : ''">
                    <p v-if="createForm.errors.password" class="text-xs text-red-500 mt-1">{{ createForm.errors.password }}</p>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Роль</label>
                    <div class="flex gap-2">
                        <select v-model="createForm.role" class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                        <button type="submit" :disabled="createForm.processing" class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-4 rounded-lg text-sm">
                            <i class="fas" :class="createForm.processing ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </transition>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 flex flex-col md:flex-row gap-3">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input v-model="search" type="search" placeholder="Поиск по имени или email…"
                class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500">
        </div>
        <select v-model="roleFilter" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все роли</option>
            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
        </select>
    </div>

    <!-- Users list -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            <li v-for="u in users" :key="u.id" class="px-4 py-3 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-red-500 to-red-700 text-white flex items-center justify-center font-semibold flex-shrink-0">
                    {{ avatar(u) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-medium text-gray-900 dark:text-white truncate">{{ u.name }}</span>
                        <span v-if="u.id === currentUserId" class="text-xs px-1.5 py-0.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded">вы</span>
                        <i v-if="u.email_verified" class="fas fa-circle-check text-green-500 text-xs" title="Email подтверждён"></i>
                    </div>
                    <div class="text-sm text-gray-500 truncate">{{ u.email }}</div>
                </div>
                <div class="hidden sm:block text-xs text-gray-400 whitespace-nowrap">{{ u.created_at }}</div>
                <select :value="u.role" @change="changeRole(u, $event.target.value)" :disabled="u.id === currentUserId"
                    :class="['px-2 py-1 rounded text-xs font-medium border-0 focus:ring-2 focus:ring-red-500', roleBadge(u.role), u.id === currentUserId ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer']">
                    <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
                <button v-if="u.id !== currentUserId" @click="destroy(u)" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded">
                    <i class="fas fa-trash"></i>
                </button>
            </li>
            <li v-if="users.length === 0" class="px-4 py-12 text-center text-gray-500">
                <i class="fas fa-users text-4xl mb-2 opacity-30"></i>
                <p>Пользователей не найдено</p>
            </li>
        </ul>
    </div>
</template>
