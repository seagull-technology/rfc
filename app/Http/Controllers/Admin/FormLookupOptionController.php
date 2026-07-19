<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\FormLookupOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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

        $query = FormLookupOption::query()->with('entities.group');

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
            'authorityEntities' => $this->authorityEntities(),
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

        $option = FormLookupOption::query()->create([
            ...Arr::except($validated, ['entity_ids', 'notes_prompt_en', 'notes_prompt_ar']),
            'code' => $code,
            'metadata' => $this->optionMetadata($validated),
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->syncAuthorityEntities($option, $validated);

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
            ...Arr::except($validated, ['entity_ids', 'notes_prompt_en', 'notes_prompt_ar']),
            'code' => $code,
            'metadata' => $this->optionMetadata($validated, (array) $option->metadata),
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->syncAuthorityEntities($option, $validated);

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
        $authorityEntityIds = $this->authorityEntities()->pluck('id')->all();

        return $request->validate([
            'type' => ['required', Rule::in($types)],
            'code' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer', Rule::in($authorityEntityIds)],
            'notes_prompt_en' => ['nullable', 'string', 'max:1000'],
            'notes_prompt_ar' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @return Collection<int, Entity>
     */
    private function authorityEntities()
    {
        return Entity::query()
            ->with('group')
            ->where('status', 'active')
            ->whereHas('group', fn ($query) => $query->where('code', 'authorities'))
            ->orderBy('name_en')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function optionMetadata(array $validated, array $existing = []): array
    {
        if (($validated['type'] ?? null) !== FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT) {
            return $existing;
        }

        data_set($existing, 'notes_prompt_en', filled($validated['notes_prompt_en'] ?? null)
            ? trim((string) $validated['notes_prompt_en'])
            : null);
        data_set($existing, 'notes_prompt_ar', filled($validated['notes_prompt_ar'] ?? null)
            ? trim((string) $validated['notes_prompt_ar'])
            : null);

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncAuthorityEntities(FormLookupOption $option, array $validated): void
    {
        $entityIds = $option->type === FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT
            ? collect((array) ($validated['entity_ids'] ?? []))->map(fn ($id): int => (int) $id)->unique()->all()
            : [];

        $option->entities()->sync($entityIds);
    }
}
