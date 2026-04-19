<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Facades\AdminCore;

/**
 * Role × resource × action permission matrix.
 *
 * Depends on spatie/laravel-permission being installed in the
 * consumer — every Laravel-based CMS already has it and adding it to
 * admin-core as a hard dep would be a step in the wrong direction
 * for sites that don't need RBAC. The controller gracefully 404s when
 * the classes aren't there.
 *
 * Permission strings follow the `{resource}.{action}` convention
 * (`articles.view`, `articles.update`, `blocks.publish`…) — any
 * permission that already matches a registered resource shows up in
 * the matrix; anything else is preserved but not rendered here.
 */
class PermissionsController extends Controller
{
    /** Actions shown as matrix columns. */
    protected const ACTIONS = ['view', 'create', 'update', 'delete', 'publish'];

    public function index(): Response
    {
        $this->requireSpatie();

        /** @var class-string $roleModel */
        $roleModel = config('permission.models.role', \Spatie\Permission\Models\Role::class);
        /** @var class-string $permModel */
        $permModel = config('permission.models.permission', \Spatie\Permission\Models\Permission::class);

        $resources = collect(AdminCore::getResources())
            ->map(fn ($cfg, $name) => [
                'name'  => $name,
                'label' => $cfg['label'] ?? ucfirst((string) $name),
                'menu'  => $cfg['menu']  ?? 'Другое',
            ])
            ->values()
            ->all();

        // Make sure every resource×action permission exists — one-shot
        // idempotent backfill so the matrix has full coverage even for
        // fresh resources added after the last deploy.
        foreach ($resources as $r) {
            foreach (self::ACTIONS as $a) {
                $permModel::firstOrCreate([
                    'name'       => "{$r['name']}.{$a}",
                    'guard_name' => 'web',
                ]);
            }
        }

        $roles = $roleModel::with('permissions:id,name')
            ->get(['id', 'name'])
            ->map(fn ($role) => [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
            ]);

        return Inertia::render('Permissions/Matrix', [
            'title'     => 'Права доступа',
            'resources' => $resources,
            'actions'   => self::ACTIONS,
            'roles'     => $roles,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->requireSpatie();

        $data = $request->validate([
            'role'        => 'required|string',
            'permissions' => 'array',
            'permissions.*' => 'string',
        ]);

        /** @var class-string $roleModel */
        $roleModel = config('permission.models.role', \Spatie\Permission\Models\Role::class);
        $role = $roleModel::where('name', $data['role'])->firstOrFail();

        // Only sync permissions that follow the resource.action shape —
        // leaves untouched any custom permission a consumer assigned
        // outside the matrix.
        $registered = [];
        foreach (AdminCore::getResources()->keys() as $resName) {
            foreach (self::ACTIONS as $a) {
                $registered[] = "{$resName}.{$a}";
            }
        }

        $current     = $role->permissions->pluck('name')->all();
        $outsideMatrix = array_values(array_diff($current, $registered));
        $requested   = array_values(array_intersect($registered, $data['permissions'] ?? []));
        $merged      = array_values(array_unique(array_merge($outsideMatrix, $requested)));

        $role->syncPermissions($merged);

        return back()->with('success', "Права роли «{$role->name}» обновлены.");
    }

    protected function requireSpatie(): void
    {
        abort_unless(
            class_exists(\Spatie\Permission\Models\Role::class),
            404,
            'spatie/laravel-permission is required to use the permissions matrix.'
        );
    }
}
