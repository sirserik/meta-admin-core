<?php

namespace Meta\AdminCore\Content;

use Meta\AdminCore\Content\Blocks\HeroBlock;
use Meta\AdminCore\Content\Blocks\LinksBlock;
use Meta\AdminCore\Content\Blocks\StatsBlock;

/**
 * Maps a PageBlock `block_type` to its typed PresentedBlock subclass.
 *
 * Unknown types fall back to the generic PresentedBlock — so adding a
 * new block type is zero-friction, and shipping a typed variant later
 * is a purely additive change.
 *
 * Consumers can extend the registry at runtime from a service provider:
 *
 *   BlockTypeRegistry::register('pricing', PricingBlock::class);
 */
class BlockTypeRegistry
{
    /** @var array<string, class-string<PresentedBlock>> */
    protected static array $map = [
        'hero'  => HeroBlock::class,
        'links' => LinksBlock::class,
        'stats' => StatsBlock::class,
    ];

    /** @param class-string<PresentedBlock> $class */
    public static function register(string $blockType, string $class): void
    {
        self::$map[$blockType] = $class;
    }

    /** @return class-string<PresentedBlock> */
    public static function classFor(string $blockType): string
    {
        return self::$map[$blockType] ?? PresentedBlock::class;
    }

    /** @return array<string, class-string<PresentedBlock>> */
    public static function all(): array
    {
        return self::$map;
    }
}
