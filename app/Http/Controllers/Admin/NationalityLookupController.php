<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Nationality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NationalityLookupController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'usage' => ['nullable', Rule::in(['all', 'project', 'director', 'international_producer'])],
        ]);

        $query = Nationality::query()->ordered();

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

        if (($filters['usage'] ?? 'all') !== 'all') {
            $query->forUsage((string) $filters['usage']);
        }

        return view('admin.nationalities.index', [
            'nationalities' => $query->paginate(80)->withQueryString(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
                'usage' => $filters['usage'] ?? 'all',
            ],
            'stats' => [
                'total' => Nationality::query()->count(),
                'active' => Nationality::query()->active()->count(),
                'project' => Nationality::query()->active()->forProject()->count(),
                'director' => Nationality::query()->active()->forDirector()->count(),
                'international_producer' => Nationality::query()->active()->forInternationalProducer()->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'available_for_project' => ['nullable', 'boolean'],
            'available_for_director' => ['nullable', 'boolean'],
            'available_for_international_producer' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $code = $this->normalizedCode(($validated['code'] ?? null) ?: $validated['name_en']);

        if ($code === '' || Nationality::query()->where('code', $code)->exists()) {
            return back()
                ->withErrors(['code' => __('app.admin.nationalities.code_taken')])
                ->withInput();
        }

        Nationality::query()->create([
            'code' => $code,
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'sort_order' => (int) ($validated['sort_order'] ?? 500),
            'is_active' => $request->boolean('is_active', true),
            'available_for_project' => $request->boolean('available_for_project'),
            'available_for_director' => $request->boolean('available_for_director'),
            'available_for_international_producer' => $request->boolean('available_for_international_producer'),
        ]);

        return redirect()
            ->route('admin.nationalities.index')
            ->with('status', __('app.admin.nationalities.created'));
    }

    public function update(Request $request, Nationality $nationality): RedirectResponse
    {
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'available_for_project' => ['nullable', 'boolean'],
            'available_for_director' => ['nullable', 'boolean'],
            'available_for_international_producer' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $nationality->forceFill([
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
            'available_for_project' => $request->boolean('available_for_project'),
            'available_for_director' => $request->boolean('available_for_director'),
            'available_for_international_producer' => $request->boolean('available_for_international_producer'),
        ])->save();

        return redirect()
            ->route('admin.nationalities.index', $request->only(['q', 'status', 'usage']))
            ->with('status', __('app.admin.nationalities.updated'));
    }

    public function updateStatus(Request $request, Nationality $nationality): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $nationality->forceFill([
            'is_active' => (bool) $validated['is_active'],
        ])->save();

        return redirect()
            ->route('admin.nationalities.index', $request->only(['q', 'status', 'usage']))
            ->with('status', $nationality->is_active
                ? __('app.admin.nationalities.activated')
                : __('app.admin.nationalities.deactivated'));
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
