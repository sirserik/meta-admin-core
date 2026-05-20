<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class AdmissionStepBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'admission_step';
    }

    public function label(): string
    {
        return 'Admission Step — шаг приёма';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'list-ol';
    }

    public function schema(): array
    {
        return [
            'step' => ['type' => 'number', 'label' => 'Номер шага', 'required' => true],
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true, 'required' => true],
            'content' => ['type' => 'richtext', 'label' => 'Описание', 'translatable' => true],
            'icon' => ['type' => 'text', 'label' => 'Иконка (FA)', 'default' => 'fas fa-check'],
            'color' => [
                'type' => 'select',
                'label' => 'Акцент',
                'default' => 'red',
                'options' => ['red' => 'Red', 'gold' => 'Gold', 'blue' => 'Blue', 'green' => 'Green'],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return View::make('blocks.v2.admission-step', [
            'step' => $data['step'] ?? 1,
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'content' => $this->localized($data['content'] ?? ($data['_content'] ?? null), $locale),
            'icon' => $data['icon'] ?? 'fas fa-check',
            'color' => $data['color'] ?? 'red',
        ])->render();
    }
}
