<?php

namespace Tests\Unit;

use Meta\AdminCore\Http\Controllers\ResourceController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Правила валидации полей формы ресурса.
 *
 * Поводом стали три случая на живом проекте:
 *   • `type => 'array'` (повторяющаяся группа) улетал в ветку по умолчанию
 *     и валидировался как строка — сохранить список было нельзя;
 *   • `type => 'image'` там же превращался в string|max:500, а путь к файлу
 *     с длинным именем в это не всегда влезает;
 *   • у `textarea` действовал тот же лимит 500 знаков, что и у однострочного
 *     поля, поэтому длинный список в форме молча не сохранялся.
 */
class ResourceFieldRulesTest extends TestCase
{
    private function ruleFor(array $attribute): string
    {
        $method = new ReflectionMethod(ResourceController::class, 'ruleForAttribute');

        return $method->invoke($method->getDeclaringClass()->newInstanceWithoutConstructor(), $attribute);
    }

    public function test_array_attribute_is_validated_as_array(): void
    {
        $this->assertSame('nullable|array', $this->ruleFor(['name' => 'team', 'type' => 'array']));
        $this->assertSame('required|array', $this->ruleFor(['name' => 'team', 'type' => 'array', 'required' => true]));
    }

    public function test_image_and_file_accept_long_paths(): void
    {
        $this->assertSame('nullable|string|max:2000', $this->ruleFor(['name' => 'cover', 'type' => 'image']));
        $this->assertSame('nullable|string|max:2000', $this->ruleFor(['name' => 'doc', 'type' => 'file']));
    }

    public function test_textarea_is_not_capped_at_single_line_limit(): void
    {
        $this->assertSame('nullable|string|max:20000', $this->ruleFor(['name' => 'notes', 'type' => 'textarea']));
        // явный max по-прежнему уважается
        $this->assertSame('nullable|string|max:300', $this->ruleFor(['name' => 'notes', 'type' => 'textarea', 'max' => 300]));
    }

    public function test_plain_text_keeps_its_previous_limit(): void
    {
        $this->assertSame('nullable|string|max:500', $this->ruleFor(['name' => 'title', 'type' => 'text']));
    }
}
