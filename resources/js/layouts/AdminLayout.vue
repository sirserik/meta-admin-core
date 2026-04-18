<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CommandPalette from '../components/CommandPalette.vue';
import FlashToasts from '../components/FlashToasts.vue';

const page = usePage();
const sidebarOpen = ref(false);        // mobile drawer
const sidebarCollapsed = ref(false);   // desktop narrow mode
const paletteRef = ref(null);

const nav = computed(() => page.props.navigation ?? []);
const brand = computed(() => page.props.brand ?? { name: 'Admin', color: '#C41E3A', logo_char: 'A' });
const user = computed(() => page.props.auth?.user);
const currentUrl = computed(() => page.url);

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPod|iPad/i.test(navigator.platform);

// Persist collapsed state across page navigations in this session.
onMounted(() => {
    try {
        const saved = localStorage.getItem('admin-core:sidebar-collapsed');
        if (saved === '1') sidebarCollapsed.value = true;
    } catch {}
});

function toggleCollapsed() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    try { localStorage.setItem('admin-core:sidebar-collapsed', sidebarCollapsed.value ? '1' : '0'); } catch {}
}

function isActive(href) {
    if (href === '/admin') return currentUrl.value === '/admin' || currentUrl.value === '/admin/';
    return currentUrl.value.startsWith(href);
}

function logout() {
    router.post('/admin/logout');
}

function openPalette() {
    paletteRef.value?.open();
}
</script>

<template>
    <div class="min-h-screen flex bg-gray-50 dark:bg-gray-900">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transform transition-[width,transform] duration-200 md:translate-x-0"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarCollapsed ? 'md:w-16' : 'md:w-64',
                'w-64',
            ]"
        >
            <div class="flex items-center gap-3 h-16 px-3 md:px-4 border-b border-gray-200 dark:border-gray-700">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-white font-bold flex-shrink-0"
                     :style="{ background: `linear-gradient(135deg, ${brand.color} 0%, ${brand.color}dd 100%)` }">
                    {{ brand.logo_char || 'A' }}
                </div>
                <div v-if="!sidebarCollapsed" class="flex-1 min-w-0">
                    <div class="font-bold text-gray-900 dark:text-white text-sm truncate">{{ brand.name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ brand.subtitle || 'Admin' }}</div>
                </div>
                <button v-if="!sidebarCollapsed" @click="toggleCollapsed" title="Свернуть меню"
                    class="hidden md:block p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded">
                    <i class="fas fa-angles-left text-xs"></i>
                </button>
            </div>

            <nav class="p-2 md:p-3 overflow-y-auto" style="height: calc(100vh - 4rem)">
                <button v-if="sidebarCollapsed" @click="toggleCollapsed" title="Развернуть меню"
                    class="hidden md:flex w-full items-center justify-center p-2 mb-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded">
                    <i class="fas fa-angles-right"></i>
                </button>
                <div v-for="group in nav" :key="group.section" class="mb-5">
                    <div v-if="!sidebarCollapsed" class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider px-3 mb-1.5">
                        {{ group.section }}
                    </div>
                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.href">
                            <Link
                                :href="item.href"
                                :title="sidebarCollapsed ? item.label : ''"
                                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors"
                                :class="[
                                    isActive(item.href)
                                        ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 font-medium'
                                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50',
                                    sidebarCollapsed ? 'justify-center' : '',
                                ]"
                            >
                                <i :class="item.icon" class="w-4 text-center flex-shrink-0"></i>
                                <span v-if="!sidebarCollapsed" class="truncate">{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0 transition-[margin] duration-200"
             :class="sidebarCollapsed ? 'md:ml-16' : 'md:ml-64'">
            <!-- Header -->
            <header class="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center px-3 md:px-6 gap-3 sticky top-0 z-30">
                <button class="md:hidden p-2 -ml-2 text-gray-600 dark:text-gray-300" @click="sidebarOpen = !sidebarOpen">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="text-sm md:text-base font-semibold text-gray-900 dark:text-white flex-1 truncate">
                    <slot name="title">{{ page.props.title ?? '' }}</slot>
                </h1>
                <!-- Global quick-nav search -->
                <button @click="openPalette" title="Быстрый поиск (Cmd+K)"
                    class="hidden sm:flex items-center gap-2 px-3 py-1.5 text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg border border-gray-200 dark:border-gray-600">
                    <i class="fas fa-search"></i>
                    <span>Поиск…</span>
                    <kbd class="text-[10px] font-mono bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded px-1.5 py-0.5 ml-1">
                        {{ isMac ? '⌘K' : 'Ctrl K' }}
                    </kbd>
                </button>
                <button @click="openPalette" class="sm:hidden p-2 text-gray-600 dark:text-gray-300" title="Быстрый поиск">
                    <i class="fas fa-search"></i>
                </button>

                <div v-if="user" class="flex items-center gap-3">
                    <div class="hidden md:block text-right">
                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[180px]">{{ user.name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[180px]">{{ user.email }}</div>
                    </div>
                    <button @click="logout" title="Выйти"
                        class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-right-from-bracket"></i>
                    </button>
                </div>
            </header>

            <!-- Flash messages are handled globally by FlashToasts. Inline banners
                 kept for legacy screens that call $this->with('success', ...).
                 The toast component shows them transiently; page-level banners
                 below are kept as fallback for accessibility/no-JS. -->

            <!-- Page content -->
            <main class="flex-1 p-4 md:p-6">
                <slot />
            </main>
        </div>

        <!-- Sidebar overlay (mobile) -->
        <div v-if="sidebarOpen"
            class="fixed inset-0 bg-black/40 z-30 md:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Global quick-navigation palette (Cmd+K) -->
        <CommandPalette ref="paletteRef" />

        <!-- Flash-message toast notifications -->
        <FlashToasts />
    </div>
</template>
