<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['all', 'super_admin', 'platform_admin', 'moderator', 'reporter', 'rfc_admin', 'rfc_intake_officer', 'rfc_reviewer', 'rfc_approver', 'authority_reviewer', 'authority_approver', 'applicant_owner', 'applicant_member'])],
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

        return view('admin.groups.index', [
            'groups' => $query
                ->orderBy('id')
                ->get(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'role' => $filters['role'] ?? 'all',
            ],
        ]);
    }
}
