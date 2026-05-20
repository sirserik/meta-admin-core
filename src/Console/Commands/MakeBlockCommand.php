<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeBlockCommand extends Command
{
    protected $signature = 'core:make-block
                            {name : Имя класса блока (PascalCase, например Pricing — даст PricingBlock)}
                            {--handle= : Handle блока (snake-case, по умолчанию kebab→snake от name)}
                            {--label= : Человекочитаемый лейбл для админки}
                            {--force : Перезаписать существующие файлы}';

    protected $description = 'Скаффолдинг кастомного блока: класс App\\Blocks\\{Name}Block + Blade-шаблон + хинт по регистрации';

    public function handle(Filesystem $files): int
    {
        $name   = Str::studly($this->argument('name'));
        if (! Str::endsWith($name, 'Block')) {
            $name .= 'Block';
        }
        $class  = $name;

        $handle = $this->option('handle')
            ?: Str::snake(Str::replaceLast('Block', '', $class));

        $label  = $this->option('label') ?: ($class . ' — кастомный блок');
        $force  = (bool) $this->option('force');

        // 1. PHP class
        $classPath = app_path("Blocks/{$class}.php");
        if ($files->exists($classPath) && ! $force) {
            $this->error("File exists: {$classPath} (use --force to overwrite)");
            return self::FAILURE;
        }
        $files->ensureDirectoryExists(dirname($classPath));
        $stub = $files->get(__DIR__ . '/../../../stubs/block.stub');
        $files->put($classPath, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ handle }}', '{{ label }}'],
            ['App\\Blocks',       $class,       $handle,       $label],
            $stub
        ));
        $this->info("✓ Created class: {$classPath}");

        // 2. Blade view
        $viewPath = resource_path("views/blocks/v2/{$handle}.blade.php");
        if ($files->exists($viewPath) && ! $force) {
            $this->warn("View exists, skipped: {$viewPath} (use --force to overwrite)");
        } else {
            $files->ensureDirectoryExists(dirname($viewPath));
            $stub = $files->get(__DIR__ . '/../../../stubs/block-view.stub');
            $files->put($viewPath, str_replace(['{{ class }}'], [$class], $stub));
            $this->info("✓ Created view:  {$viewPath}");
        }

        // 3. Registration hint
        $this->newLine();
        $this->line("Регистрация в <fg=yellow>app/Providers/AppServiceProvider.php::boot()</>:");
        $this->newLine();
        $this->line("    use Meta\\AdminCore\\Facades\\AdminCore;");
        $this->line("    use App\\Blocks\\{$class};");
        $this->newLine();
        $this->line("    AdminCore::registerBlock(new {$class});");
        $this->newLine();
        $this->line("После регистрации блок появится в админ-палитре (handle: <fg=cyan>{$handle}</>).");

        return self::SUCCESS;
    }
}
