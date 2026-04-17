<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Underline from '@tiptap/extension-underline';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Введите текст…' },
    uploadUrl: { type: String, default: '/admin/upload/image' },
});
const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
        }),
        Underline,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: { rel: 'noopener', target: '_blank' },
        }),
        Image.configure({
            inline: false,
            allowBase64: false,
        }),
    ],
    editorProps: {
        attributes: {
            class: 'tiptap-editor prose prose-sm max-w-none focus:outline-none',
            'data-placeholder': props.placeholder,
        },
        // Paste-guard: перехватываем вставленные картинки (из буфера, drag-n-drop,
        // скриншоты, Google Docs) и загружаем их на сервер. Без этого пользователь
        // мог бы вставить 500КБ base64 прямо в translations.value и раздуть БД.
        handlePaste(view, event) {
            const items = event.clipboardData?.items;
            if (!items) return false;
            for (const item of items) {
                if (item.kind === 'file' && item.type.startsWith('image/')) {
                    event.preventDefault();
                    uploadAndInsert(item.getAsFile());
                    return true;
                }
            }
            return false;
        },
        handleDrop(view, event) {
            const files = event.dataTransfer?.files;
            if (!files || files.length === 0) return false;
            const image = Array.from(files).find(f => f.type.startsWith('image/'));
            if (!image) return false;
            event.preventDefault();
            uploadAndInsert(image);
            return true;
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

async function uploadAndInsert(file) {
    try {
        const fd = new FormData();
        fd.append('file', file);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fd.append('_token', csrf);
        const res = await fetch(props.uploadUrl, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (json.url) editor.value?.chain().focus().setImage({ src: json.url }).run();
    } catch (e) {
        console.warn('[RichTextEditor] upload failed:', e);
    }
}

// Sync incoming modelValue when parent updates it externally (rare but possible)
watch(() => props.modelValue, (value) => {
    const current = editor.value?.getHTML();
    if (editor.value && value !== current) {
        editor.value.commands.setContent(value || '', false);
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function run(cmd) {
    editor.value?.chain().focus()[cmd]().run();
}
function setHeading(level) {
    editor.value?.chain().focus().toggleHeading({ level }).run();
}
function addLink() {
    const url = window.prompt('URL ссылки:', editor.value?.getAttributes('link')?.href || '');
    if (url === null) return;
    if (url === '') {
        editor.value?.chain().focus().unsetLink().run();
        return;
    }
    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}
function triggerImageUpload() {
    fileInput.value?.click();
}
async function onImageSelected(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fd.append('_token', csrf);
    try {
        const res = await fetch(props.uploadUrl, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (json.url) editor.value?.chain().focus().setImage({ src: json.url }).run();
    } catch (err) {
        alert('Ошибка загрузки: ' + err.message);
    } finally {
        e.target.value = '';
    }
}
function isActive(name, attrs) {
    return editor.value?.isActive(name, attrs) ?? false;
}
function btn(active) {
    return [
        'w-8 h-8 flex items-center justify-center rounded text-sm transition-colors',
        active
            ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
            : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700',
    ];
}
</script>

<template>
    <div v-if="editor" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
        <div class="flex flex-wrap gap-1 p-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
            <button type="button" @click="run('toggleBold')" :class="btn(isActive('bold'))"><i class="fas fa-bold"></i></button>
            <button type="button" @click="run('toggleItalic')" :class="btn(isActive('italic'))"><i class="fas fa-italic"></i></button>
            <button type="button" @click="run('toggleUnderline')" :class="btn(isActive('underline'))"><i class="fas fa-underline"></i></button>
            <button type="button" @click="run('toggleStrike')" :class="btn(isActive('strike'))"><i class="fas fa-strikethrough"></i></button>

            <span class="w-px bg-gray-300 dark:bg-gray-600 mx-1"></span>

            <button type="button" @click="setHeading(2)"  :class="btn(isActive('heading', {level: 2}))">H2</button>
            <button type="button" @click="setHeading(3)"  :class="btn(isActive('heading', {level: 3}))">H3</button>

            <span class="w-px bg-gray-300 dark:bg-gray-600 mx-1"></span>

            <button type="button" @click="run('toggleBulletList')"  :class="btn(isActive('bulletList'))"><i class="fas fa-list-ul"></i></button>
            <button type="button" @click="run('toggleOrderedList')" :class="btn(isActive('orderedList'))"><i class="fas fa-list-ol"></i></button>
            <button type="button" @click="run('toggleBlockquote')"  :class="btn(isActive('blockquote'))"><i class="fas fa-quote-right"></i></button>
            <button type="button" @click="run('toggleCodeBlock')"   :class="btn(isActive('codeBlock'))"><i class="fas fa-code"></i></button>

            <span class="w-px bg-gray-300 dark:bg-gray-600 mx-1"></span>

            <button type="button" @click="addLink" :class="btn(isActive('link'))"><i class="fas fa-link"></i></button>
            <button type="button" @click="triggerImageUpload" :class="btn(false)"><i class="fas fa-image"></i></button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImageSelected">

            <span class="w-px bg-gray-300 dark:bg-gray-600 mx-1"></span>

            <button type="button" @click="run('undo')" :class="btn(false)"><i class="fas fa-rotate-left"></i></button>
            <button type="button" @click="run('redo')" :class="btn(false)"><i class="fas fa-rotate-right"></i></button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
