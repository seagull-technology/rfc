@php
    $metadata = $requestRecord->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $responsiblePerson = data_get($metadata, 'responsible_person', []);
    $production = data_get($metadata, 'production', []);
    $locationRows = old('locations', data_get($metadata, 'locations', [['governorate' => 'amman', 'location_name' => '', 'google_map_url' => '', 'location_nature' => 'public_site', 'start_date' => '', 'end_date' => '']]));
    $crewRows = old('crew', data_get($metadata, 'crew', [['name' => '', 'job_title' => '', 'nationality' => 'jordanian', 'national_id_passport' => '']]));
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="form-card text-start">
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
                                @foreach (['jordanian', 'international'] as $option)
                                    <option value="{{ $option }}" @selected(old('project_nationality', $requestRecord->project_nationality) === $option)>{{ __('app.applications.project_nationalities.'.$option) }}</option>
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
                                            'responsible' => __('app.scouting.responsible_person_tab'),
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
                                                        @foreach (['jordanian', 'international'] as $option)
                                                            <option value="{{ $option }}" @selected(old('producer_nationality', data_get($producer, 'producer_nationality', 'jordanian')) === $option)>{{ __('app.applications.project_nationalities.'.$option) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="responsible_tab" role="tabpanel">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label class="form-label">{{ __('app.scouting.responsible_person_name') }}</label><span class="text-danger">*</span>
                                                    <input type="text" class="form-control" name="responsible_person_name" value="{{ old('responsible_person_name', data_get($responsiblePerson, 'name')) }}">
                                                </div>
                                            </div>
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label class="form-label">{{ __('app.scouting.responsible_person_nationality') }}</label><span class="text-danger">*</span>
                                                    <select name="responsible_person_nationality" class="form-control select2-basic-single">
                                                        @foreach (['jordanian', 'international'] as $option)
                                                            <option value="{{ $option }}" @selected(old('responsible_person_nationality', data_get($responsiblePerson, 'nationality', 'jordanian')) === $option)>{{ __('app.applications.project_nationalities.'.$option) }}</option>
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
                                                @foreach (['feature_film', 'short_film', 'animation', 'music_video', 'television', 'commercial', 'documentary', 'other'] as $option)
                                                    <option value="{{ $option }}" @selected(in_array($option, old('production_types', data_get($production, 'types', [])), true))>{{ __('app.scouting.production_type_options.'.$option) }}</option>
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
                                        <table class="table align-middle" id="scoutLocationTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>{{ __('app.scouting.governorate') }}</th>
                                                    <th>{{ __('app.scouting.location_name') }}</th>
                                                    <th>{{ __('app.scouting.google_map_url') }}</th>
                                                    <th>{{ __('app.scouting.location_nature') }}</th>
                                                    <th>{{ __('app.scouting.start_date') }}</th>
                                                    <th>{{ __('app.scouting.end_date') }}</th>
                                                    <th>{{ __('app.applications.actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($locationRows as $index => $location)
                                                    <tr>
                                                        <td class="row-number">{{ $index + 1 }}</td>
                                                        <td>
                                                            <select class="form-select" name="locations[{{ $index }}][governorate]">
                                                                @foreach (['amman', 'irbid', 'zarqa', 'balqa', 'madaba', 'karak', 'tafilah', 'maan', 'aqaba', 'mafraq', 'jerash', 'ajloun'] as $option)
                                                                    <option value="{{ $option }}" @selected(($location['governorate'] ?? 'amman') === $option)>{{ __('app.scouting.governorate_options.'.$option) }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td><input type="text" class="form-control" name="locations[{{ $index }}][location_name]" value="{{ $location['location_name'] ?? '' }}"></td>
                                                        <td><input type="text" class="form-control" name="locations[{{ $index }}][google_map_url]" value="{{ $location['google_map_url'] ?? '' }}"></td>
                                                        <td>
                                                            <select class="form-select" name="locations[{{ $index }}][location_nature]">
                                                                @foreach (['public_site', 'border_area', 'archaeological', 'religious', 'schools', 'universities', 'museums', 'syrian_camps', 'palestinian_camps', 'petra', 'reserves', 'valleys', 'private_site'] as $option)
                                                                    <option value="{{ $option }}" @selected(($location['location_nature'] ?? 'public_site') === $option)>{{ __('app.scouting.location_nature_options.'.$option) }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td><input type="date" class="form-control" name="locations[{{ $index }}][start_date]" value="{{ $location['start_date'] ?? '' }}"></td>
                                                        <td><input type="date" class="form-control" name="locations[{{ $index }}][end_date]" value="{{ $location['end_date'] ?? '' }}"></td>
                                                        <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeDynamicRow(this, '#scoutLocationTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="tab-pane fade" id="crew_tab" role="tabpanel">
                                        <div class="d-flex justify-content-end py-3">
                                            <button type="button" class="btn btn-success" onclick="addScoutCrewRow()"><i class="fa-solid fa-plus me-2"></i>{{ __('app.scouting.add_crew_action') }}</button>
                                        </div>
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
                                                                @foreach (['jordanian', 'international'] as $option)
                                                                    <option value="{{ $option }}" @selected(($member['nationality'] ?? 'jordanian') === $option)>{{ __('app.applications.project_nationalities.'.$option) }}</option>
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

    <div class="form-actions d-flex gap-2 flex-wrap justify-content-end">
        <button type="submit" class="btn btn-danger d-flex align-items-center gap-2">
            <i class="ph-fill ph-floppy-disk-back"></i>
            <span>{{ $submitLabel }}</span>
        </button>
    </div>
</form>

@push('scripts')
    <script>
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
                    <select class="form-select" name="locations[${index}][governorate]">
                        <option value="amman">{{ __('app.scouting.governorate_options.amman') }}</option>
                        <option value="irbid">{{ __('app.scouting.governorate_options.irbid') }}</option>
                        <option value="zarqa">{{ __('app.scouting.governorate_options.zarqa') }}</option>
                        <option value="balqa">{{ __('app.scouting.governorate_options.balqa') }}</option>
                        <option value="madaba">{{ __('app.scouting.governorate_options.madaba') }}</option>
                        <option value="karak">{{ __('app.scouting.governorate_options.karak') }}</option>
                        <option value="tafilah">{{ __('app.scouting.governorate_options.tafilah') }}</option>
                        <option value="maan">{{ __('app.scouting.governorate_options.maan') }}</option>
                        <option value="aqaba">{{ __('app.scouting.governorate_options.aqaba') }}</option>
                        <option value="mafraq">{{ __('app.scouting.governorate_options.mafraq') }}</option>
                        <option value="jerash">{{ __('app.scouting.governorate_options.jerash') }}</option>
                        <option value="ajloun">{{ __('app.scouting.governorate_options.ajloun') }}</option>
                    </select>
                </td>
                <td><input type="text" class="form-control" name="locations[${index}][location_name]"></td>
                <td><input type="text" class="form-control" name="locations[${index}][google_map_url]"></td>
                <td>
                    <select class="form-select" name="locations[${index}][location_nature]">
                        <option value="public_site">{{ __('app.scouting.location_nature_options.public_site') }}</option>
                        <option value="border_area">{{ __('app.scouting.location_nature_options.border_area') }}</option>
                        <option value="archaeological">{{ __('app.scouting.location_nature_options.archaeological') }}</option>
                        <option value="religious">{{ __('app.scouting.location_nature_options.religious') }}</option>
                        <option value="schools">{{ __('app.scouting.location_nature_options.schools') }}</option>
                        <option value="universities">{{ __('app.scouting.location_nature_options.universities') }}</option>
                        <option value="museums">{{ __('app.scouting.location_nature_options.museums') }}</option>
                        <option value="syrian_camps">{{ __('app.scouting.location_nature_options.syrian_camps') }}</option>
                        <option value="palestinian_camps">{{ __('app.scouting.location_nature_options.palestinian_camps') }}</option>
                        <option value="petra">{{ __('app.scouting.location_nature_options.petra') }}</option>
                        <option value="reserves">{{ __('app.scouting.location_nature_options.reserves') }}</option>
                        <option value="valleys">{{ __('app.scouting.location_nature_options.valleys') }}</option>
                        <option value="private_site">{{ __('app.scouting.location_nature_options.private_site') }}</option>
                    </select>
                </td>
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
                        <option value="jordanian">{{ __('app.applications.project_nationalities.jordanian') }}</option>
                        <option value="international">{{ __('app.applications.project_nationalities.international') }}</option>
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
    </script>
@endpush
