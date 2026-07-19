<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReleaseMethod;
use App\Models\WorkCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkAndReleaseLookupController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);

        $workCategories = $this->applyLookupFilters(WorkCategory::query(), $filters)
            ->ordered()
            ->get();
        $releaseMethods = $this->applyLookupFilters(ReleaseMethod::query(), $filters)
            ->ordered()
            ->get();

        return view('admin.work-release-lookups.index', [
            'workCategories' => $workCategories,
            'releaseMethods' => $releaseMethods,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'work_categories' => WorkCategory::query()->count(),
                'active_work_categories' => WorkCategory::query()->active()->count(),
                'release_methods' => ReleaseMethod::query()->count(),
                'active_release_methods' => ReleaseMethod::query()->active()->count(),
            ],
        ]);
    }

    public function storeWorkCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->lookupRules(includeWorkSummaryWords: true));
        $code = $this->normalizedCode(($validated['code'] ?? null) ?: $validated['name_en']);

        if ($code === '' || WorkCategory::query()->where('code', $code)->exists()) {
            return back()
                ->withErrors(['code' => __('app.admin.work_release_lookups.code_taken')])
                ->withInput();
        }

        WorkCategory::query()->create([
            'code' => $code,
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'work_summary_min_words' => (int) $validated['work_summary_min_words'],
            'sort_order' => (int) ($validated['sort_order'] ?? 500),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.work-release-lookups.index')
            ->with('status', __('app.admin.work_release_lookups.work_category_created'));
    }

    public function updateWorkCategory(Request $request, WorkCategory $workCategory): RedirectResponse
    {
        $validated = $request->validate($this->lookupRules(
            requireCode: false,
            requireSortOrder: true,
            includeWorkSummaryWords: true,
        ));

        $workCategory->forceFill([
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'work_summary_min_words' => (int) $validated['work_summary_min_words'],
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return redirect()
            ->route('admin.work-release-lookups.index', $request->only(['q', 'status']))
            ->with('status', __('app.admin.work_release_lookups.work_category_updated'));
    }

    public function updateWorkCategoryStatus(Request $request, WorkCategory $workCategory): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $workCategory->forceFill([
            'is_active' => (bool) $validated['is_active'],
        ])->save();

        return redirect()
            ->route('admin.work-release-lookups.index', $request->only(['q', 'status']))
            ->with('status', $workCategory->is_active
                ? __('app.admin.work_release_lookups.work_category_activated')
                : __('app.admin.work_release_lookups.work_category_deactivated'));
    }

    public function storeReleaseMethod(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->lookupRules());
        $code = $this->normalizedCode(($validated['code'] ?? null) ?: $validated['name_en']);

        if ($code === '' || ReleaseMethod::query()->where('code', $code)->exists()) {
            return back()
                ->withErrors(['code' => __('app.admin.work_release_lookups.code_taken')])
                ->withInput();
        }

        ReleaseMethod::query()->create([
            'code' => $code,
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'sort_order' => (int) ($validated['sort_order'] ?? 500),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.work-release-lookups.index')
            ->with('status', __('app.admin.work_release_lookups.release_method_created'));
    }

    public function updateReleaseMethod(Request $request, ReleaseMethod $releaseMethod): RedirectResponse
    {
        $validated = $request->validate($this->lookupRules(requireCode: false, requireSortOrder: true));

        $releaseMethod->forceFill([
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return redirect()
            ->route('admin.work-release-lookups.index', $request->only(['q', 'status']))
            ->with('status', __('app.admin.work_release_lookups.release_method_updated'));
    }

    public function updateReleaseMethodStatus(Request $request, ReleaseMethod $releaseMethod): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $releaseMethod->forceFill([
            'is_active' => (bool) $validated['is_active'],
        ])->save();

        return redirect()
            ->route('admin.work-release-lookups.index', $request->only(['q', 'status']))
            ->with('status', $releaseMethod->is_active
                ? __('app.admin.work_release_lookups.release_method_activated')
                : __('app.admin.work_release_lookups.release_method_deactivated'));
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
    private function lookupRules(bool $requireCode = false, bool $requireSortOrder = false, bool $includeWorkSummaryWords = false): array
    {
        $rules = [
            'code' => [$requireCode ? 'required' : 'nullable', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'sort_order' => [$requireSortOrder ? 'required' : 'nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($includeWorkSummaryWords) {
            $rules['work_summary_min_words'] = ['required', 'integer', 'min:1', 'max:5000'];
        }

        return $rules;
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
