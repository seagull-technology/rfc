@php
    $annex = data_get($application->metadata ?? [], 'annex', []);
    $workContentSummary = data_get($annex, 'work_content_summary', []);
    $safetyGuidelines = data_get($annex, 'safety_guidelines', []);
    $airportFilming = data_get($annex, 'airport_filming', []);
    $castCrewRows = collect(data_get($annex, 'cast_crew', []));
    $filmingLocationRows = collect(data_get($annex, 'filming_locations', []));
    $specialLocationRequirementRows = collect(data_get($annex, 'special_location_requirements', []));
    $equipmentFlightRows = collect(data_get($annex, 'equipment_flights', []));
    $equipmentTravelerRows = collect(data_get($annex, 'equipment_travelers', []));
    $importedEquipmentRows = collect(data_get($annex, 'imported_equipment', []));
    $militaryBorderLocationRows = collect(data_get($annex, 'military_border_locations', []));
    $militaryBorderEquipmentRows = collect(data_get($annex, 'military_border_equipment', []));
    $airportPeopleRows = collect(data_get($annex, 'airport_people', []));
    $governmentalSceneRows = collect(data_get($annex, 'governmental_scenes', []));
    $onlySections = collect($onlySections ?? [])
        ->filter(fn ($section): bool => filled($section))
        ->map(fn ($section): string => (string) $section)
        ->unique()
        ->values();
    $hideEmptySections = (bool) ($hideEmptySections ?? false);
    $baseTableClass = trim($tableClass ?? 'table table-striped mb-0');
    $annexTableClass = str_contains($baseTableClass, 'annex-summary-table')
        ? $baseTableClass
        : trim($baseTableClass.' annex-summary-table');
	    $fallback = static fn ($value): string => filled($value) ? (string) $value : __('app.dashboard.not_available');
	    $nationalityLabel = static fn ($value): string => filled($value) ? \App\Models\Nationality::labelFor((string) $value) : __('app.dashboard.not_available');
	    $genderLabel = static function ($value) use ($fallback): string {
	        if (! filled($value)) {
	            return __('app.dashboard.not_available');
	        }

	        $translationKey = 'app.auth.gender_options.'.$value;
	        $translation = __($translationKey);

	        return $translation === $translationKey ? $fallback($value) : $translation;
	    };
		    $translatedGovernorate = static fn (?string $value): string => \App\Models\Governorate::labelFor($value);
    $translatedLocationType = static fn (?string $value): string => \App\Models\FilmingLocationType::labelFor($value);
    $formLookupLabel = static fn (string $type, $value): string => filled($value)
        ? \App\Models\FormLookupOption::labelFor($type, (string) $value)
        : __('app.dashboard.not_available');
    $hasFilledValuesForAnnex = static fn ($values): bool => collect(\Illuminate\Support\Arr::dot((array) $values))
        ->contains(fn ($value): bool => filled($value));
    $rowsHaveDataForAnnex = static fn ($rows): bool => collect((array) $rows)
        ->contains(fn ($row): bool => is_array($row) && $hasFilledValuesForAnnex($row));
    $sectionHasData = [
        'work_content_summary' => $hasFilledValuesForAnnex($workContentSummary),
        'cast_crew' => $rowsHaveDataForAnnex($castCrewRows->all()),
        'filming_locations' => $rowsHaveDataForAnnex($filmingLocationRows->all()),
        'special_location_requirements' => $rowsHaveDataForAnnex($specialLocationRequirementRows->all()),
        'safety_guidelines' => (bool) data_get($safetyGuidelines, 'acknowledged') || filled(data_get($safetyGuidelines, 'notes')),
        'equipment_flights' => $rowsHaveDataForAnnex($equipmentFlightRows->all()),
        'equipment_travelers' => $rowsHaveDataForAnnex($equipmentTravelerRows->all()),
        'imported_equipment' => $rowsHaveDataForAnnex($importedEquipmentRows->all()),
        'military_border_locations' => $rowsHaveDataForAnnex($militaryBorderLocationRows->all()),
        'military_border_equipment' => $rowsHaveDataForAnnex($militaryBorderEquipmentRows->all()),
        'airport_filming' => $hasFilledValuesForAnnex($airportFilming),
        'airport_people' => $rowsHaveDataForAnnex($airportPeopleRows->all()),
        'governmental_scenes' => $rowsHaveDataForAnnex($governmentalSceneRows->all()),
    ];
    $sectionVisible = static fn (string $section): bool => ($onlySections->isEmpty() || $onlySections->contains($section))
        && (! $hideEmptySections || (bool) ($sectionHasData[$section] ?? false));
    $hasAnnexData = collect($sectionHasData)->contains(fn (bool $hasData): bool => $hasData);
    $hasVisibleAnnexData = collect(array_keys($sectionHasData))->contains(fn (string $section): bool => $sectionVisible($section));
