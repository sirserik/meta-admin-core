# Новости: галереи, видео, обложки, лента с фильтрами

С v1.18.0. Всё это сделано сначала на сайте META (ETU) и перенесено в ядро.

## Редактор

В панели инструментов текста две новые кнопки:

- **Галерея** — выбрать несколько фото; в тексте появится блок с превью, где
  можно подписать каждое фото, поменять порядок стрелками, добавить ещё или
  убрать лишнее.
- **Видео** — вставить ссылку «Поделиться» из Instagram (`/reel/…`, `/p/…`),
  YouTube (`watch?v=`, `youtu.be`, `shorts`) или Vimeo. Другие адреса
  отклоняются.

Разметка на выходе:

```html
<div class="news-gallery">
  <figure><img src="/storage/news/1.jpg" alt="Подпись"><figcaption>Подпись</figcaption></figure>
  …
</div>

<figure class="embed embed-instagram" style="max-width:400px;margin:32px auto">
  <iframe src="https://www.instagram.com/reel/CODE/embed/" style="width:100%;aspect-ratio:400/711;…"></iframe>
  <figcaption><a href="https://www.instagram.com/reel/CODE/">Смотреть в Instagram</a></figcaption>
</figure>
```

Уже существующие записи с такой разметкой (в том числе ETU, где ролики лежат
без класса `embed`) открываются и сохраняются без потерь.

> Если сайт пропускает HTML новостей через HTMLPurifier, iframe будет вырезан
> на выводе — разрешите в нём `www.instagram.com`, `www.youtube-nocookie.com`,
> `player.vimeo.com`.

## Показ на Blade-сайте

```blade
<div class="article-content" data-lightbox>{!! $news->content !!}</div>
<x-admin-core::article-media />
```

Компонент даёт стили галереи и роликов и лайтбокс для всех картинок внутри
контейнеров `[data-lightbox]` (свой селектор — `selector=".a, .b"`). Подключать
можно сколько угодно раз — стили и скрипт попадут на страницу один раз.
`sirserik/starter-theme` подключает его в layout сам.

## Обложка карточки

```php
AdminCore::resource('news', [
    'image_field' => 'image',
    'card_image'  => true,   // → meta_data.card_image, 1600×860
    // или: ['to' => 'card_image', 'width' => 1200, 'height' => 630]
    …
]);
```

`to` — колонка или путь внутри JSON-колонки через точку (её нужно привести к
`array` в `$casts`). Обложка собирается при сохранении, если сменился снимок
или обложки ещё нет; старая автоматическая удаляется. Для записей, заведённых
раньше:

```bash
php artisan admin-core:make-cards news --dry-run
php artisan admin-core:make-cards news
php artisan admin-core:make-cards news --force   # пересобрать все
```

Широкий снимок обрезается по центру, вертикальный ставится целиком на
размытый и притемнённый фон — заголовок афиши и лица не режутся. Апскейла нет.

## Лента с фильтрами

Content API:

```
GET /api/content/news?q=музей&year=2026&category=students&sort=oldest&per_page=10
GET /api/content/news/facets
→ {"total":84,"categories":{"events":27,"students":15,…},"years":{"2026":58,"2025":22}}
```

- `q` — заголовок и анонс, в базовых колонках и в `translations` на любом языке;
- `year` — по `date_field` (по умолчанию `published_at`, иначе `created_at`);
- `category` — по `category_field` (по умолчанию `category`) или терм Taxable;
- опубликованность — скоуп модели `published()`, если он есть.

В каждой записи — `image_ratio` (для `image_field`): ≥1.15 — широкая карточка,
0.85–1.15 — квадратная, меньше — вертикальная.

Blade-сайт делает то же из своего контроллера:

```php
use Meta\AdminCore\Facades\AdminCore;
use Meta\AdminCore\Support\ResourceQuery;

$config = AdminCore::getResource('news');
$news   = ResourceQuery::filtered($config, $request->query())->paginate(10)->withQueryString();
$facets = ResourceQuery::facets($config);
```

## Найти человека по всему сайту

```bash
php artisan admin-core:find-mentions "Иванова" "Ivanova" "Ивановой"
```

Все текстовые колонки всех таблиц: карточки, переводы, HTML блоков и
новостей, выключенные блоки, JSON, история правок (`revisions`). Регистр не
важен. Только чтение — что удалять, решает человек.
