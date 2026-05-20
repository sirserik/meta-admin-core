<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class TeamBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'team';
    }

    public function label(): string
    {
        return 'Team — команда';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'columns' => ['type' => 'select', 'label' => 'Колонок', 'default' => '4', 'options' => ['2' => '2', '3' => '3', '4' => '4']],
            'members' => [
                'type' => 'repeater',
                'label' => 'Сотрудники',
                'fields' => [
                    'photo' => ['type' => 'media', 'label' => 'Фото'],
                    'name' => ['type' => 'text', 'label' => 'ФИО', 'translatable' => true, 'required' => true],
                    'position' => ['type' => 'text', 'label' => 'Должность', 'translatable' => true],
                    'email' => ['type' => 'text', 'label' => 'Email'],
                    'phone' => ['type' => 'text', 'label' => 'Телефон'],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return View::make('blocks.v2.team', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'columns' => (int) ($data['columns'] ?? 4),
            'members' => collect($data['members'] ?? [])->map(fn ($m) => [
                'photo' => $m['photo'] ?? null,
                'name' => $this->localized($m['name'] ?? null, $locale),
                'position' => $this->localized($m['position'] ?? null, $locale),
                'email' => $m['email'] ?? null,
                'phone' => $m['phone'] ?? null,
            ])->filter(fn ($m) => ! empty($m['name']))->all(),
        ])->render();
    }
}
