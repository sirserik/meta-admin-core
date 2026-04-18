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

    public function test_isset_mirrors_get_including_model_attributes(): void
    {
        // Regression: Collection::firstWhere('block_key', ...) uses data_get
        // → __isset. Before the fix, model attrs returned false here, so
        // Laravel filters silently dropped every PresentedBlock.
        $block = $this->fakeBlock([
            'block_key' => 'hero',
            'data'      => ['cards' => [['title' => 'A']]],
            'settings'  => ['bg' => 'red'],
        ]);
        // Forge an ad-hoc attribute that isn't declared on the default fake.
        $block->is_active = true;

        $p = new PresentedBlock($block, 'ru');

        // Declared readonly props always visible.
        $this->assertTrue(isset($p->id));
        $this->assertTrue(isset($p->key));
        $this->assertTrue(isset($p->title));
        $this->assertTrue(isset($p->locale));

        // Data / settings keys.
        $this->assertTrue(isset($p->cards));
        $this->assertTrue(isset($p->bg));

        // Model attributes reachable via __get MUST also be reachable via isset.
        $this->assertTrue(isset($p->block_key));
        $this->assertTrue(isset($p->block_type));
        $this->assertTrue(isset($p->is_active));

        // Truly missing keys → false.
        $this->assertFalse(isset($p->nonexistent));
    }

    public function test_collection_firstWhere_finds_by_model_attribute(): void
    {
        // End-to-end flavor of the above regression.
        $items = collect([
            new PresentedBlock($this->fakeBlock(['id' => 1, 'block_key' => 'hero']), 'ru'),
            new PresentedBlock($this->fakeBlock(['id' => 2, 'block_key' => 'vision']), 'ru'),
            new PresentedBlock($this->fakeBlock(['id' => 3, 'block_key' => 'cta']), 'ru'),
        ]);

        $vision = $items->firstWhere('block_key', 'vision');
        $this->assertNotNull($vision);
        $this->assertSame(2, $vision->id);
    }

    public function test_readonly_props_shadow_conflicting_data_keys(): void
    {
        // Documented behaviour: PresentedBlock exposes readonly `type`, `id`,
        // `status`, `sort`, `locale` that shadow same-named data keys.
        // Templates that need the inner value must use $block->data['type']
        // or $block->raw('type'). This test pins the contract.
        $block = $this->fakeBlock([
            'block_type' => 'content',
            'data'       => ['type' => 'accreditation-status'],
        ]);
        $p = new PresentedBlock($block, 'ru');

        $this->assertSame('content', $p->type);                        // readonly wins
        $this->assertSame('accreditation-status', $p->data['type']);   // data still reachable
        $this->assertSame('accreditation-status', $p->raw('type'));    // raw() bypasses shadow
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

