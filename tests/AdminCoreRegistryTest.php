<?php

namespace Meta\AdminCore\Tests;

use Meta\AdminCore\AdminCore;
use PHPUnit\Framework\TestCase;

class AdminCoreRegistryTest extends TestCase
{
    public function test_resource_registration_stores_config_with_defaults(): void
    {
        $core = new AdminCore;
        $core->resource('articles', [
            'model' => 'App\\Models\\Article',
            'label' => 'Статьи',
        ]);

        $r = $core->getResource('articles');

        $this->assertSame('articles', $r['name']);
        $this->assertSame('articles', $r['route']);
        $this->assertSame('Resource', $r['page']);
        $this->assertSame('Контент', $r['menu']);
        $this->assertSame('fa-file', $r['icon']);
        $this->assertSame(15, $r['per_page']);
        $this->assertSame(['created_at' => 'desc'], $r['order_by']);
        $this->assertSame([], $r['translatable']);
        $this->assertSame([], $r['fields']);
        $this->assertSame([], $r['attributes']);
        $this->assertNull($r['image_field']);
    }

    public function test_resource_registration_preserves_explicit_config(): void
    {
        $core = new AdminCore;
        $core->resource('teachers', [
            'model'        => 'App\\Models\\Teacher',
            'label'        => 'Преподаватели',
            'menu'         => 'Образование',
            'icon'         => 'fa-chalkboard-user',
            'image_field'  => 'photo',
            'translatable' => ['name', 'bio'],
            'order_by'     => ['order' => 'asc'],
            'per_page'     => 50,
        ]);

        $r = $core->getResource('teachers');
        $this->assertSame('Образование', $r['menu']);
        $this->assertSame('fa-chalkboard-user', $r['icon']);
        $this->assertSame('photo', $r['image_field']);
        $this->assertSame(['name', 'bio'], $r['translatable']);
        $this->assertSame(['order' => 'asc'], $r['order_by']);
        $this->assertSame(50, $r['per_page']);
    }

    public function test_has_resource(): void
    {
        $core = new AdminCore;
        $this->assertFalse($core->hasResource('articles'));
        $core->resource('articles', ['model' => 'X']);
        $this->assertTrue($core->hasResource('articles'));
    }

    public function test_navigation_groups_resources_and_menu_items_by_menu_section(): void
    {
        $core = new AdminCore;
        $core->resource('articles', ['model' => 'X', 'label' => 'Статьи', 'menu' => 'Контент', 'icon' => 'fa-newspaper']);
        $core->resource('schools',  ['model' => 'Y', 'label' => 'Школы',  'menu' => 'Образование', 'icon' => 'fa-school']);
        $core->menuItem('Кэш',    '/admin/cache', 'fa-broom', 'Система', 99);
        $core->menuItem('Бэкапы', '/admin/backup', 'fa-database', 'Система', 100);

        $nav = $core->navigation();

        $sections = array_column($nav, 'section');
        $this->assertContains('Контент', $sections);
        $this->assertContains('Образование', $sections);
        $this->assertContains('Система', $sections);

        $system = collect($nav)->firstWhere('section', 'Система');
        $this->assertCount(2, $system['items']);
        $this->assertSame('Кэш', $system['items'][0]['label']);
        $this->assertSame('Бэкапы', $system['items'][1]['label']);
    }

    public function test_navigation_prefixes_fas_on_icons_missing_it(): void
    {
        $core = new AdminCore;
        $core->resource('articles', ['model' => 'X', 'label' => 'A', 'icon' => 'fa-newspaper']);
        $core->menuItem('B', '/admin/b', 'fas fa-gear', 'System');

        $nav = $core->navigation();
        $items = array_merge(...array_column($nav, 'items'));

        $icons = array_column($items, 'icon');
        foreach ($icons as $icon) {
            $this->assertStringStartsWith('fas ', $icon);
            $this->assertStringNotContainsString('fas fas ', $icon);
        }
    }

    public function test_dashboard_stats_invokes_registered_providers(): void
    {
        $core = new AdminCore;
        $core->dashboardStat(fn () => ['label' => 'A', 'value' => 1, 'icon' => 'fa-x']);
        $core->dashboardStat(fn () => ['label' => 'B', 'value' => 2, 'icon' => 'fa-y']);
        $core->dashboardStat(fn () => null);

        $stats = $core->dashboardStats();
        $this->assertCount(2, $stats, 'null-returning providers are filtered out');
        $this->assertSame('A', $stats[0]['label']);
        $this->assertSame(2, $stats[1]['value']);
    }
}
