<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProducerDirectoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active', 'pending_review', 'needs_completion', 'rejected'])],
        ]);

        $entities = Entity::query()
            ->with(['group', 'users'])
            ->whereNull('deleted_at')
            ->whereIn('registration_type', ['student', 'company', 'ngo', 'school'])
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['q']);
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('name_en', 'like', '%'.$search.'%')
                        ->orWhere('name_ar', 'like', '%'.$search.'%')
                        ->orWhere('registration_no', 'like', '%'.$search.'%')
                        ->orWhere('national_id', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhereHas('users', fn (Builder $userQuery): Builder => $userQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%'));
                });
            })
            ->when(($filters['status'] ?? 'all') !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByRaw("case when status = 'pending_review' then 0 when status = 'needs_completion' then 1 when status = 'rejected' then 2 else 3 end")
            ->latest('created_at')
            ->get();

        $groupedEntities = collect(['student', 'company', 'ngo', 'school'])
            ->mapWithKeys(fn (string $type): array => [
                $type => $entities
                    ->filter(fn (Entity $entity): bool => $entity->registration_type === $type)
                    ->values(),
            ]);

        return view('admin.producers.index', [
            'groupedEntities' => $groupedEntities,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
        ]);
    }
}
