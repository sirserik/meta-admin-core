<script setup>
/**
 * Listens to Inertia flash props (shared via HandleInertiaRequests)
 * and pops a toast in the bottom-right corner for each new
 * success/error/info message. Auto-dismisses after 4s.
 *
 * Consumers can also fire an event manually:
 *   window.dispatchEvent(new CustomEvent('admin-toast', {
 *       detail: { type: 'success', message: 'Сохранено' }
 *   }));
 */
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const toasts = ref([]);
let idCounter = 0;

function push(type, message) {
    if (!message) return;
    const id = ++idCounter;
    toasts.value.push({ id, type, message });
    setTimeout(() => dismiss(id), 4000);
}

function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

// Inertia flash props — emitted on every navigation.
watch(() => page.props.flash, (flash) => {
    if (!flash) return;
    if (flash.success) push('success', flash.success);
    if (flash.error)   push('error',   flash.error);
    if (flash.info)    push('info',    flash.info);
}, { deep: true, immediate: true });

function onCustomToast(e) {
    const { type = 'info', message } = e.detail || {};
    push(type, message);
}

onMounted(() => window.addEventListener('admin-toast', onCustomToast));
onUnmounted(() => window.removeEventListener('admin-toast', onCustomToast));

function iconFor(type) {
    return {
        success: 'fa-circle-check',
        error:   'fa-circle-xmark',
        info:    'fa-circle-info',
    }[type] || 'fa-circle-info';
}

function classesFor(type) {
    return {
        success: 'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 border-green-200 dark:border-green-800',
        error:   'bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 border-red-200 dark:border-red-800',
        info:    'bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800',
    }[type] || 'bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-600';
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-4 right-4 z-[90] flex flex-col gap-2 max-w-xs sm:max-w-sm w-[calc(100vw-2rem)] sm:w-auto pointer-events-none">
            <Transition
                v-for="t in toasts" :key="t.id"
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 translate-x-4"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0 translate-x-2">
                <div :class="['pointer-events-auto flex items-start gap-3 border rounded-lg px-4 py-3 shadow-lg backdrop-blur-sm', classesFor(t.type)]">
                    <i class="fas mt-0.5" :class="iconFor(t.type)"></i>
                    <div class="flex-1 text-sm">{{ t.message }}</div>
                    <button @click="dismiss(t.id)" class="text-current opacity-50 hover:opacity-100 -mr-1">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
