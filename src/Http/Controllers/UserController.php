<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin user management — list, create, update, delete users and
 * sync their spatie role. Model-agnostic: resolves the user class
 * via config('auth.providers.users.model') so consumers can keep
 * their own User Eloquent with site-specific traits.
 *
 * spatie/laravel-permission is a soft dependency — the same strategy
 * as PermissionsController. Without it the panel 404s with a hint.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireSpatie();

        /** @var class-string<Model> $userModel */
        $userModel = $this->userModel();
        /** @var class-string $roleModel */
        $roleModel = config('permission.models.role', \Spatie\Permission\Models\Role::class);

        $filters = $request->only(['search', 'role']);

        $query = $userModel::query()->with('roles')->orderBy('name');
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }
        if (!empty($filters['role'])) {
            $role = $filters['role'];
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        return Inertia::render('Users/Index', [
            'title'         => 'Пользователи',
            'users'         => $query->get()->map(fn ($u) => $this->presentUser($u)),
            'roles'         => $roleModel::orderBy('name')->get(['id', 'name'])->map(fn ($r) => [
                'value' => $r->name,
                'label' => ucfirst($r->name),
            ]),
            'filters'       => $filters,
            'currentUserId' => auth()->id(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireSpatie();
        $userModel = $this->userModel();

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|exists:roles,name',
        ]);

        $user = $userModel::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
        $user->assignRole($data['role']);

        return back()->with('success', "Пользователь {$user->name} создан");
    }

    public function updateRole(Request $request, $user): RedirectResponse
    {
        $this->requireSpatie();
        $user = $this->resolveUser($user);

        $data = $request->validate(['role' => 'required|exists:roles,name']);
        $user->syncRoles([$data['role']]);

        return back()->with('success', "Роль обновлена на «{$data['role']}»");
    }

    public function update(Request $request, $user): RedirectResponse
    {
        $this->requireSpatie();
        $user = $this->resolveUser($user);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'role'     => 'required|exists:roles,name',
            'password' => 'nullable|string|min:8',
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];
        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }
        $user->save();
        $user->syncRoles([$data['role']]);

        return back()->with('success', "Пользователь {$user->name} обновлён");
    }

    public function destroy($user): RedirectResponse
    {
        $user = $this->resolveUser($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Нельзя удалить самого себя');
        }
        $user->delete();
        return back()->with('success', 'Пользователь удалён');
    }

    protected function presentUser(Model $u): array
    {
        return [
            'id'             => $u->id,
            'name'           => $u->name,
            'email'          => $u->email,
            'email_verified' => (bool) $u->email_verified_at,
            'role'           => optional($u->roles->first())->name,
            'created_at'     => optional($u->created_at)->format('d.m.Y'),
        ];
    }

    protected function userModel(): string
    {
        return config('auth.providers.users.model', \App\Models\User::class);
    }

    protected function resolveUser($user): Model
    {
        if ($user instanceof Model) return $user;
        $userModel = $this->userModel();
        return $userModel::findOrFail($user);
    }

    protected function requireSpatie(): void
    {
        abort_unless(
            class_exists(\Spatie\Permission\Models\Role::class),
            404,
            'spatie/laravel-permission is required to manage users and roles.'
        );
    }
}
