# 20. Feature Modules

«Модуль-фича» — самодостаточный пакет ресурсов/меню/настроек, который
можно включить или выключить тумблером. Используется для сценариев
типа «SDG портал», «Green Deal Center» — они нужны только на некоторых
сайтах.

## Базовый класс

```php
namespace Meta\AdminCore\Features;

abstract class FeatureModule
{
    abstract public function name(): string;          // уникальный key
    abstract public function label(): string;         // отображаемое имя
    public function description(): string { return ''; }
    public function icon(): string { return 'fa-puzzle-piece'; }
    public function available(): bool { return true; } // доступен ли в принципе
    abstract public function register(AdminCore $core): void;
}
```

## Встроенные модули

Пакет ship'ит:

- `SDGFeature` — SDG-портал (17 goals, отчётность по устойчивому
  развитию).
- `GreenDealFeature` — Green Deal Center.

Они регистрируются автоматически в `AdminCoreServiceProvider::boot()`.

## Включить / выключить

Через env:

```env
ADMIN_FEATURE_SDG=true
ADMIN_FEATURE_GREEN_DEAL=false
```

Через UI: `/admin/features` — тумблеры. Сохраняются в `settings`.

Через код:

```php
AdminCore::setEnabled('sdg', true);
AdminCore::enabled('sdg');    // true/false
```

## Проверка в коде

```php
// Регистрация ресурсов внутри фичи:
AdminCore::whenEnabled('sdg', function ($core) {
    $core->resource('sdg-goals', [...]);
    $core->menuGroup('SDG Портал', [
        ['label' => 'Цели',  'href' => '/admin/sdg-goals', 'icon' => 'fa-target'],
        ['label' => 'Новости', 'href' => '/admin/sdg-news', 'icon' => 'fa-newspaper'],
    ]);
});
```

Если фича выключена — ресурс не регистрируется, пункт меню не появляется.

## Свой модуль

```php
namespace App\Features;

use Meta\AdminCore\AdminCore;
use Meta\AdminCore\Features\FeatureModule;

class EtecAdmissionFeature extends FeatureModule
{
    public function name(): string  { return 'etec_admission'; }
    public function label(): string { return 'Приёмная комиссия ETEC'; }
    public function description(): string {
        return 'Расширенная обработка заявок, CRM-like workflow.';
    }
    public function icon(): string { return 'fa-door-open'; }

    public function register(AdminCore $core): void
    {
        $core->resource('applications', [
            'model' => \App\Models\Application::class,
            'label' => 'Заявки на приём',
            // …
        ]);
    }
}
```

Регистрация в `AppServiceProvider::boot()`:

```php
AdminCore::registerFeature(new \App\Features\EtecAdmissionFeature());
```

Модуль появится в `/admin/features` как тумблер.

## Побочные эффекты

Когда `register()` вызывается внутри `withFeature()` — каждый
`resource()`, `menuItem()`, `menuGroup()` автоматически получает тэг
`feature_name` для визуального выделения в сайдбаре (цветные пункты).

## Деактивация

Выключение фичи через UI сохраняет `0` в `settings` по ключу
`feature.{$name}.enabled`. На следующем boot пакет не вызовет
`register()` этого модуля — все его ресурсы исчезнут из сайдбара и
CRUD-экранов.

Данные в БД **не удаляются** — таблицы, записи остаются. Включил обратно
— всё снова.

## Пример SDGFeature

```php
namespace Meta\AdminCore\Features;

class SDGFeature extends FeatureModule
{
    public function name(): string { return 'sdg'; }
    public function label(): string { return 'SDG Портал'; }
    public function description(): string {
        return '17 целей устойчивого развития + отчётность.';
    }
    public function icon(): string { return 'fa-seedling'; }

    public function register(AdminCore $core): void
    {
        $core->resource('sdg-goals', [
            'model' => \App\Models\SdgGoal::class,
            'label' => 'SDG Цели',
            'menu'  => 'SDG',
            'icon'  => 'fa-globe',
            // …
        ]);

        $core->resource('sdg-news', [
            'model' => \App\Models\SdgNews::class,
            'label' => 'SDG Новости',
            'menu'  => 'SDG',
            // …
        ]);

        $core->menuItem('Ресурсы', '/admin/sdg-resources', 'fa-folder-open', 'SDG', 50);
    }
}
```

## Встроенная регистрация

Пакет регистрирует встроенные модули внутри `$app->booted(...)`:

```php
// Meta\AdminCore\AdminCoreServiceProvider::boot()
foreach ($this->builtInFeatures() as $class) {
    $module = new $class;
    $core->registerFeature($module);
    if ($module->available() && $core->enabled($module->name())) {
        $core->withFeature($module->name(), fn ($c) => $module->register($c));
    }
}

protected function builtInFeatures(): array
{
    return [
        \Meta\AdminCore\Features\SDGFeature::class,
        \Meta\AdminCore\Features\GreenDealFeature::class,
    ];
}
```

Consumer может override этот класс через свой service-provider, добавляя/
убирая модули.

## Следующее

→ [21. Темизация (design tokens)](./21-theme.md)
