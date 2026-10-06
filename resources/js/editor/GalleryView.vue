<script setup>
// Галерея внутри редактора: превью, подписи, порядок, добавление и удаление
// фото. Сам текст статьи получает обычную разметку из renderHTML.
import { ref, computed } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';

const props = defineProps({
    node: Object,
    updateAttributes: Function,
    deleteNode: Function,
    extension: Object,
    selected: Boolean,
});

const images = computed(() => props.node.attrs.images || []);
const fileInput = ref(null);
const busy = ref(false);

// Превью «голых» путей (news/x.jpg) — через /media/, как в BlockDataEditor.
function preview(src) {
    if (!src) return '';
    if (/^(https?:)?\/\//.test(src) || src.startsWith('/')) return src;
    return '/media/' + src;
}

function save(list) {
    props.updateAttributes({ images: list });
}
function setCaption(i, caption) {
    const list = images.value.map((im, k) => (k === i ? { ...im, caption, alt: caption } : im));
    save(list);
}
function move(i, d) {
    const j = i + d;
    if (j < 0 || j >= images.value.length) return;
    const list = [...images.value];
    [list[i], list[j]] = [list[j], list[i]];
    save(list);
}
function remove(i) {
    const list = images.value.filter((_, k) => k !== i);
    if (list.length === 0) props.deleteNode();
    else save(list);
}

async function onFiles(e) {
    const files = Array.from(e.target.files || []);
    e.target.value = '';
    const upload = props.extension.options.upload;
    if (!files.length || !upload) return;
    busy.value = true;
    try {
        const added = [];
        for (const f of files) {
            added.push({ src: await upload(f), alt: '', caption: '' });
        }
        save([...images.value, ...added]);
    } catch (err) {
        alert('Ошибка загрузки: ' + err.message);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <NodeViewWrapper class="tiptap-gallery" :class="{ 'is-selected': selected }" contenteditable="false">
        <div class="tiptap-gallery-head" data-drag-handle>
            <span><i class="fas fa-images"></i> Галерея · {{ images.length }} фото</span>
            <span class="tiptap-gallery-actions">
                <button type="button" :disabled="busy" @click="fileInput?.click()">
                    <i :class="busy ? 'fas fa-spinner fa-spin' : 'fas fa-plus'"></i> Фото
                </button>
                <button type="button" class="danger" title="Удалить галерею" @click="deleteNode()">
                    <i class="fas fa-trash"></i>
                </button>
            </span>
            <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onFiles">
        </div>
        <div class="tiptap-gallery-grid">
            <div v-for="(im, i) in images" :key="im.src + i" class="tiptap-gallery-item">
                <img :src="preview(im.src)" :alt="im.alt">
                <div class="tiptap-gallery-tools">
                    <button type="button" title="Левее" :disabled="i === 0" @click="move(i, -1)"><i class="fas fa-arrow-left"></i></button>
                    <button type="button" title="Правее" :disabled="i === images.length - 1" @click="move(i, 1)"><i class="fas fa-arrow-right"></i></button>
                    <button type="button" title="Убрать" class="danger" @click="remove(i)"><i class="fas fa-xmark"></i></button>
                </div>
                <input :value="im.caption" placeholder="Подпись" @change="setCaption(i, $event.target.value.trim())">
            </div>
        </div>
    </NodeViewWrapper>
</template>
