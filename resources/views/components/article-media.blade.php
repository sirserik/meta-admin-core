{{--
    Галерея (`div.news-gallery`) и ролики (`figure.embed`) — разметка, которую
    кладёт редактор admin-core, плюс лайтбокс: клик по снимку — во весь экран,
    второй клик — натуральная величина с прокруткой, стрелки и ←/→ листают
    все снимки статьи, Esc и фон закрывают. Без зависимостей; стили и скрипт
    попадают на страницу один раз, сколько бы раз компонент ни подключили.

    Подключение — рядом с текстом статьи:
        <div class="article-content" data-lightbox>{!! $news->content !!}</div>
        <x-admin-core::article-media />
    Свой селектор контейнеров (через запятую):
        <x-admin-core::article-media selector=".article-content, .article-hero" />
--}}
@props(['selector' => '[data-lightbox]'])
@php
    $lbLabels = [
        'ru' => ['close' => 'Закрыть', 'prev' => 'Назад', 'next' => 'Вперёд'],
        'kk' => ['close' => 'Жабу', 'prev' => 'Артқа', 'next' => 'Алға'],
        'en' => ['close' => 'Close', 'prev' => 'Previous', 'next' => 'Next'],
    ][app()->getLocale()] ?? ['close' => 'Close', 'prev' => 'Previous', 'next' => 'Next'];
@endphp
<script>window.__acLightboxRoots = (window.__acLightboxRoots || []).concat(@json($selector));</script>
@once
<style>
.news-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:32px 0}
.news-gallery figure{margin:0}
.news-gallery img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:14px;display:block;margin:0}
.news-gallery figcaption{text-align:center;margin-top:6px;font-size:14px;opacity:.75}
@media(max-width:560px){.news-gallery{grid-template-columns:1fr}}
figure.embed{margin:32px auto}
figure.embed iframe{display:block;width:100%}

