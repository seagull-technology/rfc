<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FilmingLocationType;
use App\Models\Governorate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FilmingLocationLookupController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);

        $governorates = $this->filteredGovernorateQuery($filters)
            ->ordered()
            ->get();
        $locationTypes = $this->filteredLocationTypeQuery($filters)
            ->with(['governorates' => fn ($query) => $query->ordered()])
            ->ordered()
            ->get();
        $allGovernorates = Governorate::query()
            ->ordered()
            ->get();

        return view('admin.filming-location-lookups.index', [
            'governorates' => $governorates,
            'locationTypes' => $locationTypes,
            'allGovernorates' => $allGovernorates,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'governorates' => Governorate::query()->count(),
                'active_governorates' => Governorate::query()->active()->count(),
                'location_types' => FilmingLocationType::query()->count(),
                'active_location_types' => FilmingLocationType::query()->active()->count(),
            ],
        ]);
    }

    public function storeGovernorate(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->governorateRules());
        $code = $this->normalizedCode(($validated['code'] ?? null) ?: $validated['name_en']);

        if ($code === '' || Governorate::query()->where('code', $code)->exists()) {
            return back()
                ->withErrors(['code' => __('app.admin.filming_location_lookups.code_taken')])
                ->withInput();
        }

        Governorate::query()->create([
            'code' => $code,
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'sort_order' => (int) ($validated['sort_order'] ?? 500),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.filming-location-lookups.index')
            ->with('status', __('app.admin.filming_location_lookups.governorate_created'));
    }

    public function updateGovernorate(Request $request, Governorate $governorate): RedirectResponse
    {
        $validated = $request->validate($this->governorateRules(requireCode: false, requireSortOrder: true));

        $governorate->forceFill([
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return redirect()
            ->route('admin.filming-location-lookups.index', $request->only(['q', 'status']))
            ->with('status', __('app.admin.filming_location_lookups.governorate_updated'));
    }

    public function updateGovernorateStatus(Request $request, Governorate $governorate): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $governorate->forceFill([
            'is_active' => (bool) $validated['is_active'],
        ])->save();

        return redirect()
            ->route('admin.filming-location-lookups.index', $request->only(['q', 'status']))
            ->with('status', $governorate->is_active
                ? __('app.admin.filming_location_lookups.governorate_activated')
                : __('app.admin.filming_location_lookups.governorate_deactivated'));
    }

    public function storeLocationType(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->locationTypeRules());
        $code = $this->normalizedCode(($validated['code'] ?? null) ?: $validated['name_en']);

        if ($code === '' || FilmingLocationType::query()->where('code', $code)->exists()) {
            return back()
                ->withErrors(['code' => __('app.admin.filming_location_lookups.code_taken')])
                ->withInput();
        }

        $locationType = FilmingLocationType::query()->create([
            'code' => $code,
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'sort_order' => (int) ($validated['sort_order'] ?? 500),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $locationType->governorates()->sync($this->governorateIds((array) $validated['governorates']));

        return redirect()
            ->route('admin.filming-location-lookups.index')
            ->with('status', __('app.admin.filming_location_lookups.location_type_created'));
    }

    public function updateLocationType(Request $request, FilmingLocationType $locationType): RedirectResponse
    {
        $validated = $request->validate($this->locationTypeRules(requireCode: false, requireSortOrder: true));

        $locationType->forceFill([
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ])->save();
        $locationType->governorates()->sync($this->governorateIds((array) $validated['governorates']));

        return redirect()
            ->route('admin.filming-location-lookups.index', $request->only(['q', 'status']))
            ->with('status', __('app.admin.filming_location_lookups.location_type_updated'));
    }

    public function updateLocationTypeStatus(Request $request, FilmingLocationType $locationType): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $locationType->forceFill([
            'is_active' => (bool) $validated['is_active'],
        ])->save();

        return redirect()
            ->route('admin.filming-location-lookups.index', $request->only(['q', 'status']))
            ->with('status', $locationType->is_active
                ? __('app.admin.filming_location_lookups.location_type_activated')
                : __('app.admin.filming_location_lookups.location_type_deactivated'));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredGovernorateQuery(array $filters): Builder
    {
        $query = Governorate::query();

        return $this->applyLookupFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredLocationTypeQuery(array $filters): Builder
    {
        $query = FilmingLocationType::query();

        return $this->applyLookupFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyLookupFilters(Builder $query, array $filters): Builder
    {
        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name_en', 'like', '%'.$search.'%')
                    ->orWhere('name_ar', 'like', '%'.$search.'%');
            });
        }

        if (($filters['status'] ?? 'all') === 'active') {
            $query->active();
        } elseif (($filters['status'] ?? 'all') === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function governorateRules(bool $requireCode = false, bool $requireSortOrder = false): array
    {
        return [
            'code' => [$requireCode ? 'required' : 'nullable', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'sort_order' => [$requireSortOrder ? 'required' : 'nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function locationTypeRules(bool $requireCode = false, bool $requireSortOrder = false): array
    {
        return [
            'code' => [$requireCode ? 'required' : 'nullable', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'sort_order' => [$requireSortOrder ? 'required' : 'nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
            'governorates' => ['required', 'array', 'min:1'],
            'governorates.*' => [Rule::in(Governorate::query()->pluck('code')->all())],
        ];
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, int>
     */
    private function governorateIds(array $codes): array
    {
        return Governorate::query()
            ->whereIn('code', $codes)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function normalizedCode(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
