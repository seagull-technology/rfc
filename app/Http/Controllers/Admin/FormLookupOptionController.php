<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormLookupOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FormLookupOptionController extends Controller
{
    public function index(Request $request): View
    {
        $types = FormLookupOption::typeLabels();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys($types))],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);

        $query = FormLookupOption::query();

        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name_en', 'like', '%'.$search.'%')
                    ->orWhere('name_ar', 'like', '%'.$search.'%');
            });
        }

        if (filled($filters['type'] ?? null)) {
            $query->ofType((string) $filters['type']);
        }

        if (($filters['status'] ?? 'all') === 'active') {
            $query->active();
        } elseif (($filters['status'] ?? 'all') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('admin.form-lookups.index', [
            'options' => $query
                ->orderBy('type')
                ->ordered()
                ->paginate(80)
                ->withQueryString(),
            'types' => $types,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'type' => $filters['type'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'total' => FormLookupOption::query()->count(),
                'active' => FormLookupOption::query()->active()->count(),
                'types' => FormLookupOption::query()->distinct('type')->count('type'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $types = array_keys(FormLookupOption::typeLabels());
        $validated = $this->validatedOption($request, $types);
        $code = str($validated['code'])->slug('_')->toString();

        if (FormLookupOption::query()->where('type', $validated['type'])->where('code', $code)->exists()) {
            return back()
                ->withInput()
                ->withErrors(['code' => __('app.admin.form_lookups.code_taken')]);
        }

        FormLookupOption::query()->create([
            ...$validated,
            'code' => $code,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.form-lookups.index', $request->only(['type', 'status', 'q']))
            ->with('status', __('app.admin.form_lookups.created'));
    }

    public function update(Request $request, FormLookupOption $option): RedirectResponse
    {
        $types = array_keys(FormLookupOption::typeLabels());
        $validated = $this->validatedOption($request, $types);
        $code = str($validated['code'])->slug('_')->toString();

        $duplicate = FormLookupOption::query()
            ->where('type', $validated['type'])
            ->where('code', $code)
            ->whereKeyNot($option->getKey())
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors(['code' => __('app.admin.form_lookups.code_taken')]);
        }

        $option->update([
            ...$validated,
            'code' => $code,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.form-lookups.index', $request->only(['type', 'status', 'q']))
            ->with('status', __('app.admin.form_lookups.updated'));
    }

    public function updateStatus(Request $request, FormLookupOption $option): RedirectResponse
    {
        $option->forceFill([
            'is_active' => ! $option->is_active,
        ])->save();

        return redirect()
            ->route('admin.form-lookups.index', $request->only(['type', 'status', 'q']))
            ->with('status', $option->is_active
                ? __('app.admin.form_lookups.activated')
                : __('app.admin.form_lookups.deactivated'));
    }

    /**
     * @param  array<int, string>  $types
     * @return array<string, mixed>
     */
    private function validatedOption(Request $request, array $types): array
    {
        return $request->validate([
            'type' => ['required', Rule::in($types)],
            'code' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