.ac-lb-zoomable img{cursor:zoom-in}
.ac-lb-zoomable a img{cursor:pointer}
.ac-lb{position:fixed;inset:0;z-index:10000;background:rgba(16,12,10,.94);display:flex;flex-direction:column;animation:ac-lb-in .18s ease-out}
@keyframes ac-lb-in{from{opacity:0}to{opacity:1}}
.ac-lb[hidden]{display:none}
.ac-lb-stage{flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:56px 72px 0;overflow:hidden}
.ac-lb-stage img{max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;border-radius:6px;cursor:zoom-in;box-shadow:0 30px 80px -30px rgba(0,0,0,.8);user-select:none;margin:0}
.ac-lb-stage.zoomed{display:block;overflow:auto;padding:56px 0 0;text-align:center}
.ac-lb-stage.zoomed img{max-width:none;max-height:none;margin:0 auto;display:block;cursor:zoom-out;border-radius:0}
.ac-lb-bar{flex:none;display:flex;align-items:center;justify-content:center;gap:18px;padding:14px 72px 18px;color:#fff;font-size:14px;min-height:52px}
.ac-lb-cap{opacity:.9;text-align:center}
.ac-lb-count{opacity:.55;white-space:nowrap;font-variant-numeric:tabular-nums}
.ac-lb-btn{position:absolute;z-index:2;display:flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.08);color:#fff;cursor:pointer;padding:0}
.ac-lb-btn:hover{background:rgba(255,255,255,.2)}
.ac-lb-btn[hidden]{display:none}
.ac-lb-close{top:14px;right:14px}
.ac-lb-prev{left:14px;top:50%;transform:translateY(-50%)}
.ac-lb-next{right:14px;top:50%;transform:translateY(-50%)}
@media(max-width:720px){
  .ac-lb-stage{padding:60px 8px 0}
  .ac-lb-stage.zoomed{padding:60px 0 0}
  .ac-lb-bar{padding:12px 16px 16px;flex-wrap:wrap;gap:8px}
  .ac-lb-btn{width:40px;height:40px}
  .ac-lb-prev{left:6px;top:auto;bottom:10px;transform:none}
  .ac-lb-next{right:6px;top:auto;bottom:10px;transform:none}
}
</style>
<div class="ac-lb" role="dialog" aria-modal="true" hidden>
    <button type="button" class="ac-lb-btn ac-lb-close" aria-label="{{ $lbLabels['close'] }}">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <button type="button" class="ac-lb-btn ac-lb-prev" aria-label="{{ $lbLabels['prev'] }}">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 5l-7 7 7 7"/></svg>
    </button>
    <button type="button" class="ac-lb-btn ac-lb-next" aria-label="{{ $lbLabels['next'] }}">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>
    </button>
    <div class="ac-lb-stage"><img alt=""></div>
    <div class="ac-lb-bar"><span class="ac-lb-cap"></span><span class="ac-lb-count"></span></div>
</div>
<script>
(function () {
    var lb = document.querySelector('.ac-lb');
    if (!lb) return;
    // В body: position:fixed внутри предка с transform/filter встал бы не на весь экран.
    document.body.appendChild(lb);
    var stage = lb.querySelector('.ac-lb-stage'), view = stage.querySelector('img');
    var cap = lb.querySelector('.ac-lb-cap'), count = lb.querySelector('.ac-lb-count');
    var prev = lb.querySelector('.ac-lb-prev'), next = lb.querySelector('.ac-lb-next');
    var slides = [], index = 0;

    function roots() {
        var sel = (window.__acLightboxRoots || []).join(',');
        return sel ? Array.prototype.slice.call(document.querySelectorAll(sel)) : [];
    }
    function images() {
        var out = [];
        roots().forEach(function (r) {
            Array.prototype.forEach.call(r.tagName === 'IMG' ? [r] : r.querySelectorAll('img'), function (img) {
                if (out.indexOf(img) < 0) out.push(img);
            });
        });
        return out;
    }
    function captionOf(img) {
        var fig = img.closest('figure'), fc = fig && fig.querySelector('figcaption');
        return (fc && fc.textContent.trim()) || img.alt || '';
    }
    function show() {
        var s = slides[index];
        view.src = s.src; view.alt = s.caption;
        cap.textContent = s.caption;
        count.textContent = slides.length > 1 ? (index + 1) + ' / ' + slides.length : '';
        prev.hidden = next.hidden = slides.length < 2;
        stage.classList.remove('zoomed');
    }
    function open(i, all) {
        slides = all.map(function (el) { return { src: el.currentSrc || el.src, caption: captionOf(el) }; });
        index = i; show();
        lb.hidden = false;
        document.documentElement.style.overflow = 'hidden';
    }
    function close() {
        lb.hidden = true;
        document.documentElement.style.overflow = '';
    }
    function step(d) {
        if (slides.length < 2) return;
        index = (index + d + slides.length) % slides.length; show();
    }

    document.addEventListener('click', function (e) {
        var img = e.target.closest && e.target.closest('img');
        if (!img || !lb.hidden || img.closest('a') || img.closest('.ac-lb')) return;
        var all = images(), i = all.indexOf(img);
        if (i < 0) return;
        e.preventDefault();
        open(i, all);
    });
    lb.querySelector('.ac-lb-close').addEventListener('click', close);
    prev.addEventListener('click', function (e) { e.stopPropagation(); step(-1); });
    next.addEventListener('click', function (e) { e.stopPropagation(); step(1); });
    lb.addEventListener('click', function (e) { if (e.target === lb || e.target === stage) close(); });
    view.addEventListener('click', function (e) { e.stopPropagation(); stage.classList.toggle('zoomed'); });
    document.addEventListener('keydown', function (e) {
        if (lb.hidden) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowRight') step(1);
        else if (e.key === 'ArrowLeft') step(-1);
    });
    function mark() { roots().forEach(function (r) { r.classList.add('ac-lb-zoomable'); }); }
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', mark) : mark();
})();
</script>
@endonce
