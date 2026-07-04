<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GroupManagementController extends Controller
{
    public function index(Request $request): View
    {
        $roleNames = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(collect(['all'])->merge($roleNames)->all())],
        ]);

        $query = Group::query()
            ->with('roles.permissions')
            ->withCount('entities');

        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name_en', 'like', '%'.$search.'%')
                    ->orWhere('name_ar', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if (($filters['role'] ?? 'all') !== 'all') {
            $query->whereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery->where('name', $filters['role']));
        }

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        return view('admin.groups.index', [
            'groups' => $query
                ->orderBy('id')
                ->get(),
            'permissionGroups' => $permissions
                ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString()),
            'roles' => $roleNames,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'role' => $filters['role'] ?? 'all',
            ],
        ]);
    }

    public function updateRolePermissions(Request $request, string $role): RedirectResponse
    {
        $roleRecord = Role::query()
            ->where('guard_name', 'web')
            ->where('name', $role)
            ->firstOrFail();

        if ($roleRecord->name === 'super_admin') {
            throw ValidationException::withMessages([
                'role' => __('app.admin.groups.super_admin_locked'),
            ]);
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ]);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', collect($validated['permissions'] ?? [])->filter()->unique()->values())
            ->get();

        DB::transaction(function () use ($roleRecord, $permissions): void {
            $roleRecord->syncPermissions($permissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.groups.index', $request->only(['q', 'role']))
            ->with('status', __('app.admin.groups.permissions_updated', [
                'role' => __('app.roles.'.$roleRecord->name),
            ]));
    }
}
