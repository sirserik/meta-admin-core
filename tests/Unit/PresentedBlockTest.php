<?php

namespace Tests\Unit;

use Meta\AdminCore\Content\PresentedBlock;
use PHPUnit\Framework\TestCase;

class PresentedBlockTest extends TestCase
{
    public function test_scalar_data_keys_passthrough(): void
    {
        $block = $this->fakeBlock([
            'data' => ['layout' => 'grid', 'count' => 5],
        ]);
        $p = new PresentedBlock($block, 'ru');
        $this->assertSame('grid', $p->layout);
        $this->assertSame(5, $p->count);
        $this->assertNull($p->missing);
    }

    public function test_translatable_field_resolves_locale(): void
    {
        $block = $this->fakeBlock([
            'data' => [
                'links' => [[
                    'title' => ['ru' => 'Заголовок', 'kk' => 'Тақырып', 'en' => 'Title'],
                    'url'   => ['ru' => '/ru/doc.pdf'],
                ]],
            ],
        ]);
        $kk = new PresentedBlock($block, 'kk');
        $this->assertSame('Тақырып', $kk->links[0]['title']);

        // Missing kk → falls back to ru
        $this->assertSame('/ru/doc.pdf', $kk->links[0]['url']);
    }

    public function test_nested_arrays_recursed(): void
    {
        $block = $this->fakeBlock([
            'data' => ['groups' => [
                ['name' => ['ru' => 'Первый'], 'items' => [
                    ['label' => ['ru' => 'Один', 'en' => 'One']],
                ]],
            ]],
        ]);
        $p = new PresentedBlock($block, 'en');
        $this->assertSame('Первый', $p->groups[0]['name']);  // ru fallback
        $this->assertSame('One',     $p->groups[0]['items'][0]['label']);
    }

    public function test_raw_returns_untouched_value(): void
    {
        $block = $this->fakeBlock([
            'data' => ['heading' => ['ru' => 'Русский', 'en' => 'English']],
        ]);
        $p = new PresentedBlock($block, 'ru');
        // Magic __get resolves to locale string
        $this->assertSame('Русский', $p->heading);
        // raw() returns the untouched value
        $raw = $p->raw('heading');
        $this->assertIsArray($raw);
        $this->assertSame('English', $raw['en']);
    }

    public function test_has_checks_both_data_and_settings(): void
    {
        $block = $this->fakeBlock([
            'data'     => ['a' => 1],
            'settings' => ['b' => 2],
        ]);
        $p = new PresentedBlock($block, 'ru');
        $this->assertTrue($p->has('a'));
        $this->assertTrue($p->has('b'));
        $this->assertFalse($p->has('c'));
    }

    public function test_to_array_exposes_augmented_payload(): void
    {
        $block = $this->fakeBlock([
            'title' => 'Hello',
            'data'  => ['message' => ['ru' => 'Привет', 'en' => 'Hi']],
        ]);
        $arr = (new PresentedBlock($block, 'en'))->toArray();
        $this->assertSame('Hi', $arr['data']['message']);
        $this->assertSame('en', $arr['locale']);
    }

    // Helpers ----------------------------------------------------------

    protected function fakeBlock(array $overrides = []): object
    {
        $defaults = [
            'id'         => 1,
            'block_key'  => 'test',
            'block_type' => 'content',
            'title'      => 'Default',
            'subtitle'   => '',
            'content'    => '',
            'status'     => 'published',
            'sort_order' => 0,
            'data'       => [],
            'settings'   => [],
        ];
        return (object) array_merge($defaults, $overrides);
    }
}

