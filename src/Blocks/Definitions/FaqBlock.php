<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class FaqBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return 'FAQ — часто задаваемые вопросы';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'question-circle';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'items' => [
                'type' => 'repeater',
                'label' => 'Вопросы',
                'fields' => [
                    'question' => ['type' => 'text', 'label' => 'Вопрос', 'translatable' => true, 'required' => true],
                    'answer' => ['type' => 'richtext', 'label' => 'Ответ', 'translatable' => true, 'required' => true],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return View::make('blocks.v2.faq', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'items' => collect($data['items'] ?? [])->map(fn ($i) => [
                'question' => $this->localized($i['question'] ?? null, $locale),
                'answer' => $this->localized($i['answer'] ?? null, $locale),
            ])->filter(fn ($i) => ! empty($i['question']))->all(),
        ])->render();
    }
}
