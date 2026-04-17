<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const sidebarOpen = ref(false);

// Navigation приходит из AdminCore::navigation() через shared props.
// Каждый сайт регистрирует свои ресурсы/пункты меню в AppServiceProvider:
//   AdminCore::resource('articles', [...]);
//   AdminCore::menuItem('Бэкапы', '/admin/backup', 'fa-database', 'Система');
const nav = computed(() => page.props.navigation ?? []);
const brand = computed(() => page.props.brand ?? { name: 'Admin', color: '#C41E3A', logo_char: 'A' });

const user = computed(() => page.props.auth?.user);
const currentUrl = computed(() => page.url);

function isActive(href) {
    if (href === '/admin') return currentUrl.value === '/admin' || currentUrl.value === '/admin/';
    return currentUrl.value.startsWith(href);
}

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <div class="min-h-screen flex bg-gray-50 dark:bg-gray-900">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transform transition-transform md:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center gap-3 h-16 px-5 border-b border-gray-200 dark:border-gray-700">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-white font-bold"
                     :style="{ background: `linear-gradient(135deg, ${brand.color} 0%, ${brand.color}dd 100%)` }">
                    {{ brand.logo_char || 'A' }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-gray-900 dark:text-white text-sm truncate">{{ brand.name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ brand.subtitle || 'Admin' }}</div>
                </div>
            </div>

            <nav class="p-4 overflow-y-auto" style="height: calc(100vh - 4rem)">
                <div v-for="group in nav" :key="group.section" class="mb-6">
                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-2">
                        {{ group.section }}
                    </div>
                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.href">
                            <Link
                                :href="item.href"
                                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors"
                                :class="isActive(item.href)
                                    ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 font-medium'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50'"
                            >
                                <i :class="item.icon" class="w-4 text-center"></i>
                                <span>{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col md:ml-64 min-w-0">
            <!-- Header -->
            <header class="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center px-4 md:px-6 gap-4 sticky top-0 z-30">
                <button
                    class="md:hidden p-2 -ml-2 text-gray-600 dark:text-gray-300"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="text-base font-semibold text-gray-900 dark:text-white flex-1 truncate">
                    <slot name="title">{{ page.props.title ?? '' }}</slot>
                </h1>
                <div v-if="user" class="flex items-center gap-3">
                    <div class="hidden sm:block text-right">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                    </div>
                    <button
                        @click="logout"
                        title="Выйти"
                        class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600"
                    >
                        <i class="fas fa-right-from-bracket"></i>
                    </button>
                </div>
            </header>

            <!-- Flash -->
            <div v-if="page.props.flash?.success" class="mx-4 md:mx-6 mt-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300 text-sm">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="mx-4 md:mx-6 mt-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 text-sm">
                {{ page.props.flash.error }}
            </div>

            <!-- Page content -->
            <main class="flex-1 p-4 md:p-6">
                <slot />
            </main>
        </div>

        <!-- Sidebar overlay (mobile) -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 bg-black/40 z-30 md:hidden"
            @click="sidebarOpen = false"
        ></div>
    </div>
</template>
