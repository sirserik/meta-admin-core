<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Create (or promote) an admin user from the CLI — handy on a fresh deploy
 * before any UI exists. Uses the configured auth user model. If
 * spatie/laravel-permission is present, assigns `--role` (default: the first
 * of `admin-core.admin_roles`), creating the role if missing.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'admin-core:make-admin
                            {email : Email пользователя}
                            {--name=Администратор : Имя}
                            {--password= : Пароль (если не указан — будет сгенерирован)}
                            {--role= : Роль (spatie); по умолчанию первая из admin-core.admin_roles}';

    protected $description = 'Создать/повысить администратора из CLI (модель из auth.providers.users.model, опц. роль spatie)';

    public function handle(): int
    {
        $model = config('auth.providers.users.model');
        if (! $model || ! class_exists($model)) {
            $this->error('Не найдена модель пользователя (auth.providers.users.model).');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $password = (string) ($this->option('password') ?: \Illuminate\Support\Str::password(16));

        $user = $model::where('email', $email)->first();
        if ($user) {
            $this->warn("Пользователь {$email} уже существует — обновляю пароль/роль.");
            $user->forceFill(['password' => Hash::make($password)])->save();
        } else {
            $user = $model::create([
                'name'              => (string) $this->option('name'),
                'email'             => $email,
                'password'          => Hash::make($password),
                'email_verified_at' => now(),
            ]);
        }

        if (method_exists($user, 'assignRole')) {
            $roles = (array) config('admin-core.admin_roles', ['admin']);
            $role = (string) ($this->option('role') ?: ($roles[0] ?? 'admin'));
            $roleModel = config('permission.models.role', \Spatie\Permission\Models\Role::class);
            if (class_exists($roleModel)) {
                $roleModel::findOrCreate($role, $user->guard_name ?? config('auth.defaults.guard', 'web'));
            }
            $user->assignRole($role);
            $this->line("   Роль: {$role}");
        }

        $this->info('✅ Администратор готов:');
        $this->line("   Email: {$user->email}");
        $this->line("   Пароль: {$password}");

        return self::SUCCESS;
    }
}
