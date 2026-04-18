<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import { BubbleMenu } from '@tiptap/vue-3/menus';
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
const replaceFileInput = ref(null);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            link: false,
            underline: false,
        }),
        Underline,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: { rel: 'noopener', target: '_blank' },
        }),
        Image.configure({
            inline: false,
            allowBase64: false,
            HTMLAttributes: { class: 'tiptap-image' },
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

async function upload(file) {
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
    if (!json.url) throw new Error('No URL in response');
    return json.url;
}

async function uploadAndInsert(file) {
    try {
        const url = await upload(file);
        editor.value?.chain().focus().setImage({ src: url }).run();
    } catch (e) {
        console.warn('[RichTextEditor] upload failed:', e);
    }
}

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
    try {
        const url = await upload(file);
        editor.value?.chain().focus().setImage({ src: url }).run();
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

// ===== Image bubble menu =====
// Shown when the cursor is on an image node. Offers replace / delete /
// width toggles / alt text.

const imageBubbleShouldShow = ({ editor }) => !!editor?.isActive('image');

function triggerImageReplace() {
    replaceFileInput.value?.click();
}
async function onImageReplace(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
        const url = await upload(file);
        editor.value?.chain().focus().updateAttributes('image', { src: url }).run();
    } catch (err) {
        alert('Ошибка загрузки: ' + err.message);
    } finally {
        e.target.value = '';
    }
}
function deleteImage() {
    editor.value?.chain().focus().deleteSelection().run();
}
function editImageAlt() {
    const current = editor.value?.getAttributes('image')?.alt ?? '';
    const alt = window.prompt('Подпись / alt-текст:', current);
    if (alt === null) return;
    editor.value?.chain().focus().updateAttributes('image', { alt }).run();
}
// Tiptap's Image extension accepts a `width` attribute on the node —
// we store it as a % string and let CSS render the resize.
function setImageWidth(width) {
    editor.value?.chain().focus().updateAttributes('image', { width }).run();
}
function imageWidth() {
    return editor.value?.getAttributes('image')?.width ?? '100%';
}
</script>

<template>
    <div v-if="editor" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
        <!-- Main toolbar -->
        <div class="flex flex-wrap gap-1 p-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
            <button type="button" @click="run('toggleBold')" :class="btn(isActive('bold'))" title="Жирный"><i class="fas fa-bold"></i></button>
            <button type="button" @click="run('toggleItalic')" :class="btn(isActive('italic'))" title="Курсив"><i class="fas fa-italic"></i></button>
            <button type="button" @click="run('toggleUnderline')" :class="btn(isActive('underline'))" title="Подчёркивание"><i class="fas fa-underline"></i></button>
            <button type="button" @click="run('toggleStrike')" :class="btn(isActive('strike'))" title="Зачёркнутый"><i class="fas fa-strikethrough"></i></button>

            <span class="w-px bg-gray-300 dark:bg-gray-600 mx-1"></span>

            <button type="button" @click="setHeading(2)"  :class="btn(isActive('heading', {level: 2}))" title="Заголовок 2">H2</button>
            <button type="button" @click="setHeading(3)"  :class="btn(isActive('heading', {level: 3}))" title="Заголовок 3">H3</button>

            <span class="w-px bg-gray-300 dark:bg-gray-600 mx-1"></span>

            <button type="button" @click="run('toggleBulletList')"  :class="btn(isActive('bulletList'))" title="Список"><i class="fas fa-list-ul"></i></button>
            <button type="button" @click="run('toggleOrderedList')" :class="btn(isActive('orderedList'))" title="Нумерованный список"><i class="fas fa-list-ol"></i></button>
            <button type="button" @click="run('toggleBlockquote')"  :class="btn(isActive('blockquote'))" title="Цитата"><i class="fas fa-quote-right"></i></button>
            <button type="button" @click="run('toggleCodeBlock')"   :class="btn(isActive('codeBlock'))" title="Блок кода"><i class="fas fa-code"></i></button>

            <span class="w-px bg-gray-300 dark:bg-gray-600 mx-1"></span>

            <button type="button" @click="addLink" :class="btn(isActive('link'))" title="Ссылка"><i class="fas fa-link"></i></button>
            <button type="button" @click="triggerImageUpload" :class="btn(false)" title="Вставить картинку"><i class="fas fa-image"></i></button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImageSelected">

            <span class="w-px bg-gray-300 dark:bg-gray-600 mx-1"></span>

            <button type="button" @click="run('undo')" :class="btn(false)" title="Отменить"><i class="fas fa-rotate-left"></i></button>
            <button type="button" @click="run('redo')" :class="btn(false)" title="Повторить"><i class="fas fa-rotate-right"></i></button>
        </div>

        <!-- Image bubble menu — appears when the user clicks an image -->
        <BubbleMenu :editor="editor" :should-show="imageBubbleShouldShow">
            <div class="flex items-center gap-1 bg-gray-900 text-white rounded-lg shadow-xl p-1 text-sm">
                <button type="button" @click="triggerImageReplace" class="px-2.5 py-1.5 rounded hover:bg-gray-700 flex items-center gap-1.5" title="Заменить">
                    <i class="fas fa-arrows-rotate text-xs"></i><span class="hidden sm:inline">Заменить</span>
                </button>
                <input ref="replaceFileInput" type="file" accept="image/*" class="hidden" @change="onImageReplace">

                <button type="button" @click="editImageAlt" class="px-2.5 py-1.5 rounded hover:bg-gray-700 flex items-center gap-1.5" title="Подпись / alt">
                    <i class="fas fa-closed-captioning text-xs"></i><span class="hidden sm:inline">Alt</span>
                </button>

                <span class="w-px bg-gray-700 mx-0.5"></span>

                <button type="button" @click="setImageWidth('50%')"
                    :class="['px-2 py-1.5 rounded hover:bg-gray-700 text-xs', imageWidth() === '50%' ? 'bg-gray-700 text-red-300' : '']"
                    title="Ширина 50%">50%</button>
                <button type="button" @click="setImageWidth('75%')"
                    :class="['px-2 py-1.5 rounded hover:bg-gray-700 text-xs', imageWidth() === '75%' ? 'bg-gray-700 text-red-300' : '']"
                    title="Ширина 75%">75%</button>
                <button type="button" @click="setImageWidth('100%')"
                    :class="['px-2 py-1.5 rounded hover:bg-gray-700 text-xs', (imageWidth() === '100%' || !editor.getAttributes('image')?.width) ? 'bg-gray-700 text-red-300' : '']"
                    title="Ширина 100%">100%</button>

                <span class="w-px bg-gray-700 mx-0.5"></span>

                <button type="button" @click="deleteImage" class="px-2.5 py-1.5 rounded hover:bg-red-600 flex items-center gap-1.5 text-red-300" title="Удалить">
                    <i class="fas fa-trash text-xs"></i><span class="hidden sm:inline">Удалить</span>
                </button>
            </div>
        </BubbleMenu>

        <EditorContent :editor="editor" />
    </div>
</template>

<style>
/* Tiptap image width attribute is serialized to the DOM; render it as a
   flexible block so resizing via the bubble menu shows visually. */
.tiptap-editor img.tiptap-image,
.tiptap-editor img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 1rem auto;
    border-radius: 0.5rem;
}
.tiptap-editor img[width="50%"]  { max-width: 50%; }
.tiptap-editor img[width="75%"]  { max-width: 75%; }
.tiptap-editor img[width="100%"] { max-width: 100%; }
/* Selected image — dashed outline to make selection obvious. */
.tiptap-editor img.ProseMirror-selectednode {
    outline: 3px solid #C41E3A;
    outline-offset: 2px;
}
</style>
