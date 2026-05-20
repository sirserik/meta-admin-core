<?php

namespace Meta\AdminCore\Blocks;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BlockRegistry
{
    /** @var array<string, BlockDefinition> */
    protected array $blocks = [];

    public function register(BlockDefinition $block): void
    {
        $this->blocks[$block->handle()] = $block;
    }

    public function get(string $handle): ?BlockDefinition
    {
        return $this->blocks[$handle] ?? null;
    }

    public function has(string $handle): bool
    {
        return isset($this->blocks[$handle]);
    }

    /** @return array<string, BlockDefinition> */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * Группировка по категориям для admin-палитры.
     *
     * @return array<string, BlockDefinition[]>
     */
    public function byCategory(): array
    {
        $out = [];
        foreach ($this->blocks as $block) {
            $out[$block->category()][] = $block;
        }

        return $out;
    }

    /**
     * Рендер блока по handle. Возвращает HTML или пустую строку если блок не найден.
     * В production (не debug) молча пропускает неизвестные блоки и логирует warning.
     */
    public function render(string $handle, array $data, ?string $locale = null): string
    {
        $block = $this->get($handle);
        if (! $block) {
            Log::warning("BlockRegistry: unknown handle '{$handle}' — rendered empty.");

            return '';
        }

        $locale = $locale ?? app()->getLocale();

        try {
            return $block->render($data, $locale);
        } catch (\Throwable $e) {
            Log::error("BlockRegistry: render error for '{$handle}'", [
                'exception' => $e->getMessage(),
                'data' => $data,
            ]);
            if (config('app.debug')) {
                throw $e;
            }

            return '';
        }
    }

    /**
     * Валидация data против schema. Бросает ValidationException на ошибках.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(string $handle, array $data): array
    {
        $block = $this->get($handle);
        if (! $block) {
            throw new \InvalidArgumentException("Unknown block handle: {$handle}");
        }

        // Оборачиваем data в массив под ключом 'data' чтобы правила 'data.foo' работали
        $validated = Validator::make(['data' => $data], $block->validationRules())->validate();

        return $validated['data'] ?? [];
    }
}
