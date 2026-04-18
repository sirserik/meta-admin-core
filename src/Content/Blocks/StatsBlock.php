<?php

namespace Meta\AdminCore\Content\Blocks;

use Meta\AdminCore\Content\PresentedBlock;

/**
 * Typed view over a `stats` block.
 *
 *   @foreach ($block->items() as $stat)
 *       <strong>{{ $stat['value'] }}{{ $stat['suffix'] }}</strong>
 *       <span>{{ $stat['label'] }}</span>
 *   @endforeach
 *
 * Accepts both `data.stats` and `data.items` as the source key — some
 * legacy blocks use one, newer blocks the other.
 */
class StatsBlock extends PresentedBlock
{
    /**
     * @return list<array{
     *   value: string,
     *   suffix: string,
     *   label: string,
     *   description: ?string,
     *   icon: ?string
     * }>
     */
    public function items(): array
    {
        $raw = $this->data['stats'] ?? $this->data['items'] ?? [];
        // `data.stats` may be `['ru' => [..], 'en' => [..]]` (locale-first
        // pattern) — resolve() collapses it to the active-locale list.
        $resolved = $this->resolve($raw);
        if (!is_array($resolved)) return [];

        return array_values(array_map(
            fn (array $s) => [
                'value'       => (string) ($s['value'] ?? $s['number'] ?? '0'),
                'suffix'      => (string) ($s['suffix'] ?? ''),
                'label'       => (string) ($s['label'] ?? $s['title'] ?? ''),
                'description' => isset($s['description']) ? (string) $s['description'] : null,
                'icon'        => isset($s['icon']) && $s['icon'] !== '' ? (string) $s['icon'] : null,
            ],
            array_filter($resolved, 'is_array'),
        ));
    }

    public function gradientFrom(): string
    {
        return (string) ($this->data['gradient_from'] ?? $this->settings['gradient_from'] ?? '#dc2626');
    }

    public function gradientTo(): string
    {
        return (string) ($this->data['gradient_to'] ?? $this->settings['gradient_to'] ?? '#b91c1c');
    }

    public function isEmpty(): bool
    {
        return empty($this->items());
    }
}
