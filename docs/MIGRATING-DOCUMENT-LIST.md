# Миграция legacy `links` блоков → `document-list`

## TL;DR

```bash
# 1. Превью (без изменений)
php artisan admin-core:migrate-to-document-list --dry

# 2. Применить
php artisan admin-core:migrate-to-document-list

# 3. ОБЯЗАТЕЛЬНО: проверить что consumer-views рендерят новый тип.
#    Команда сама подскажет что и где править.
```

## Зачем это нужно

Исторически у каждого consumer-сайта были свои варианты «списка документов»: `links`, `downloadable-docs`, `admission-documents`, `accreditation-documents`, `grid-documents`, `download` — все с одной потребностью (заголовок + раскладка + набор файлов/ссылок). Пакет ввёл канонический `document-list`. Команда `migrate-to-document-list` переводит `links` на канон.

Что меняется в БД:
- `block_type = 'links'` → `'document-list'`
- `data.links[]` → `data.items[]` (без потери порядка/полей)
- `data.layout` нормализуется: пусто → `list`, `'grid'` → `'grid-3'`, остальное — без изменений

## ⚠️ Важно: пакет НЕ патчит ваши blade-шаблоны

Каждый consumer-сайт держит свой собственный switch/рендер (например `etu-laravel/resources/views/components/page-blocks.blade.php`). Пакет переименовал тип в БД — но если в вашем switch есть только `@case('links')`, мигрированные блоки **молча упадут в `@default`** и перестанут рендериться. Это привело к регрессии на ETU 2026-04-22 (исчезли все ссылки на документы на sdg-resources, library, electronic-resources, about).

Команда после успешной миграции выводит warning и точные diff'ы, которые нужно применить. Ниже — что именно надо поправить.

## Что чинить в consumer-views

### 1. `resources/views/components/page-blocks.blade.php`

Найти `@case('links')` и добавить рядом `@case('document-list')` без `@break` между ними — оба ведут в один и тот же partial:

```diff
  @case('links')
+ @case('document-list')
      @include('components.page-blocks.types.links', compact('block', 'blocks', 'locale', 'localize', 'page'))
      @break
```

### 2. `resources/views/components/page-blocks/types/links.blade.php`

Партиал должен принимать оба формата (`links` для legacy, `items` для мигрированных) и нормализовать новые layout'ы:

```diff
- $linksLayout = $block->layout ?? 'grid';
- $links       = $block->links  ?? [];
+ $links     = $block->links ?? $block->items ?? [];
+ $rawLayout = $block->layout ?? 'grid';
+ $linksLayout = match (true) {
+     str_starts_with((string) $rawLayout, 'grid') => 'grid',
+     $rawLayout === 'list', $rawLayout === 'cards' => 'list',
+     default => $rawLayout,
+ };
```

Это сохраняет рендер старых `links` блоков и одновременно поднимает мигрированные `document-list`.

## Re-run safety

Команда идемпотентна:
- если блок уже `document-list` (или содержит `data.items` без `data.links`) — она его пропускает / просто переписывает `block_type`.
- `--dry` откатывает транзакцию.

## Reference

- Регрессия и фикс: ETU `b17d1c6b fix(blocks): render document-list (post migrate-to-document-list)` (2026-04-27).
- Команда: `src/Console/Commands/MigrateToDocumentListCommand.php`.
- Каталог типов: `Meta\AdminCore\Support\DefaultBlockCatalog::BLOCK_TYPES['document-list']`.
