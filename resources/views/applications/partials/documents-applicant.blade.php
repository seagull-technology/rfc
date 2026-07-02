@php
    $annex = data_get($application->metadata ?? [], 'annex', []);
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
    $equipmentFlightRows = $nonEmptyRows(data_get($annex, 'equipment_flights', []));
    $equipmentTravelerRows = $nonEmptyRows(data_get($annex, 'equipment_travelers', []));
    $importedEquipmentRows = $nonEmptyRows(data_get($annex, 'imported_equipment', []));
    $militaryBorderLocationRows = $nonEmptyRows(data_get($annex, 'military_border_locations', []));
    $militaryBorderEquipmentRows = $nonEmptyRows(data_get($annex, 'military_border_equipment', []));
    $airportPeopleRows = $nonEmptyRows(data_get($annex, 'airport_people', []));
    $governmentalSceneRows = $nonEmptyRows(data_get($annex, 'governmental_scenes', []));
    $importedEquipmentTotal = $importedEquipmentRows->sum(fn ($row) => (float) data_get($row, 'total_value'));
    $militaryBorderEquipmentTotal = $militaryBorderEquipmentRows->sum(fn ($row) => (float) data_get($row, 'total_value'));
    $onlySections = collect($onlySections ?? [])
        ->filter(fn ($section): bool => filled($section))
        ->map(fn ($section): string => (string) $section)
        ->unique()
        ->values();
    $hideEmptySections = (bool) ($hideEmptySections ?? false);
    $sectionHasData = [
        'work_content_summary' => $rowHasData($workContentSummary),
        'cast_crew' => $castCrewRows->isNotEmpty(),
        'filming_locations' => $filmingLocationRows->isNotEmpty(),
        'special_location_requirements' => $specialLocationRequirementRows->isNotEmpty(),
        'safety_guidelines' => (bool) data_get($safetyGuidelines, 'acknowledged') || filled(data_get($safetyGuidelines, 'notes')),
        'equipment_flights' => $equipmentFlightRows->isNotEmpty(),
        'equipment_travelers' => $equipmentTravelerRows->isNotEmpty(),
        'imported_equipment' => $importedEquipmentRows->isNotEmpty(),
        'military_border_locations' => $militaryBorderLocationRows->isNotEmpty(),
        'military_border_equipment' => $militaryBorderEquipmentRows->isNotEmpty(),
        'airport_filming' => $rowHasData($airportFilming),
        'airport_people' => $airportPeopleRows->isNotEmpty(),
        'governmental_scenes' => $governmentalSceneRows->isNotEmpty(),
    ];
    $formMatchesSections = static fn (array $form): bool => $onlySections->isEmpty()
        || collect($form['sections'])->intersect($onlySections)->isNotEmpty();
    $formHasVisibleData = static fn (array $form): bool => collect($form['sections'])
        ->contains(fn (string $section): bool => (bool) ($sectionHasData[$section] ?? false));
    $attachedForms = collect([
        ['target' => 'WorkContentSummaryView', 'label' => __('app.applications.annex_sections.work_content_summary'), 'sections' => ['work_content_summary']],
        ['target' => 'CastCrewListView', 'label' => __('app.applications.annex_sections.cast_crew'), 'sections' => ['cast_crew']],
        ['target' => 'LocationListView', 'label' => __('app.applications.annex_sections.filming_locations'), 'sections' => ['filming_locations', 'special_location_requirements']],
        ['target' => 'RFCGuidelinesView', 'label' => __('app.applications.annex_sections.safety_guidelines'), 'sections' => ['safety_guidelines']],
        ['target' => 'EquipmentListView', 'label' => __('app.applications.annex_sections.imported_equipment'), 'sections' => ['equipment_flights', 'equipment_travelers', 'imported_equipment']],
        ['target' => 'EquipmentMilitaryBorderView', 'label' => __('app.applications.annex_sections.military_border_equipment'), 'sections' => ['military_border_locations', 'military_border_equipment']],
        ['target' => 'FilmingAirportsView', 'label' => __('app.applications.annex_sections.airport_filming'), 'sections' => ['airport_filming', 'airport_people']],
        ['target' => 'FilmingGovernmentalView', 'label' => __('app.applications.annex_sections.governmental_scenes'), 'sections' => ['governmental_scenes']],
    ])
        ->filter(fn (array $form): bool => $formMatchesSections($form) && (! $hideEmptySections || $formHasVisibleData($form)))
        ->values();
    $attachedFormTargets = $attachedForms->pluck('target');
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

            .attached-form-view-drawer {
                width: min(1180px, 92vw) !important;
            }

            .attached-form-view-drawer .offcanvas-body {
                background: #fff;
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

        </style>
    @endpush
@endonce

<div class="card">
    <div class="card-header">
        <div class="header-title">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.documents.attached_forms_heading') }}:</span>
            </h2>
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
                                <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $formRow['target'] }}" aria-controls="{{ $formRow['target'] }}">
                                    <i class="ph ph-eye fs-6 me-2"></i>{{ __('app.documents.view_form_action') }}
                                </button>
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
            <label class="form-label">{{ __('app.applications.annex_fields.sensitive_content_notes') }}</label>
            <div class="attached-form-value">{{ $fallback(data_get($workContentSummary, 'sensitive_notes')) }}</div>
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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($castCrewRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $fallback(data_get($row, 'name')) }}</td>
	                            <td>{{ $fallback(data_get($row, 'role')) }}</td>
	                        <td>{{ $nationalityLabel(data_get($row, 'nationality')) }}</td>
	                            <td>{{ $genderLabel(data_get($row, 'gender')) }}</td>
	                            <td>{{ $fallback(data_get($row, 'birth_date')) }}</td>
	                            <td>{{ $fallback(data_get($row, 'identity_number')) }}</td>
                        </tr>
                    @empty
	                        <tr><td colspan="7">{{ __('app.documents.not_filled') }}</td></tr>
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
        <div class="table-responsive">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.scouting.governorate') }}</th>
                        <th>{{ __('app.applications.annex_fields.location_exact_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.location_address') }}</th>
                        <th>{{ __('app.applications.annex_fields.location_nature') }}</th>
                        <th>{{ __('app.applications.annex_fields.location_type') }}</th>
                        <th>{{ __('app.scouting.start_date') }}</th>
                        <th>{{ __('app.scouting.end_date') }}</th>
                        <th>{{ __('app.applications.annex_fields.notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filmingLocationRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ \App\Models\Governorate::labelFor(data_get($row, 'governorate')) }}</td>
                            <td>{{ $fallback(data_get($row, 'location_name')) }}</td>
                            <td>{{ $fallback(data_get($row, 'address')) }}</td>
                            <td>{{ $fallback(data_get($row, 'nature')) }}</td>
                            <td>{{ \App\Models\FilmingLocationType::labelFor(data_get($row, 'location_type')) }}</td>
                            <td>{{ $fallback(data_get($row, 'start_date')) }}</td>
                            <td>{{ $fallback(data_get($row, 'end_date')) }}</td>
                            <td>{{ $fallback(data_get($row, 'notes')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($specialLocationRequirementRows->isNotEmpty())
            <h5 class="mt-4 mb-3">{{ __('app.applications.special_location_requirements_title') }}</h5>
            <div class="table-responsive">
                <table class="table table-striped mb-0 attached-form-readonly-table">
                    <thead>
                        <tr>
                            <th>{{ __('app.applications.special_requirement') }}</th>
                            <th>{{ __('app.applications.locations') }}</th>
                            <th>{{ __('app.applications.annex_fields.notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($specialLocationRequirementRows as $key => $row)
                            <tr>
	                                <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $key) }}</td>
                                <td>{{ collect((array) data_get($row, 'locations', []))->filter()->join(', ') ?: __('app.dashboard.not_available') }}</td>
                                <td>{{ $fallback(data_get($row, 'notes')) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="text-muted mt-3 mb-0">{{ __('app.applications.location_damage_instruction') }}</p>
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
        <h5 class="mb-3">{{ __('app.applications.flight_details_title') }}</h5>
        <div class="table-responsive mb-4">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.applications.annex_fields.flight_type') }}</th>
                        <th>{{ __('app.applications.annex_fields.flight_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.date') }}</th>
                        <th>{{ __('app.applications.annex_fields.time') }}</th>
                        <th>{{ __('app.applications.annex_fields.departure_city') }}</th>
                        <th>{{ __('app.applications.annex_fields.arrival_city') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipmentFlightRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $translateOption('app.applications.flight_types', data_get($row, 'flight_type')) }}</td>
                            <td>{{ $fallback(data_get($row, 'flight_number')) }}</td>
                            <td>{{ $fallback(data_get($row, 'flight_date')) }}</td>
                            <td>{{ $fallback(data_get($row, 'flight_time')) }}</td>
                            <td>{{ $fallback(data_get($row, 'departure_city')) }}</td>
                            <td>{{ $fallback(data_get($row, 'arrival_city')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h5 class="mb-3">{{ __('app.applications.travelers_list_title') }}</h5>
        <div class="table-responsive mb-4">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.applications.annex_fields.traveler_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.arrival_date') }}</th>
                        <th>{{ __('app.applications.annex_fields.flight_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.departure_date') }}</th>
                        <th>{{ __('app.applications.annex_fields.departure_flight_number') }}</th>
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
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h5 class="mb-3">{{ __('app.applications.equipment_list_title') }}</h5>
        <div class="table-responsive">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.applications.annex_fields.equipment_item') }}</th>
                        <th>{{ __('app.applications.annex_fields.serial_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.flight') }}</th>
                        <th>{{ __('app.applications.annex_fields.traveler_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.quantity') }}</th>
                        <th>{{ __('app.applications.annex_fields.unit_value_usd') }}</th>
                        <th>{{ __('app.applications.annex_fields.total_value') }}</th>
                        <th>{{ __('app.applications.annex_fields.classification') }}</th>
                        <th>{{ __('app.applications.annex_fields.shipping_method') }}</th>
                        <th>{{ __('app.applications.annex_fields.entry_point') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($importedEquipmentRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $fallback(data_get($row, 'item')) }}</td>
                            <td>{{ $fallback(data_get($row, 'serial_number')) }}</td>
                            <td>{{ $fallback(data_get($row, 'flight_reference')) }}</td>
                            <td>{{ $fallback(data_get($row, 'traveler_name')) }}</td>
                            <td>{{ $fallback(data_get($row, 'quantity')) }}</td>
                            <td>{{ $fallback(data_get($row, 'unit_value')) }}</td>
                            <td>{{ $fallback(data_get($row, 'total_value')) }}</td>
	                            <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_EQUIPMENT_CATEGORY, data_get($row, 'classification')) }}</td>
	                            <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_EQUIPMENT_SHIPPING_METHOD, data_get($row, 'shipping_method')) }}</td>
	                            <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT, data_get($row, 'entry_point')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="text-end fw-semibold mt-3">{{ __('app.applications.equipment_total_label') }} {{ number_format($importedEquipmentTotal, 2) }} {{ __('app.applications.usd') }}</div>
    </div>
</div>
@endif

@if ($attachedFormTargets->contains('EquipmentMilitaryBorderView'))
<div class="offcanvas offcanvas-end attached-form-view-drawer" tabindex="-1" id="EquipmentMilitaryBorderView" aria-labelledby="EquipmentMilitaryBorderViewLabel">
    <div class="offcanvas-header">
        <h2 id="EquipmentMilitaryBorderViewLabel" class="mb-0">{{ __('app.applications.annex_sections.military_border_equipment') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <h5 class="mb-3">{{ __('app.applications.military_border_locations_title') }}</h5>
        <div class="table-responsive mb-4">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.scouting.governorate') }}</th>
                        <th>{{ __('app.applications.annex_fields.location_exact_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.location_address') }}</th>
                        <th>{{ __('app.applications.annex_fields.location_nature') }}</th>
                        <th>{{ __('app.applications.annex_fields.location_type') }}</th>
                        <th>{{ __('app.scouting.start_date') }}</th>
                        <th>{{ __('app.scouting.end_date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($militaryBorderLocationRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ \App\Models\Governorate::labelFor(data_get($row, 'governorate')) }}</td>
                            <td>{{ $fallback(data_get($row, 'location_name')) }}</td>
                            <td>{{ $fallback(data_get($row, 'address')) }}</td>
                            <td>{{ $fallback(data_get($row, 'nature')) }}</td>
	                            <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_MILITARY_BORDER_LOCATION_TYPE, data_get($row, 'location_type')) }}</td>
                            <td>{{ $fallback(data_get($row, 'start_date')) }}</td>
                            <td>{{ $fallback(data_get($row, 'end_date')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h5 class="mb-3">{{ __('app.applications.equipment_list_title') }}</h5>
        <div class="table-responsive">
            <table class="table table-striped mb-0 attached-form-readonly-table">
                <thead>
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>{{ __('app.scouting.location_name') }}</th>
                        <th>{{ __('app.applications.annex_fields.equipment_item') }}</th>
                        <th>{{ __('app.applications.annex_fields.serial_number') }}</th>
                        <th>{{ __('app.applications.annex_fields.quantity') }}</th>
                        <th>{{ __('app.applications.annex_fields.unit_value_usd') }}</th>
                        <th>{{ __('app.applications.annex_fields.total_value') }}</th>
                        <th>{{ __('app.applications.annex_fields.classification') }}</th>
                        <th>{{ __('app.applications.annex_fields.entry_method') }}</th>
                        <th>{{ __('app.applications.annex_fields.entry_point') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($militaryBorderEquipmentRows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $fallback(data_get($row, 'location_reference') ?: data_get($row, 'location_name')) }}</td>
                            <td>{{ $fallback(data_get($row, 'item') ?: data_get($row, 'equipment')) }}</td>
                            <td>{{ $fallback(data_get($row, 'serial_number')) }}</td>
                            <td>{{ $fallback(data_get($row, 'quantity')) }}</td>
                            <td>{{ $fallback(data_get($row, 'unit_value')) }}</td>
                            <td>{{ $fallback(data_get($row, 'total_value')) }}</td>
	                            <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_EQUIPMENT_CATEGORY, data_get($row, 'classification')) }}</td>
                            <td>{{ $translateOption('app.applications.entry_methods', data_get($row, 'entry_method')) }}</td>
	                            <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT, data_get($row, 'entry_point')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10">{{ __('app.documents.not_filled') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="text-end fw-semibold mt-3">{{ __('app.applications.equipment_total_label') }} {{ number_format($militaryBorderEquipmentTotal, 2) }} {{ __('app.applications.usd') }}</div>
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
