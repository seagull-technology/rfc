@php
    $metadata = $requestRecord->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $production = data_get($metadata, 'production', []);
    $projectNationalityOptions = collect(data_get($lookupOptions ?? [], 'nationalities.project', []));
    $personNationalityOptions = collect(data_get($lookupOptions ?? [], 'nationalities.person', []));
    $workCategoryOptions = collect(data_get($lookupOptions ?? [], 'work_categories', []));
    $governorateOptions = collect(data_get($lookupOptions ?? [], 'locations.governorates', []));
    $locationTypeOptions = collect(data_get($lookupOptions ?? [], 'locations.location_types', []));
    $locationTypesByGovernorate = (array) data_get($lookupOptions ?? [], 'locations.location_types_by_governorate', []);
    $locationTypeLabels = (array) data_get($lookupOptions ?? [], 'locations.location_type_labels', []);
    $legacyLocationTypeMap = [
        'public_site' => 'public_locations',
        'border_area' => 'border_areas',
        'archaeological' => 'archaeological_sites',
        'religious' => 'religious_sites',
        'syrian_camps' => 'syrian_refugee_camps',
        'palestinian_camps' => 'palestinian_refugee_camps',
        'private_site' => 'private_location',
    ];
    $defaultGovernorate = $governorateOptions->first()?->code ?? 'amman';
    $defaultLocationType = $locationTypeOptions->first()?->code ?? 'public_locations';
    $normalizeLocationType = static fn ($value): string => (string) ($legacyLocationTypeMap[(string) $value] ?? ($value ?: $defaultLocationType));
    $locationRows = collect(old('locations', data_get($metadata, 'locations', [[
        'governorate' => $defaultGovernorate,
        'location_type' => $defaultLocationType,
        'location_name' => '',
        'google_map_url' => '',
        'location_description' => '',
        'start_date' => '',
        'end_date' => '',
    ]])))
        ->map(function ($row) use ($defaultGovernorate, $defaultLocationType, $normalizeLocationType): array {
            $row = (array) $row;

            return [
                'governorate' => $row['governorate'] ?? $defaultGovernorate,
                'location_type' => $normalizeLocationType($row['location_type'] ?? $row['location_nature'] ?? $defaultLocationType),
                'location_name' => $row['location_name'] ?? '',
                'google_map_url' => $row['google_map_url'] ?? '',
                'location_description' => $row['location_description'] ?? '',
                'start_date' => $row['start_date'] ?? '',
                'end_date' => $row['end_date'] ?? '',
            ];
        })
        ->values()
        ->all();
    $crewRows = old('crew', data_get($metadata, 'crew', [['name' => '', 'job_title' => '', 'nationality' => 'jordanian', 'national_id_passport' => '']]));
    $locationTypeOptionsForGovernorate = static function ($governorateCode) use ($locationTypeOptions, $locationTypesByGovernorate) {
        $governorateCode = filled($governorateCode) ? (string) $governorateCode : null;

        if (! $governorateCode || ! isset($locationTypesByGovernorate[$governorateCode])) {
            return $locationTypeOptions;
        }

        return $locationTypeOptions->filter(fn ($locationType): bool => in_array($locationType->code, (array) $locationTypesByGovernorate[$governorateCode], true))->values();
    };
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="form-card text-start" data-scouting-wizard-form>
    @csrf
    <div class="row">
        <div class="section-form">
            <div class="p-4 px-2">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ __('app.applications.project_name') }}</label><span class="text-danger">*</span>
                            <input class="form-control bg-white" type="text" name="project_name" value="{{ old('project_name', $requestRecord->project_name) }}">
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ __('app.applications.project_nationality') }}</label><span class="text-danger">*</span>
                            <select name="project_nationality" class="form-control select2-basic-single">
                                @foreach ($projectNationalityOptions as $option)
                                    <option value="{{ $option->code }}" @selected(old('project_nationality', $requestRecord->project_nationality) === $option->code)>{{ $option->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card streamit-tabs-card">
                    <div class="card-body">
                        <div class="row gy-4">
                            <div class="col-lg-3">
                                <div class="streamit-verticle-tab">
                                    <div class="nav flex-column nav-pills me-0 me-lg-3 mb-3 mb-md-0 list-inline streamit-tabs" role="tablist" aria-orientation="vertical">
                                        @foreach ([
                                            'producer' => __('app.scouting.producer_tab'),
                                            'production_type' => __('app.scouting.production_type_tab'),
                                            'scout_dates' => __('app.scouting.scout_dates_tab'),
                                            'production_dates' => __('app.scouting.production_dates_tab'),
                                            'summary' => __('app.scouting.summary_tab'),
                                            'story' => __('app.scouting.story_tab'),
                                            'locations' => __('app.scouting.locations_tab'),
                                            'crew' => __('app.scouting.crew_tab'),
                                        ] as $tabKey => $tabLabel)
                                            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" type="button" data-bs-target="#{{ $tabKey }}_tab" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                                <span>{{ $tabLabel }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-9 edit-tab-content">
                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="producer_tab" role="tabpanel">
                                        <div class="row">
                                            @foreach ([
                                                'producer_name' => __('app.scouting.producer_name'),
                                                'production_company_name' => __('app.applications.production_company_name'),
                                                'contact_address' => __('app.applications.contact_address'),
                                                'producer_phone' => __('app.scouting.producer_phone'),
                                                'producer_mobile' => __('app.scouting.producer_mobile'),
                                                'producer_fax' => __('app.scouting.producer_fax'),
                                                'producer_email' => __('app.scouting.producer_email'),
                                                'producer_profile_url' => __('app.scouting.producer_profile_url'),
                                                'website_url' => __('app.scouting.website_url'),
                                                'liaison_name' => __('app.scouting.liaison_name'),
                                                'liaison_job_title' => __('app.scouting.liaison_job_title'),
                                                'liaison_email' => __('app.scouting.liaison_email'),
                                                'liaison_mobile' => __('app.scouting.liaison_mobile'),
                                            ] as $field => $label)
                                                <div class="col-lg-{{ in_array($field, ['producer_name', 'production_company_name', 'liaison_name'], true) ? '12' : '6' }}">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ $label }}</label><span class="text-danger">*</span>
                                                        <input type="{{ str_contains($field, 'email') ? 'email' : (str_contains($field, 'url') ? 'url' : 'text') }}" class="form-control" name="{{ $field }}" value="{{ old($field, data_get($producer, $field)) }}">
                                                    </div>
                                                </div>
                                            @endforeach
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label class="form-label">{{ __('app.scouting.producer_nationality') }}</label><span class="text-danger">*</span>
                                                    <select name="producer_nationality" class="form-control select2-basic-single">
                                                        @foreach ($personNationalityOptions as $option)
                                                            <option value="{{ $option->code }}" @selected(old('producer_nationality', data_get($producer, 'producer_nationality', 'jordanian')) === $option->code)>{{ $option->displayName() }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="production_type_tab" role="tabpanel">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('app.scouting.production_type') }}</label><span class="text-danger">*</span>
                                            <select name="production_types[]" class="form-select select2-basic-multiple" multiple>
                                                @foreach ($workCategoryOptions as $option)
                                                    <option value="{{ $option->code }}" @selected(in_array($option->code, old('production_types', data_get($production, 'types', [])), true))>{{ $option->displayName() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">{{ __('app.scouting.production_type_other') }}</label>
                                            <input type="text" class="form-control" name="production_type_other" value="{{ old('production_type_other', data_get($production, 'type_other')) }}">
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="scout_dates_tab" role="tabpanel">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label class="form-label">{{ __('app.scouting.scout_start_date') }}</label><span class="text-danger">*</span>
                                                    <input type="date" class="form-control" name="scout_start_date" value="{{ old('scout_start_date', optional($requestRecord->scout_start_date)->format('Y-m-d')) }}">
                                                </div>
                                            </div>
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label class="form-label">{{ __('app.scouting.scout_end_date') }}</label><span class="text-danger">*</span>
                                                    <input type="date" class="form-control" name="scout_end_date" value="{{ old('scout_end_date', optional($requestRecord->scout_end_date)->format('Y-m-d')) }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="production_dates_tab" role="tabpanel">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label class="form-label">{{ __('app.scouting.production_start_date') }}</label>
                                                    <input type="date" class="form-control" name="production_start_date" value="{{ old('production_start_date', optional($requestRecord->production_start_date)->format('Y-m-d')) }}">
                                                </div>
                                            </div>
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label class="form-label">{{ __('app.scouting.production_end_date') }}</label>
                                                    <input type="date" class="form-control" name="production_end_date" value="{{ old('production_end_date', optional($requestRecord->production_end_date)->format('Y-m-d')) }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="summary_tab" role="tabpanel">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('app.scouting.project_summary') }}</label><span class="text-danger">*</span>
                                            <textarea class="form-control" name="project_summary" rows="8">{{ old('project_summary', $requestRecord->project_summary) }}</textarea>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="story_tab" role="tabpanel">
                                        <div class="form-group">
                                            <label class="form-label">{{ __('app.scouting.story_text') }}</label>
                                            <textarea class="form-control" name="story_text" rows="5">{{ old('story_text', $requestRecord->story_text) }}</textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">{{ __('app.scouting.story_file') }}</label>
                                            <input class="form-control" type="file" name="story_file">
                                        </div>
                                        @if ($requestRecord->story_file_path)
                                            <a class="btn btn-outline-primary" href="{{ route('scouting-requests.story-file.download', $requestRecord) }}">{{ __('app.scouting.download_story_file') }}</a>
                                        @endif
                                    </div>

                                    <div class="tab-pane fade" id="locations_tab" role="tabpanel">
                                        <div class="d-flex justify-content-end py-3">
                                            <button type="button" class="btn btn-success" onclick="addScoutLocationRow()"><i class="fa-solid fa-plus me-2"></i>{{ __('app.scouting.add_location_action') }}</button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table align-middle" id="scoutLocationTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>{{ __('app.scouting.governorate') }}</th>
                                                        <th>{{ __('app.scouting.location_type') }}</th>
                                                        <th>{{ __('app.scouting.location_name') }}</th>
                                                        <th>{{ __('app.scouting.google_map_url') }}</th>
                                                        <th>{{ __('app.scouting.location_description') }}</th>
                                                        <th>{{ __('app.scouting.start_date') }}</th>
                                                        <th>{{ __('app.scouting.end_date') }}</th>
                                                        <th>{{ __('app.applications.actions') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($locationRows as $index => $location)
                                                        @php
                                                            $selectedGovernorate = (string) ($location['governorate'] ?? $defaultGovernorate);
                                                            $selectedLocationType = (string) ($location['location_type'] ?? $defaultLocationType);
                                                        @endphp
                                                        <tr>
                                                            <td class="row-number">{{ $index + 1 }}</td>
                                                            <td>
                                                                <select class="form-select" name="locations[{{ $index }}][governorate]" data-scout-governorate-select>
                                                                    @foreach ($governorateOptions as $option)
                                                                        <option value="{{ $option->code }}" @selected($selectedGovernorate === $option->code)>{{ $option->displayName() }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <select class="form-select" name="locations[{{ $index }}][location_type]" data-scout-location-type-select data-selected-type="{{ $selectedLocationType }}">
                                                                    @foreach ($locationTypeOptionsForGovernorate($selectedGovernorate) as $option)
                                                                        <option value="{{ $option->code }}" @selected($selectedLocationType === $option->code)>{{ $option->displayName() }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                            <td><input type="text" class="form-control" name="locations[{{ $index }}][location_name]" value="{{ $location['location_name'] ?? '' }}"></td>
                                                            <td><input type="text" class="form-control" name="locations[{{ $index }}][google_map_url]" value="{{ $location['google_map_url'] ?? '' }}"></td>
                                                            <td><input type="text" class="form-control" name="locations[{{ $index }}][location_description]" value="{{ $location['location_description'] ?? '' }}"></td>
                                                            <td><input type="date" class="form-control" name="locations[{{ $index }}][start_date]" value="{{ $location['start_date'] ?? '' }}"></td>
                                                            <td><input type="date" class="form-control" name="locations[{{ $index }}][end_date]" value="{{ $location['end_date'] ?? '' }}"></td>
                                                            <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeDynamicRow(this, '#scoutLocationTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="crew_tab" role="tabpanel">
                                        <div class="d-flex justify-content-end py-3">
                                            <button type="button" class="btn btn-success" onclick="addScoutCrewRow()"><i class="fa-solid fa-plus me-2"></i>{{ __('app.scouting.add_crew_action') }}</button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table align-middle" id="scoutCrewTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>{{ __('app.scouting.crew_name') }}</th>
                                                        <th>{{ __('app.scouting.crew_job_title') }}</th>
                                                        <th>{{ __('app.scouting.crew_nationality') }}</th>
                                                        <th>{{ __('app.scouting.crew_identity') }}</th>
                                                        <th>{{ __('app.applications.actions') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($crewRows as $index => $member)
                                                        <tr>
                                                            <td class="row-number">{{ $index + 1 }}</td>
                                                            <td><input type="text" class="form-control" name="crew[{{ $index }}][name]" value="{{ $member['name'] ?? '' }}"></td>
                                                            <td><input type="text" class="form-control" name="crew[{{ $index }}][job_title]" value="{{ $member['job_title'] ?? '' }}"></td>
                                                            <td>
                                                                <select class="form-select" name="crew[{{ $index }}][nationality]">
                                                                    @foreach ($personNationalityOptions as $option)
                                                                        <option value="{{ $option->code }}" @selected(($member['nationality'] ?? 'jordanian') === $option->code)>{{ $option->displayName() }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                            <td><input type="text" class="form-control" name="crew[{{ $index }}][national_id_passport]" value="{{ $member['national_id_passport'] ?? '' }}"></td>
                                                            <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeDynamicRow(this, '#scoutCrewTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions d-flex gap-2 flex-wrap justify-content-between">
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" name="previous" class="btn btn-dark request-wizard-previous action-button-previous btn-lg" data-scouting-wizard-previous>
                {{ app()->getLocale() === 'ar' ? 'السابق' : 'Previous' }}
            </button>
            <button type="button" name="next" class="btn btn-danger request-wizard-next action-button btn-lg" data-scouting-wizard-next>
                {{ app()->getLocale() === 'ar' ? 'التالي' : 'Next' }}
            </button>
        </div>
        <button type="submit" class="btn btn-danger d-flex align-items-center gap-2">
            <i class="ph-fill ph-floppy-disk-back"></i>
            <span>{{ $submitLabel }}</span>
        </button>
    </div>
</form>

@push('scripts')
    <script>
        const scoutGovernorateOptions = @json($governorateOptions->map(fn ($option) => ['value' => $option->code, 'label' => $option->displayName()])->values());
        const scoutLocationTypeOptions = @json($locationTypeOptions->map(fn ($option) => ['value' => $option->code, 'label' => $option->displayName()])->values());
        const scoutLocationTypesByGovernorate = @json($locationTypesByGovernorate);
        const scoutLocationTypeLabels = @json($locationTypeLabels);
        const scoutPersonNationalityOptions = @json($personNationalityOptions->map(fn ($option) => ['value' => $option->code, 'label' => $option->displayName()])->values());

        function escapeScoutHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function scoutOptionsHtml(options, selected) {
            return options.map(function (option) {
                const isSelected = selected && option.value === selected ? ' selected' : '';

                return '<option value="' + escapeScoutHtml(option.value) + '"' + isSelected + '>' + escapeScoutHtml(option.label) + '</option>';
            }).join('');
        }

        function scoutLocationTypeOptionsHtml(governorate, selected) {
            const allowedCodes = scoutLocationTypesByGovernorate[governorate] || scoutLocationTypeOptions.map(function (option) {
                return option.value;
            });

            return allowedCodes.map(function (code) {
                const label = scoutLocationTypeLabels[code] || code;
                const isSelected = selected && code === selected ? ' selected' : '';

                return '<option value="' + escapeScoutHtml(code) + '"' + isSelected + '>' + escapeScoutHtml(label) + '</option>';
            }).join('');
        }

        function syncScoutLocationTypeSelect(row) {
            const governorateSelect = row.querySelector('[data-scout-governorate-select]');
            const locationTypeSelect = row.querySelector('[data-scout-location-type-select]');

            if (!governorateSelect || !locationTypeSelect) {
                return;
            }

            const selectedType = locationTypeSelect.value || locationTypeSelect.dataset.selectedType || '';
            locationTypeSelect.innerHTML = scoutLocationTypeOptionsHtml(governorateSelect.value, selectedType);

            if (!locationTypeSelect.value && locationTypeSelect.options.length > 0) {
                locationTypeSelect.selectedIndex = 0;
            }

            locationTypeSelect.dataset.selectedType = locationTypeSelect.value;
        }

        function renumberRows(selector) {
            document.querySelectorAll(selector + ' tbody tr').forEach(function (row, index) {
                const cell = row.querySelector('.row-number');
                if (cell) {
                    cell.textContent = index + 1;
                }
            });
        }

        function removeDynamicRow(button, selector) {
            const table = document.querySelector(selector + ' tbody');
            if (!table || table.querySelectorAll('tr').length === 1) {
                return;
            }

            button.closest('tr').remove();
            renumberRows(selector);
        }

        function addScoutLocationRow() {
            const table = document.querySelector('#scoutLocationTable tbody');
            const index = table.querySelectorAll('tr').length;
            const row = document.createElement('tr');

            row.innerHTML = `
                <td class="row-number"></td>
                <td>
                    <select class="form-select" name="locations[${index}][governorate]" data-scout-governorate-select>
                        ${scoutOptionsHtml(scoutGovernorateOptions, scoutGovernorateOptions[0]?.value || '')}
                    </select>
                </td>
                <td>
                    <select class="form-select" name="locations[${index}][location_type]" data-scout-location-type-select>
                        ${scoutLocationTypeOptionsHtml(scoutGovernorateOptions[0]?.value || '', scoutLocationTypeOptions[0]?.value || '')}
                    </select>
                </td>
                <td><input type="text" class="form-control" name="locations[${index}][location_name]"></td>
                <td><input type="text" class="form-control" name="locations[${index}][google_map_url]"></td>
                <td><input type="text" class="form-control" name="locations[${index}][location_description]"></td>
                <td><input type="date" class="form-control" name="locations[${index}][start_date]"></td>
                <td><input type="date" class="form-control" name="locations[${index}][end_date]"></td>
                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeDynamicRow(this, '#scoutLocationTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
            `;

            table.appendChild(row);
            renumberRows('#scoutLocationTable');
        }

        function addScoutCrewRow() {
            const table = document.querySelector('#scoutCrewTable tbody');
            const index = table.querySelectorAll('tr').length;
            const row = document.createElement('tr');

            row.innerHTML = `
                <td class="row-number"></td>
                <td><input type="text" class="form-control" name="crew[${index}][name]"></td>
                <td><input type="text" class="form-control" name="crew[${index}][job_title]"></td>
                <td>
                    <select class="form-select" name="crew[${index}][nationality]">
                        ${scoutOptionsHtml(scoutPersonNationalityOptions, 'jordanian')}
                    </select>
                </td>
                <td><input type="text" class="form-control" name="crew[${index}][national_id_passport]"></td>
                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeDynamicRow(this, '#scoutCrewTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
            `;

            table.appendChild(row);
            renumberRows('#scoutCrewTable');
        }

        renumberRows('#scoutLocationTable');
        renumberRows('#scoutCrewTable');
        document.querySelectorAll('#scoutLocationTable tbody tr').forEach(syncScoutLocationTypeSelect);

        const scoutWizardForm = document.querySelector('[data-scouting-wizard-form]');
        const scoutWizardTabs = scoutWizardForm ? Array.from(scoutWizardForm.querySelectorAll('.streamit-tabs [data-bs-toggle="pill"]')) : [];
        const scoutWizardPanes = scoutWizardTabs.map(function (button) {
            const target = button.getAttribute('data-bs-target');

            return target ? scoutWizardForm.querySelector(target) : null;
        });
        const scoutPreviousButton = scoutWizardForm ? scoutWizardForm.querySelector('[data-scouting-wizard-previous]') : null;
        const scoutNextButton = scoutWizardForm ? scoutWizardForm.querySelector('[data-scouting-wizard-next]') : null;

        function scoutActiveTabIndex() {
            const activeIndex = scoutWizardTabs.findIndex(function (button) {
                return button.classList.contains('active');
            });

            return activeIndex >= 0 ? activeIndex : 0;
        }

        function updateScoutWizardButtons() {
            const activeIndex = scoutActiveTabIndex();
            const isFirst = activeIndex === 0;
            const isLast = activeIndex >= scoutWizardTabs.length - 1;

            if (scoutPreviousButton) {
                scoutPreviousButton.disabled = isFirst;
                scoutPreviousButton.classList.toggle('disabled', isFirst);
            }

            if (scoutNextButton) {
                scoutNextButton.disabled = isLast;
                scoutNextButton.classList.toggle('disabled', isLast);
            }
        }

        function scrollScoutWizardTop() {
            if (!scoutWizardForm) {
                return;
            }

            window.requestAnimationFrame(function () {
                const top = scoutWizardForm.getBoundingClientRect().top + window.pageYOffset - 24;

                window.scrollTo({
                    top: Math.max(0, top),
                    behavior: 'smooth',
                });
            });
        }

        function showScoutInvalidControl(control) {
            control.scrollIntoView({ behavior: 'smooth', block: 'center' });

            if (typeof control.reportValidity === 'function') {
                control.reportValidity();
            }

            if (typeof control.focus === 'function') {
                control.focus({ preventScroll: true });
            }
        }

        function scoutControlIsInvalid(control) {
            return !control.disabled
                && control.type !== 'hidden'
                && typeof control.checkValidity === 'function'
                && !control.checkValidity();
        }

        function validateActiveScoutPane() {
            const activePane = scoutWizardPanes[scoutActiveTabIndex()];
            const generalControls = scoutWizardForm
                ? Array.from(scoutWizardForm.querySelectorAll('input, select, textarea')).filter(function (control) {
                    return !control.closest('.tab-pane');
                })
                : [];
            const activeControls = activePane
                ? Array.from(activePane.querySelectorAll('input, select, textarea'))
                : [];
            const invalidControl = generalControls.concat(activeControls).find(scoutControlIsInvalid);

            if (invalidControl) {
                showScoutInvalidControl(invalidControl);

                return false;
            }

            return true;
        }

        function activateScoutTab(index, shouldScroll) {
            if (scoutWizardTabs.length === 0) {
                return;
            }

            const safeIndex = Math.max(0, Math.min(index, scoutWizardTabs.length - 1));

            scoutWizardTabs.forEach(function (button, buttonIndex) {
                const isActive = buttonIndex === safeIndex;
                const pane = scoutWizardPanes[buttonIndex];

                button.classList.toggle('active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');

                if (pane) {
                    pane.classList.toggle('active', isActive);
                    pane.classList.toggle('show', isActive);
                }
            });

            updateScoutWizardButtons();

            if (shouldScroll) {
                scrollScoutWizardTop();
            }
        }

        if (scoutPreviousButton) {
            scoutPreviousButton.addEventListener('click', function (event) {
                event.preventDefault();
                activateScoutTab(scoutActiveTabIndex() - 1, true);
            });
        }

        if (scoutNextButton) {
            scoutNextButton.addEventListener('click', function (event) {
                event.preventDefault();

                if (!validateActiveScoutPane()) {
                    return;
                }

                activateScoutTab(scoutActiveTabIndex() + 1, true);
            });
        }

        scoutWizardTabs.forEach(function (button, buttonIndex) {
            button.addEventListener('click', function (event) {
                if (buttonIndex > scoutActiveTabIndex() && !validateActiveScoutPane()) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();

                    return;
                }

                activateScoutTab(buttonIndex, false);
            });
        });

        updateScoutWizardButtons();

        document.addEventListener('change', function (event) {
            if (event.target.matches('[data-scout-governorate-select]')) {
                syncScoutLocationTypeSelect(event.target.closest('tr'));
            }

            if (event.target.matches('[data-scout-location-type-select]')) {
                event.target.dataset.selectedType = event.target.value;
            }
        });
    </script>
@endpush
