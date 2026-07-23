@php
    $annex = data_get($application->metadata ?? [], 'annex', []);
    $productionTerms = data_get($annex, 'production_terms', []);
    $ministryInteriorPersonalDetails = data_get($annex, 'ministry_interior_personal_details', []);
    $workContentSummary = data_get($annex, 'work_content_summary', []);
    $safetyGuidelines = data_get($annex, 'safety_guidelines', []);
    $airportFilming = data_get($annex, 'airport_filming', []);

    $rowHasData = static fn ($row): bool => collect((array) $row)
        ->flatten()
        ->contains(fn ($value) => filled($value));
    $nonEmptyRows = static fn ($rows) => collect($rows ?? [])
        ->filter(fn ($row) => $rowHasData($row))
        ->values();
    $fallback = static fn ($value): string => filled($value) ? (string) $value : __('app.dashboard.not_available');
    $nationalityLabel = static fn ($value): string => filled($value) ? \App\Models\Nationality::labelFor((string) $value) : __('app.dashboard.not_available');
    $valueLabel = static function ($value) use ($fallback): string {
        if (is_array($value)) {
            return collect($value)->filter(fn ($item) => filled($item))->join(', ') ?: __('app.dashboard.not_available');
        }

        return $fallback($value);
    };
	    $translateOption = static function (string $baseKey, $value) use ($fallback): string {
        if (is_array($value)) {
            return collect($value)
                ->filter(fn ($item) => filled($item))
                ->map(fn ($item) => __($baseKey.'.'.$item) === $baseKey.'.'.$item ? $fallback($item) : __($baseKey.'.'.$item))
                ->join(', ') ?: __('app.dashboard.not_available');
        }

        if (! filled($value)) {
            return __('app.dashboard.not_available');
        }

        $translationKey = $baseKey.'.'.$value;
        $translation = __($translationKey);

	        return $translation === $translationKey ? $fallback($value) : $translation;
	    };
	    $genderLabel = static function ($value) use ($fallback): string {
		        if (! filled($value)) {
		            return __('app.dashboard.not_available');
		        }

	        $translationKey = 'app.auth.gender_options.'.$value;
	        $translation = __($translationKey);

		        return $translation === $translationKey ? $fallback($value) : $translation;
		    };
    $formLookupLabel = static fn (string $type, $value): string => filled($value)
        ? \App\Models\FormLookupOption::labelFor($type, (string) $value)
        : __('app.dashboard.not_available');

    $castCrewRows = $nonEmptyRows(data_get($annex, 'cast_crew', []));
    $filmingLocationRows = $nonEmptyRows(data_get($annex, 'filming_locations', []));
    $specialLocationRequirementRows = collect((array) data_get($annex, 'special_location_requirements', []))
        ->filter(fn ($row) => is_array($row) && $rowHasData($row));
    $specialLocationRequirementLabelsForRow = static function (array $row) use ($specialLocationRequirementRows, $formLookupLabel): string {
        $requirements = collect((array) data_get($row, 'special_requirements', []))
            ->map(fn ($value): string => (string) $value)
            ->filter(fn (string $value): bool => filled($value))
            ->values();

        if ($requirements->isEmpty()) {
            $locationName = trim((string) data_get($row, 'location_name'));

            if (filled($locationName)) {
                $requirements = $specialLocationRequirementRows
                    ->filter(fn ($requirementRow): bool => in_array($locationName, (array) data_get($requirementRow, 'locations', []), true))
                    ->keys()
                    ->map(fn ($value): string => (string) $value)
                    ->values();
            }
        }

        return $requirements
            ->map(fn (string $value): string => $formLookupLabel(\App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $value))
            ->filter(fn (string $value): bool => filled($value))
            ->join(', ') ?: __('app.dashboard.not_available');
    };
    $equipmentTravelerRows = $nonEmptyRows(data_get($annex, 'equipment_travelers', []));
    $importedEquipmentRows = $nonEmptyRows(data_get($annex, 'imported_equipment', []));
    $shippingEquipmentRows = $importedEquipmentRows
        ->filter(fn ($row): bool => data_get($row, 'transport_group', 'shipping') !== 'traveler')
        ->values();
    $travelerEquipmentRows = $importedEquipmentRows
        ->filter(fn ($row): bool => data_get($row, 'transport_group') === 'traveler')
        ->values();
    $publicSecuritySupportRows = $nonEmptyRows(data_get($annex, 'public_security_support', []));
    $militarySupportRows = $nonEmptyRows(data_get($annex, 'military_support', []));
    $storedLocalizedSupportLabel = static function (array $row, string $prefix): ?string {
        $language = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $fallbackLanguage = $language === 'ar' ? 'en' : 'ar';

        return collect([
            data_get($row, $prefix.'_name_'.$language),
            data_get($row, $prefix.'_name_'.$fallbackLanguage),
        ])->first(fn ($value): bool => filled($value));
    };
    $supportAuthorityLabel = static function ($supportRequirement) use ($fallback, $storedLocalizedSupportLabel): string {
        $row = is_array($supportRequirement) ? $supportRequirement : [];
        $storedLabel = $storedLocalizedSupportLabel($row, 'authority');

        if (filled($storedLabel)) {
            return (string) $storedLabel;
        }

        $value = is_array($supportRequirement)
            ? data_get($supportRequirement, 'authority')
            : $supportRequirement;

        return match ((string) $value) {
            'public_security' => __('app.applications.support_authorities.public_security'),
            'military' => __('app.applications.support_authorities.military'),
            default => $fallback($value),
        };
    };
    $supportRequirementLabel = static function ($supportRequirement) use ($fallback, $formLookupLabel, $storedLocalizedSupportLabel): string {
        $row = is_array($supportRequirement) ? $supportRequirement : [];
        $storedLabel = $storedLocalizedSupportLabel($row, 'requirement');

        if (filled($storedLabel)) {
            return (string) $storedLabel;
        }

        $value = is_array($supportRequirement)
            ? data_get($supportRequirement, 'requirement')
            : $supportRequirement;

        if (! filled($value)) {
            return __('app.dashboard.not_available');
        }

        $label = $formLookupLabel(\App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $value);
        $generatedFallback = str((string) $value)->replace('_', ' ')->headline()->toString();

        return $label === $generatedFallback ? (string) $value : $label;
    };
    $locationSupportRequirementsForRow = static function (array $row) use ($publicSecuritySupportRows, $militarySupportRows, $supportRequirementLabel, $supportAuthorityLabel) {
        $supportRequirements = collect((array) data_get($row, 'support_requirements', []))
            ->filter(fn ($requirement): bool => collect((array) $requirement)->filter(fn ($value): bool => filled($value))->isNotEmpty())
            ->values();
        $locationName = trim((string) data_get($row, 'location_name'));

        if ($supportRequirements->isEmpty() && filled($locationName)) {
            $publicSecurityLegacyRows = $publicSecuritySupportRows
                ->filter(fn ($supportRow): bool => trim((string) data_get($supportRow, 'location')) === $locationName)
                ->map(fn ($supportRow): array => [
                    'authority' => 'public_security',
                    'requirement' => data_get($supportRow, 'requirement'),
                    'date' => data_get($supportRow, 'date'),
                    'time_from' => data_get($supportRow, 'time_from'),
                    'time_to' => data_get($supportRow, 'time_to'),
                    'notes' => data_get($supportRow, 'notes'),
                ]);
            $militaryLegacyRows = $militarySupportRows
                ->filter(fn ($supportRow): bool => trim((string) data_get($supportRow, 'location')) === $locationName)
                ->map(fn ($supportRow): array => [
                    'authority' => 'military',
                    'requirement' => data_get($supportRow, 'requirement'),
                    'date' => data_get($supportRow, 'date'),
                    'time_from' => data_get($supportRow, 'time_from'),
                    'time_to' => data_get($supportRow, 'time_to'),
                    'notes' => data_get($supportRow, 'notes'),
                ]);

            $supportRequirements = $publicSecurityLegacyRows->concat($militaryLegacyRows)->values();
        }

        if ($supportRequirements->isEmpty()) {
            return collect();
        }

        return $supportRequirements
            ->map(function ($supportRequirement) use ($supportRequirementLabel, $supportAuthorityLabel): array {
                return [
                    'authority' => $supportAuthorityLabel($supportRequirement),
                    'requirement' => $supportRequirementLabel($supportRequirement),
                    'date' => data_get($supportRequirement, 'date'),
                    'time_from' => data_get($supportRequirement, 'time_from'),
                    'time_to' => data_get($supportRequirement, 'time_to'),
                    'notes' => data_get($supportRequirement, 'notes'),
                ];
            })
            ->values();
    };
    $airportPeopleRows = $nonEmptyRows(data_get($annex, 'airport_people', []));
    $governmentalSceneRows = $nonEmptyRows(data_get($annex, 'governmental_scenes', []));
    $onlySections = collect($onlySections ?? [])
        ->filter(fn ($section): bool => filled($section))
        ->map(fn ($section): string => (string) $section)
        ->unique()
        ->values();
    $hideEmptySections = (bool) ($hideEmptySections ?? false);
    $sectionHasData = [
        'production_terms' => (bool) data_get($productionTerms, 'accepted'),
        'ministry_interior_personal_details' => $rowHasData($ministryInteriorPersonalDetails),
        'work_content_summary' => $rowHasData($workContentSummary),
        'cast_crew' => $castCrewRows->isNotEmpty(),
        'filming_locations' => $filmingLocationRows->isNotEmpty(),
        'special_location_requirements' => $specialLocationRequirementRows->isNotEmpty(),
        'safety_guidelines' => (bool) data_get($safetyGuidelines, 'acknowledged') || filled(data_get($safetyGuidelines, 'notes')),
        'imported_equipment' => $importedEquipmentRows->isNotEmpty() || $equipmentTravelerRows->isNotEmpty(),
        'public_security_support' => $publicSecuritySupportRows->isNotEmpty(),
        'military_support' => $militarySupportRows->isNotEmpty(),
        'airport_filming' => $rowHasData($airportFilming),
        'airport_people' => $airportPeopleRows->isNotEmpty(),
        'governmental_scenes' => $governmentalSceneRows->isNotEmpty(),
    ];
    $formMatchesSections = static fn (array $form): bool => $onlySections->isEmpty()
        || collect($form['sections'])->intersect($onlySections)->isNotEmpty();
    $formHasVisibleData = static fn (array $form): bool => collect($form['sections'])
        ->contains(fn (string $section): bool => (bool) ($sectionHasData[$section] ?? false));
    $attachedForms = collect([
        ['key' => 'work_content_summary', 'target' => 'WorkContentSummaryView', 'label' => __('app.applications.annex_sections.work_content_summary'), 'sections' => ['work_content_summary']],
        ['key' => 'cast_crew', 'target' => 'CastCrewListView', 'label' => __('app.applications.annex_sections.cast_crew'), 'sections' => ['cast_crew']],
        ['key' => 'filming_locations', 'target' => 'LocationListView', 'label' => __('app.applications.annex_sections.filming_locations'), 'sections' => ['filming_locations', 'special_location_requirements', 'public_security_support', 'military_support']],
        ['key' => 'safety_guidelines', 'target' => 'RFCGuidelinesView', 'label' => __('app.applications.annex_sections.safety_guidelines'), 'sections' => ['safety_guidelines']],
        ['key' => 'production_terms', 'target' => 'ProductionTermsView', 'label' => __('app.applications.annex_sections.production_terms'), 'sections' => ['production_terms']],
        ['key' => 'ministry_interior_personal_details', 'target' => 'MinistryInteriorPersonalDetailsView', 'label' => __('app.applications.annex_sections.ministry_interior_personal_details'), 'sections' => ['ministry_interior_personal_details']],
        ['key' => 'imported_equipment', 'target' => 'EquipmentListView', 'label' => __('app.applications.annex_sections.imported_equipment'), 'sections' => ['equipment_travelers', 'imported_equipment']],
        ['key' => 'airport_filming', 'target' => 'FilmingAirportsView', 'label' => __('app.applications.annex_sections.airport_filming'), 'sections' => ['airport_filming', 'airport_people']],
        ['key' => 'governmental_scenes', 'target' => 'FilmingGovernmentalView', 'label' => __('app.applications.annex_sections.governmental_scenes'), 'sections' => ['governmental_scenes']],
    ])
        ->filter(fn (array $form): bool => $formMatchesSections($form) && (! $hideEmptySections || $formHasVisibleData($form)))
        ->values();
    $attachedFormTargets = $attachedForms->pluck('target');
    $hasPrintableForms = $attachedForms->contains(fn (array $form): bool => $formHasVisibleData($form));
    $printFormsUrl = match (true) {
        request()->routeIs('admin.applications.*') => route('admin.applications.forms.print', $application),
        request()->routeIs('authority.applications.*') => route('authority.applications.forms.print', $application),
        default => route('applications.forms.print', $application),
    };