@endphp

@once
    @push('styles')
        <style>
            .annex-summary-table-scroll {
                overflow-x: auto;
            }

            .annex-summary-table {
                table-layout: fixed;
                width: 100%;
            }

            .annex-summary-table th,
            .annex-summary-table td {
                vertical-align: top;
                white-space: normal;
                word-break: break-word;
            }

            .annex-cast-crew-table,
            .annex-military-border-table,
            .annex-governmental-scenes-table {
                min-width: 920px;
            }

            .annex-filming-locations-table,
            .annex-imported-equipment-table,
            .annex-airport-people-table {
                min-width: 1100px;
            }
        </style>
    @endpush
@endonce

@if (! $hasAnnexData || ! $hasVisibleAnnexData)
    <div class="text-muted">{{ __('app.applications.annex_form_empty_state') }}</div>
@else
    <div class="d-grid gap-4">
        @if ($sectionVisible('work_content_summary'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.work_content_summary') }}</h5>
            <div class="mb-2"><span class="fw-600">{{ __('app.applications.annex_fields.synopsis') }}:</span><span class="ms-2">{{ $fallback(data_get($workContentSummary, 'synopsis')) }}</span></div>
            <div class="mb-2"><span class="fw-600">{{ __('app.applications.annex_fields.sensitive_content_notes') }}:</span><span class="ms-2">{{ $fallback(data_get($workContentSummary, 'sensitive_notes')) }}</span></div>
            <div class="mb-0"><span class="fw-600">{{ __('app.applications.annex_fields.content_confirmation') }}</span><span class="ms-2 badge bg-{{ data_get($workContentSummary, 'confirmed') ? 'success' : 'secondary' }}">{{ data_get($workContentSummary, 'confirmed') ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}</span></div>
        </div>
        @endif

        @if ($sectionVisible('cast_crew'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.cast_crew') }}</h5>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-cast-crew-table">
                    <colgroup>
                        <col style="width: 64px">
	                        <col style="width: 220px">
	                        <col style="width: 180px">
	                        <col style="width: 170px">
	                        <col style="width: 130px">
	                        <col style="width: 150px">
	                        <col style="width: 220px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
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
	                            <tr><td colspan="7">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($sectionVisible('filming_locations'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.filming_locations') }}</h5>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-filming-locations-table">
                    <colgroup>
                        <col style="width: 64px">
                        <col style="width: 150px">
                        <col style="width: 220px">
                        <col style="width: 220px">
                        <col style="width: 180px">
                        <col style="width: 170px">
                        <col style="width: 140px">
                        <col style="width: 140px">
                        <col style="width: 220px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
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
                                <td>{{ $translatedGovernorate(data_get($row, 'governorate')) }}</td>
                                <td>{{ $fallback(data_get($row, 'location_name')) }}</td>
                                <td>{{ $fallback(data_get($row, 'address')) }}</td>
                                <td>{{ $fallback(data_get($row, 'nature')) }}</td>
                                <td>{{ $translatedLocationType(data_get($row, 'location_type')) }}</td>
                                <td>{{ $fallback(data_get($row, 'start_date')) }}</td>
                                <td>{{ $fallback(data_get($row, 'end_date')) }}</td>
                                <td>{{ $fallback(data_get($row, 'notes')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($sectionVisible('special_location_requirements'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.special_location_requirements_title') }}</h5>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-military-border-table">
                    <colgroup>
                        <col style="width: 260px">
                        <col style="width: 320px">
                        <col style="width: 320px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>{{ __('app.applications.special_requirement') }}</th>
                            <th>{{ __('app.applications.locations') }}</th>
                            <th>{{ __('app.applications.annex_fields.notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($specialLocationRequirementRows as $key => $row)
                            <tr>
                                <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $key) }}</td>
                                <td>{{ collect((array) data_get($row, 'locations', []))->filter()->join(', ') ?: __('app.dashboard.not_available') }}</td>
                                <td>{{ $fallback(data_get($row, 'notes')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($sectionVisible('safety_guidelines'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.safety_guidelines') }}</h5>
            <div class="mb-2"><span class="fw-600">{{ __('app.applications.annex_fields.safety_acknowledgement') }}</span><span class="ms-2 badge bg-{{ data_get($safetyGuidelines, 'acknowledged') ? 'success' : 'secondary' }}">{{ data_get($safetyGuidelines, 'acknowledged') ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}</span></div>
            <div class="mb-0"><span class="fw-600">{{ __('app.applications.annex_fields.safety_notes') }}:</span><span class="ms-2">{{ $fallback(data_get($safetyGuidelines, 'notes')) }}</span></div>
        </div>
        @endif

        @if ($sectionVisible('equipment_flights'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.flight_details_title') }}</h5>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-imported-equipment-table">
                    <colgroup>
                        <col style="width: 64px">
                        <col style="width: 140px">
                        <col style="width: 160px">
                        <col style="width: 140px">
                        <col style="width: 130px">
                        <col style="width: 180px">
                        <col style="width: 180px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
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
                                <td>{{ $fallback(data_get($row, 'flight_type')) }}</td>
                                <td>{{ $fallback(data_get($row, 'flight_number')) }}</td>
                                <td>{{ $fallback(data_get($row, 'flight_date')) }}</td>
                                <td>{{ $fallback(data_get($row, 'flight_time')) }}</td>
                                <td>{{ $fallback(data_get($row, 'departure_city')) }}</td>
                                <td>{{ $fallback(data_get($row, 'arrival_city')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($sectionVisible('equipment_travelers'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.travelers_list_title') }}</h5>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-imported-equipment-table">
                    <thead>
                        <tr>
                            <th>#</th>
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
                            <tr><td colspan="6">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($sectionVisible('imported_equipment'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.imported_equipment') }}</h5>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-imported-equipment-table">
                    <colgroup>
                        <col style="width: 64px">
                        <col style="width: 220px">
                        <col style="width: 170px">
                        <col style="width: 170px">
                        <col style="width: 170px">
                        <col style="width: 120px">
                        <col style="width: 170px">
                        <col style="width: 170px">
                        <col style="width: 160px">
                        <col style="width: 160px">
                        <col style="width: 160px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
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
                            <tr><td colspan="11">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($sectionVisible('military_border_locations'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.military_border_locations_title') }}</h5>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-filming-locations-table">
                    <thead>
                        <tr>
                            <th>#</th>
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
                                <td>{{ $translatedGovernorate(data_get($row, 'governorate')) }}</td>
                                <td>{{ $fallback(data_get($row, 'location_name')) }}</td>
                                <td>{{ $fallback(data_get($row, 'address')) }}</td>
                                <td>{{ $fallback(data_get($row, 'nature')) }}</td>
                                <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_MILITARY_BORDER_LOCATION_TYPE, data_get($row, 'location_type')) }}</td>
                                <td>{{ $fallback(data_get($row, 'start_date')) }}</td>
                                <td>{{ $fallback(data_get($row, 'end_date')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($sectionVisible('military_border_equipment'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.military_border_equipment') }}</h5>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-military-border-table">
                    <colgroup>
                        <col style="width: 64px">
                        <col style="width: 220px">
                        <col style="width: 220px">
                        <col style="width: 220px">
                        <col style="width: 120px">
                        <col style="width: 160px">
                        <col style="width: 160px">
                        <col style="width: 180px">
                        <col style="width: 180px">
                        <col style="width: 260px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
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
                                <td>{{ $fallback(data_get($row, 'entry_method')) }}</td>
                                <td>{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT, data_get($row, 'entry_point')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($sectionVisible('airport_filming') || $sectionVisible('airport_people'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.airport_filming') }}</h5>
            @if ($sectionVisible('airport_filming'))
            <div class="row g-2">
                <div class="col-md-6"><span class="fw-600">{{ __('app.applications.annex_fields.airport_name') }}:</span><span class="ms-2">{{ $formLookupLabel(\App\Models\FormLookupOption::TYPE_AIRPORT, data_get($airportFilming, 'airport_name')) }}</span></div>
                <div class="col-md-6"><span class="fw-600">{{ __('app.applications.annex_fields.airport_area') }}:</span><span class="ms-2">{{ $fallback(data_get($airportFilming, 'area')) }}</span></div>
                <div class="col-md-6"><span class="fw-600">{{ __('app.applications.annex_fields.filming_date') }}:</span><span class="ms-2">{{ $fallback(data_get($airportFilming, 'filming_date')) }}</span></div>
                <div class="col-md-6"><span class="fw-600">{{ __('app.applications.annex_fields.airport_crew_count') }}:</span><span class="ms-2">{{ $fallback(data_get($airportFilming, 'crew_count')) }}</span></div>
                <div class="col-12"><span class="fw-600">{{ __('app.applications.annex_fields.notes') }}:</span><span class="ms-2">{{ $fallback(data_get($airportFilming, 'notes')) }}</span></div>
            </div>
            @endif
            @if ($sectionVisible('airport_people'))
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-airport-people-table">
                    <thead>
                        <tr>
                            <th>#</th>
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
                            <tr><td colspan="9">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        @endif

        @if ($sectionVisible('governmental_scenes'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.governmental_scenes') }}</h5>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-governmental-scenes-table">
                    <colgroup>
                        <col style="width: 64px">
                        <col style="width: 220px">
                        <col style="width: 220px">
                        <col style="width: 300px">
                        <col style="width: 160px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
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
                            <tr><td colspan="5">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
@endif
