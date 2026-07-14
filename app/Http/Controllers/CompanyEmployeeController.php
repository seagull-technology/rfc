<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\User;
use App\Models\UserRoleAssignmentAudit;
use App\Services\RoleAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CompanyEmployeeController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const ASSIGNABLE_ROLES = [
        'company_admin',
        'company_manager',
        'company_creator',
        'company_viewer',
    ];

    public function __construct(
        private readonly RoleAssignmentService $roleAssignmentService,
    ) {}

    public function index(Request $request): View
    {
        [$user, $entity] = $this->companyContext($request);

        abort_unless($user->canViewCompanyEmployees($entity), 403);

        $members = $entity->users()
            ->orderByDesc('entity_user.is_primary')
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $member): array => [
                'user' => $member,
                'roles' => $member->roleNamesForEntity($entity)
                    ->filter(fn (string $roleName): bool => in_array($roleName, self::ASSIGNABLE_ROLES, true) || $roleName === 'applicant_owner')
                    ->values(),
            ]);

        return view('company.employees.index', [
            'user' => $user,
            'entity' => $entity,
            'members' => $members,
            'roles' => $this->companyRoles(),
            'canManageEmployees' => $user->canManageCompanyEmployees($entity),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$actor, $entity] = $this->companyContext($request);

        abort_unless($actor->canManageCompanyEmployees($entity), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255', 'unique:users,phone'],
            'national_id' => ['nullable', 'string', 'max:255', 'unique:users,national_id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $member = DB::transaction(function () use ($actor, $entity, $validated): User {
            $member = User::query()->create([
                'name' => $validated['name'],
                'username' => $this->uniqueUsername($validated['email']),
                'email' => $validated['email'],
                'national_id' => $validated['national_id'] ?: null,
                'phone' => $validated['phone'] ?: null,
                'status' => 'active',
                'registration_type' => 'company',
                'password' => Hash::make($validated['password']),
            ]);

            $member->entities()->attach($entity->getKey(), [
                'job_title' => $validated['job_title'] ?: null,
                'is_primary' => false,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $this->assignCompanyRole($member, $entity, $validated['role'], $actor->getKey());

            return $member;
        });

        return redirect()
            ->route('company.employees.index')
            ->with('status', __('app.company.employees.created', ['name' => $member->displayName()]));
    }

    public function update(Request $request, string $member): RedirectResponse
    {
        [$actor, $entity] = $this->companyContext($request);

        abort_unless($actor->canManageCompanyEmployees($entity), 403);

        $member = $this->findCompanyMember($entity, $member);
        $this->ensureCompanyCanManageMember($actor, $member, $entity);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($member->getKey())],
            'national_id' => ['nullable', 'string', 'max:255', Rule::unique('users', 'national_id')->ignore($member->getKey())],
            'job_title' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        DB::transaction(function () use ($actor, $entity, $member, $validated): void {
            $member->forceFill([
                'name' => $validated['name'],
                'national_id' => $validated['national_id'] ?: null,
                'phone' => $validated['phone'] ?: null,
            ]);

            if (filled($validated['password'] ?? null)) {
                $member->password = Hash::make($validated['password']);
            }

            $member->save();

            $entity->users()->updateExistingPivot($member->getKey(), [
                'job_title' => $validated['job_title'] ?: null,
            ]);

            $this->syncCompanyRole($member, $entity, $validated['role'], $actor->getKey());
        });

        return redirect()
            ->route('company.employees.index')
            ->with('status', __('app.company.employees.updated', ['name' => $member->displayName()]));
    }

    public function updateStatus(Request $request, string $member): RedirectResponse
    {
        [$actor, $entity] = $this->companyContext($request);

        abort_unless($actor->canManageCompanyEmployees($entity), 403);

        $member = $this->findCompanyMember($entity, $member);
        $this->ensureCompanyCanManageMember($actor, $member, $entity);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $entity->users()->updateExistingPivot($member->getKey(), [
            'status' => $validated['status'],
            'left_at' => $validated['status'] === 'inactive' ? now() : null,
        ]);

        return redirect()
            ->route('company.employees.index')
            ->with('status', __('app.company.employees.status_updated'));
    }

    /**
     * @return array{0: User, 1: Entity}
     */
    private function companyContext(Request $request): array
    {
        $user = $request->user();
        $entity = $user?->primaryEntity();

        abort_unless($user && $entity, 404);
        abort_unless($entity->registration_type === 'company', 403);

        return [$user, $entity];
    }

    private function findCompanyMember(Entity $entity, string $member): User
    {
        return $entity->users()
            ->whereKey($member)
            ->firstOrFail();
    }

    private function ensureCompanyCanManageMember(User $actor, User $member, Entity $entity): void
    {
        abort_if((int) $actor->getKey() === (int) $member->getKey(), 403);

        $membership = $member->entities()->whereKey($entity->getKey())->firstOrFail()->pivot;

        abort_if((bool) ($membership?->is_primary ?? false), 403);
    }

    /**
     * @return Collection<int, array{name:string,label:string,description:string}>
     */
    private function companyRoles(): Collection
    {
        return collect(self::ASSIGNABLE_ROLES)
            ->map(fn (string $roleName): array => [
                'name' => $roleName,
                'label' => __('app.roles.'.$roleName),
                'description' => __('app.company.employees.role_descriptions.'.$roleName),
            ]);
    }

    private function syncCompanyRole(User $member, Entity $entity, string $roleName, int $changedByUserId): void
    {
        foreach (self::ASSIGNABLE_ROLES as $existingRole) {
            $hadRole = $member->roleNamesForEntity($entity)->contains($existingRole);

            if ($existingRole !== $roleName && $hadRole) {
                $this->roleAssignmentService->removeFromEntity($member, $entity, $existingRole);
                $this->logRoleAssignmentAudit($member, $entity, $existingRole, 'removed', $changedByUserId);
            }
        }

        $this->assignCompanyRole($member, $entity, $roleName, $changedByUserId);
    }

    private function assignCompanyRole(User $member, Entity $entity, string $roleName, int $changedByUserId): void
    {
        $hadRole = $member->roleNamesForEntity($entity)->contains($roleName);

        $this->roleAssignmentService->assignToEntity($member, $entity, $roleName);

        if (! $hadRole) {
            $this->logRoleAssignmentAudit($member, $entity, $roleName, 'added', $changedByUserId);
        }
    }

    private function logRoleAssignmentAudit(User $user, Entity $entity, string $roleName, string $action, ?int $changedByUserId): void
    {
        UserRoleAssignmentAudit::query()->create([
            'user_id' => $user->getKey(),
            'entity_id' => $entity->getKey(),
            'changed_by_user_id' => $changedByUserId,
            'role_name' => $roleName,
            'action' => $action,
        ]);
    }

    private function uniqueUsername(string $email): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->limit(40, '')
            ->toString() ?: 'company_user';

        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $username = $base.'_'.$suffix;
            $suffix++;
        }

        return $username;
    }
}
