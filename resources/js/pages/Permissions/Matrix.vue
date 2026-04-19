<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    resources: Array,   // [{name, label, menu}]
    actions:   Array,   // ['view', 'create', …]
    roles:     Array,   // [{id, name, permissions: ['articles.view', …]}]
});

defineOptions({ layout: AdminLayout });

// Local mutable state keyed by role name → Set of permission strings.
// Start from server data; we push the whole set on save per role.
const state = ref(
    Object.fromEntries(
        props.roles.map(r => [r.name, new Set(r.permissions)]),
    ),
);

const dirtyRoles = ref(new Set());
function markDirty(roleName) { dirtyRoles.value.add(roleName); dirtyRoles.value = new Set(dirtyRoles.value); }

function has(roleName, perm) { return state.value[roleName]?.has(perm); }
function toggle(roleName, perm) {
    const set = state.value[roleName];
    if (!set) return;
    if (set.has(perm)) set.delete(perm); else set.add(perm);
    state.value[roleName] = new Set(set); // reactivity
    markDirty(roleName);
}
function toggleRow(roleName, resource) {
    const perms = props.actions.map(a => `${resource}.${a}`);
    const all = perms.every(p => has(roleName, p));
    perms.forEach(p => (all ? state.value[roleName].delete(p) : state.value[roleName].add(p)));
    state.value[roleName] = new Set(state.value[roleName]);
    markDirty(roleName);
}
function toggleColumn(roleName, action) {
    const perms = props.resources.map(r => `${r.name}.${action}`);
    const all = perms.every(p => has(roleName, p));
    perms.forEach(p => (all ? state.value[roleName].delete(p) : state.value[roleName].add(p)));
    state.value[roleName] = new Set(state.value[roleName]);
    markDirty(roleName);
}

function save(roleName) {
    router.put('/admin/permissions', {
        role: roleName,
        permissions: Array.from(state.value[roleName] ?? []),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            dirtyRoles.value.delete(roleName);
            dirtyRoles.value = new Set(dirtyRoles.value);
        },
    });
}

const groupedResources = computed(() => {
    const map = new Map();
    for (const r of props.resources) {
        if (!map.has(r.menu)) map.set(r.menu, []);
        map.get(r.menu).push(r);
    }
    return Array.from(map.entries()).map(([menu, list]) => ({ menu, list }));
});

const actionLabels = { view: 'Просмотр', create: 'Создание', update: 'Изменение', delete: 'Удаление', publish: 'Публикация' };
</script>

<template>
    <div class="max-w-7xl mx-auto p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Права доступа</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Матрица «роль × ресурс × действие». Отметь ячейку, чтобы дать право, сними — чтобы забрать.
            </p>
        </div>

        <div v-for="role in roles" :key="role.id"
             class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                <div class="flex items-center gap-3">
                    <i class="fas fa-user-shield text-gray-400"></i>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ role.name }}</h2>
                    <span v-if="dirtyRoles.has(role.name)"
                          class="text-xs px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300">
                        несохранённые изменения
                    </span>
                </div>
                <button type="button" @click="save(role.name)"
                        :disabled="!dirtyRoles.has(role.name)"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium">
                    Сохранить
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/30">
                            <th class="py-2 px-4 sticky left-0 bg-gray-50 dark:bg-gray-900/30">Ресурс</th>
                            <th v-for="a in actions" :key="a" class="py-2 px-3 text-center">
                                <button type="button" @click="toggleColumn(role.name, a)" class="hover:text-red-600">
                                    {{ actionLabels[a] || a }}
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="g in groupedResources" :key="g.menu">
                            <tr>
                                <td :colspan="actions.length + 1"
                                    class="py-1.5 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/30">
                                    {{ g.menu }}
                                </td>
                            </tr>
                            <tr v-for="r in g.list" :key="r.name"
                                class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="py-2 px-4 sticky left-0 bg-white dark:bg-gray-800">
                                    <button type="button" @click="toggleRow(role.name, r.name)"
                                            class="font-medium text-gray-900 dark:text-white hover:text-red-600 text-left">
                                        {{ r.label }}
                                    </button>
                                </td>
                                <td v-for="a in actions" :key="a" class="py-2 px-3 text-center">
                                    <input type="checkbox" :checked="has(role.name, `${r.name}.${a}`)"
                                           @change="toggle(role.name, `${r.name}.${a}`)"
                                           class="w-4 h-4 rounded text-red-600 focus:ring-red-500 cursor-pointer">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="roles.length === 0"
             class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
            Ролей пока нет. Создай их через seed или консоль:
            <code class="text-xs">Role::create(['name' => 'editor'])</code>
        </div>
    </div>
</template>
