// Блоки редактора, которых нет в StarterKit: галерея фото и встроенное видео.
//
// Без них TipTap выбрасывает при разборе всё, чего не знает его схема: открыть
// новость с `<div class="news-gallery">` или роликом Instagram и нажать
// «Сохранить» — значит потерять галерею и ролик. Разметка на выходе та же,
// что сайты уже умеют показывать, поэтому старые записи читаются как есть.

import { Node, mergeAttributes, VueNodeViewRenderer } from '@tiptap/vue-3';
import GalleryView from './GalleryView.vue';

/* ------------------------------------------------------------------ */
/* Галерея: <div class="news-gallery"><figure><img><figcaption>…        */
/* ------------------------------------------------------------------ */

function readGallery(el) {
    const images = [];
    el.querySelectorAll('img').forEach((img) => {
        const src = img.getAttribute('src');
        if (!src) return;
        const cap = img.closest('figure')?.querySelector('figcaption')?.textContent?.trim();
        images.push({ src, alt: img.getAttribute('alt') || cap || '', caption: cap || '' });
    });
    return images;
}

export const Gallery = Node.create({
    name: 'gallery',

    addOptions() {
        // upload(file) → Promise<url>; передаёт RichTextEditor.
        return { upload: null };
    },
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,

    addAttributes() {
        return {
            images: {
                default: [],
                parseHTML: (el) => readGallery(el),
                rendered: false,
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div.news-gallery', priority: 60 }];
    },

    renderHTML({ node }) {
        const figures = (node.attrs.images || []).map((im) => {
            const img = ['img', { src: im.src, alt: im.alt || im.caption || '', loading: 'lazy' }];
            return im.caption ? ['figure', {}, img, ['figcaption', {}, im.caption]] : ['figure', {}, img];
        });
        return ['div', { class: 'news-gallery' }, ...figures];
    },

    addNodeView() {
        return VueNodeViewRenderer(GalleryView, {
            // Подписи и кнопки внутри блока — не повод ProseMirror'у ловить клавиши
            // (Backspace в поле подписи иначе удалил бы всю галерею).
            stopEvent: ({ event }) => /^(INPUT|BUTTON|TEXTAREA)$/.test(event.target?.tagName || ''),
        });
    },

    addCommands() {
        return {
            insertGallery: (images) => ({ commands }) =>
                commands.insertContent({ type: this.name, attrs: { images } }),
        };
    },
});

/* ------------------------------------------------------------------ */
/* Видео: <figure class="embed"><iframe src=…><figcaption><a>…          */
/* ------------------------------------------------------------------ */

// Только площадки, чей плеер встраивается по адресу из ссылки «Поделиться».
// Произвольный iframe в редактор не пускаем.
const PROVIDERS = [
    {
        name: 'instagram',
        // instagram.com/reel/CODE/?igsh=… · /p/CODE/ · /tv/CODE/
        match: /instagram\.com\/(?:[\w.]+\/)?(reel|p|tv)\/([\w-]+)/i,
        embed: (m) => `https://www.instagram.com/${m[1] === 'tv' ? 'reel' : m[1]}/${m[2]}/embed/`,
        page: (m) => `https://www.instagram.com/${m[1] === 'tv' ? 'reel' : m[1]}/${m[2]}/`,
        ratio: '400/711',
        maxWidth: '400px',
        label: 'Смотреть в Instagram',
    },
    {
        name: 'youtube',
        match: /(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([\w-]{11})/i,
        embed: (m) => `https://www.youtube-nocookie.com/embed/${m[1]}`,
        page: (m) => `https://www.youtube.com/watch?v=${m[1]}`,
        ratio: '16/9',
        maxWidth: '100%',
        label: 'Смотреть на YouTube',
    },
    {
        name: 'vimeo',
        match: /vimeo\.com\/(?:video\/)?(\d+)/i,
        embed: (m) => `https://player.vimeo.com/video/${m[1]}`,
        page: (m) => `https://vimeo.com/${m[1]}`,
        ratio: '16/9',
        maxWidth: '100%',
        label: 'Смотреть на Vimeo',
    },
];

/** Ссылка «Поделиться» или адрес плеера → параметры встраивания, иначе null. */
export function parseVideoUrl(url) {
    const clean = String(url || '').trim();
    for (const p of PROVIDERS) {
        const m = clean.match(p.match);
        if (m) {
            return {
                provider: p.name,
                src: p.embed(m),
                href: p.page(m),
                ratio: p.ratio,
                maxWidth: p.maxWidth,
                caption: p.label,
            };
        }
    }
    return null;
}

function readEmbed(el) {
    const iframe = el.tagName === 'IFRAME' ? el : el.querySelector('iframe');
    const parsed = parseVideoUrl(iframe?.getAttribute('src'));
    if (!parsed) return false;
    const cap = el.tagName === 'FIGURE' ? el.querySelector('figcaption') : null;
    const a = cap?.querySelector('a');
    return {
        ...parsed,
        href: a?.getAttribute('href') || parsed.href,
        caption: cap?.textContent?.trim() || parsed.caption,
    };
}

export const Embed = Node.create({
    name: 'embed',
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,

    addAttributes() {
        return {
            provider: { default: null, rendered: false },
            src: { default: null, rendered: false },
            href: { default: null, rendered: false },
            ratio: { default: '16/9', rendered: false },
            maxWidth: { default: '100%', rendered: false },
            caption: { default: '', rendered: false },
        };
    },

    parseHTML() {
        return [
            // Обёртка с iframe внутри — так ролики лежат в новостях ETU.
            { tag: 'figure', priority: 60, getAttrs: (el) => (el.querySelector('iframe') ? readEmbed(el) : false) },
            { tag: 'iframe', getAttrs: (el) => readEmbed(el) },
        ];
    },

    renderHTML({ node }) {
        const a = node.attrs;
        // Стили строкой: сайт может не знать класса .embed, а ролик всё равно
        // должен встать по центру и в нужной пропорции.
        const figure = {
            class: `embed embed-${a.provider}`,
            style: `max-width:${a.maxWidth};margin:32px auto`,
        };
        const iframe = [
            'iframe',
            {
                src: a.src,
                style: `width:100%;aspect-ratio:${a.ratio};border:0;border-radius:12px;background:#000`,
                scrolling: 'no',
                allowfullscreen: 'true',
                allow: 'autoplay; encrypted-media; picture-in-picture',
                loading: 'lazy',
            },
        ];
        if (!a.href) return ['figure', figure, iframe];
        return [
            'figure', figure, iframe,
            ['figcaption', { style: 'text-align:center;margin-top:8px;font-size:14px' },
                ['a', { href: a.href, target: '_blank', rel: 'noopener' }, a.caption || a.href]],
        ];
    },

    addNodeView() {
        // В редакторе живой плеер мешает: клик уходит в iframe, блок не выделить.
        // Показываем карточку с площадкой и адресом.
        return ({ node }) => {
            const dom = document.createElement('div');
            dom.className = 'tiptap-embed';
            dom.setAttribute('data-provider', node.attrs.provider || '');
            const icon = { instagram: 'fab fa-instagram', youtube: 'fab fa-youtube', vimeo: 'fab fa-vimeo-v' }[node.attrs.provider] || 'fas fa-play';
            dom.innerHTML = `<i class="${icon}"></i><span></span>`;
            dom.querySelector('span').textContent = node.attrs.href || node.attrs.src || '';
            return { dom };
        };
    },

    addCommands() {
        return {
            insertEmbed: (attrs) => ({ commands }) => commands.insertContent({ type: this.name, attrs }),
        };
    },
});
