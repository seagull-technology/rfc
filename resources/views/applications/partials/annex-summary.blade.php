@php
    $annex = data_get($application->metadata ?? [], 'annex', []);
    $workContentSummary = data_get($annex, 'work_content_summary', []);
    $safetyGuidelines = data_get($annex, 'safety_guidelines', []);
    $airportFilming = data_get($annex, 'airport_filming', []);
    $castCrewRows = collect(data_get($annex, 'cast_crew', []));
    $filmingLocationRows = collect(data_get($annex, 'filming_locations', []));
    $importedEquipmentRows = collect(data_get($annex, 'imported_equipment', []));
    $militaryBorderEquipmentRows = collect(data_get($annex, 'military_border_equipment', []));
    $governmentalSceneRows = collect(data_get($annex, 'governmental_scenes', []));
    $tableClass = $tableClass ?? 'table table-striped mb-0';
    $fallback = static fn ($value): string => filled($value) ? (string) $value : __('app.dashboard.not_available');
    $translatedGovernorate = static function (?string $value) use ($fallback): string {
        if (! filled($value)) {
            return __('app.dashboard.not_available');
        }

        $key = 'app.scouting.governorate_options.'.$value;
        $translated = __($key);

        return $translated === $key ? $fallback($value) : $translated;
    };
    $hasAnnexData = filled(data_get($workContentSummary, 'synopsis'))
        || filled(data_get($workContentSummary, 'sensitive_notes'))
        || (bool) data_get($workContentSummary, 'confirmed')
        || $castCrewRows->isNotEmpty()
        || $filmingLocationRows->isNotEmpty()
        || (bool) data_get($safetyGuidelines, 'acknowledged')
        || filled(data_get($safetyGuidelines, 'notes'))
        || $importedEquipmentRows->isNotEmpty()
        || $militaryBorderEquipmentRows->isNotEmpty()
        || filled(data_get($airportFilming, 'airport_name'))
        || filled(data_get($airportFilming, 'area'))
        || filled(data_get($airportFilming, 'filming_date'))
        || filled(data_get($airportFilming, 'crew_count'))
        || filled(data_get($airportFilming, 'notes'))
        || $governmentalSceneRows->isNotEmpty();
@endphp

@if (! $hasAnnexData)
    <div class="text-muted">{{ __('app.applications.annex_form_empty_state') }}</div>
