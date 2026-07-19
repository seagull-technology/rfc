@php
    $annex = $annexPayload ?? data_get($application->metadata ?? [], 'annex', []);
    $productionTerms = data_get($annex, 'production_terms', []);
    $ministryInteriorPersonalDetails = data_get($annex, 'ministry_interior_personal_details', []);
    $workContentSummary = data_get($annex, 'work_content_summary', []);
    $safetyGuidelines = data_get($annex, 'safety_guidelines', []);
    $airportFilming = data_get($annex, 'airport_filming', []);
    $castCrewRows = collect(data_get($annex, 'cast_crew', []));
    $filmingLocationRows = collect(data_get($annex, 'filming_locations', []));
    $specialLocationRequirementRows = collect(data_get($annex, 'special_location_requirements', []));
    $equipmentTravelerRows = collect(data_get($annex, 'equipment_travelers', []));
    $importedEquipmentRows = collect(data_get($annex, 'imported_equipment', []));
    $shippingEquipmentRows = $importedEquipmentRows
        ->filter(fn ($row): bool => data_get($row, 'transport_group', 'shipping') !== 'traveler')
        ->values();
    $travelerEquipmentRows = $importedEquipmentRows
        ->filter(fn ($row): bool => data_get($row, 'transport_group') === 'traveler')
        ->values();
    $publicSecuritySupportRows = collect(data_get($annex, 'public_security_support', []));
    $militarySupportRows = collect(data_get($annex, 'military_support', []));
    $supportAuthorityLabel = static fn ($value): string => match ((string) $value) {
        'public_security' => __('app.applications.support_authorities.public_security'),
        'military' => __('app.applications.support_authorities.military'),
        default => filled($value) ? (string) $value : __('app.dashboard.not_available'),
    };
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
    $supportRequirementLabel = static function ($value) use ($formLookupLabel): string {
        if (! filled($value)) {
            return __('app.dashboard.not_available');
        }

        $label = $formLookupLabel(\App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $value);
        $generatedFallback = str((string) $value)->replace('_', ' ')->headline()->toString();

        return $label === $generatedFallback ? (string) $value : $label;
    };
    $locationSupportRequirementSummaryForRow = static function (array $row) use ($publicSecuritySupportRows, $militarySupportRows, $supportRequirementLabel, $supportAuthorityLabel): string {
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
            return __('app.dashboard.not_available');
        }

        return $supportRequirements
            ->map(function ($supportRequirement) use ($supportRequirementLabel, $supportAuthorityLabel): string {
                return collect([
                    $supportAuthorityLabel(data_get($supportRequirement, 'authority')),
                    $supportRequirementLabel(data_get($supportRequirement, 'requirement')),
                    data_get($supportRequirement, 'date'),
                    trim((string) data_get($supportRequirement, 'time_from').' - '.(string) data_get($supportRequirement, 'time_to')),
                    data_get($supportRequirement, 'notes'),
                ])->filter(fn ($value): bool => filled($value) && $value !== __('app.dashboard.not_available'))->join(' | ');
            })
            ->filter(fn (string $summary): bool => filled($summary))
            ->join(' ؛ ') ?: __('app.dashboard.not_available');
    };
    $hasFilledValuesForAnnex = static fn ($values): bool => collect(\Illuminate\Support\Arr::dot((array) $values))
        ->contains(fn ($value): bool => filled($value));
    $rowsHaveDataForAnnex = static fn ($rows): bool => collect((array) $rows)
        ->contains(fn ($row): bool => is_array($row) && $hasFilledValuesForAnnex($row));
    $sectionHasData = [
        'production_terms' => (bool) data_get($productionTerms, 'accepted'),
        'ministry_interior_personal_details' => $hasFilledValuesForAnnex($ministryInteriorPersonalDetails),
        'work_content_summary' => $hasFilledValuesForAnnex($workContentSummary),
        'cast_crew' => $rowsHaveDataForAnnex($castCrewRows->all()),
        'filming_locations' => $rowsHaveDataForAnnex($filmingLocationRows->all()),
        'special_location_requirements' => $rowsHaveDataForAnnex($specialLocationRequirementRows->all()),
        'safety_guidelines' => (bool) data_get($safetyGuidelines, 'acknowledged') || filled(data_get($safetyGuidelines, 'notes')),
        'imported_equipment' => $rowsHaveDataForAnnex($importedEquipmentRows->all()) || $rowsHaveDataForAnnex($equipmentTravelerRows->all()),
        'public_security_support' => $rowsHaveDataForAnnex($publicSecuritySupportRows->all()),
        'military_support' => $rowsHaveDataForAnnex($militarySupportRows->all()),
        'airport_filming' => $hasFilledValuesForAnnex($airportFilming),
        'airport_people' => $rowsHaveDataForAnnex($airportPeopleRows->all()),
        'governmental_scenes' => $rowsHaveDataForAnnex($governmentalSceneRows->all()),
    ];
    $sectionVisible = static fn (string $section): bool => ($onlySections->isEmpty() || $onlySections->contains($section))
        && (! $hideEmptySections || (bool) ($sectionHasData[$section] ?? false));
    $filmingLocationsVisible = $sectionVisible('filming_locations')
        || $sectionVisible('special_location_requirements')
        || $sectionVisible('public_security_support')
        || $sectionVisible('military_support');
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
            .annex-support-schedule-table,
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
        @if ($sectionVisible('production_terms'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.production_terms') }}</h5>
            @include('applications.partials.production-terms-form', [
                'productionTerms' => $productionTerms,
                'productionTermsReadOnly' => true,
            ])
        </div>
        @endif

        @if ($sectionVisible('ministry_interior_personal_details'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.ministry_interior_personal_details') }}</h5>
            @include('applications.partials.ministry-interior-personal-details-form', [
                'ministryInteriorPersonalDetails' => $ministryInteriorPersonalDetails,
                'ministryInteriorPersonalDetailsReadOnly' => true,
                'ministryInteriorPersonalDetailsIdPrefix' => 'ministry_interior_personal_details_summary',
            ])
        </div>
        @endif

        @if ($sectionVisible('work_content_summary'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.work_content_summary') }}</h5>
            <div class="mb-2"><span class="fw-600">{{ __('app.applications.annex_fields.synopsis') }}:</span><span class="ms-2">{{ $fallback(data_get($workContentSummary, 'synopsis')) }}</span></div>
            <div class="mb-2"><span class="fw-600">{{ __('app.applications.annex_fields.work_summary_english_attachment') }}:</span><span class="ms-2">{{ $fallback(data_get($workContentSummary, 'attachment_name')) }}</span></div>
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
	                        <col style="width: 170px">
	                        <col style="width: 220px">
	                        <col style="width: 180px">
	                        <col style="width: 130px">
	                        <col style="width: 150px">
	                        <col style="width: 220px">
	                        <col style="width: 220px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
	                            <th>{{ __('app.applications.annex_fields.nationality') }}</th>
                            <th>{{ __('app.applications.annex_fields.person_name') }}</th>
	                            <th>{{ __('app.applications.annex_fields.role') }}</th>
	                            <th>{{ __('app.applications.annex_fields.gender') }}</th>
	                            <th>{{ __('app.applications.annex_fields.birth_date') }}</th>
	                            <th>{{ __('app.applications.annex_fields.identity_number') }}</th>
	                            <th>{{ __('app.applications.annex_fields.passport_image') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($castCrewRows as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
	                                <td>{{ $nationalityLabel(data_get($row, 'nationality')) }}</td>
                                <td>{{ $fallback(data_get($row, 'name')) }}</td>
	                                <td>{{ $fallback(data_get($row, 'role')) }}</td>
	                                <td>{{ $genderLabel(data_get($row, 'gender')) }}</td>
	                                <td>{{ $fallback(data_get($row, 'birth_date')) }}</td>
	                                <td>{{ $fallback(data_get($row, 'identity_number')) }}</td>
	                                <td>{{ $fallback(data_get($row, 'passport_image_name')) }}</td>
                            </tr>
                        @empty
	                            <tr><td colspan="8">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($filmingLocationsVisible)
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
                        <col style="width: 240px">
                        <col style="width: 300px">
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
                            <th>{{ __('app.applications.special_requirement') }}</th>
                            <th>{{ __('app.applications.location_support_requirements_title') }}</th>
                            <th>{{ __('app.scouting.start_date') }}</th>
                            <th>{{ __('app.scouting.end_date') }}</th>
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
                                <td>{{ $specialLocationRequirementLabelsForRow((array) $row) }}</td>
                                <td>{{ $locationSupportRequirementSummaryForRow((array) $row) }}</td>
                                <td>{{ $fallback(data_get($row, 'start_date')) }}</td>
                                <td>{{ $fallback(data_get($row, 'end_date')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
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

        @if ($sectionVisible('imported_equipment'))
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.imported_equipment') }}</h5>
            @if ($shippingEquipmentRows->isNotEmpty())
                <div class="mb-3">
                    <span class="fw-600">{{ __('app.applications.shipping_equipment_acknowledgement') }}</span>
                    <span class="ms-2 badge bg-{{ data_get($annex, 'shipping_equipment_acknowledged') ? 'success' : 'secondary' }}">
                        {{ data_get($annex, 'shipping_equipment_acknowledged') ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}
                    </span>
                </div>
            @endif
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }} annex-imported-equipment-table">
                    <colgroup>
                        <col style="width: 64px">
                        <col style="width: 220px">
                        <col style="width: 170px">
                        <col style="width: 170px">
                        <col style="width: 170px">
                        <col style="width: 170px">
                        <col style="width: 170px">
                        <col style="width: 160px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
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
                            <tr><td colspan="8">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <h6 class="mt-4 mb-3">{{ __('app.applications.travelers_list_title') }}</h6>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }}">
                    <thead>
                        <tr>
                            <th>#</th>
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
                            <tr><td colspan="7">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <h6 class="mt-4 mb-3">{{ __('app.applications.equipment_list_title') }}</h6>
            <div class="table-responsive rounded py-4 annex-summary-table-scroll">
                <table class="{{ $annexTableClass }}">
                    <thead>
                        <tr>
                            <th>#</th>
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
                            <tr><td colspan="9">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
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
