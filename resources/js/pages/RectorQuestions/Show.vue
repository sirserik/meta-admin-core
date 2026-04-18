<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';
import RichTextEditor from '@admin-core/components/RichTextEditor.vue';

const props = defineProps({
    title: String,
    question: Object,
    statuses: Array,
    categories: Array,
});

const form = useForm({
    answer:       props.question.answer       ?? '',
    status:       props.question.status       ?? 'in_review',
    is_published: props.question.is_published ?? false,
});

function save() {
    form.put(`/admin/rector-questions/${props.question.id}`, { preserveScroll: true });
}
function destroy() {
    if (!confirm(`Удалить вопрос #${props.question.id}?`)) return;
    router.delete(`/admin/rector-questions/${props.question.id}`);
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title">
        <template #actions>
            <Link href="/admin/rector-questions" class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i><span>К списку</span>
            </Link>
            <button @click="destroy" class="inline-flex items-center gap-2 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 hover:bg-red-100 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-trash"></i><span>Удалить</span>
            </button>
        </template>
    </PageHeader>

    <form @submit.prevent="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">{{ question.subject || '(без темы)' }}</h2>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        {{ question.name }} · <a :href="`mailto:${question.email}`" class="text-red-600 hover:underline">{{ question.email }}</a>
                        <span v-if="question.category_label" class="mx-2">·</span>
                        <span v-if="question.category_label">{{ question.category_label }}</span>
                    </div>
                    <div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ question.question }}</div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-3">Ответ</label>
                    <RichTextEditor v-model="form.answer" placeholder="Введите ответ…" />
                    <p v-if="form.errors.answer" class="mt-1 text-sm text-red-500">{{ form.errors.answer }}</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Управление</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Статус</label>
                        <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.is_published" type="checkbox" class="w-5 h-5 rounded text-red-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Опубликовать на сайте</span>
                    </label>
                    <p class="text-xs text-gray-500">
                        Публикуются только вопросы в статусе "Отвечено" с заполненным ответом.
                    </p>
                    <button type="submit" :disabled="form.processing" class="w-full bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white py-2.5 rounded-lg font-medium">
                        <i class="fas" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                        Сохранить
                    </button>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Метаданные</h3>
                    <div><span class="text-gray-400">Создано:</span> {{ question.created_at }}</div>
                    <div v-if="question.answered_at"><span class="text-gray-400">Отвечено:</span> {{ question.answered_at }}</div>
                    <div v-if="question.ip_address"><span class="text-gray-400">IP:</span> <span class="font-mono">{{ question.ip_address }}</span></div>
                </div>
            </div>
        </div>
    </form>
</template>
