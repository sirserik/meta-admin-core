<?php

namespace Meta\AdminCore\Content\Blocks;

use Meta\AdminCore\Content\PresentedBlock;

/**
 * Typed view over a `links` block.
 *
 * Templates already work via PresentedBlock's magic accessors:
 *   $block->links, $block->layout.
 * This subclass adds explicit shape + defaults so `items()` is a
 * fully-normalized list (no `?? []` / `isset()` in the loop).
 *
 *   @foreach ($block->items() as $link)
 *       <a href="{{ $link['url'] }}">{{ $link['title'] }}</a>
 *   @endforeach
 */
class LinksBlock extends PresentedBlock
{
    public const LAYOUT_GRID    = 'grid';
    public const LAYOUT_LIST    = 'list';
    public const LAYOUT_BUTTONS = 'buttons';

    /**
     * Normalized link items — every field guaranteed present.
     *
     * @return list<array{
     *   title: string,
     *   description: ?string,
     *   url: string,
     *   icon: string,
     *   color: string,
     *   features: array<int, string>
     * }>
     */
    public function items(): array
    {
        $raw = $this->data['links'] ?? [];
        if (!is_array($raw)) return [];

        return array_values(array_map(
            fn (array $l) => [
                'title'       => (string) $this->resolve($l['title']       ?? ''),
                'description' => $this->resolve($l['description']          ?? null),
                'url'         => (string) $this->resolve($l['url']         ?? '#'),
                'icon'        => (string) ($l['icon']                      ?? 'fas fa-link'),
                'color'       => (string) ($l['color']                     ?? 'red'),
                'features'    => is_array($l['features'] ?? null) ? array_values($l['features']) : [],
            ],
            array_filter($raw, 'is_array'),
        ));
    }

    /** Layout selector — grid | list | buttons. Falls back to grid. */
    public function layout(): string
    {
        $l = $this->data['layout'] ?? self::LAYOUT_GRID;
        return in_array($l, [self::LAYOUT_GRID, self::LAYOUT_LIST, self::LAYOUT_BUTTONS], true)
            ? $l
            : self::LAYOUT_GRID;
    }

    public function isEmpty(): bool
    {
        return empty($this->data['links']);
    }
}
