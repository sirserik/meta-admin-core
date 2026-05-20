<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class TimelineBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'timeline';
    }

    public function label(): string
    {
        return 'Timeline — история';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'history';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'events' => [
                'type' => 'repeater',
                'label' => 'События',
                'fields' => [
                    'year' => ['type' => 'text', 'label' => 'Год', 'required' => true],
                    'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true, 'required' => true],
                    'description' => ['type' => 'textarea', 'label' => 'Описание', 'translatable' => true],
                    'icon' => ['type' => 'text', 'label' => 'Иконка', 'default' => 'fas fa-star'],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        // Legacy формат events = {ru: [...], kk: [...], en: [...]}
        $eventsRaw = $data['events'] ?? [];
        if (is_array($eventsRaw) && isset($eventsRaw[$locale]) && is_array($eventsRaw[$locale])) {
            $eventsRaw = $eventsRaw[$locale];
        }

        return View::make('blocks.v2.timeline', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'events' => collect($eventsRaw)->map(fn ($e) => [
                'year' => $e['year'] ?? '',
                'title' => $this->localized($e['title'] ?? null, $locale),
                'description' => $this->localized($e['description'] ?? null, $locale),
                'icon' => $e['icon'] ?? 'fas fa-star',
            ])->filter(fn ($e) => ! empty($e['year']))->all(),
        ])->render();
    }
}
