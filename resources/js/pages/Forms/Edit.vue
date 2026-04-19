<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const props = defineProps({
    title:      String,
    item:       Object,
    isEdit:     Boolean,
    fieldTypes: Array,
    submit_url: String,
});
defineOptions({ layout: AdminLayout });

const form = useForm({
    name:            props.item.name,
    slug:            props.item.slug,
    fields:          props.item.fields.map(f => ({ options_raw: toOptionsRaw(f.options), ...f })),
    notify_email:    props.item.notify_email ?? '',
    success_message: props.item.success_message ?? '',
    is_active:       props.item.is_active,
});

function addField() {
    form.fields.push({
        name: 'field_' + (form.fields.length + 1),
        label: 'Новое поле',
        type: 'text',
        required: false,
        placeholder: '',
        help: '',
        options_raw: '',
    });
}
function removeField(i) { form.fields.splice(i, 1); }
function moveField(i, d) {
    const j = i + d;
    if (j < 0 || j >= form.fields.length) return;
    const tmp = form.fields[i];
    form.fields[i] = form.fields[j];
    form.fields[j] = tmp;
}

function toOptionsRaw(options) {
    if (!options) return '';
    return options.map(o => {
        if (typeof o === 'string') return o;
        if (o.value && o.label) return `${o.value}=${o.label}`;
        return o.label || o.value || '';
    }).join('\n');
}
function parseOptionsRaw(raw) {
    if (!raw || !raw.trim()) return null;
    return raw.split('\n').map(l => l.trim()).filter(Boolean).map(l => {
        if (l.includes('=')) {
            const [v, ...rest] = l.split('=');
            return { value: v.trim(), label: rest.join('=').trim() };
        }
        return { value: l, label: l };
    });
}

function submit() {
    form.transform(d => ({
        ...d,
        fields: d.fields.map(f => {
            const clone = { ...f };
            if (['select', 'radio'].includes(clone.type)) {
                clone.options = parseOptionsRaw(clone.options_raw) ?? [];
            }
            delete clone.options_raw;
            return clone;
        }),
    }));
    if (props.isEdit) form.put(`/admin/forms/${props.item.id}`);
    else form.post('/admin/forms');
}
</script>

<template>
    <div class="max-w-4xl mx-auto p-6 space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ isEdit ? 'Редактирование формы' : 'Новая форма' }}
                </h1>
                <p v-if="isEdit && submit_url" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Public submit: <code class="font-mono text-xs">POST {{ submit_url }}</code>
                </p>
            </div>
            <Link href="/admin/forms"
                  class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 rounded-lg text-sm">
                ← К списку
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Settings card -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Название</label>
                        <input v-model="form.name" type="text"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Slug <span class="text-xs font-normal text-gray-500">(auto)</span>
                        </label>
                        <input v-model="form.slug" type="text" placeholder="auto"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm font-mono">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Уведомления на email
                            <span class="text-xs font-normal text-gray-500">(опц.)</span>
                        </label>
                        <input v-model="form.notify_email" type="email"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Сообщение после отправки</label>
                        <input v-model="form.success_message" type="text"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded text-red-600">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Форма активна (принимает заявки)</span>
                </label>
            </div>

            <!-- Fields -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Поля формы</h2>
                    <button type="button" @click="addField"
                            class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm">
                        + добавить поле
                    </button>
                </div>
                <div v-if="form.fields.length === 0" class="text-sm text-gray-500 dark:text-gray-400 text-center py-6">
                    Пока ни одного поля. Нажми «+ добавить поле» сверху.
                </div>
                <div v-for="(f, i) in form.fields" :key="i"
                     class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Тип</label>
                            <select v-model="f.type" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md text-sm">
                                <option v-for="t in fieldTypes" :key="t.key" :value="t.key">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Имя (name)</label>
                            <input v-model="f.name" type="text"
                                   class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md text-sm font-mono">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Подпись</label>
                            <input v-model="f.label" type="text"
                                   class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <input v-model="f.placeholder" type="text" placeholder="Placeholder"
                               class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md text-sm">
                        <input v-model="f.help" type="text" placeholder="Подсказка под полем"
                               class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md text-sm">
                    </div>
                    <div v-if="['select','radio'].includes(f.type)">
                        <label class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                            Варианты <span class="lowercase text-gray-400">(одна строка = один вариант; форматы: «label» или «value=label»)</span>
                        </label>
                        <textarea v-model="f.options_raw" rows="4"
                                  class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md text-sm font-mono"></textarea>
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input v-model="f.required" type="checkbox" class="w-4 h-4 rounded text-red-600">
                            <span class="text-gray-700 dark:text-gray-300">Обязательное</span>
                        </label>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="moveField(i, -1)" class="p-1.5 text-gray-500 hover:text-gray-700 dark:hover:text-gray-200">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button type="button" @click="moveField(i, 1)" class="p-1.5 text-gray-500 hover:text-gray-700 dark:hover:text-gray-200">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button type="button" @click="removeField(i)" class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <Link href="/admin/forms" class="px-5 py-2.5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-sm">
                    Отмена
                </Link>
                <button type="submit" :disabled="form.processing"
                        class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-medium">
                    <i class="fas" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                    {{ isEdit ? 'Сохранить' : 'Создать' }}
                </button>
            </div>
        </form>
    </div>
</template>
