<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permit;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PermitRegistryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->directoryFilters($request);
        $permits = $this->directoryQuery($filters)
            ->latest('issued_at')
            ->get();

        return view('admin.permits.index', [
            'permits' => $permits,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'total' => $permits->count(),
                'active' => $permits->where('status', 'active')->count(),
                'issued_this_month' => $permits->filter(fn (Permit $permit): bool => $permit->issued_at?->isSameMonth(now()) ?? false)->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->directoryFilters($request);
        $permits = $this->directoryQuery($filters)
            ->latest('issued_at')
            ->get();

        $rows = $permits->map(fn (Permit $permit): array => [
            $permit->permit_number,
            $permit->application?->code ?? '',
            $permit->application?->project_name ?? '',
            $permit->entity?->displayName() ?? '',
            $permit->localizedStatus(),
            $permit->issued_at?->format('Y-m-d H:i') ?? '',
            $permit->issuedBy?->displayName() ?? '',
        ])->all();

        return CsvExport::download(
            filename: 'permit-registry-'.now()->format('Ymd-His').'.csv',
            headers: [
                __('app.permits.permit_number'),
                __('app.admin.applications.application'),
                __('app.applications.project_name'),
                __('app.admin.applications.entity'),
                __('app.permits.status'),
                __('app.permits.issued_at'),
                __('app.final_decision.issued_by'),
            ],
            rows: $rows,
        );
    }

    public function show(Permit $permit): View
    {
        return view('admin.permits.show', [
            'permit' => $permit->load(['application.entity', 'application.submittedBy', 'issuedBy', 'audits.user']),
        ]);
    }

    /**
     * @return array{q:string,status:string}
     */
    private function directoryFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active'])],
        ]);

        return [
            'q' => $validated['q'] ?? '',
            'status' => $validated['status'] ?? 'all',
        ];
    }

    /**
     * @param  array{q:string,status:string}  $filters
     */
    private function directoryQuery(array $filters): Builder
    {
        $query = Permit::query()->with(['application.submittedBy', 'entity', 'issuedBy']);

        if (filled($filters['q'])) {
            $search = trim($filters['q']);

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('permit_number', 'like', '%'.$search.'%')
                    ->orWhereHas('application', fn (Builder $applicationQuery): Builder => $applicationQuery
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('project_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('entity', fn (Builder $entityQuery): Builder => $entityQuery
                        ->where('name_en', 'like', '%'.$search.'%')
                        ->orWhere('name_ar', 'like', '%'.$search.'%'));
            });
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }
}
