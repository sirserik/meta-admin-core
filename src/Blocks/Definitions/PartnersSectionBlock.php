<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class PartnersSectionBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'partners-section';
    }

    public function label(): string
    {
        return 'Partners — секция с партнёрами';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'handshake';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'icon' => ['type' => 'text', 'label' => 'Иконка секции', 'default' => 'fas fa-handshake'],
            'partners' => [
                'type' => 'repeater',
                'label' => 'Партнёры',
                'fields' => [
                    'name' => ['type' => 'text', 'label' => 'Название', 'translatable' => true, 'required' => true],
                    'logo' => ['type' => 'media', 'label' => 'Логотип'],
                    'url' => ['type' => 'url', 'label' => 'Ссылка'],
                    'category' => ['type' => 'text', 'label' => 'Категория', 'translatable' => true],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        // Legacy: partners в формате categories:[{partners:[...]}]
        $partners = $data['partners'] ?? [];
        if (empty($partners) && ! empty($data['categories'])) {
            foreach ($data['categories'] as $cat) {
                foreach ($cat['partners'] ?? [] as $p) {
                    $p['category'] = $cat['name'] ?? null;
                    $partners[] = $p;
                }
            }
        }

        return View::make('blocks.v2.partners-section', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'icon' => $data['icon'] ?? 'fas fa-handshake',
            'partners' => collect($partners)->map(fn ($p) => [
                'name' => $this->localized($p['name'] ?? null, $locale),
                'logo' => $p['logo'] ?? null,
                'url' => $this->localized($p['url'] ?? null, $locale),
                'category' => $this->localized($p['category'] ?? null, $locale),
            ])->filter(fn ($p) => ! empty($p['name']))->all(),
        ])->render();
    }
}
