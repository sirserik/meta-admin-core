<?php

namespace Meta\AdminCore\Content\Blocks;

use Meta\AdminCore\Content\PresentedBlock;

/**
 * Typed view over a `hero` block.
 *
 *   {{ $hero->title }}  {{-- still works (readonly prop) --}}
 *   {{ $hero->backgroundType() }}
 *   @foreach ($hero->buttons() as $btn)
 *     <a href="{{ $btn['url'] }}">{{ $btn['text'] }}</a>
 *   @endforeach
 *   @foreach ($hero->highlights() as $h)
 *     <i class="{{ $h['icon'] }}"></i> {{ $h['text'] }}
 *   @endforeach
 */
class HeroBlock extends PresentedBlock
{
    public const BG_RED      = 'red';
    public const BG_GOLD     = 'gold';
    public const BG_BLUE     = 'blue';
    public const BG_DARK     = 'dark';
    public const BG_GRADIENT = 'gradient';
    public const BG_WHITE    = 'white';
    public const BG_GRAY     = 'gray';

    public function backgroundType(): string
    {
        return (string) ($this->data['background_type']
            ?? $this->data['background']
            ?? self::BG_RED);
    }

    public function icon(): ?string
    {
        $i = $this->data['icon'] ?? null;
        return is_string($i) && $i !== '' ? $i : null;
    }

    public function imageUrl(): ?string
    {
        $u = $this->model->image_url ?? null;
        return is_string($u) && $u !== '' ? $u : null;
    }

    /**
     * @return list<array{text: string, url: string, icon: ?string, style: string}>
     */
    public function buttons(): array
    {
        $raw = $this->resolve($this->data['buttons'] ?? []);
        if (!is_array($raw)) return [];
        return array_values(array_map(
            fn (array $b) => [
                'text'  => (string) ($b['text']  ?? 'Подробнее'),
                'url'   => (string) ($b['url']   ?? '#'),
                'icon'  => isset($b['icon']) && $b['icon'] !== '' ? (string) $b['icon'] : null,
                'style' => (string) ($b['style'] ?? 'primary'),
            ],
            array_filter($raw, 'is_array'),
        ));
    }

    /**
     * @return list<array{icon: ?string, text: string}>
     */
    public function highlights(): array
    {
        $raw = $this->resolve($this->data['highlights'] ?? []);
        if (!is_array($raw)) return [];
        return array_values(array_map(
            fn (array $h) => [
                'icon' => isset($h['icon']) && $h['icon'] !== '' ? (string) $h['icon'] : null,
                'text' => (string) ($h['text'] ?? ''),
            ],
            array_filter($raw, 'is_array'),
        ));
    }

    /**
     * @return list<array{url: string, alt: string}>
     */
    public function logos(): array
    {
        $raw = $this->data['logos'] ?? [];
        if (!is_array($raw)) return [];
        return array_values(array_map(
            fn (array $l) => [
                'url' => (string) ($l['url'] ?? $l['src'] ?? ''),
                'alt' => (string) ($l['alt'] ?? ''),
            ],
            array_filter($raw, 'is_array'),
        ));
    }
}
