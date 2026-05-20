<?php

namespace Meta\AdminCore\Blocks;

abstract class BlockDefinition
{
    /** Уникальный идентификатор блока (snake-case). Пример: 'hero', 'content', 'cta'. */
    abstract public function handle(): string;

    /** Человекочитаемый лейбл для админ-UI. */
    abstract public function label(): string;

    /**
     * Схема полей блока — источник правды для админ-формы, валидации, render.
     * Формат:
     *   [
     *     'field_key' => [
     *       'type' => 'text|richtext|textarea|number|select|media|url|color|repeater|group|checkbox|date',
     *       'label' => string,
     *       'required' => bool (default false),
     *       'translatable' => bool (default false) — хранится как {ru, kk, en}
     *       'default' => mixed,
     *       'help' => string — подсказка в UI,
     *       'options' => array — для select
     *       'fields' => array — для repeater/group (вложенные поля)
     *       'max' => int — для repeater (макс. элементов)
     *     ],
     *   ]
     */
    abstract public function schema(): array;

    /** Рендер HTML на публичную страницу. Получает data (уже валидированную) и локаль. */
    abstract public function render(array $data, string $locale): string;

    /** Категория в админ-палитре блоков: 'layout' | 'content' | 'media' | 'widget' | 'cta' */
    public function category(): string
    {
        return 'content';
    }

    /** Иконка (FontAwesome handle без 'fa-'). */
    public function icon(): string
    {
        return 'cube';
    }

    /** Возвращает дефолтные значения всех полей из schema (для создания нового блока). */
    public function defaultData(): array
    {
        $out = [];
        foreach ($this->schema() as $key => $def) {
            if (array_key_exists('default', $def)) {
                $out[$key] = $def['default'];
            }
        }

        return $out;
    }

    /**
     * Laravel validation rules, сгенерированные из schema.
     * Переопределяй для кастомных правил.
     */
    public function validationRules(): array
    {
        $rules = [];
        foreach ($this->schema() as $key => $def) {
            $rule = [];
            if ($def['required'] ?? false) {
                $rule[] = 'required';
            } else {
                $rule[] = 'nullable';
            }
            $rule[] = match ($def['type'] ?? 'text') {
                'text', 'textarea', 'richtext', 'url', 'color', 'date' => 'string',
                'number' => 'numeric',
                'checkbox' => 'boolean',
                'select' => 'string',
                'media' => 'string',
                'repeater', 'group' => 'array',
                default => 'string',
            };
            $rules["data.{$key}"] = implode('|', $rule);

            if (($def['type'] ?? null) === 'select' && ! empty($def['options'])) {
                $rules["data.{$key}"] .= '|in:' . implode(',', array_keys($def['options']));
            }
        }

        return $rules;
    }

    /**
     * Какие handle-ы блоков разрешены внутри этого блока (Gutenberg innerBlocks).
     * Пустой массив = нет вложенности.
     */
    public function innerBlocksAllowed(): array
    {
        return [];
    }

    /**
     * Варианты вёрстки одного и того же блока. Аналог Gutenberg block patterns.
     *
     * Каждый вариант — пара `key => label`. Админка показывает radio/select
     * в форме редактирования, value сохраняется в `data['variant']`. Render
     * автоматически ищет blade `blocks.v2.{handle}.{variant}`, фоллбек на
     * базовый `blocks.v2.{handle}` если файла нет.
     *
     * Пустой массив (по умолчанию) = у блока ровно одна вёрстка.
     *
     * Пример:
     *   public function variants(): array
     *   {
     *       return [
     *           'centered'  => 'По центру, светлый',
     *           'split'     => 'Сплит с картинкой справа',
     *           'image-bg'  => 'Полноэкранная картинка',
     *       ];
     *   }
     *
     * @return array<string, string>
     */
    public function variants(): array
    {
        return [];
    }

    /**
     * Рендерит шаблон блока с учётом варианта.
     *
     * Поиск:
     *  1) `blocks.v2.{handle}.{variant}` — variant-specific
     *  2) `blocks.v2.{handle}`           — default fallback
     *
     * Сайт может перебить любую ступень, положив свой view в
     * `resources/views/blocks/v2/{handle}/{variant}.blade.php` — Laravel
     * view finder возьмёт локальный первым (consumer overrides package).
     *
     * Используется в render() реализациях:
     *   public function render(array $data, string $locale): string
     *   {
     *       return $this->renderVariant($data, $locale);
     *   }
     */
    protected function renderVariant(array $data, string $locale, array $extra = []): string
    {
        $handle  = $this->handle();
        $variant = $data['variant'] ?? null;

        $view = \Illuminate\Support\Facades\View::class;
        $candidates = [];
        if (is_string($variant) && $variant !== '' && array_key_exists($variant, $this->variants())) {
            $candidates[] = "blocks.v2.{$handle}.{$variant}";
        }
        $candidates[] = "blocks.v2.{$handle}";

        $payload = array_merge(['data' => $data, 'locale' => $locale, 'block' => $this], $extra);

        foreach ($candidates as $candidate) {
            if ($view::exists($candidate)) {
                return $view::make($candidate, $payload)->render();
            }
        }

        \Illuminate\Support\Facades\Log::warning(
            "BlockDefinition::renderVariant: no view found for block '{$handle}'",
            ['tried' => $candidates]
        );
        return '';
    }

    /** Помощник: достать локализованное значение из {ru, kk, en} массива или вернуть как есть. */
    protected function localized(mixed $value, string $locale): ?string
    {
        if (is_string($value) || $value === null) {
            return $value;
        }
        if (is_array($value)) {
            return $value[$locale] ?? $value['kk'] ?? $value['ru'] ?? $value['en'] ?? null;
        }

        return (string) $value;
    }
}
