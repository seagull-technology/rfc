@php
    $metadata = $application->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $director = data_get($metadata, 'director', []);
    $international = data_get($metadata, 'international', []);
    $requirements = data_get($metadata, 'requirements', []);
    $annex = data_get($metadata, 'annex', []);
    $workContentSummary = data_get($annex, 'work_content_summary', []);
    $safetyGuidelines = data_get($annex, 'safety_guidelines', []);
    $airportFilming = data_get($annex, 'airport_filming', []);
    $castCrewRows = old('cast_crew', data_get($annex, 'cast_crew', [['name' => '', 'role' => '', 'nationality' => '', 'identity_number' => '']]));
    $filmingLocationRows = old('filming_locations', data_get($annex, 'filming_locations', [['governorate' => '', 'location_name' => '', 'location_type' => '', 'start_date' => '', 'end_date' => '', 'notes' => '']]));
    $importedEquipmentRows = old('imported_equipment', data_get($annex, 'imported_equipment', [['item' => '', 'serial_number' => '', 'quantity' => '', 'origin_country' => '', 'entry_point' => '', 'arrival_date' => '']]));
    $militaryBorderEquipmentRows = old('military_border_equipment', data_get($annex, 'military_border_equipment', [['location_name' => '', 'equipment' => '', 'security_need' => '', 'notes' => '']]));
    $governmentalSceneRows = old('governmental_scenes', data_get($annex, 'governmental_scenes', [['site_name' => '', 'authority' => '', 'scene_description' => '', 'filming_date' => '']]));
@endphp