@else
    <div class="d-grid gap-4">
        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.work_content_summary') }}</h5>
            <div class="mb-2"><span class="fw-600">{{ __('app.applications.annex_fields.synopsis') }}:</span><span class="ms-2">{{ $fallback(data_get($workContentSummary, 'synopsis')) }}</span></div>
            <div class="mb-2"><span class="fw-600">{{ __('app.applications.annex_fields.sensitive_content_notes') }}:</span><span class="ms-2">{{ $fallback(data_get($workContentSummary, 'sensitive_notes')) }}</span></div>
            <div class="mb-0"><span class="fw-600">{{ __('app.applications.annex_fields.content_confirmation') }}</span><span class="ms-2 badge bg-{{ data_get($workContentSummary, 'confirmed') ? 'success' : 'secondary' }}">{{ data_get($workContentSummary, 'confirmed') ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}</span></div>
        </div>

        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.cast_crew') }}</h5>
            <div class="table-responsive">
                <table class="{{ $tableClass }}">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('app.applications.annex_fields.person_name') }}</th>
                            <th>{{ __('app.applications.annex_fields.role') }}</th>
                            <th>{{ __('app.applications.annex_fields.nationality') }}</th>
                            <th>{{ __('app.applications.annex_fields.identity_number') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($castCrewRows as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $fallback(data_get($row, 'name')) }}</td>
                                <td>{{ $fallback(data_get($row, 'role')) }}</td>
                                <td>{{ $fallback(data_get($row, 'nationality')) }}</td>
                                <td>{{ $fallback(data_get($row, 'identity_number')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.filming_locations') }}</h5>
            <div class="table-responsive">
                <table class="{{ $tableClass }}">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('app.scouting.governorate') }}</th>
                            <th>{{ __('app.scouting.location_name') }}</th>
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
                                <td>{{ $fallback(data_get($row, 'location_type')) }}</td>
                                <td>{{ $fallback(data_get($row, 'start_date')) }}</td>
                                <td>{{ $fallback(data_get($row, 'end_date')) }}</td>
                                <td>{{ $fallback(data_get($row, 'notes')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.safety_guidelines') }}</h5>
            <div class="mb-2"><span class="fw-600">{{ __('app.applications.annex_fields.safety_acknowledgement') }}</span><span class="ms-2 badge bg-{{ data_get($safetyGuidelines, 'acknowledged') ? 'success' : 'secondary' }}">{{ data_get($safetyGuidelines, 'acknowledged') ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}</span></div>
            <div class="mb-0"><span class="fw-600">{{ __('app.applications.annex_fields.safety_notes') }}:</span><span class="ms-2">{{ $fallback(data_get($safetyGuidelines, 'notes')) }}</span></div>
        </div>

        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.imported_equipment') }}</h5>
            <div class="table-responsive">
                <table class="{{ $tableClass }}">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('app.applications.annex_fields.equipment_item') }}</th>
                            <th>{{ __('app.applications.annex_fields.serial_number') }}</th>
                            <th>{{ __('app.applications.annex_fields.quantity') }}</th>
                            <th>{{ __('app.applications.annex_fields.origin_country') }}</th>
                            <th>{{ __('app.applications.annex_fields.entry_point') }}</th>
                            <th>{{ __('app.applications.annex_fields.arrival_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($importedEquipmentRows as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $fallback(data_get($row, 'item')) }}</td>
                                <td>{{ $fallback(data_get($row, 'serial_number')) }}</td>
                                <td>{{ $fallback(data_get($row, 'quantity')) }}</td>
                                <td>{{ $fallback(data_get($row, 'origin_country')) }}</td>
                                <td>{{ $fallback(data_get($row, 'entry_point')) }}</td>
                                <td>{{ $fallback(data_get($row, 'arrival_date')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.military_border_equipment') }}</h5>
            <div class="table-responsive">
                <table class="{{ $tableClass }}">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('app.scouting.location_name') }}</th>
                            <th>{{ __('app.applications.annex_fields.equipment_item') }}</th>
                            <th>{{ __('app.applications.annex_fields.security_need') }}</th>
                            <th>{{ __('app.applications.annex_fields.notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($militaryBorderEquipmentRows as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $fallback(data_get($row, 'location_name')) }}</td>
                                <td>{{ $fallback(data_get($row, 'equipment')) }}</td>
                                <td>{{ $fallback(data_get($row, 'security_need')) }}</td>
                                <td>{{ $fallback(data_get($row, 'notes')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">{{ __('app.applications.annex_form_empty_state') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.airport_filming') }}</h5>
            <div class="row g-2">
                <div class="col-md-6"><span class="fw-600">{{ __('app.applications.annex_fields.airport_name') }}:</span><span class="ms-2">{{ $fallback(data_get($airportFilming, 'airport_name')) }}</span></div>
                <div class="col-md-6"><span class="fw-600">{{ __('app.applications.annex_fields.airport_area') }}:</span><span class="ms-2">{{ $fallback(data_get($airportFilming, 'area')) }}</span></div>
                <div class="col-md-6"><span class="fw-600">{{ __('app.applications.annex_fields.filming_date') }}:</span><span class="ms-2">{{ $fallback(data_get($airportFilming, 'filming_date')) }}</span></div>
                <div class="col-md-6"><span class="fw-600">{{ __('app.applications.annex_fields.airport_crew_count') }}:</span><span class="ms-2">{{ $fallback(data_get($airportFilming, 'crew_count')) }}</span></div>
                <div class="col-12"><span class="fw-600">{{ __('app.applications.annex_fields.notes') }}:</span><span class="ms-2">{{ $fallback(data_get($airportFilming, 'notes')) }}</span></div>
            </div>
        </div>

        <div>
            <h5 class="mb-3">{{ __('app.applications.annex_sections.governmental_scenes') }}</h5>
            <div class="table-responsive">
                <table class="{{ $tableClass }}">
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
    </div>
@endif
