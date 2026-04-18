<?php

namespace Tests\Unit;

use Meta\AdminCore\Content\Blocks\HeroBlock;
use Meta\AdminCore\Content\Blocks\LinksBlock;
use Meta\AdminCore\Content\Blocks\StatsBlock;
use Meta\AdminCore\Content\BlockTypeRegistry;
use Meta\AdminCore\Content\PageBlockResolver;
use Meta\AdminCore\Content\PresentedBlock;
use PHPUnit\Framework\TestCase;

class BlockTypeRegistryTest extends TestCase
{
    public function test_known_types_map_to_subclass(): void
    {
        $this->assertSame(HeroBlock::class,  BlockTypeRegistry::classFor('hero'));
        $this->assertSame(LinksBlock::class, BlockTypeRegistry::classFor('links'));
        $this->assertSame(StatsBlock::class, BlockTypeRegistry::classFor('stats'));
    }

    public function test_unknown_type_falls_back_to_generic(): void
    {
        $this->assertSame(PresentedBlock::class, BlockTypeRegistry::classFor('nonexistent'));
    }

    public function test_consumer_can_register_a_custom_type(): void
    {
        $custom = new class ((object) [
            'id' => 0, 'block_key' => '', 'block_type' => 'x', 'title' => '',
            'subtitle' => '', 'content' => '', 'status' => 'published',
            'sort_order' => 0, 'data' => [], 'settings' => [],
        ], 'ru') extends PresentedBlock {};

        BlockTypeRegistry::register('pricing', $custom::class);
        $this->assertSame($custom::class, BlockTypeRegistry::classFor('pricing'));
    }

    public function test_resolver_present_returns_typed_instance(): void
    {
        $block = (object) [
            'id' => 1, 'block_key' => 'main-cta', 'block_type' => 'hero',
            'title' => 'Welcome', 'subtitle' => '', 'content' => '',
            'status' => 'published', 'sort_order' => 0,
            'data' => ['background_type' => 'gold'], 'settings' => [],
        ];
        $p = PageBlockResolver::present($block, 'ru');
        $this->assertInstanceOf(HeroBlock::class, $p);
        $this->assertSame('gold', $p->backgroundType());
    }
}