<form id="form-wizard1" method="POST" action="{{ $formAction }}" class="mt-3 text-center form-content">
    @csrf

    <ul id="top-tab-list" class="p-0 row list-inline">
        <li class="mb-2 col-lg-6 col-md-6 text-start active" id="step1">
            <a href="javascript:void(0);">
                <div class="iq-icon me-3"><img src="{{ asset('images/video-camera.png') }}" alt=""></div>
                <span class="dark-wizard">{{ __('app.applications.general_information') }}</span>
            </a>
        </li>
        <li id="step2" class="mb-2 col-lg-6 col-md-6 text-start">
            <a href="javascript:void(0);">
                <div class="iq-icon me-3"><img src="{{ asset('images/todo-list.png') }}" alt=""></div>
                <span class="dark-wizard">{{ __('app.applications.requirements_list') }}</span>
            </a>
        </li>
    </ul>

    <fieldset>
        <div class="form-card text-start">
            <div class="section-form">
                <div class="p-4 px-2">
                    <div class="row g-3 mb-4">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="form-label">{{ __('app.applications.project_name') }}</label>
                                <span class="text-danger">*</span>
                                <input class="form-control" type="text" name="project_name" value="{{ old('project_name', $application->project_name) }}" required>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="form-label">{{ __('app.applications.project_nationality') }}</label>
                                <span class="text-danger">*</span>
                                <select name="project_nationality" class="form-control select2-basic-single" required>
                                    @foreach (['jordanian', 'international'] as $option)
                                        <option value="{{ $option }}" @selected(old('project_nationality', $application->project_nationality) === $option)>{{ __('app.applications.project_nationalities.'.$option) }}</option>
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
                                                'local_producer' => __('app.applications.producer_information'),
                                                'director_info' => __('app.applications.director_information'),
                                                'international_projects' => __('app.applications.international_project_information'),
                                                'work_category' => __('app.applications.work_category'),
                                                'release_method' => __('app.applications.release_method'),
                                                'schedule' => __('app.applications.schedule_title'),
                                                'crew_count' => __('app.applications.estimated_crew_count'),
                                                'summary' => __('app.applications.project_summary'),
                                                'budget' => __('app.applications.estimated_budget'),
                                            ] as $tabKey => $tabLabel)
                                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" type="button" data-bs-target="#{{ $tabKey }}_tab" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                                    <span>{{ $tabLabel }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-9 edit-tab-content">
                                    <div class="tab-content" id="streamit-tabs-content">
                                        <div class="tab-pane fade show active" id="local_producer_tab" role="tabpanel">
                                            <div class="row g-3">
                                                @foreach ([
                                                    'producer_name' => __('app.applications.producer_name'),
                                                    'production_company_name' => __('app.applications.production_company_name'),
                                                    'contact_address' => __('app.applications.contact_address'),
                                                    'contact_phone' => __('app.applications.contact_phone'),
                                                    'contact_mobile' => __('app.applications.contact_mobile'),
                                                    'contact_fax' => __('app.applications.contact_fax'),
                                                    'contact_email' => __('app.applications.contact_email'),
                                                    'liaison_name' => __('app.applications.liaison_name'),
                                                    'liaison_position' => __('app.applications.liaison_position'),
                                                    'liaison_email' => __('app.applications.liaison_email'),
                                                    'liaison_mobile' => __('app.applications.liaison_mobile'),
                                                ] as $field => $label)
                                                    <div class="col-lg-{{ in_array($field, ['producer_name', 'production_company_name', 'liaison_name'], true) ? '12' : '6' }}">
                                                        <div class="form-group">
                                                            <label class="form-label">{{ $label }}</label>
                                                            @if (! in_array($field, ['contact_mobile', 'contact_fax'], true))
                                                                <span class="text-danger">*</span>
                                                            @endif
                                                            <input class="form-control" type="{{ str_contains($field, 'email') ? 'email' : 'text' }}" name="{{ $field }}" value="{{ old($field, data_get($producer, $field)) }}" @required(! in_array($field, ['contact_mobile', 'contact_fax'], true))>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="director_info_tab" role="tabpanel">
                                            <div class="row g-3">
                                                <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.director_name') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="text" name="director_name" value="{{ old('director_name', data_get($director, 'director_name')) }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.director_nationality') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="text" name="director_nationality" value="{{ old('director_nationality', data_get($director, 'director_nationality')) }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.director_profile_url') }}</label>
                                                        <input class="form-control" type="url" name="director_profile_url" value="{{ old('director_profile_url', data_get($director, 'director_profile_url')) }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="international_projects_tab" role="tabpanel">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_name') }}</label>
                                                        <input class="form-control" type="text" name="international_producer_name" value="{{ old('international_producer_name', data_get($international, 'international_producer_name')) }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_company') }}</label>
                                                        <input class="form-control" type="text" name="international_producer_company" value="{{ old('international_producer_company', data_get($international, 'international_producer_company')) }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="work_category_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.work_category') }}</label>
                                                <span class="text-danger">*</span>
                                                <select name="work_category" class="form-control select2-basic-single" required>
                                                    @foreach (['feature_film', 'documentary', 'series', 'commercial', 'tv_program', 'student_project'] as $option)
                                                        <option value="{{ $option }}" @selected(old('work_category', $application->work_category) === $option)>{{ __('app.applications.work_categories.'.$option) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="release_method_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.release_method') }}</label>
                                                <span class="text-danger">*</span>
                                                <select name="release_method" class="form-control select2-basic-single" required>
                                                    @foreach (['cinema', 'television', 'streaming', 'festival', 'digital'] as $option)
                                                        <option value="{{ $option }}" @selected(old('release_method', $application->release_method) === $option)>{{ __('app.applications.release_methods.'.$option) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="schedule_tab" role="tabpanel">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.planned_start_date') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="date" name="planned_start_date" value="{{ old('planned_start_date', optional($application->planned_start_date)->format('Y-m-d')) }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.planned_end_date') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="date" name="planned_end_date" value="{{ old('planned_end_date', optional($application->planned_end_date)->format('Y-m-d')) }}" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="crew_count_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.estimated_crew_count') }}</label>
                                                <input class="form-control" type="number" min="1" name="estimated_crew_count" value="{{ old('estimated_crew_count', $application->estimated_crew_count) }}">
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="summary_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.project_summary') }}</label>
                                                <span class="text-danger">*</span>
                                                <textarea class="form-control" name="project_summary" rows="7" required>{{ old('project_summary', $application->project_summary) }}</textarea>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="budget_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.estimated_budget') }}</label>
                                                <input class="form-control" type="number" step="0.01" min="0" name="estimated_budget" value="{{ old('estimated_budget', $application->estimated_budget) }}">
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
            <button type="button" name="previous" class="btn btn-dark request-wizard-previous action-button-previous btn-lg">
                {{ app()->getLocale() === 'ar' ? 'السابق' : 'Previous' }}
            </button>
            <button type="button" name="next" class="btn btn-danger request-wizard-next action-button float-end btn-lg">
                {{ app()->getLocale() === 'ar' ? 'التالي' : 'Next' }}
            </button>
        </div>
    </fieldset>

    <fieldset>
        <div class="form-card text-start">
            <div class="card mt-0">
                <div class="card-header">
                    <div class="header-title">
                        <h2 class="episode-playlist-title wp-heading-inline">
                            <span class="position-relative">{{ __('app.applications.requirements_list') }}</span>
                        </h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="alert alert-light border mb-0">
                                <div class="fw-600 mb-2">{{ __('app.applications.auto_approval_routing_title') }}</div>
                                <div>{{ __('app.applications.auto_approval_routing_body') }}</div>
                                <small class="text-muted d-block mt-2">{{ __('app.applications.auto_approval_routing_hint') }}</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded p-3 bg-light" data-approval-route-preview data-rules='@json($approvalRoutePreviewRules ?? [])' data-empty-label="{{ __('app.applications.approval_route_preview_empty') }}" data-unassigned-label="{{ __('app.dashboard.not_available') }}" data-status-label="{{ __('app.applications.approval_route_preview_status') }}">
                                <div class="fw-600 mb-2">{{ __('app.applications.approval_route_preview_title') }}</div>
                                <div class="text-muted small mb-3">{{ __('app.applications.approval_route_preview_body') }}</div>
                                <ul class="list-inline p-0 m-0" data-approval-route-list></ul>
                                <div class="text-muted" data-approval-route-empty>{{ __('app.applications.approval_route_preview_empty') }}</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card mb-0">
                                <div class="card-header">
                                    <div class="header-title">
                                        <h3 class="mb-0">{{ __('app.applications.annex_forms_title') }}</h3>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <h5 class="mb-3">{{ __('app.applications.annex_sections.work_content_summary') }}</h5>
                                        </div>
                                        <div class="col-lg-6">
                                            <label class="form-label">{{ __('app.applications.annex_fields.synopsis') }}</label>
                                            <textarea class="form-control" name="work_content_summary_synopsis" rows="5">{{ old('work_content_summary_synopsis', data_get($workContentSummary, 'synopsis')) }}</textarea>
                                        </div>
                                        <div class="col-lg-6">
                                            <label class="form-label">{{ __('app.applications.annex_fields.sensitive_content_notes') }}</label>
                                            <textarea class="form-control" name="work_content_summary_sensitive_notes" rows="5">{{ old('work_content_summary_sensitive_notes', data_get($workContentSummary, 'sensitive_notes')) }}</textarea>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input type="hidden" name="work_content_summary_confirmed" value="0">
                                                <input class="form-check-input" type="checkbox" id="work_content_summary_confirmed" name="work_content_summary_confirmed" value="1" @checked(old('work_content_summary_confirmed', data_get($workContentSummary, 'confirmed', false)))>
                                                <label class="form-check-label" for="work_content_summary_confirmed">{{ __('app.applications.annex_fields.content_confirmation') }}</label>
                                            </div>
                                        </div>

                                        <div class="col-12 pt-3">
                                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                                                <h5 class="mb-0">{{ __('app.applications.annex_sections.cast_crew') }}</h5>
                                                <button type="button" class="btn btn-sm btn-success" onclick="addApplicationAnnexRow('castCrewTable', 'cast_crew')">
                                                    <i class="fa-solid fa-plus me-2"></i>{{ __('app.scouting.add_crew_action') }}
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table align-middle" id="castCrewTable">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>{{ __('app.applications.annex_fields.person_name') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.role') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.nationality') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.identity_number') }}</th>
                                                            <th>{{ __('app.applications.actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($castCrewRows as $index => $row)
                                                            <tr>
                                                                <td class="row-number">{{ $index + 1 }}</td>
                                                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][role]" value="{{ $row['role'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][nationality]" value="{{ $row['nationality'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][identity_number]" value="{{ $row['identity_number'] ?? '' }}"></td>
                                                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#castCrewTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="col-12 pt-3">
                                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                                                <h5 class="mb-0">{{ __('app.applications.annex_sections.filming_locations') }}</h5>
                                                <button type="button" class="btn btn-sm btn-success" onclick="addApplicationAnnexRow('filmingLocationsTable', 'filming_locations')">
                                                    <i class="fa-solid fa-plus me-2"></i>{{ __('app.scouting.add_location_action') }}
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table align-middle" id="filmingLocationsTable">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>{{ __('app.scouting.governorate') }}</th>
                                                            <th>{{ __('app.scouting.location_name') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.location_type') }}</th>
                                                            <th>{{ __('app.scouting.start_date') }}</th>
                                                            <th>{{ __('app.scouting.end_date') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.notes') }}</th>
                                                            <th>{{ __('app.applications.actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($filmingLocationRows as $index => $row)
                                                            <tr>
                                                                <td class="row-number">{{ $index + 1 }}</td>
                                                                <td>
                                                                    <select class="form-select" name="filming_locations[{{ $index }}][governorate]">
                                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                                        @foreach (['amman', 'irbid', 'zarqa', 'balqa', 'madaba', 'karak', 'tafilah', 'maan', 'aqaba', 'mafraq', 'jerash', 'ajloun'] as $option)
                                                                            <option value="{{ $option }}" @selected(($row['governorate'] ?? '') === $option)>{{ __('app.scouting.governorate_options.'.$option) }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td><input type="text" class="form-control" name="filming_locations[{{ $index }}][location_name]" value="{{ $row['location_name'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="filming_locations[{{ $index }}][location_type]" value="{{ $row['location_type'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control" name="filming_locations[{{ $index }}][start_date]" value="{{ $row['start_date'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control" name="filming_locations[{{ $index }}][end_date]" value="{{ $row['end_date'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="filming_locations[{{ $index }}][notes]" value="{{ $row['notes'] ?? '' }}"></td>
                                                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#filmingLocationsTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="col-12 pt-3">
                                            <h5 class="mb-3">{{ __('app.applications.annex_sections.safety_guidelines') }}</h5>
                                            <div class="form-check mb-3">
                                                <input type="hidden" name="safety_guidelines_acknowledged" value="0">
                                                <input class="form-check-input" type="checkbox" id="safety_guidelines_acknowledged" name="safety_guidelines_acknowledged" value="1" @checked(old('safety_guidelines_acknowledged', data_get($safetyGuidelines, 'acknowledged', false)))>
                                                <label class="form-check-label" for="safety_guidelines_acknowledged">{{ __('app.applications.annex_fields.safety_acknowledgement') }}</label>
                                            </div>
                                            <label class="form-label">{{ __('app.applications.annex_fields.safety_notes') }}</label>
                                            <textarea class="form-control" name="safety_guidelines_notes" rows="4">{{ old('safety_guidelines_notes', data_get($safetyGuidelines, 'notes')) }}</textarea>
                                        </div>

                                        <div class="col-12 pt-3">
                                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                                                <h5 class="mb-0">{{ __('app.applications.annex_sections.imported_equipment') }}</h5>
                                                <button type="button" class="btn btn-sm btn-success" onclick="addApplicationAnnexRow('importedEquipmentTable', 'imported_equipment')">
                                                    <i class="fa-solid fa-plus me-2"></i>{{ __('app.scouting.add_location_action') }}
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table align-middle" id="importedEquipmentTable">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>{{ __('app.applications.annex_fields.equipment_item') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.serial_number') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.quantity') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.origin_country') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.entry_point') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.arrival_date') }}</th>
                                                            <th>{{ __('app.applications.actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($importedEquipmentRows as $index => $row)
                                                            <tr>
                                                                <td class="row-number">{{ $index + 1 }}</td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][item]" value="{{ $row['item'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][serial_number]" value="{{ $row['serial_number'] ?? '' }}"></td>
                                                                <td><input type="number" min="0" class="form-control" name="imported_equipment[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][origin_country]" value="{{ $row['origin_country'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][entry_point]" value="{{ $row['entry_point'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control" name="imported_equipment[{{ $index }}][arrival_date]" value="{{ $row['arrival_date'] ?? '' }}"></td>
                                                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#importedEquipmentTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="col-12 pt-3">
                                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                                                <h5 class="mb-0">{{ __('app.applications.annex_sections.military_border_equipment') }}</h5>
                                                <button type="button" class="btn btn-sm btn-success" onclick="addApplicationAnnexRow('militaryBorderEquipmentTable', 'military_border_equipment')">
                                                    <i class="fa-solid fa-plus me-2"></i>{{ __('app.scouting.add_location_action') }}
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table align-middle" id="militaryBorderEquipmentTable">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>{{ __('app.scouting.location_name') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.equipment_item') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.security_need') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.notes') }}</th>
                                                            <th>{{ __('app.applications.actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($militaryBorderEquipmentRows as $index => $row)
                                                            <tr>
                                                                <td class="row-number">{{ $index + 1 }}</td>
                                                                <td><input type="text" class="form-control" name="military_border_equipment[{{ $index }}][location_name]" value="{{ $row['location_name'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="military_border_equipment[{{ $index }}][equipment]" value="{{ $row['equipment'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="military_border_equipment[{{ $index }}][security_need]" value="{{ $row['security_need'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="military_border_equipment[{{ $index }}][notes]" value="{{ $row['notes'] ?? '' }}"></td>
                                                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#militaryBorderEquipmentTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="col-12 pt-3">
                                            <h5 class="mb-3">{{ __('app.applications.annex_sections.airport_filming') }}</h5>
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <label class="form-label">{{ __('app.applications.annex_fields.airport_name') }}</label>
                                                    <input type="text" class="form-control" name="airport_filming_airport_name" value="{{ old('airport_filming_airport_name', data_get($airportFilming, 'airport_name')) }}">
                                                </div>
                                                <div class="col-lg-6">
                                                    <label class="form-label">{{ __('app.applications.annex_fields.airport_area') }}</label>
                                                    <input type="text" class="form-control" name="airport_filming_area" value="{{ old('airport_filming_area', data_get($airportFilming, 'area')) }}">
                                                </div>
                                                <div class="col-lg-6">
                                                    <label class="form-label">{{ __('app.applications.annex_fields.filming_date') }}</label>
                                                    <input type="date" class="form-control" name="airport_filming_date" value="{{ old('airport_filming_date', data_get($airportFilming, 'filming_date')) }}">
                                                </div>
                                                <div class="col-lg-6">
                                                    <label class="form-label">{{ __('app.applications.annex_fields.airport_crew_count') }}</label>
                                                    <input type="number" min="0" class="form-control" name="airport_filming_crew_count" value="{{ old('airport_filming_crew_count', data_get($airportFilming, 'crew_count')) }}">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">{{ __('app.applications.annex_fields.notes') }}</label>
                                                    <textarea class="form-control" name="airport_filming_notes" rows="4">{{ old('airport_filming_notes', data_get($airportFilming, 'notes')) }}</textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 pt-3">
                                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                                                <h5 class="mb-0">{{ __('app.applications.annex_sections.governmental_scenes') }}</h5>
                                                <button type="button" class="btn btn-sm btn-success" onclick="addApplicationAnnexRow('governmentalScenesTable', 'governmental_scenes')">
                                                    <i class="fa-solid fa-plus me-2"></i>{{ __('app.scouting.add_location_action') }}
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table align-middle" id="governmentalScenesTable">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>{{ __('app.applications.annex_fields.site_name') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.authority_name') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.scene_description') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.filming_date') }}</th>
                                                            <th>{{ __('app.applications.actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($governmentalSceneRows as $index => $row)
                                                            <tr>
                                                                <td class="row-number">{{ $index + 1 }}</td>
                                                                <td><input type="text" class="form-control" name="governmental_scenes[{{ $index }}][site_name]" value="{{ $row['site_name'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="governmental_scenes[{{ $index }}][authority]" value="{{ $row['authority'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="governmental_scenes[{{ $index }}][scene_description]" value="{{ $row['scene_description'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control" name="governmental_scenes[{{ $index }}][filming_date]" value="{{ $row['filming_date'] ?? '' }}"></td>
                                                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#governmentalScenesTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
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

                        <div class="col-12">
                            <label class="form-label">{{ __('app.applications.supporting_notes') }}</label>
                            <textarea class="form-control" name="supporting_notes" rows="5">{{ old('supporting_notes', data_get($requirements, 'supporting_notes')) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-actions d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-danger d-flex align-items-center gap-2" type="submit">
                <i class="ph-fill ph-floppy-disk-back"></i>
                <span>{{ $submitLabel }}</span>
            </button>
            <button type="button" name="previous" class="btn btn-dark request-wizard-previous action-button-previous">
                {{ app()->getLocale() === 'ar' ? 'السابق' : 'Previous' }}
            </button>
        </div>
    </fieldset>
</form>

@push('scripts')
    <script src="{{ asset('js/form-wizard.js') }}?v={{ filemtime(public_path('js/form-wizard.js')) }}"></script>
    <script>
        function renumberApplicationAnnexRows(selector) {
            document.querySelectorAll(selector + ' tbody tr').forEach(function (row, index) {
                const cell = row.querySelector('.row-number');

                if (cell) {
                    cell.textContent = index + 1;
                }
            });
        }

        function removeApplicationAnnexRow(button, selector) {
            const table = document.querySelector(selector + ' tbody');

            if (!table || table.querySelectorAll('tr').length === 1) {
                return;
            }

            button.closest('tr').remove();
            renumberApplicationAnnexRows(selector);
        }

        function addApplicationAnnexRow(tableId, fieldName) {
            const table = document.querySelector('#' + tableId + ' tbody');

            if (!table) {
                return;
            }

            const index = table.querySelectorAll('tr').length;
            const row = document.createElement('tr');
            const deleteCell = '<td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, \\'#' + tableId + '\\')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>';

            if (fieldName === 'cast_crew') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][name]"></td>'
                    + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][role]"></td>'
                    + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][nationality]"></td>'
                    + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][identity_number]"></td>'
                    + deleteCell;
            } else if (fieldName === 'filming_locations') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><select class="form-select" name="filming_locations[' + index + '][governorate]">'
                    + '<option value="">{{ __('app.admin.select_placeholder') }}</option>'
                    + '<option value="amman">{{ __('app.scouting.governorate_options.amman') }}</option>'
                    + '<option value="irbid">{{ __('app.scouting.governorate_options.irbid') }}</option>'
                    + '<option value="zarqa">{{ __('app.scouting.governorate_options.zarqa') }}</option>'
                    + '<option value="balqa">{{ __('app.scouting.governorate_options.balqa') }}</option>'
                    + '<option value="madaba">{{ __('app.scouting.governorate_options.madaba') }}</option>'
                    + '<option value="karak">{{ __('app.scouting.governorate_options.karak') }}</option>'
                    + '<option value="tafilah">{{ __('app.scouting.governorate_options.tafilah') }}</option>'
                    + '<option value="maan">{{ __('app.scouting.governorate_options.maan') }}</option>'
                    + '<option value="aqaba">{{ __('app.scouting.governorate_options.aqaba') }}</option>'
                    + '<option value="mafraq">{{ __('app.scouting.governorate_options.mafraq') }}</option>'
                    + '<option value="jerash">{{ __('app.scouting.governorate_options.jerash') }}</option>'
                    + '<option value="ajloun">{{ __('app.scouting.governorate_options.ajloun') }}</option>'
                    + '</select></td>'
                    + '<td><input type="text" class="form-control" name="filming_locations[' + index + '][location_name]"></td>'
                    + '<td><input type="text" class="form-control" name="filming_locations[' + index + '][location_type]"></td>'
                    + '<td><input type="date" class="form-control" name="filming_locations[' + index + '][start_date]"></td>'
                    + '<td><input type="date" class="form-control" name="filming_locations[' + index + '][end_date]"></td>'
                    + '<td><input type="text" class="form-control" name="filming_locations[' + index + '][notes]"></td>'
                    + deleteCell;
            } else if (fieldName === 'imported_equipment') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + index + '][item]"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + index + '][serial_number]"></td>'
                    + '<td><input type="number" min="0" class="form-control" name="imported_equipment[' + index + '][quantity]"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + index + '][origin_country]"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + index + '][entry_point]"></td>'
                    + '<td><input type="date" class="form-control" name="imported_equipment[' + index + '][arrival_date]"></td>'
                    + deleteCell;
            } else if (fieldName === 'military_border_equipment') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][location_name]"></td>'
                    + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][equipment]"></td>'
                    + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][security_need]"></td>'
                    + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][notes]"></td>'
                    + deleteCell;
            } else if (fieldName === 'governmental_scenes') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="governmental_scenes[' + index + '][site_name]"></td>'
                    + '<td><input type="text" class="form-control" name="governmental_scenes[' + index + '][authority]"></td>'
                    + '<td><input type="text" class="form-control" name="governmental_scenes[' + index + '][scene_description]"></td>'
                    + '<td><input type="date" class="form-control" name="governmental_scenes[' + index + '][filming_date]"></td>'
                    + deleteCell;
            }

            table.appendChild(row);
            renumberApplicationAnnexRows('#' + tableId);
        }

        ['#castCrewTable', '#filmingLocationsTable', '#importedEquipmentTable', '#militaryBorderEquipmentTable', '#governmentalScenesTable'].forEach(renumberApplicationAnnexRows);
    </script>
@endpush