@endphp

@once
    @push('styles')
        <style>
            .attached-forms-table-wrap {
                overflow-x: auto;
            }

            .attached-forms-table {
                min-width: 640px;
            }

            .attached-forms-table td {
                vertical-align: middle;
            }

            .offcanvas.attached-form-view-drawer {
                --bs-offcanvas-width: 100vw;
                border: 0 !important;
                border-radius: 0 !important;
                bottom: 0 !important;
                box-shadow: none;
                display: flex;
                flex-direction: column;
                height: 100vh !important;
                height: 100dvh !important;
                inset: 0 !important;
                left: 0 !important;
                margin: 0 !important;
                max-height: 100vh !important;
                max-height: 100dvh !important;
                max-width: none;
                min-height: 100vh;
                min-height: 100dvh;
                padding: 0;
                position: fixed !important;
                right: 0 !important;
                top: 0 !important;
                width: 100vw !important;
                z-index: 20000 !important;
            }

            .offcanvas.attached-form-view-drawer.show,
            .offcanvas.attached-form-view-drawer.showing {
                transform: none !important;
            }

            .offcanvas.attached-form-view-drawer .offcanvas-header {
                flex: 0 0 auto;
            }

            .offcanvas.attached-form-view-drawer .offcanvas-body {
                background: #fff;
                flex: 1 1 auto;
                overflow-y: auto;
            }

            .attached-form-readonly-table {
                min-width: 940px;
                table-layout: fixed;
            }

            .attached-form-readonly-table th,
            .attached-form-readonly-table td {
                vertical-align: top;
                white-space: normal;
                word-break: break-word;
            }

            .attached-form-value {
                border: 1px solid rgba(0, 0, 0, 0.08);
                border-radius: 4px;
                background: #f8f9fa;
                padding: 0.85rem 1rem;
                min-height: 46px;
            }

            .attached-location-list {
                display: grid;
                gap: 1.25rem;
                margin-inline: auto;
                max-width: 1500px;
            }

            .attached-location-card {
                background: #fff;
                border: 1px solid #dfe3e8;
                border-radius: 6px;
                overflow: hidden;
            }

            .attached-location-card__header {
                align-items: flex-start;
                background: #f5f6f8;
                border-bottom: 1px solid #dfe3e8;
                display: flex;
                gap: 1rem;
                justify-content: space-between;
                padding: 1.1rem 1.25rem;
            }

            .attached-location-card__number {
                align-items: center;
                background: var(--bs-danger);
                border-radius: 4px;
                color: #fff;
                display: inline-flex;
                flex: 0 0 38px;
                font-weight: 700;
                height: 38px;
                justify-content: center;
            }

            .attached-location-card__title {
                font-size: 1.15rem;
                line-height: 1.5;
                margin: 0;
                overflow-wrap: anywhere;
            }

            .attached-location-card__body {
                padding: 1.25rem;
            }

            .attached-location-details {
                display: grid;
                gap: 1rem;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                margin: 0;
            }

            .attached-location-detail {
                min-width: 0;
            }

            .attached-location-detail--wide {
                grid-column: span 2;
            }

            .attached-location-detail dt,
            .attached-support-detail dt {
                color: #6c757d;
                font-size: 0.82rem;
                font-weight: 600;
                margin-bottom: 0.3rem;
            }

            .attached-location-detail dd,
            .attached-support-detail dd {
                color: #1f2328;
                line-height: 1.6;
                margin: 0;
                overflow-wrap: anywhere;
            }

            .attached-location-support {
                border-top: 1px solid #e5e7eb;
                margin-top: 1.25rem;
                padding-top: 1.25rem;
            }

            .attached-location-support__heading {
                align-items: center;
                display: flex;
                gap: 0.65rem;
                justify-content: space-between;
                margin-bottom: 0.9rem;
            }

            .attached-support-list {
                display: grid;
                gap: 0.75rem;
            }

            .attached-support-item {
                background: #f8f9fa;
                border-inline-start: 4px solid var(--bs-danger);
                padding: 1rem;
            }

            .attached-support-item__header {
                align-items: flex-start;
                display: flex;
                flex-wrap: wrap;
                gap: 0.65rem 1rem;
                justify-content: space-between;
                margin-bottom: 0.85rem;
            }

            .attached-support-item__authority,
            .attached-support-item__requirement {
                line-height: 1.5;
                overflow-wrap: anywhere;
            }

            .attached-support-details {
                display: grid;
                gap: 0.85rem 1rem;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                margin: 0;
            }

            .attached-support-detail--notes {
                grid-column: span 4;
            }

            @media (max-width: 991.98px) {
                .attached-location-details,
                .attached-support-details {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .attached-support-detail--notes {
                    grid-column: span 2;
                }
            }

            @media (max-width: 575.98px) {
                .attached-location-card__header {
                    align-items: stretch;
                    flex-direction: column;
                }

                .attached-location-details,
                .attached-support-details {
                    grid-template-columns: minmax(0, 1fr);
                }

                .attached-location-detail--wide,
                .attached-support-detail--notes {
                    grid-column: auto;
                }
            }

        </style>
    @endpush
@endonce

<div class="card">
    <div class="card-header">
        <div class="header-title d-flex align-items-center justify-content-between gap-3 flex-wrap w-100">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.documents.attached_forms_heading') }}:</span>
            </h2>
            @if ($hasPrintableForms)
                <a class="btn btn-outline-danger" href="{{ $printFormsUrl }}" target="_blank" rel="noopener" data-print-all-forms>
                    <i class="ph ph-printer fs-6 me-2"></i>{{ __('app.documents.print_all_forms_action') }}
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="attached-forms-table-wrap">
            <table class="table table-striped mb-0 mx-auto attached-forms-table" style="width: 88%" role="grid">
                <tbody>
                    @forelse ($attachedForms as $formRow)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img class="rounded img-fluid avatar-40 me-3 bg-primary-subtle" src="{{ asset('images/clapboard.png') }}" alt="" loading="lazy">
                                    <h6 class="mb-0">{{ $formRow['label'] }}</h6>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                                    @if ($formHasVisibleData($formRow))
                                        <a class="btn btn-outline-danger" href="{{ $printFormsUrl }}?form={{ $formRow['key'] }}" target="_blank" rel="noopener" data-print-form="{{ $formRow['key'] }}">
                                            <i class="ph ph-printer fs-6 me-2"></i>{{ __('app.documents.print_form_action') }}
                                        </a>
                                    @endif
                                    <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $formRow['target'] }}" aria-controls="{{ $formRow['target'] }}">
                                        <i class="ph ph-eye fs-6 me-2"></i>{{ __('app.documents.view_form_action') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted py-4">{{ __('app.documents.empty_state') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($attachedFormTargets->contains('ProductionTermsView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="ProductionTermsView" aria-labelledby="ProductionTermsViewLabel">
    <div class="offcanvas-header">
        <h2 id="ProductionTermsViewLabel" class="mb-0">{{ __('app.applications.annex_sections.production_terms') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        @include('applications.partials.production-terms-form', [
            'productionTerms' => $productionTerms,
            'productionTermsReadOnly' => true,
        ])
    </div>
</div>
@endif

@if ($attachedFormTargets->contains('MinistryInteriorPersonalDetailsView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="MinistryInteriorPersonalDetailsView" aria-labelledby="MinistryInteriorPersonalDetailsViewLabel">
    <div class="offcanvas-header">
        <h2 id="MinistryInteriorPersonalDetailsViewLabel" class="mb-0">{{ __('app.applications.annex_sections.ministry_interior_personal_details') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        @include('applications.partials.ministry-interior-personal-details-form', [
            'ministryInteriorPersonalDetails' => $ministryInteriorPersonalDetails,
            'ministryInteriorPersonalDetailsReadOnly' => true,
            'ministryInteriorPersonalDetailsIdPrefix' => 'ministry_interior_personal_details_view',
        ])
    </div>
</div>
@endif

@if ($attachedFormTargets->contains('WorkContentSummaryView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="WorkContentSummaryView" aria-labelledby="WorkContentSummaryViewLabel">
    <div class="offcanvas-header">
        <h2 id="WorkContentSummaryViewLabel" class="mb-0">{{ __('app.applications.annex_sections.work_content_summary') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <p class="text-danger small">{{ __('app.applications.work_summary_instruction') }}</p>
        <div class="mb-3">
            <label class="form-label">{{ __('app.applications.annex_fields.synopsis') }}</label>
            <div class="attached-form-value">{{ $fallback(data_get($workContentSummary, 'synopsis')) }}</div>
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('app.applications.annex_fields.work_summary_english_attachment') }}</label>
            <div class="attached-form-value">{{ $fallback(data_get($workContentSummary, 'attachment_name')) }}</div>
        </div>
        <span class="badge bg-{{ data_get($workContentSummary, 'confirmed') ? 'success' : 'secondary' }}">
            {{ data_get($workContentSummary, 'confirmed') ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}
        </span>
    </div>
</div>
@endif

@if ($attachedFormTargets->contains('CastCrewListView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="CastCrewListView" aria-labelledby="CastCrewListViewLabel">
    <div class="offcanvas-header">
        <h2 id="CastCrewListViewLabel" class="mb-0">{{ __('app.applications.annex_sections.cast_crew') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <p class="text-danger small">{{ __('app.applications.cast_crew_instruction') }}</p>
        <div class="table-responsive">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.applications.annex_fields.person_name') }}</th>
	                        <th>{{ __('app.applications.annex_fields.role') }}</th>
	                        <th>{{ __('app.applications.annex_fields.nationality') }}</th>
	                        <th>{{ __('app.applications.annex_fields.gender') }}</th>
	                        <th>{{ __('app.applications.annex_fields.birth_date') }}</th>
	                        <th>{{ __('app.applications.annex_fields.identity_number') }}</th>
	                        <th>{{ __('app.applications.annex_fields.individual_number') }}</th>
	                        <th>{{ __('app.applications.annex_fields.passport_image') }}</th>
	                        <th>{{ __('app.applications.cast_crew_verification.verification') }}</th>
                    </tr>
                </thead>
                <tbody>
	                    @forelse ($castCrewRows as $row)
	                        @php
	                            $crewVerificationStatus = in_array(data_get($row, 'identity_verification_status'), ['verified', 'pending', 'manual', 'unverified'], true)
	                                ? data_get($row, 'identity_verification_status')
	                                : 'unverified';
	                            $crewVerificationBadge = match ($crewVerificationStatus) {
	                                'verified' => 'success',
	                                'pending' => 'warning text-dark',
	                                'manual' => 'secondary',
	                                default => 'light text-dark border',
	                            };
	                            $crewVerificationSource = data_get($row, 'identity_verification_source');
	                            $crewVerificationSourceLabel = $crewVerificationSource
	                                ? __('app.applications.cast_crew_verification.sources.'.$crewVerificationSource)
	                                : null;
	                        @endphp
	                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $fallback(data_get($row, 'name')) }}</td>
	                            <td>{{ $fallback(data_get($row, 'role')) }}</td>
	                        <td>{{ $nationalityLabel(data_get($row, 'nationality')) }}</td>
	                            <td>{{ $genderLabel(data_get($row, 'gender')) }}</td>
	                            <td>{{ $fallback(data_get($row, 'birth_date')) }}</td>
	                            <td>{{ $fallback(data_get($row, 'identity_number')) }}</td>
	                            <td>{{ $fallback(data_get($row, 'individual_number')) }}</td>
	                            <td>{{ $fallback(data_get($row, 'passport_image_name')) }}</td>
	                            <td>
	                                <span class="badge bg-{{ $crewVerificationBadge }}">{{ __('app.applications.cast_crew_verification.statuses.'.$crewVerificationStatus) }}</span>
	                                @if ($crewVerificationSourceLabel)
	                                    <small class="d-block text-muted mt-1">{{ __('app.applications.cast_crew_verification.source', ['source' => $crewVerificationSourceLabel]) }}</small>
	                                @endif
	                                @if (filled(data_get($row, 'identity_verified_at')))
	                                    <small class="d-block text-muted">{{ __('app.applications.cast_crew_verification.verified_at', ['date' => data_get($row, 'identity_verified_at')]) }}</small>
	                                @endif
	                            </td>
	                        </tr>
	                    @empty
	                        <tr><td colspan="10">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if ($attachedFormTargets->contains('LocationListView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="LocationListView" aria-labelledby="LocationListViewLabel">
    <div class="offcanvas-header">
        <h2 id="LocationListViewLabel" class="mb-0">{{ __('app.applications.annex_sections.filming_locations') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="attached-location-list">
            @forelse ($filmingLocationRows as $row)
                @php
                    $locationAddress = data_get($row, 'address');
                    $locationSupportRequirements = $locationSupportRequirementsForRow((array) $row);
                @endphp
                <article class="attached-location-card">
                    <header class="attached-location-card__header">
                        <div class="d-flex align-items-start gap-3 min-w-0">
                            <span class="attached-location-card__number">{{ $loop->iteration }}</span>
                            <div class="min-w-0">
                                <h3 class="attached-location-card__title">{{ $fallback(data_get($row, 'location_name')) }}</h3>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <span class="badge bg-primary-subtle text-primary-emphasis">{{ \App\Models\Governorate::labelFor(data_get($row, 'governorate')) }}</span>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ \App\Models\FilmingLocationType::labelFor(data_get($row, 'location_type')) }}</span>
                                </div>
                            </div>
                        </div>
                    </header>

                    <div class="attached-location-card__body">
                        <dl class="attached-location-details">
                            <div class="attached-location-detail attached-location-detail--wide">
                                <dt>{{ __('app.applications.annex_fields.location_address') }}</dt>
                                <dd>
                                    @if (filled($locationAddress) && filter_var($locationAddress, FILTER_VALIDATE_URL))
                                        <a href="{{ $locationAddress }}" target="_blank" rel="noreferrer">{{ $locationAddress }}</a>
                                    @else
                                        {{ $fallback($locationAddress) }}
                                    @endif
                                </dd>
                            </div>
                            <div class="attached-location-detail attached-location-detail--wide">
                                <dt>{{ __('app.applications.annex_fields.location_nature') }}</dt>
                                <dd>{{ $fallback(data_get($row, 'nature')) }}</dd>
                            </div>
                            <div class="attached-location-detail">
                                <dt>{{ __('app.scouting.start_date') }}</dt>
                                <dd>{{ $fallback(data_get($row, 'start_date')) }}</dd>
                            </div>
                            <div class="attached-location-detail">
                                <dt>{{ __('app.scouting.end_date') }}</dt>
                                <dd>{{ $fallback(data_get($row, 'end_date')) }}</dd>
                            </div>
                            <div class="attached-location-detail attached-location-detail--wide">
                                <dt>{{ __('app.applications.special_requirement') }}</dt>
                                <dd>{{ $specialLocationRequirementLabelsForRow((array) $row) }}</dd>
                            </div>
                        </dl>

                        <section class="attached-location-support">
                            <div class="attached-location-support__heading">
                                <h4 class="h6 mb-0">{{ __('app.applications.location_support_requirements_title') }}</h4>
                                <span class="badge bg-danger">{{ $locationSupportRequirements->count() }}</span>
                            </div>

                            <div class="attached-support-list">
                                @forelse ($locationSupportRequirements as $supportRequirement)
                                    <div class="attached-support-item">
                                        <div class="attached-support-item__header">
                                            <div class="attached-support-item__authority fw-semibold">{{ data_get($supportRequirement, 'authority') }}</div>
                                            <div class="attached-support-item__requirement text-danger fw-semibold">{{ data_get($supportRequirement, 'requirement') }}</div>
                                        </div>
                                        <dl class="attached-support-details">
                                            <div class="attached-support-detail">
                                                <dt>{{ __('app.applications.annex_fields.date') }}</dt>
                                                <dd>{{ $fallback(data_get($supportRequirement, 'date')) }}</dd>
                                            </div>
                                            <div class="attached-support-detail">
                                                <dt>{{ __('app.applications.annex_fields.time_from') }}</dt>
                                                <dd>{{ $fallback(data_get($supportRequirement, 'time_from')) }}</dd>
                                            </div>
                                            <div class="attached-support-detail">
                                                <dt>{{ __('app.applications.annex_fields.time_to') }}</dt>
                                                <dd>{{ $fallback(data_get($supportRequirement, 'time_to')) }}</dd>
                                            </div>
                                            <div class="attached-support-detail attached-support-detail--notes">
                                                <dt>{{ __('app.applications.annex_fields.notes') }}</dt>
                                                <dd>{{ $fallback(data_get($supportRequirement, 'notes')) }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                @empty
                                    <div class="text-muted border p-3">{{ __('app.documents.not_filled') }}</div>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </article>
            @empty
                <div class="text-center text-muted border p-4">{{ __('app.documents.not_filled') }}</div>
            @endforelse
        </div>
        <p class="text-muted mx-auto mt-3 mb-0" style="max-width: 1500px">{{ __('app.applications.location_damage_instruction') }}</p>
    </div>
</div>
@endif

@if ($attachedFormTargets->contains('RFCGuidelinesView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="RFCGuidelinesView" aria-labelledby="RFCGuidelinesViewLabel">
    <div class="offcanvas-header">
        <h2 id="RFCGuidelinesViewLabel" class="mb-0">{{ __('app.applications.safety_guidelines_full_title') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="d-grid gap-3 mb-4">
            @foreach (trans('app.applications.safety_sections') as $section)
                <div>
                    <h5 class="mb-2">{{ $section['title'] }}</h5>
                    <ul class="mb-0">
                        @foreach ($section['points'] as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <div class="mb-3">
            <span class="fw-600">{{ __('app.applications.annex_fields.safety_acknowledgement') }}</span>
            <span class="ms-2 badge bg-{{ data_get($safetyGuidelines, 'acknowledged') ? 'success' : 'secondary' }}">
                {{ data_get($safetyGuidelines, 'acknowledged') ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}
            </span>
        </div>
        <label class="form-label">{{ __('app.applications.annex_fields.safety_notes') }}</label>
        <div class="attached-form-value">{{ $fallback(data_get($safetyGuidelines, 'notes')) }}</div>
    </div>
</div>
@endif

@if ($attachedFormTargets->contains('EquipmentListView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="EquipmentListView" aria-labelledby="EquipmentListViewLabel">
    <div class="offcanvas-header">
        <h2 id="EquipmentListViewLabel" class="mb-0">{{ __('app.applications.annex_sections.imported_equipment') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        @if ($shippingEquipmentRows->isNotEmpty())
            <div class="attached-form-value mb-3">
                <span class="fw-600">{{ __('app.applications.shipping_equipment_acknowledgement') }}</span>
                <span class="ms-2 badge bg-{{ data_get($annex, 'shipping_equipment_acknowledged') ? 'success' : 'secondary' }}">
                    {{ data_get($annex, 'shipping_equipment_acknowledged') ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}
                </span>
            </div>
        @endif
        <div class="table-responsive">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.applications.annex_fields.shipping_company_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.invoice_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.bill_of_lading_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.arrival_date') }}</th>
                        <th>{{ __('app.applications.annex_fields.departure_date') }}</th>
                        <th>{{ __('app.applications.annex_fields.customs_center') }}</th>
                        <th>{{ __('app.applications.annex_fields.invoice_attachment') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shippingEquipmentRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $fallback(data_get($row, 'shipping_company_name')) }}</td>
                            <td>{{ $fallback(data_get($row, 'invoice_number')) }}</td>
                            <td>{{ $fallback(data_get($row, 'bill_of_lading_number')) }}</td>
                            <td>{{ $fallback(data_get($row, 'arrival_date')) }}</td>
                            <td>{{ $fallback(data_get($row, 'departure_date')) }}</td>
                            <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT, data_get($row, 'customs_center', data_get($row, 'entry_point'))) }}</td>
                            <td>{{ $fallback(data_get($row, 'attachment_name')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <h3 class="mt-4 mb-3">{{ __('app.applications.travelers_list_title') }}</h3>
        <div class="table-responsive">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.applications.annex_fields.traveler_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.arrival_date') }}</th>
                        <th>{{ __('app.applications.annex_fields.flight_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.departure_date') }}</th>
                        <th>{{ __('app.applications.annex_fields.departure_flight_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.passport_image') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipmentTravelerRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $fallback(data_get($row, 'traveler_name')) }}</td>
                            <td>{{ $fallback(data_get($row, 'arrival_date')) }}</td>
                            <td>{{ $fallback(data_get($row, 'arrival_flight_number')) }}</td>
                            <td>{{ $fallback(data_get($row, 'departure_date')) }}</td>
                            <td>{{ $fallback(data_get($row, 'departure_flight_number')) }}</td>
                            <td>{{ $fallback(data_get($row, 'passport_image_name')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <h3 class="mt-4 mb-3">{{ __('app.applications.equipment_list_title') }}</h3>
        <div class="table-responsive">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.applications.annex_fields.equipment_item') }}</th>
                        <th>{{ __('app.applications.annex_fields.serial_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.traveler_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.quantity') }}</th>
                        <th>{{ __('app.applications.annex_fields.unit_value_usd') }}</th>
                        <th>{{ __('app.applications.annex_fields.total_value') }}</th>
                        <th>{{ __('app.applications.annex_fields.classification') }}</th>
                        <th>{{ __('app.applications.annex_fields.entry_point') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($travelerEquipmentRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $fallback(data_get($row, 'item')) }}</td>
                            <td>{{ $fallback(data_get($row, 'serial_number')) }}</td>
                            <td>{{ $fallback(data_get($row, 'traveler_name')) }}</td>
                            <td>{{ $fallback(data_get($row, 'quantity')) }}</td>
                            <td>{{ $fallback(data_get($row, 'unit_value')) }}</td>
                            <td>{{ $fallback(data_get($row, 'total_value')) }}</td>
                            <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_EQUIPMENT_CATEGORY, data_get($row, 'classification')) }}</td>
                            <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT, data_get($row, 'entry_point')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if ($attachedFormTargets->contains('FilmingAirportsView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="FilmingAirportsView" aria-labelledby="FilmingAirportsViewLabel">
    <div class="offcanvas-header">
        <h2 id="FilmingAirportsViewLabel" class="mb-0">{{ __('app.applications.annex_sections.airport_filming') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="row g-3 mb-4">
	            <div class="col-md-6">
	                <label class="form-label">{{ __('app.applications.annex_fields.airport_name') }}</label>
	                <div class="attached-form-value">{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_AIRPORT, data_get($airportFilming, 'airport_name')) }}</div>
	            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('app.applications.annex_fields.airport_area') }}</label>
                <div class="attached-form-value">{{ $fallback(data_get($airportFilming, 'area')) }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('app.applications.annex_fields.filming_date') }}</label>
                <div class="attached-form-value">{{ $fallback(data_get($airportFilming, 'filming_date')) }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('app.applications.annex_fields.airport_crew_count') }}</label>
                <div class="attached-form-value">{{ $fallback(data_get($airportFilming, 'crew_count')) }}</div>
            </div>
            <div class="col-12">
                <label class="form-label">{{ __('app.applications.annex_fields.notes') }}</label>
                <div class="attached-form-value">{{ $fallback(data_get($airportFilming, 'notes')) }}</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.applications.annex_fields.person_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.nationality') }}</th>
                        <th>{{ __('app.applications.annex_fields.mother_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.identity_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.profession') }}</th>
                        <th>{{ __('app.applications.annex_fields.address_phone') }}</th>
                        <th>{{ __('app.applications.annex_fields.entry_reason') }}</th>
                        <th>{{ __('app.applications.annex_fields.target_area') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($airportPeopleRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $fallback(data_get($row, 'full_name')) }}</td>
                            <td>{{ $nationalityLabel(data_get($row, 'nationality')) }}</td>
                            <td>{{ $fallback(data_get($row, 'mother_name')) }}</td>
                            <td>{{ $fallback(data_get($row, 'identity_number')) }}</td>
                            <td>{{ $fallback(data_get($row, 'profession')) }}</td>
                            <td>{{ $fallback(data_get($row, 'address_phone')) }}</td>
                            <td>{{ $fallback(data_get($row, 'entry_reason')) }}</td>
                            <td>{{ $fallback(data_get($row, 'target_area')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if ($attachedFormTargets->contains('FilmingGovernmentalView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="FilmingGovernmentalView" aria-labelledby="FilmingGovernmentalViewLabel">
    <div class="offcanvas-header">
        <h2 id="FilmingGovernmentalViewLabel" class="mb-0">{{ __('app.applications.annex_sections.governmental_scenes') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="table-responsive">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.applications.annex_fields.site_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.authority_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.scene_description') }}</th>
                        <th>{{ __('app.applications.annex_fields.filming_date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($governmentalSceneRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $fallback(data_get($row, 'site_name')) }}</td>
                            <td>{{ $fallback(data_get($row, 'authority')) }}</td>
                            <td>{{ $fallback(data_get($row, 'scene_description')) }}</td>
                            <td>{{ $fallback(data_get($row, 'filming_date')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <span class="fw-600">{{ __('app.applications.governmental_scenes_acknowledgement') }}</span>
            <span class="ms-2 badge bg-{{ data_get($annex, 'governmental_scenes_confirmed') ? 'success' : 'secondary' }}">
                {{ data_get($annex, 'governmental_scenes_confirmed') ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}
            </span>
        </div>
    </div>
</div>
@endif
