@php
    $metadata = $application->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $lockedProducerFields = $lockedProducerFields ?? [];
    $lockedProducerFieldNames = array_keys($lockedProducerFields);
    $director = data_get($metadata, 'director', []);
    $international = data_get($metadata, 'international', []);
    $requirements = data_get($metadata, 'requirements', []);
    $projectMeta = data_get($metadata, 'project', []);
    $scheduleMeta = data_get($metadata, 'schedule', []);
    $budgetMeta = data_get($metadata, 'budget', []);
    $annex = data_get($metadata, 'annex', []);
    $workContentSummary = data_get($annex, 'work_content_summary', []);
    $safetyGuidelines = data_get($annex, 'safety_guidelines', []);
    $airportFilming = data_get($annex, 'airport_filming', []);
    $workCategoryOptions = collect(data_get($workLookupOptions ?? [], 'work_categories', []));
    $releaseMethodOptions = collect(data_get($workLookupOptions ?? [], 'release_methods', []));
    $formLookupOptions = $formLookupOptions ?? [];
    $equipmentClassificationOptions = collect(data_get($formLookupOptions, 'equipment_categories', []));
    $equipmentShippingMethodOptions = collect(data_get($formLookupOptions, 'equipment_shipping_methods', []));
    $equipmentEntryPointOptions = collect(data_get($formLookupOptions, 'equipment_entry_points', []));
    $airportOptions = collect(data_get($formLookupOptions, 'airports', []));
    $specialLocationRequirementOptions = collect(data_get($formLookupOptions, 'special_location_requirements', []));
    $budgetSpendingCategoryOptions = collect(data_get($formLookupOptions, 'budget_spending_categories', []));
    $militaryBorderLocationTypeOptions = collect(data_get($formLookupOptions, 'military_border_location_types', []));
    $schedulePhaseOptions = ['preparation', 'shooting', 'wrap', 'post_production'];
    $budgetItemOptions = $budgetSpendingCategoryOptions->pluck('code')->all() ?: ['jordanian_actors', 'jordanian_crew', 'flights_travel', 'accommodation', 'transportation', 'production_design', 'picture_vehicles', 'wardrobe', 'hair_makeup', 'catering', 'equipment_costs', 'location_fees', 'insurance', 'per_diems', 'health_safety', 'other_1', 'other_2', 'other_3'];
    $budgetItemLabels = $budgetSpendingCategoryOptions->mapWithKeys(fn ($option) => [$option->code => $option->displayName()])->all();
    $locationRequirementOptions = $specialLocationRequirementOptions->pluck('code')->all() ?: ['road_closures', 'police_presence', 'armed_forces', 'regular_aerial_filming', 'drone_filming', 'special_effects', 'construction_work', 'animals', 'weapons', 'other'];
    $locationRequirementLabels = $specialLocationRequirementOptions->mapWithKeys(fn ($option) => [$option->code => $option->displayName()])->all();
    $governorateOptions = collect(data_get($locationLookupOptions ?? [], 'governorates', []));
    $locationTypeOptions = collect(data_get($locationLookupOptions ?? [], 'location_types', []));
    $locationTypesByGovernorate = (array) data_get($locationLookupOptions ?? [], 'location_types_by_governorate', []);
    $locationTypeLabels = (array) data_get($locationLookupOptions ?? [], 'location_type_labels', []);
    $militaryLocationTypeOptions = $militaryBorderLocationTypeOptions->pluck('code')->all() ?: ['military_area', 'border_area'];
    $militaryLocationTypeLabels = $militaryBorderLocationTypeOptions->mapWithKeys(fn ($option) => [$option->code => $option->displayName()])->all();
    $flightTypeOptions = ['arrival', 'departure'];
    $projectNationalityOptions = collect(data_get($nationalityOptions ?? [], 'project', []));
    $directorNationalityOptions = collect(data_get($nationalityOptions ?? [], 'director', []));
    $internationalProducerNationalityOptions = collect(data_get($nationalityOptions ?? [], 'international_producer', []));
    $defaultWorkCategory = \App\Models\WorkCategory::defaultCode();
    $defaultReleaseMethod = \App\Models\ReleaseMethod::defaultCode();
    $workCategoryOptionCodes = $workCategoryOptions->pluck('code')->map(fn ($code): string => (string) $code)->all();
    $releaseMethodOptionCodes = $releaseMethodOptions->pluck('code')->map(fn ($code): string => (string) $code)->all();
    $selectedWorkCategories = array_values(array_filter(
        (array) old('work_categories', data_get($projectMeta, 'work_categories', [$application->work_category ?: $defaultWorkCategory])),
        fn ($code): bool => filled($code) && in_array((string) $code, $workCategoryOptionCodes, true),
    ));
    $selectedReleaseMethods = array_values(array_filter(
        (array) old('release_methods', data_get($projectMeta, 'release_methods', [$application->release_method ?: $defaultReleaseMethod])),
        fn ($code): bool => filled($code) && in_array((string) $code, $releaseMethodOptionCodes, true),
    ));
    $selectedWorkCategories = $selectedWorkCategories ?: (in_array($defaultWorkCategory, $workCategoryOptionCodes, true) ? [$defaultWorkCategory] : []);
    $selectedReleaseMethods = $selectedReleaseMethods ?: (in_array($defaultReleaseMethod, $releaseMethodOptionCodes, true) ? [$defaultReleaseMethod] : []);
    $schedulePhases = old('schedule_phases', data_get($scheduleMeta, 'phases', []));
    $budgetItems = old('budget_items', data_get($budgetMeta, 'items', []));
    $castCrewRows = old('cast_crew', data_get($annex, 'cast_crew', [['name' => '', 'role' => '', 'nationality' => '', 'gender' => '', 'birth_date' => '', 'identity_number' => '']]));
    $filmingLocationRows = old('filming_locations', data_get($annex, 'filming_locations', [['governorate' => '', 'location_name' => '', 'address' => '', 'nature' => '', 'location_type' => '', 'start_date' => '', 'end_date' => '']]));
    $specialLocationRequirementRows = old('special_location_requirements', data_get($annex, 'special_location_requirements', collect($locationRequirementOptions)->mapWithKeys(fn ($option) => [$option => ['locations' => [], 'notes' => '']])->all()));
    $equipmentFlightRows = old('equipment_flights', data_get($annex, 'equipment_flights', [['flight_type' => '', 'flight_number' => '', 'flight_date' => '', 'flight_time' => '', 'departure_city' => '', 'arrival_city' => '']]));
    $equipmentTravelerRows = old('equipment_travelers', data_get($annex, 'equipment_travelers', [['traveler_name' => '', 'arrival_date' => '', 'arrival_flight_number' => '', 'departure_date' => '', 'departure_flight_number' => '']]));
    $importedEquipmentRows = old('imported_equipment', data_get($annex, 'imported_equipment', [['transport_group' => 'shipping', 'item' => '', 'serial_number' => '', 'flight_reference' => '', 'traveler_name' => '', 'quantity' => '', 'unit_value' => '', 'total_value' => '', 'classification' => '', 'shipping_method' => '', 'entry_point' => '', 'arrival_date' => '', 'origin_country' => '']]));
    $militaryBorderLocationRows = old('military_border_locations', data_get($annex, 'military_border_locations', [['governorate' => '', 'location_name' => '', 'address' => '', 'nature' => '', 'location_type' => '', 'start_date' => '', 'end_date' => '']]));
    $militaryBorderEquipmentRows = old('military_border_equipment', data_get($annex, 'military_border_equipment', [['item' => '', 'serial_number' => '', 'location_reference' => '', 'quantity' => '', 'unit_value' => '', 'total_value' => '', 'classification' => '', 'entry_method' => '', 'entry_point' => '', 'location_name' => '', 'equipment' => '', 'security_need' => '', 'notes' => '']]));
    $airportPeopleRows = old('airport_people', data_get($annex, 'airport_people', [['full_name' => '', 'nationality' => '', 'mother_name' => '', 'identity_number' => '', 'profession' => '', 'address_phone' => '', 'entry_reason' => '', 'target_area' => '']]));
    $governmentalSceneRows = old('governmental_scenes', data_get($annex, 'governmental_scenes', [['site_name' => '', 'authority' => '', 'scene_description' => '', 'filming_date' => '']]));
    $selectedProjectNationality = old('project_nationality', $application->project_nationality);
    $selectedDirectorNationality = old('director_nationality', data_get($director, 'director_nationality'));
    $selectedInternationalProducerNationality = old('international_producer_nationality', data_get($international, 'international_producer_nationality'));
    $locationTypeOptionsForGovernorate = static function ($governorateCode) use ($locationTypeOptions, $locationTypesByGovernorate) {
        $governorateCode = filled($governorateCode) ? (string) $governorateCode : null;

        if (! $governorateCode || ! isset($locationTypesByGovernorate[$governorateCode])) {
            return $locationTypeOptions;
        }

        return $locationTypeOptions->filter(fn ($locationType): bool => in_array($locationType->code, (array) $locationTypesByGovernorate[$governorateCode], true))->values();
    };
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
                                    @if (filled($selectedProjectNationality) && ! $projectNationalityOptions->contains('code', $selectedProjectNationality))
                                        <option value="{{ $selectedProjectNationality }}" selected>{{ \App\Models\Nationality::labelFor($selectedProjectNationality) }}</option>
                                    @endif
                                    @foreach ($projectNationalityOptions as $nationality)
                                        <option value="{{ $nationality->code }}" @selected($selectedProjectNationality === $nationality->code)>{{ $nationality->displayName() }}</option>
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
                                                    @php
                                                        $isLockedProducerField = in_array($field, $lockedProducerFieldNames, true);
                                                        $producerFieldValue = old($field, $isLockedProducerField ? ($lockedProducerFields[$field] ?? data_get($producer, $field)) : data_get($producer, $field));
                                                    @endphp
                                                    <div class="col-lg-{{ in_array($field, ['producer_name', 'production_company_name', 'liaison_name'], true) ? '12' : '6' }}">
                                                        <div class="form-group">
                                                            <label class="form-label">{{ $label }}</label>
                                                            @if (! in_array($field, ['contact_mobile', 'contact_fax'], true))
                                                                <span class="text-danger">*</span>
                                                            @endif
                                                            <input class="form-control {{ $isLockedProducerField ? 'bg-light' : '' }}" type="{{ str_contains($field, 'email') ? 'email' : 'text' }}" name="{{ $field }}" value="{{ $producerFieldValue }}" @readonly($isLockedProducerField) @if($isLockedProducerField) aria-readonly="true" @endif @required(! in_array($field, ['contact_mobile', 'contact_fax'], true))>
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
                                                        <select class="form-select select2-basic-single" name="director_nationality" required>
                                                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                            @if (filled($selectedDirectorNationality) && ! $directorNationalityOptions->contains('code', $selectedDirectorNationality))
                                                                <option value="{{ $selectedDirectorNationality }}" selected>{{ \App\Models\Nationality::labelFor($selectedDirectorNationality) }}</option>
                                                            @endif
                                                            @foreach ($directorNationalityOptions as $nationality)
                                                                <option value="{{ $nationality->code }}" @selected($selectedDirectorNationality === $nationality->code)>{{ $nationality->displayName() }}</option>
                                                            @endforeach
                                                        </select>
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
                                                <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_name') }}</label>
                                                        <input class="form-control" type="text" name="international_producer_name" value="{{ old('international_producer_name', data_get($international, 'international_producer_name')) }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_nationality') }}</label>
                                                        <select class="form-select select2-basic-single" name="international_producer_nationality">
                                                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                            @if (filled($selectedInternationalProducerNationality) && ! $internationalProducerNationalityOptions->contains('code', $selectedInternationalProducerNationality))
                                                                <option value="{{ $selectedInternationalProducerNationality }}" selected>{{ \App\Models\Nationality::labelFor($selectedInternationalProducerNationality) }}</option>
                                                            @endif
                                                            @foreach ($internationalProducerNationalityOptions as $nationality)
                                                                <option value="{{ $nationality->code }}" @selected($selectedInternationalProducerNationality === $nationality->code)>{{ $nationality->displayName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_company') }}</label>
                                                        <input class="form-control" type="text" name="international_producer_company" value="{{ old('international_producer_company', data_get($international, 'international_producer_company')) }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_email') }}</label>
                                                        <input class="form-control" type="email" name="international_producer_email" value="{{ old('international_producer_email', data_get($international, 'international_producer_email')) }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_profile_url') }}</label>
                                                        <input class="form-control" type="url" name="international_producer_profile_url" value="{{ old('international_producer_profile_url', data_get($international, 'international_producer_profile_url')) }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_address') }}</label>
                                                        <input class="form-control" type="text" name="international_producer_address" value="{{ old('international_producer_address', data_get($international, 'international_producer_address')) }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_website') }}</label>
                                                        <input class="form-control" type="url" name="international_producer_website" value="{{ old('international_producer_website', data_get($international, 'international_producer_website')) }}">
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="border rounded p-3 bg-transparent">
                                                            <div class="row g-3">
                                                                <div class="col-lg-12">
                                                                    <div class="form-group">
                                                                        <label class="form-label">{{ __('app.applications.international_liaison_name') }}</label>
                                                                        <input class="form-control" type="text" name="international_liaison_name" value="{{ old('international_liaison_name', data_get($international, 'international_liaison_name')) }}">
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="form-group">
                                                                        <label class="form-label">{{ __('app.applications.international_liaison_email') }}</label>
                                                                        <input class="form-control" type="email" name="international_liaison_email" value="{{ old('international_liaison_email', data_get($international, 'international_liaison_email')) }}">
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="form-group">
                                                                        <label class="form-label">{{ __('app.applications.international_liaison_mobile') }}</label>
                                                                        <input class="form-control" type="text" name="international_liaison_mobile" value="{{ old('international_liaison_mobile', data_get($international, 'international_liaison_mobile')) }}">
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="form-group" data-application-password-wrapper>
                                                                        <label class="form-label">{{ __('app.auth.password') }}</label>
                                                                        <div class="application-password-control">
                                                                            <input class="form-control" type="password" name="international_account_password" autocomplete="new-password" data-application-password-strength aria-describedby="international-account-password-rules">
                                                                            <button type="button" class="application-password-toggle" data-application-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                                                <i class="ph ph-eye-slash"></i>
                                                                            </button>
                                                                        </div>
                                                                        <ul class="application-password-rules" id="international-account-password-rules" data-application-password-rules hidden>
                                                                            <li data-application-password-rule="length">{{ __('app.auth.password_rule_length') }}</li>
                                                                            <li data-application-password-rule="mixed">{{ __('app.auth.password_rule_mixed') }}</li>
                                                                            <li data-application-password-rule="number">{{ __('app.auth.password_rule_number') }}</li>
                                                                            <li data-application-password-rule="symbol">{{ __('app.auth.password_rule_symbol') }}</li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="form-group">
                                                                        <label class="form-label">{{ __('app.auth.confirm_password') }}</label>
                                                                        <div class="application-password-control">
                                                                            <input class="form-control" type="password" name="international_account_password_confirmation" autocomplete="new-password">
                                                                            <button type="button" class="application-password-toggle" data-application-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                                                <i class="ph ph-eye-slash"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="work_category_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.work_type') }}</label>
                                                <span class="text-danger">*</span>
                                                <input type="hidden" name="work_category" data-routing-field="work_category" value="{{ data_get($selectedWorkCategories, '0', $application->work_category ?: $defaultWorkCategory) }}">
                                                <select name="work_categories[]" class="form-select select2-basic-multiple" multiple required data-routing-multiple="work_category">
                                                    @foreach ($workCategoryOptions as $option)
                                                        <option value="{{ $option->code }}" @selected(in_array($option->code, $selectedWorkCategories, true))>{{ $option->displayName() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group pt-3">
                                                <label class="form-label">{{ __('app.applications.please_specify') }}</label>
                                                <input class="form-control" type="text" name="work_category_other" value="{{ old('work_category_other', data_get($projectMeta, 'work_category_other')) }}">
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="release_method_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.release_method') }}</label>
                                                <span class="text-danger">*</span>
                                                <input type="hidden" name="release_method" data-routing-field="release_method" value="{{ data_get($selectedReleaseMethods, '0', $application->release_method ?: $defaultReleaseMethod) }}">
                                                <select name="release_methods[]" class="form-select select2-basic-multiple" multiple required data-routing-multiple="release_method">
                                                    @foreach ($releaseMethodOptions as $option)
                                                        <option value="{{ $option->code }}" @selected(in_array($option->code, $selectedReleaseMethods, true))>{{ $option->displayName() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group pt-3">
                                                <label class="form-label">{{ __('app.applications.please_specify') }}</label>
                                                <input class="form-control" type="text" name="release_method_other" value="{{ old('release_method_other', data_get($projectMeta, 'release_method_other')) }}">
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="schedule_tab" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table align-middle">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>{{ __('app.applications.schedule_phase') }}</th>
                                                            <th>{{ __('app.scouting.start_date') }} <span class="text-danger">*</span></th>
                                                            <th>{{ __('app.scouting.end_date') }} <span class="text-danger">*</span></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($schedulePhaseOptions as $phase)
                                                            <tr>
                                                                <td class="fw-600">{{ __('app.applications.schedule_phases.'.$phase) }}</td>
                                                                <td>
                                                                    <input class="form-control" type="date" name="{{ $phase === 'shooting' ? 'planned_start_date' : 'schedule_phases['.$phase.'][start_date]' }}" value="{{ old($phase === 'shooting' ? 'planned_start_date' : 'schedule_phases.'.$phase.'.start_date', $phase === 'shooting' ? optional($application->planned_start_date)->format('Y-m-d') : data_get($schedulePhases, $phase.'.start_date')) }}" required data-schedule-date="{{ $phase }}_start">
                                                                </td>
                                                                <td>
                                                                    <input class="form-control" type="date" name="{{ $phase === 'shooting' ? 'planned_end_date' : 'schedule_phases['.$phase.'][end_date]' }}" value="{{ old($phase === 'shooting' ? 'planned_end_date' : 'schedule_phases.'.$phase.'.end_date', $phase === 'shooting' ? optional($application->planned_end_date)->format('Y-m-d') : data_get($schedulePhases, $phase.'.end_date')) }}" required data-schedule-date="{{ $phase }}_end">
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="crew_count_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.estimated_crew_count') }}</label>
                                                <span class="text-danger">*</span>
                                                <input class="form-control" type="number" min="1" name="estimated_crew_count" value="{{ old('estimated_crew_count', $application->estimated_crew_count) }}" required>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="summary_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.project_summary') }}</label>
                                                <span class="text-danger">*</span>
                                                <p class="text-danger fontSize13 fw-600">
                                                    <i class="ph ph-info fa-xl me-2 lh-lg"></i>
                                                    <span>{{ __('app.applications.project_summary_instruction') }}</span>
                                                </p>
                                                <textarea class="form-control" name="project_summary" rows="7" required>{{ old('project_summary', $application->project_summary) }}</textarea>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="budget_tab" role="tabpanel">
                                            <div class="row g-3">
                                                <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.estimated_budget') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="number" step="0.01" min="0" name="estimated_budget" value="{{ old('estimated_budget', $application->estimated_budget) }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.local_spend_estimate') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="number" step="0.01" min="0" name="local_spend_estimate" value="{{ old('local_spend_estimate', data_get($budgetMeta, 'local_spend_estimate')) }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="table-responsive">
                                                        <table class="table align-middle">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>#</th>
                                                                    <th>{{ __('app.applications.budget_item') }}</th>
                                                                    <th>{{ __('app.applications.units_count') }}</th>
                                                                    <th>{{ __('app.applications.total_jod') }}</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($budgetItemOptions as $item)
                                                                    <tr>
                                                                        <td>{{ $loop->iteration }}</td>
                                                                        <td class="fw-600">{{ $budgetItemLabels[$item] ?? __('app.applications.budget_items.'.$item) }}</td>
                                                                        <td><input type="number" min="0" class="form-control" name="budget_items[{{ $item }}][units]" value="{{ data_get($budgetItems, $item.'.units') }}" placeholder="{{ __('app.applications.enter_count') }}"></td>
                                                                        <td><input type="number" step="0.01" min="0" class="form-control" name="budget_items[{{ $item }}][total]" value="{{ data_get($budgetItems, $item.'.total') }}" placeholder="{{ __('app.applications.enter_value') }}"></td>
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
            </div>
        </div>
        <div class="form-actions d-flex gap-2 flex-wrap justify-content-between">
            <button type="button" name="previous" class="btn btn-dark request-wizard-previous action-button-previous btn-lg">
                {{ app()->getLocale() === 'ar' ? 'السابق' : 'Previous' }}
            </button>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <button class="btn btn-outline-danger d-flex align-items-center gap-2 btn-lg" type="submit" formnovalidate>
                    <i class="ph-fill ph-floppy-disk-back"></i>
                    <span>{{ $submitLabel }}</span>
                </button>
                <button type="button" name="next" class="btn btn-danger request-wizard-next action-button float-end btn-lg">
                    {{ app()->getLocale() === 'ar' ? 'التالي' : 'Next' }}
                </button>
            </div>
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
                            <h2 class="episode-playlist-title wp-heading-inline">
                                <span class="position-relative">{{ __('app.applications.mandatory_forms_title') }}</span>
                            </h2>
                            <div class="table-responsive mt-4">
                                <table class="table table-striped mb-0 mx-auto request-requirements-table" style="width: 88%" role="grid">
                                    <thead>
                                        <tr>
                                            <th style="width: 70%">{{ __('app.applications.form_name') }}</th>
                                            <th>{{ __('app.applications.fill_form_action') }}</th>
                                            <th>{{ __('app.applications.status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['target' => 'WorkContentSummary', 'label' => __('app.applications.annex_sections.work_content_summary'), 'filled' => filled(data_get($workContentSummary, 'synopsis')) || data_get($workContentSummary, 'confirmed')],
                                            ['target' => 'CastCrewList', 'label' => __('app.applications.annex_sections.cast_crew'), 'filled' => collect($castCrewRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty()],
                                            ['target' => 'LocationList', 'label' => __('app.applications.annex_sections.filming_locations'), 'filled' => collect($filmingLocationRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty()],
                                            ['target' => 'RFCGuidelines', 'label' => __('app.applications.annex_sections.safety_guidelines'), 'filled' => data_get($safetyGuidelines, 'acknowledged') || filled(data_get($safetyGuidelines, 'notes'))],
                                        ] as $formRow)
                                            <tr data-requirement-row data-requirement-target="{{ $formRow['target'] }}">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img class="rounded img-fluid avatar-40 me-3 bg-primary-subtle" src="{{ asset('images/clapboard.png') }}" alt="" loading="lazy">
                                                        <h6 class="mb-0">{{ $formRow['label'] }}</h6>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $formRow['target'] }}" aria-controls="{{ $formRow['target'] }}">
                                                        <i class="fa-solid fa-plus me-2"></i>{{ __('app.applications.fill_form_action') }}
                                                    </button>
                                                </td>
                                                <td data-requirement-status>
                                                    <div class="text-success {{ $formRow['filled'] ? '' : 'd-none' }}" data-requirement-filled><i class="ph-fill ph-check fa-xl me-2 lh-lg"></i>{{ __('app.applications.form_filled_status') }}</div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12 pt-4">
                            <h2 class="episode-playlist-title wp-heading-inline">
                                <span class="position-relative">{{ __('app.applications.project_needs_forms_title') }}</span>
                            </h2>
                            <div class="table-responsive mt-4">
                                <table class="table table-striped mb-0 mx-auto request-requirements-table" style="width: 88%" role="grid">
                                    <thead>
                                        <tr>
                                            <th style="width: 70%">{{ __('app.applications.form_name') }}</th>
                                            <th>{{ __('app.applications.fill_form_action') }}</th>
                                            <th>{{ __('app.applications.status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['target' => 'EquipmentList', 'label' => __('app.applications.annex_sections.imported_equipment'), 'filled' => collect($importedEquipmentRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty() || collect($equipmentFlightRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty() || collect($equipmentTravelerRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty()],
                                            ['target' => 'EquipmentMilitaryBorder', 'label' => __('app.applications.annex_sections.military_border_equipment'), 'filled' => collect($militaryBorderEquipmentRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty() || collect($militaryBorderLocationRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty()],
                                            ['target' => 'FilmingAirports', 'label' => __('app.applications.annex_sections.airport_filming'), 'filled' => filled(data_get($airportFilming, 'airport_name')) || collect($airportPeopleRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty()],
                                            ['target' => 'FilmingGovernmental', 'label' => __('app.applications.annex_sections.governmental_scenes'), 'filled' => collect($governmentalSceneRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty() || data_get($annex, 'governmental_scenes_confirmed')],
                                        ] as $formRow)
                                            <tr data-requirement-row data-requirement-target="{{ $formRow['target'] }}">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img class="rounded img-fluid avatar-40 me-3 bg-primary-subtle" src="{{ asset('images/clapboard.png') }}" alt="" loading="lazy">
                                                        <h6 class="mb-0">{{ $formRow['label'] }}</h6>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $formRow['target'] }}" aria-controls="{{ $formRow['target'] }}">
                                                        <i class="fa-solid fa-plus me-2"></i>{{ __('app.applications.fill_form_action') }}
                                                    </button>
                                                </td>
                                                <td data-requirement-status>
                                                    <div class="text-success {{ $formRow['filled'] ? '' : 'd-none' }}" data-requirement-filled><i class="ph-fill ph-check fa-xl me-2 lh-lg"></i>{{ __('app.applications.form_filled_status') }}</div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card mb-0 d-none legacy-annex-inline" aria-hidden="true">
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
	                                                            <th>{{ __('app.applications.annex_fields.gender') }}</th>
	                                                            <th>{{ __('app.applications.annex_fields.birth_date') }}</th>
	                                                            <th>{{ __('app.applications.annex_fields.identity_number') }}</th>
	                                                            <th>{{ __('app.applications.actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($castCrewRows as $index => $row)
                                                            <tr>
                                                                <td class="row-number">{{ $loop->iteration }}</td>
	                                                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}"></td>
	                                                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][role]" value="{{ $row['role'] ?? '' }}"></td>
	                                                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][nationality]" value="{{ $row['nationality'] ?? '' }}"></td>
	                                                                <td>
	                                                                    <select class="form-select" name="cast_crew[{{ $index }}][gender]">
	                                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
	                                                                        @foreach (['male', 'female'] as $gender)
	                                                                            <option value="{{ $gender }}" @selected(($row['gender'] ?? '') === $gender)>{{ __('app.auth.gender_options.'.$gender) }}</option>
	                                                                        @endforeach
	                                                                    </select>
	                                                                </td>
	                                                                <td><input type="date" class="form-control" name="cast_crew[{{ $index }}][birth_date]" value="{{ $row['birth_date'] ?? '' }}"></td>
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
                                                            <th>{{ __('app.applications.annex_fields.location_type') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.location_exact_name') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.location_nature') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.location_address') }}</th>
                                                            <th>{{ __('app.scouting.start_date') }}</th>
                                                            <th>{{ __('app.scouting.end_date') }}</th>
                                                            <th>{{ __('app.applications.actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($filmingLocationRows as $index => $row)
                                                            @php
                                                                $selectedGovernorate = (string) ($row['governorate'] ?? '');
                                                                $selectedLocationType = (string) ($row['location_type'] ?? '');
                                                                $rowLocationTypeOptions = $locationTypeOptionsForGovernorate($selectedGovernorate);
                                                            @endphp
                                                            <tr>
                                                                <td class="row-number">{{ $loop->iteration }}</td>
                                                                <td>
                                                                    <select class="form-select" name="filming_locations[{{ $index }}][governorate]" data-location-governorate>
                                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                                        @if (filled($selectedGovernorate) && ! $governorateOptions->contains('code', $selectedGovernorate))
                                                                            <option value="{{ $selectedGovernorate }}" selected>{{ \App\Models\Governorate::labelFor($selectedGovernorate) }}</option>
                                                                        @endif
                                                                        @foreach ($governorateOptions as $governorate)
                                                                            <option value="{{ $governorate->code }}" @selected($selectedGovernorate === $governorate->code)>{{ $governorate->displayName() }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <select class="form-select" name="filming_locations[{{ $index }}][location_type]" data-location-type-select data-selected-type="{{ $selectedLocationType }}">
                                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                                        @if (filled($selectedLocationType) && ! $rowLocationTypeOptions->contains('code', $selectedLocationType))
                                                                            <option value="{{ $selectedLocationType }}" selected>{{ \App\Models\FilmingLocationType::labelFor($selectedLocationType) }}</option>
                                                                        @endif
                                                                        @foreach ($rowLocationTypeOptions as $locationType)
                                                                            <option value="{{ $locationType->code }}" @selected($selectedLocationType === $locationType->code)>{{ $locationType->displayName() }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td><input type="text" class="form-control" name="filming_locations[{{ $index }}][location_name]" value="{{ $row['location_name'] ?? '' }}"></td>
                                                                <td><textarea class="form-control" name="filming_locations[{{ $index }}][nature]" rows="2">{{ $row['nature'] ?? '' }}</textarea></td>
                                                                <td><input type="text" class="form-control" name="filming_locations[{{ $index }}][address]" value="{{ $row['address'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control" name="filming_locations[{{ $index }}][start_date]" value="{{ $row['start_date'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control" name="filming_locations[{{ $index }}][end_date]" value="{{ $row['end_date'] ?? '' }}"></td>
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
                                                                <td class="row-number">{{ $loop->iteration }}</td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][item]" value="{{ $row['item'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][serial_number]" value="{{ $row['serial_number'] ?? '' }}"></td>
                                                                <td><input type="number" min="0" class="form-control" name="imported_equipment[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][origin_country]" value="{{ $row['origin_country'] ?? '' }}"></td>
                                                                <td>
                                                                    @php
                                                                        $selectedEntryPoint = (string) ($row['entry_point'] ?? '');
                                                                    @endphp
                                                                    <select class="form-select" name="imported_equipment[{{ $index }}][entry_point]">
                                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                                        @if (filled($selectedEntryPoint) && ! $equipmentEntryPointOptions->contains('code', $selectedEntryPoint))
                                                                            <option value="{{ $selectedEntryPoint }}" selected>{{ $selectedEntryPoint }}</option>
                                                                        @endif
                                                                        @foreach ($equipmentEntryPointOptions as $option)
                                                                            <option value="{{ $option->code }}" @selected($selectedEntryPoint === $option->code)>{{ $option->displayName() }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
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
                                                                <td class="row-number">{{ $loop->iteration }}</td>
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
                                                    @php
                                                        $selectedAirport = (string) old('airport_filming_airport_name', data_get($airportFilming, 'airport_name'));
                                                    @endphp
                                                    <select class="form-select" name="airport_filming_airport_name">
                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                        @if (filled($selectedAirport) && ! $airportOptions->contains('code', $selectedAirport))
                                                            <option value="{{ $selectedAirport }}" selected>{{ $selectedAirport }}</option>
                                                        @endif
                                                        @foreach ($airportOptions as $option)
                                                            <option value="{{ $option->code }}" @selected($selectedAirport === $option->code)>{{ $option->displayName() }}</option>
                                                        @endforeach
                                                    </select>
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
                                                                <td class="row-number">{{ $loop->iteration }}</td>
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

                        @include('applications.partials.requirement-offcanvases')

                        <div class="col-12">
                            <label class="form-label">{{ __('app.applications.supporting_notes') }}</label>
                            <textarea class="form-control" name="supporting_notes" rows="5">{{ old('supporting_notes', data_get($requirements, 'supporting_notes')) }}</textarea>
                        </div>
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
                    </div>
                </div>
            </div>
        </div>
        <div class="form-actions d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-danger d-flex align-items-center gap-2" type="submit" formnovalidate>
                <i class="ph-fill ph-floppy-disk-back"></i>
                <span>{{ $submitLabel }}</span>
            </button>
            <button type="button" name="previous" class="btn btn-dark request-wizard-previous action-button-previous">
                {{ app()->getLocale() === 'ar' ? 'السابق' : 'Previous' }}
            </button>
        </div>
    </fieldset>
</form>

@once
    @push('styles')
        <style>
            .application-password-rules {
                display: grid;
                gap: .35rem;
                list-style: none;
                margin: .6rem 0 0;
                padding: 0;
                text-align: start;
            }

            .application-password-rules li {
                align-items: center;
                color: var(--bs-secondary-color, #6c757d);
                display: flex;
                font-size: .8125rem;
                font-weight: 600;
                gap: .4rem;
            }

            .application-password-rules li::before {
                border: 1px solid currentColor;
                border-radius: 50%;
                content: "";
                height: .55rem;
                opacity: .75;
                width: .55rem;
            }

            .application-password-rules li.is-valid {
                color: var(--bs-success, #198754);
            }

            .application-password-rules li.is-valid::before {
                background: currentColor;
            }

            .application-password-control {
                position: relative;
            }

            .application-password-control .form-control {
                padding-inline-end: 3rem;
            }

            .application-password-toggle {
                align-items: center;
                background: transparent;
                border: 0;
                color: var(--bs-secondary-color, #6c757d);
                display: inline-flex;
                font-size: 1.1rem;
                inset-block: 0;
                inset-inline-end: .25rem;
                justify-content: center;
                margin: auto 0;
                padding: 0;
                position: absolute;
                width: 2.5rem;
            }

            .application-password-toggle:hover,
            .application-password-toggle:focus {
                color: var(--bs-danger, #721d18);
                outline: none;
            }
        </style>
    @endpush
@endonce

@push('scripts')
    @php
        $applicationNationalityOptionsHtml = '<option value="">'.__('app.admin.select_placeholder').'</option>'.
            $directorNationalityOptions
                ->map(fn ($nationality): string => '<option value="'.e($nationality->code).'">'.e($nationality->displayName()).'</option>')
                ->implode('');
	        $applicationGovernorateOptionsHtml = '<option value="">'.__('app.admin.select_placeholder').'</option>'.
	            $governorateOptions
	                ->map(fn ($governorate): string => '<option value="'.e($governorate->code).'">'.e($governorate->displayName()).'</option>')
	                ->implode('');
	        $applicationGenderOptionsHtml = '<option value="">'.__('app.admin.select_placeholder').'</option>'.
	            collect(['male', 'female'])
	                ->map(fn ($gender): string => '<option value="'.e($gender).'">'.e(__('app.auth.gender_options.'.$gender)).'</option>')
	                ->implode('');
    @endphp
    <script src="{{ asset('js/form-wizard.js') }}?v={{ filemtime(public_path('js/form-wizard.js')) }}"></script>
    <script>
	        const applicationNationalityOptionsHtml = @js($applicationNationalityOptionsHtml);
	        const applicationGovernorateOptionsHtml = @js($applicationGovernorateOptionsHtml);
	        const applicationGenderOptionsHtml = @js($applicationGenderOptionsHtml);
        const applicationLocationTypesByGovernorate = @json($locationTypesByGovernorate);
        const applicationLocationTypeLabels = @json($locationTypeLabels);
        const applicationLocationTypePlaceholder = @js(__('app.admin.select_placeholder'));
        const applicationEquipmentCategoryOptions = @json($equipmentClassificationOptions->map(fn ($option) => ['code' => $option->code, 'label' => $option->displayName()])->values());
        const applicationEquipmentShippingMethodOptions = @json($equipmentShippingMethodOptions->map(fn ($option) => ['code' => $option->code, 'label' => $option->displayName()])->values());
        const applicationEquipmentEntryPointOptions = @json($equipmentEntryPointOptions->map(fn ($option) => ['code' => $option->code, 'label' => $option->displayName()])->values());
        const applicationMilitaryLocationTypeOptions = @json(collect($militaryLocationTypeOptions)->map(fn ($code) => ['code' => $code, 'label' => $militaryLocationTypeLabels[$code] ?? __('app.applications.military_location_types.'.$code)])->values());
        const applicationPasswordStrengthInvalid = @js(__('app.auth.password_strength_invalid'));
        const applicationPasswordToggleMessages = {
            show: @js(__('app.auth.show_password')),
            hide: @js(__('app.auth.hide_password')),
        };

        function applicationLookupOptionsHtml(options, selectedValue) {
            const selected = String(selectedValue || '');

            return '<option value="">' + applicationLocationTypePlaceholder + '</option>'
                + options.map(function (option) {
                    const code = String(option.code || '');
                    const label = String(option.label || option.code || '');

                    return '<option value="' + applicationEscapeHtml(code) + '"' + (selected === code ? ' selected' : '') + '>' + applicationEscapeHtml(label) + '</option>';
                }).join('');
        }

        function applicationLocationTypeOptionsHtml(governorate, selectedValue) {
            const codes = applicationLocationTypesByGovernorate[governorate] || Object.keys(applicationLocationTypeLabels);
            const selected = String(selectedValue || '');

            return '<option value="">' + applicationLocationTypePlaceholder + '</option>'
                + codes.map(function (code) {
                    const safeCode = String(code).replace(/"/g, '&quot;');
                    const safeLabel = String(applicationLocationTypeLabels[code] || code).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    const selectedAttribute = selected === String(code) ? ' selected' : '';

                    return '<option value="' + safeCode + '"' + selectedAttribute + '>' + safeLabel + '</option>';
                }).join('');
        }

        function refreshApplicationLocationTypeSelect(row) {
            if (!row) {
                return;
            }

            const governorate = row.querySelector('[data-location-governorate]');
            const locationType = row.querySelector('[data-location-type-select]');

            if (!governorate || !locationType) {
                return;
            }

            const selected = locationType.value || locationType.dataset.selectedType || '';

            locationType.innerHTML = applicationLocationTypeOptionsHtml(governorate.value, selected);
            locationType.value = selected;

            if (locationType.value !== selected) {
                locationType.value = '';
            }

            locationType.dataset.selectedType = locationType.value;
        }

        function refreshApplicationLocationTypeSelects(root) {
            (root || document).querySelectorAll('[data-location-governorate]').forEach(function (governorate) {
                refreshApplicationLocationTypeSelect(governorate.closest('tr'));
            });
        }

        function applicationEscapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function filmingLocationRowKey(row, fallbackIndex) {
            const locationName = row ? row.querySelector('input[name^="filming_locations"][name$="[location_name]"]') : null;
            const match = locationName && locationName.name ? locationName.name.match(/^filming_locations\[([^\]]+)\]/) : null;

            return match ? match[1] : String(fallbackIndex);
        }

        function collectFilmingLocationOptions() {
            const table = document.querySelector('#filmingLocationsRequestTable tbody') || document.querySelector('#filmingLocationsTable tbody');

            if (!table) {
                return [];
            }

            return Array.from(table.querySelectorAll('tr')).map(function (row, index) {
                const locationName = row.querySelector('input[name^="filming_locations"][name$="[location_name]"]');
                const typedName = locationName ? String(locationName.value || '').trim() : '';
                const label = typedName || @json(__('app.applications.location_number', ['number' => '__NUMBER__'])).replace('__NUMBER__', index + 1);

                return {
                    key: filmingLocationRowKey(row, index),
                    label: label,
                };
            });
        }

        function refreshSelect2Control(select) {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                return;
            }

            const control = window.jQuery(select);
            const options = {
                width: '100%',
            };
            const dropdownParent = control.closest('.offcanvas');

            if (dropdownParent.length) {
                options.dropdownParent = dropdownParent;
            }

            if (control.data('select2')) {
                control.select2('destroy');
            }

            control.select2(options);
        }

        function refreshSpecialLocationRequirementSelects() {
            const locations = collectFilmingLocationOptions();

            document.querySelectorAll('[data-special-location-select]').forEach(function (select) {
                const selectedKeys = Array.from(select.selectedOptions || []).map(function (option) {
                    return option.dataset.locationKey;
                }).filter(Boolean);
                const selectedValues = Array.from(select.selectedOptions || []).map(function (option) {
                    return option.value;
                });

                select.innerHTML = locations.map(function (location) {
                    const selected = selectedKeys.includes(location.key) || selectedValues.includes(location.label);

                    return '<option value="' + applicationEscapeHtml(location.label) + '" data-location-key="' + applicationEscapeHtml(location.key) + '"' + (selected ? ' selected' : '') + '>' + applicationEscapeHtml(location.label) + '</option>';
                }).join('');

                select.disabled = locations.length === 0;
                refreshSelect2Control(select);
            });
        }

        function equipmentTravelerRowKey(row, fallbackIndex) {
            const travelerName = row ? row.querySelector('input[name^="equipment_travelers"][name$="[traveler_name]"]') : null;
            const match = travelerName && travelerName.name ? travelerName.name.match(/^equipment_travelers\[([^\]]+)\]/) : null;

            return match ? match[1] : String(fallbackIndex);
        }

        function collectEquipmentTravelerOptions() {
            const table = document.querySelector('#equipmentTravelersTable tbody');

            if (!table) {
                return [];
            }

            return Array.from(table.querySelectorAll('tr')).map(function (row, index) {
                const travelerName = row.querySelector('input[name^="equipment_travelers"][name$="[traveler_name]"]');
                const typedName = travelerName ? String(travelerName.value || '').trim() : '';
                const label = typedName || @json(__('app.applications.traveler_number', ['number' => '__NUMBER__'])).replace('__NUMBER__', index + 1);

                return {
                    key: equipmentTravelerRowKey(row, index),
                    label: label,
                };
            });
        }

        function equipmentTravelerOptionsHtml(selectedValue, selectedKey) {
            const travelers = collectEquipmentTravelerOptions();
            const selected = String(selectedValue || '');
            const selectedTravelerKey = String(selectedKey || '');

            return '<option value="">' + applicationLocationTypePlaceholder + '</option>'
                + travelers.map(function (traveler) {
                    const isSelected = (selectedTravelerKey && selectedTravelerKey === String(traveler.key)) || selected === String(traveler.label);

                    return '<option value="' + applicationEscapeHtml(traveler.label) + '" data-traveler-key="' + applicationEscapeHtml(traveler.key) + '"' + (isSelected ? ' selected' : '') + '>' + applicationEscapeHtml(traveler.label) + '</option>';
                }).join('');
        }

        function refreshEquipmentTravelerSelects() {
            const travelers = collectEquipmentTravelerOptions();

            document.querySelectorAll('[data-equipment-traveler-select]').forEach(function (select) {
                const selectedOption = select.selectedOptions && select.selectedOptions.length ? select.selectedOptions[0] : null;
                const selectedKey = selectedOption ? selectedOption.dataset.travelerKey || '' : '';
                const selectedValue = selectedOption ? selectedOption.value : select.value;

                select.innerHTML = equipmentTravelerOptionsHtml(selectedValue, selectedKey);
                select.disabled = travelers.length === 0;
            });
        }

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

            if (!table || !button.closest('tr')) {
                return;
            }

            button.closest('tr').remove();
            renumberApplicationAnnexRows(selector);
            refreshSpecialLocationRequirementSelects();
            refreshEquipmentTravelerSelects();
            updateRequirementStatuses();
            updateEquipmentTotals();
        }

        function addApplicationAnnexRow(tableId, fieldName) {
            const table = document.querySelector('#' + tableId + ' tbody');

            if (!table) {
                return;
            }

            const index = table.querySelectorAll('tr').length;
            const row = document.createElement('tr');
            const deleteCell = '<td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, \'#' + tableId + '\')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>';

            if (fieldName === 'cast_crew') {
	                row.innerHTML = '<td class="row-number"></td>'
	                    + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][name]"></td>'
	                    + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][role]"></td>'
	                    + '<td><select class="form-select" name="cast_crew[' + index + '][nationality]">' + applicationNationalityOptionsHtml + '</select></td>'
	                    + '<td><select class="form-select" name="cast_crew[' + index + '][gender]">' + applicationGenderOptionsHtml + '</select></td>'
	                    + '<td><input type="date" class="form-control" name="cast_crew[' + index + '][birth_date]"></td>'
	                    + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][identity_number]"></td>'
                    + deleteCell;
	            } else if (fieldName === 'filming_locations') {
	                row.innerHTML = '<td class="row-number"></td>'
	                    + '<td><select class="form-select" name="filming_locations[' + index + '][governorate]" data-location-governorate>' + applicationGovernorateOptionsHtml + '</select></td>'
	                    + '<td><select class="form-select" name="filming_locations[' + index + '][location_type]" data-location-type-select>' + applicationLocationTypeOptionsHtml('', '') + '</select></td>'
	                    + '<td><input type="text" class="form-control" name="filming_locations[' + index + '][location_name]"></td>'
	                    + '<td><textarea class="form-control" name="filming_locations[' + index + '][nature]" rows="2"></textarea></td>'
	                    + '<td><input type="text" class="form-control" name="filming_locations[' + index + '][address]"></td>'
	                    + '<td><input type="date" class="form-control" name="filming_locations[' + index + '][start_date]"></td>'
	                    + '<td><input type="date" class="form-control" name="filming_locations[' + index + '][end_date]"></td>'
	                    + deleteCell;
            } else if (fieldName === 'imported_equipment') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + index + '][item]"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + index + '][serial_number]"></td>'
                    + '<td><input type="number" min="0" class="form-control" name="imported_equipment[' + index + '][quantity]"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + index + '][origin_country]"></td>'
                    + '<td><select class="form-select" name="imported_equipment[' + index + '][entry_point]">' + applicationLookupOptionsHtml(applicationEquipmentEntryPointOptions, '') + '</select></td>'
                    + '<td><input type="date" class="form-control" name="imported_equipment[' + index + '][arrival_date]"></td>'
                    + deleteCell;
            } else if (fieldName === 'military_border_equipment') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][item]"></td>'
                    + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][serial_number]"></td>'
                    + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][location_reference]"></td>'
                    + '<td><input type="number" min="0" class="form-control" name="military_border_equipment[' + index + '][quantity]"></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control" name="military_border_equipment[' + index + '][unit_value]"></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control" name="military_border_equipment[' + index + '][total_value]"></td>'
                    + '<td><select class="form-select" name="military_border_equipment[' + index + '][classification]">' + applicationLookupOptionsHtml(applicationEquipmentCategoryOptions, '') + '</select></td>'
                    + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][entry_method]"></td>'
                    + '<td><select class="form-select" name="military_border_equipment[' + index + '][entry_point]">' + applicationLookupOptionsHtml(applicationEquipmentEntryPointOptions, '') + '</select></td>'
                    + deleteCell;
            } else if (fieldName === 'governmental_scenes') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="governmental_scenes[' + index + '][site_name]"></td>'
                    + '<td><input type="text" class="form-control" name="governmental_scenes[' + index + '][authority]"></td>'
                    + '<td><input type="text" class="form-control" name="governmental_scenes[' + index + '][scene_description]"></td>'
                    + '<td><input type="date" class="form-control" name="governmental_scenes[' + index + '][filming_date]"></td>'
                    + deleteCell;
            } else if (fieldName === 'equipment_flights') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="equipment_flights[' + index + '][flight_type]"></td>'
                    + '<td><input type="text" class="form-control" name="equipment_flights[' + index + '][flight_number]"></td>'
                    + '<td><input type="date" class="form-control" name="equipment_flights[' + index + '][flight_date]"></td>'
                    + '<td><input type="time" class="form-control" name="equipment_flights[' + index + '][flight_time]"></td>'
                    + '<td><input type="text" class="form-control" name="equipment_flights[' + index + '][departure_city]"></td>'
                    + '<td><input type="text" class="form-control" name="equipment_flights[' + index + '][arrival_city]"></td>'
                    + deleteCell;
            } else if (fieldName === 'equipment_travelers') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="equipment_travelers[' + index + '][traveler_name]"></td>'
                    + '<td><input type="date" class="form-control" name="equipment_travelers[' + index + '][arrival_date]"></td>'
                    + '<td><input type="text" class="form-control" name="equipment_travelers[' + index + '][arrival_flight_number]"></td>'
                    + '<td><input type="date" class="form-control" name="equipment_travelers[' + index + '][departure_date]"></td>'
                    + '<td><input type="text" class="form-control" name="equipment_travelers[' + index + '][departure_flight_number]"></td>'
                    + deleteCell;
            } else if (fieldName === 'imported_equipment_shipping' || fieldName === 'imported_equipment_traveler') {
                const rowKey = (fieldName === 'imported_equipment_traveler' ? 'traveler_' : 'shipping_') + index;
                const group = fieldName === 'imported_equipment_traveler' ? 'traveler' : 'shipping';
                const referenceField = group === 'traveler' ? 'traveler_name' : 'flight_reference';
                const referenceControl = group === 'traveler'
                    ? '<select class="form-select" name="imported_equipment[' + rowKey + '][' + referenceField + ']" data-equipment-traveler-select>' + equipmentTravelerOptionsHtml('', '') + '</select>'
                    : '<input type="text" class="form-control" name="imported_equipment[' + rowKey + '][' + referenceField + ']">';

                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="hidden" name="imported_equipment[' + rowKey + '][transport_group]" value="' + group + '"><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][item]"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][serial_number]"></td>'
                    + '<td>' + referenceControl + '</td>'
                    + '<td><input type="number" min="0" class="form-control" name="imported_equipment[' + rowKey + '][quantity]"></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[' + rowKey + '][unit_value]"></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[' + rowKey + '][total_value]"></td>'
                    + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][classification]">' + applicationLookupOptionsHtml(applicationEquipmentCategoryOptions, '') + '</select></td>'
                    + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][shipping_method]">' + applicationLookupOptionsHtml(applicationEquipmentShippingMethodOptions, '') + '</select></td>'
                    + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][entry_point]">' + applicationLookupOptionsHtml(applicationEquipmentEntryPointOptions, '') + '</select></td>'
                    + deleteCell;
            } else if (fieldName === 'military_border_locations') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><select class="form-select" name="military_border_locations[' + index + '][governorate]">' + applicationGovernorateOptionsHtml + '</select></td>'
                    + '<td><input type="text" class="form-control" name="military_border_locations[' + index + '][location_name]"></td>'
                    + '<td><input type="text" class="form-control" name="military_border_locations[' + index + '][address]"></td>'
                    + '<td><textarea class="form-control" name="military_border_locations[' + index + '][nature]" rows="2"></textarea></td>'
                    + '<td><select class="form-select" name="military_border_locations[' + index + '][location_type]">' + applicationLookupOptionsHtml(applicationMilitaryLocationTypeOptions, '') + '</select></td>'
                    + '<td><input type="date" class="form-control" name="military_border_locations[' + index + '][start_date]"></td>'
                    + '<td><input type="date" class="form-control" name="military_border_locations[' + index + '][end_date]"></td>'
                    + deleteCell;
            } else if (fieldName === 'airport_people') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][full_name]"></td>'
                    + '<td><select class="form-select" name="airport_people[' + index + '][nationality]">' + applicationNationalityOptionsHtml + '</select></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][mother_name]"></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][identity_number]"></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][profession]"></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][address_phone]"></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][entry_reason]"></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][target_area]"></td>'
                    + deleteCell;
            }

            table.appendChild(row);
            renumberApplicationAnnexRows('#' + tableId);
            refreshApplicationLocationTypeSelect(row);
            refreshSpecialLocationRequirementSelects();
            refreshEquipmentTravelerSelects();
            updateRequirementStatuses();
            updateEquipmentTotals();
        }

        function controlHasRequirementValue(control) {
            if (control.disabled || control.type === 'hidden' || control.closest('.legacy-annex-inline')) {
                return false;
            }

            if (control.type === 'checkbox' || control.type === 'radio') {
                return control.checked;
            }

            if (control.tagName === 'SELECT' && control.multiple) {
                return Array.from(control.selectedOptions || []).some(function (option) {
                    return option.value.trim() !== '';
                });
            }

            return String(control.value || '').trim() !== '';
        }

        function requirementFormIsFilled(targetId) {
            const drawer = document.getElementById(targetId);

            if (!drawer) {
                return false;
            }

            return Array.from(drawer.querySelectorAll('input, select, textarea')).some(controlHasRequirementValue);
        }

        function updateRequirementStatuses() {
            document.querySelectorAll('[data-requirement-row]').forEach(function (row) {
                const status = row.querySelector('[data-requirement-filled]');

                if (!status) {
                    return;
                }

                status.classList.toggle('d-none', !requirementFormIsFilled(row.dataset.requirementTarget));
            });
        }

        function updateEquipmentTotals() {
            document.querySelectorAll('[data-equipment-total]').forEach(function (target) {
                const table = document.querySelector(target.dataset.equipmentTotal);

                if (!table) {
                    target.textContent = '0';
                    return;
                }

                const total = Array.from(table.querySelectorAll('input[name*="[total_value]"]'))
                    .reduce(function (sum, field) {
                        return sum + (Number.parseFloat(field.value) || 0);
                    }, 0);

                target.textContent = total.toLocaleString(undefined, {
                    maximumFractionDigits: 2,
                });
            });
        }

        function syncRoutingField(source) {
            const targetName = source.getAttribute('data-routing-multiple');
            const target = document.querySelector('[data-routing-field="' + targetName + '"]');

            if (!target) {
                return;
            }

            const selected = Array.from(source.selectedOptions || [])
                .map(function (option) {
                    return option.value;
                })
                .filter(function (value) {
                    return value && value !== 'other';
                });

            if (selected.length > 0) {
                target.value = selected[0];
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        document.querySelectorAll('[data-routing-multiple]').forEach(function (source) {
            source.addEventListener('change', function () {
                syncRoutingField(source);
            });
            syncRoutingField(source);
        });

        function initializeScheduleDateValidation(form) {
            const fields = {};

            form.querySelectorAll('[data-schedule-date]').forEach(function (field) {
                fields[field.dataset.scheduleDate] = field;
            });

            const rules = [
                {
                    field: 'preparation_end',
                    min: 'preparation_start',
                    message: @json(__('app.applications.schedule_validation.end_after_start', ['phase' => __('app.applications.schedule_phases.preparation')])),
                },
                {
                    field: 'shooting_start',
                    min: 'preparation_end',
                    message: @json(__('app.applications.schedule_validation.start_after_previous_end', ['current' => __('app.applications.schedule_phases.shooting'), 'previous' => __('app.applications.schedule_phases.preparation')])),
                },
                {
                    field: 'shooting_end',
                    min: 'shooting_start',
                    message: @json(__('app.applications.schedule_validation.end_after_start', ['phase' => __('app.applications.schedule_phases.shooting')])),
                },
                {
                    field: 'wrap_start',
                    min: 'shooting_end',
                    message: @json(__('app.applications.schedule_validation.start_after_previous_end', ['current' => __('app.applications.schedule_phases.wrap'), 'previous' => __('app.applications.schedule_phases.shooting')])),
                },
                {
                    field: 'wrap_end',
                    min: 'wrap_start',
                    message: @json(__('app.applications.schedule_validation.end_after_start', ['phase' => __('app.applications.schedule_phases.wrap')])),
                },
                {
                    field: 'post_production_start',
                    min: 'wrap_end',
                    message: @json(__('app.applications.schedule_validation.start_after_previous_end', ['current' => __('app.applications.schedule_phases.post_production'), 'previous' => __('app.applications.schedule_phases.wrap')])),
                },
                {
                    field: 'post_production_end',
                    min: 'post_production_start',
                    message: @json(__('app.applications.schedule_validation.end_after_start', ['phase' => __('app.applications.schedule_phases.post_production')])),
                },
            ];

            const validateScheduleDates = function () {
                Object.values(fields).forEach(function (field) {
                    field.setCustomValidity('');
                });

                rules.forEach(function (rule) {
                    const field = fields[rule.field];
                    const minField = fields[rule.min];

                    if (!field || !minField) {
                        return;
                    }

                    if (minField.value) {
                        field.min = minField.value;
                    } else {
                        field.removeAttribute('min');
                    }

                    if (field.value && minField.value && field.value < minField.value) {
                        field.setCustomValidity(rule.message);
                    }
                });
            };

            Object.values(fields).forEach(function (field) {
                field.addEventListener('change', validateScheduleDates);
                field.addEventListener('input', validateScheduleDates);
            });

            validateScheduleDates();
        }

        function bindApplicationPasswordStrength(input) {
            const rules = input.closest('[data-application-password-wrapper]')?.querySelector('[data-application-password-rules]');

            if (!rules) {
                return;
            }

            const ruleItems = {
                length: rules.querySelector('[data-application-password-rule="length"]'),
                mixed: rules.querySelector('[data-application-password-rule="mixed"]'),
                number: rules.querySelector('[data-application-password-rule="number"]'),
                symbol: rules.querySelector('[data-application-password-rule="symbol"]'),
            };

            const update = function () {
                const value = input.value;
                const checks = {
                    length: value.length >= 8,
                    mixed: /[a-z]/.test(value) && /[A-Z]/.test(value),
                    number: /\d/.test(value),
                    symbol: /[^A-Za-z0-9]/.test(value),
                };
                const isStarted = value.length > 0;
                const isValid = Object.values(checks).every(Boolean);

                rules.hidden = !isStarted;

                Object.entries(checks).forEach(function ([key, passes]) {
                    ruleItems[key]?.classList.toggle('is-valid', passes);
                });

                input.setCustomValidity(isStarted && !isValid ? applicationPasswordStrengthInvalid : '');
            };

            input.addEventListener('input', update);
            input.addEventListener('focus', update);
            update();
        }

        function bindApplicationPasswordToggle(toggle) {
            const passwordInput = toggle.closest('.application-password-control')?.querySelector('input');
            const icon = toggle.querySelector('i');

            if (!passwordInput || !icon || toggle.dataset.passwordToggleBound === 'true') {
                return;
            }

            toggle.dataset.passwordToggleBound = 'true';
            toggle.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                const label = isPassword ? applicationPasswordToggleMessages.hide : applicationPasswordToggleMessages.show;

                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                icon.classList.toggle('ph-eye', isPassword);
                icon.classList.toggle('ph-eye-slash', !isPassword);
                toggle.setAttribute('aria-label', label);
                toggle.setAttribute('title', label);
            });
        }

        const requestForm = document.getElementById('form-wizard1');

        if (requestForm) {
            const disableLegacyAnnexFields = function () {
                requestForm.querySelectorAll('.legacy-annex-inline input, .legacy-annex-inline select, .legacy-annex-inline textarea').forEach(function (field) {
                    field.disabled = true;
                });
            };

            requestForm.addEventListener('invalid', function (event) {
                const drawer = event.target.closest('.offcanvas');

                if (!drawer || !window.bootstrap) {
                    return;
                }

                window.bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
            }, true);

            requestForm.addEventListener('submit', function () {
                disableLegacyAnnexFields();
            });

            initializeScheduleDateValidation(requestForm);
            requestForm.querySelectorAll('[data-application-password-strength]').forEach(bindApplicationPasswordStrength);
            requestForm.querySelectorAll('[data-application-password-toggle]').forEach(bindApplicationPasswordToggle);

            ['input', 'change'].forEach(function (eventName) {
                requestForm.addEventListener(eventName, function (event) {
                    if (event.target.matches('[data-location-governorate]')) {
                        refreshApplicationLocationTypeSelect(event.target.closest('tr'));
                    }

                    if (event.target.matches('input[name^="filming_locations"][name$="[location_name]"]')) {
                        refreshSpecialLocationRequirementSelects();
                    }

                    if (event.target.matches('input[name^="equipment_travelers"][name$="[traveler_name]"]')) {
                        refreshEquipmentTravelerSelects();
                    }

                    if (!event.target.closest('.offcanvas')) {
                        return;
                    }

                    updateRequirementStatuses();
                    updateEquipmentTotals();
                });
            });
        }

        [
            '#castCrewTable',
            '#filmingLocationsTable',
            '#importedEquipmentTable',
            '#militaryBorderEquipmentTable',
            '#governmentalScenesTable',
            '#castCrewRequestTable',
            '#filmingLocationsRequestTable',
            '#equipmentFlightsTable',
            '#importedEquipmentShippingTable',
            '#equipmentTravelersTable',
            '#importedEquipmentTravelerTable',
            '#militaryBorderLocationsTable',
            '#militaryBorderEquipmentRequestTable',
            '#airportPeopleTable',
            '#governmentalScenesRequestTable',
        ].forEach(renumberApplicationAnnexRows);

        updateRequirementStatuses();
        updateEquipmentTotals();
        refreshApplicationLocationTypeSelects(document);
        refreshSpecialLocationRequirementSelects();
        refreshEquipmentTravelerSelects();

        window.addApplicationAnnexRow = addApplicationAnnexRow;
        window.removeApplicationAnnexRow = removeApplicationAnnexRow;
    </script>
@endpush
