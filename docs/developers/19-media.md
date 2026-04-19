# 19. Медиа и `ImageService::focalCrop`

Работа с загруженными изображениями, WebP-конвертация, focal-point
умная обрезка.

## Загрузка

`ImageService::upload($uploadedFile, $folder, $width, $height, $quality)`:

- Безопасно переименовывает (транслитерирует имя).
- Если установлен `intervention/image-laravel` — конвертирует в WebP.
- Опциональный resize (с сохранением aspect-ratio).
- Кладёт в `storage/app/public/{folder}/{timestamp}_{name}.webp`.
- Возвращает **relative path** (без `/storage/`).

```php
use Meta\AdminCore\Services\ImageService;

$path = app(ImageService::class)->upload(
    $request->file('image'),
    'articles',          // folder
    1200,                // maxWidth
    800,                 // maxHeight (с сохранением AR)
    85,                  // quality
);

// $path = 'articles/1712341234_my-photo.webp'
```

Сохрани `$path` в колонку модели.

### Предустановленные размеры

```php
$path = app(ImageService::class)->uploadWithSize($file, 'articles', 'hero');
// 'thumbnail' (150×150) | 'small' (300×300) | 'medium' (800×600)
// | 'large' (1200×900) | 'hero' (1920×1080)
```

### Без resize

```php
$path = app(ImageService::class)->uploadOriginal($file, 'articles', 90);
```

## Удаление

```php
app(ImageService::class)->delete($oldPath);
```

Безопасно: если `$oldPath` начинается с `/storage/`, обрезается.
Идемпотентно.

## Replace

```php
$path = app(ImageService::class)->replace($file, $oldPath, 'articles');
// удаляет старый, загружает новый
```

## Focal Crop

Умная обрезка по «точке фокуса» — чтобы лицо/логотип остались в кадре
при любом соотношении сторон.

```php
$cropped = app(ImageService::class)->focalCrop(
    $path,     // 'articles/my-photo.webp'
    800,       // target width
    400,       // target height
    0.3,       // focal X (0.0 - 1.0; 0.5 = центр)
    0.2,       // focal Y (0.5 = центр)
);

// Возвращает $cropped = 'articles/focal/800x400_30_20-my-photo.webp'
```

Логика:
1. Масштабирует так, чтобы меньшая сторона покрыла target.
2. Обрезает вокруг focal-точки (чтобы она была видна в центре).
3. Кэширует результат на диске — на следующий вызов отдаст тот же файл.

Если `intervention/image` не установлен — возвращает исходный `$path`
без ошибок (graceful fallback).

### Использование в Blade

```blade
@php
    $croppedPath = app(\Meta\AdminCore\Services\ImageService::class)
        ->focalCrop($article->featured_image, 1200, 630, $article->focal_x, $article->focal_y);
@endphp

<img src="{{ asset('storage/' . $croppedPath) }}" alt="">
```

### Хранение focal-point

В таблице ресурса добавь 2 колонки (миграция consumer-приложения):

```php
Schema::table('articles', function ($t) {
    $t->float('focal_x')->default(0.5);
    $t->float('focal_y')->default(0.5);
});
```

И на форме — `FocalPointPicker.vue`:

```vue
<template>
    <FocalPointPicker
        v-if="form.featured_image_url"
        :src="form.featured_image_url"
        v-model="form.focal"
    />
</template>

<script setup>
import FocalPointPicker from '@admin-core/components/FocalPointPicker.vue';
// form.focal = { x: 0.5, y: 0.5 }
</script>
```

## Медиатека

Экран `/admin/media` — CRUD поверх таблицы `media`. Управляется через
`Meta\AdminCore\Http\Controllers\MediaController`. Возвращает list
+ json-ответы для загрузки.

API эндпоинты (пакетные):

```
GET    /admin/media                — list
POST   /admin/media                — upload
PUT    /admin/media/{medium}       — update (alt-text)
DELETE /admin/media/{medium}       — delete
```

### Model `Media`

```
media
 - id
 - disk            — 'public'
 - path            — relative path
 - mime            — image/webp, application/pdf, …
 - size            — bytes
 - width / height  — для изображений
 - alt_text        — accessibility
 - uploaded_by     — user_id, nullable
 - timestamps
```

Можно прямо использовать:

```php
use Meta\AdminCore\Models\Media;

Media::where('mime', 'like', 'image/%')->latest()->paginate(20);
```

## Прямая сервинг-отдача

Пакет регистрирует `/media/{path}` в `routes/public.php`:

```
GET /media/articles/1712341234_my-photo.webp
```

Serves через PHP с `Cache-Control: max-age=31536000`. Нужен, когда
Plesk/Nginx не отдаёт `/storage/*` напрямую.

Старые ссылки на `/storage/*` редиректятся 301 → `/media/*`.

## Хранилища

По умолчанию — disk `public` (→ `storage/app/public/`). Если нужен
другой (S3, локальный private):

- Для медиатеки — меняй `disk` колонку в Media.
- `ImageService` сейчас хардкодит `Storage::disk('public')`. Для S3
  — override класс, зарегистрируй свой binding:

  ```php
  $this->app->bind(
      \Meta\AdminCore\Services\ImageService::class,
      \App\Services\S3ImageService::class,
  );
  ```

## Ограничения

- **Файлы не удаляются при `delete()` модели.** Нет observer'а, который
  бы это делал — если тебе важно, добавь сам:

  ```php
  Article::observe(new class {
      public function deleted(Article $a): void {
          app(ImageService::class)->delete($a->featured_image);
      }
  });
  ```

- **Нет дубликатов detection.** Если загрузил одну и ту же картинку
  2 раза — будет 2 файла. Для dedup — пиши observer на MediaController.
- **SVG не конвертируется.** Intervention не поддерживает, пакет
  сохраняет как есть (для логотипов это обычно правильно).

## Следующее

→ [20. Feature Modules](./20-feature-modules.md)
