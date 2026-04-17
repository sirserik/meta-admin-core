<?php

namespace Meta\AdminCore\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Meta\AdminCore\AdminCore resource(string $name, array $config)
 * @method static \Meta\AdminCore\AdminCore menuItem(string $label, string $href, string $icon = 'fa-circle', string $menu = 'Другое', int $order = 100)
 * @method static \Meta\AdminCore\AdminCore dashboardStat(callable $provider)
 * @method static \Illuminate\Support\Collection getResources()
 * @method static array|null getResource(string $name)
 * @method static bool hasResource(string $name)
 * @method static array navigation()
 * @method static array dashboardStats()
 */
class AdminCore extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Meta\AdminCore\AdminCore::class;
    }
}
