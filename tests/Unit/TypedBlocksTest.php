<?php

namespace Tests\Unit;

use Meta\AdminCore\Content\Blocks\HeroBlock;
use Meta\AdminCore\Content\Blocks\LinksBlock;
use Meta\AdminCore\Content\Blocks\StatsBlock;
use PHPUnit\Framework\TestCase;

class TypedBlocksTest extends TestCase
{
    // ── HeroBlock ───────────────────────────────────────────────

    public function test_hero_default_background_is_red(): void
    {
        $hero = new HeroBlock($this->fakeBlock(), 'ru');
        $this->assertSame('red', $hero->backgroundType());
    }

    public function test_hero_reads_background_type_then_falls_back_to_background(): void
    {
        $h1 = new HeroBlock($this->fakeBlock(['data' => ['background_type' => 'gold']]), 'ru');
        $this->assertSame('gold', $h1->backgroundType());

        $h2 = new HeroBlock($this->fakeBlock(['data' => ['background' => 'blue']]), 'ru');
        $this->assertSame('blue', $h2->backgroundType());
    }

    public function test_hero_buttons_normalize_missing_fields(): void
    {
        $hero = new HeroBlock($this->fakeBlock([
            'data' => ['buttons' => [
                ['text' => 'Apply', 'url' => '/apply'],
                ['text' => 'Info'],
                'not-an-array',
            ]],
        ]), 'ru');
        $btns = $hero->buttons();
        $this->assertCount(2, $btns);
        $this->assertSame('Apply', $btns[0]['text']);
        $this->assertSame('/apply', $btns[0]['url']);
        $this->assertNull($btns[0]['icon']);
        $this->assertSame('primary', $btns[0]['style']);
        $this->assertSame('#', $btns[1]['url']);
    }

    public function test_hero_highlights_multilang_resolved(): void
    {
        $hero = new HeroBlock($this->fakeBlock([
            'data' => ['highlights' => [
                ['icon' => 'fa-star', 'text' => ['ru' => 'Первый', 'en' => 'First']],
                ['text' => ['ru' => 'Второй', 'en' => 'Second']],
            ]],
        ]), 'en');
        $h = $hero->highlights();
        $this->assertSame('First',  $h[0]['text']);
        $this->assertSame('fa-star', $h[0]['icon']);
        $this->assertSame('Second', $h[1]['text']);
        $this->assertNull($h[1]['icon']);
    }

    // ── LinksBlock ──────────────────────────────────────────────

    public function test_links_items_shape_and_defaults(): void
    {
        $block = new LinksBlock($this->fakeBlock([
            'data' => ['links' => [
                ['title' => 'Charter', 'url' => '/charter.pdf'],
                ['title' => ['ru' => 'Стратегия'], 'icon' => 'fa-file', 'color' => 'gold',
                 'features' => ['PDF', '2 MB']],
            ]],
        ]), 'ru');
        $items = $block->items();
        $this->assertSame('Charter',  $items[0]['title']);
        $this->assertSame('fas fa-link', $items[0]['icon']); // default
        $this->assertSame('red',      $items[0]['color']);   // default
        $this->assertSame([],         $items[0]['features']);

        $this->assertSame('Стратегия', $items[1]['title']);
        $this->assertSame('#',         $items[1]['url']);    // default
        $this->assertSame('fa-file',   $items[1]['icon']);
        $this->assertSame('gold',      $items[1]['color']);
        $this->assertSame(['PDF', '2 MB'], $items[1]['features']);
    }

    public function test_links_layout_falls_back_to_grid(): void
    {
        $ok  = new LinksBlock($this->fakeBlock(['data' => ['layout' => 'list']]),    'ru');
        $bad = new LinksBlock($this->fakeBlock(['data' => ['layout' => 'unknown']]), 'ru');
        $this->assertSame('list', $ok->layout());
        $this->assertSame('grid', $bad->layout());
    }

    public function test_links_is_empty_reflects_source(): void
    {
        $this->assertTrue((new LinksBlock($this->fakeBlock(), 'ru'))->isEmpty());
        $this->assertFalse((new LinksBlock(
            $this->fakeBlock(['data' => ['links' => [['title' => 'x']]]]),
            'ru'
        ))->isEmpty());
    }

    // ── StatsBlock ──────────────────────────────────────────────

    public function test_stats_items_handles_locale_first_shape(): void
    {
        // data.stats = { ru: [..], kk: [..], en: [..] } — each locale owns its own list.
        $block = new StatsBlock($this->fakeBlock([
            'data' => ['stats' => [
                'ru' => [['value' => '76', 'label' => 'лет']],
                'en' => [['value' => '76', 'label' => 'years']],
            ]],
        ]), 'en');
        $items = $block->items();
        $this->assertCount(1, $items);
        $this->assertSame('76',    $items[0]['value']);
        $this->assertSame('years', $items[0]['label']);
    }

    public function test_stats_items_handles_flat_list_shape(): void
    {
        $block = new StatsBlock($this->fakeBlock([
            'data' => ['items' => [
                ['number' => '15000', 'suffix' => '+', 'title' => 'выпускников',
                 'icon' => 'fa-users'],
            ]],
        ]), 'ru');
        $items = $block->items();
        $this->assertSame('15000',          $items[0]['value']);
        $this->assertSame('+',              $items[0]['suffix']);
        $this->assertSame('выпускников',    $items[0]['label']);
        $this->assertSame('fa-users',       $items[0]['icon']);
        $this->assertNull($items[0]['description']);
    }

    public function test_stats_gradient_falls_back(): void
    {
        $block = new StatsBlock($this->fakeBlock(), 'ru');
        $this->assertSame('#dc2626', $block->gradientFrom());
        $this->assertSame('#b91c1c', $block->gradientTo());
    }

    // ── helpers ─────────────────────────────────────────────────

    protected function fakeBlock(array $overrides = []): object
    {
        $defaults = [
            'id' => 1, 'block_key' => 't', 'block_type' => 'x',
            'title' => '', 'subtitle' => '', 'content' => '',
            'status' => 'published', 'sort_order' => 0,
            'data' => [], 'settings' => [],
        ];
        return (object) array_merge($defaults, $overrides);
    }
}
