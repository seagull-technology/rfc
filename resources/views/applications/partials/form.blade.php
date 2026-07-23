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
    $productionTerms = data_get($annex, 'production_terms', []);
    $ministryInteriorPersonalDetails = data_get($annex, 'ministry_interior_personal_details', []);
    $workContentSummary = data_get($annex, 'work_content_summary', []);
    $safetyGuidelines = data_get($annex, 'safety_guidelines', []);
    $airportFilming = data_get($annex, 'airport_filming', []);
    $workCategoryOptions = collect(data_get($workLookupOptions ?? [], 'work_categories', []));
    $releaseMethodOptions = collect(data_get($workLookupOptions ?? [], 'release_methods', []));
    $formLookupOptions = $formLookupOptions ?? [];
    $equipmentClassificationOptions = collect(data_get($formLookupOptions, 'equipment_categories', []));
    $equipmentEntryPointOptions = collect(data_get($formLookupOptions, 'equipment_entry_points', []));
    $airportOptions = collect(data_get($formLookupOptions, 'airports', []));
    $specialLocationRequirementOptions = collect(data_get($formLookupOptions, 'special_location_requirements', []));
    $supportAuthorityEntities = collect(data_get($formLookupOptions, 'support_authority_entities', []));
    $budgetSpendingCategoryOptions = collect(data_get($formLookupOptions, 'budget_spending_categories', []));
    $schedulePhaseOptions = ['preparation', 'shooting', 'wrap', 'post_production'];
    $budgetItemOptions = $budgetSpendingCategoryOptions->pluck('code')->all() ?: ['jordanian_actors', 'jordanian_crew', 'flights_travel', 'accommodation', 'transportation', 'production_design', 'picture_vehicles', 'wardrobe', 'hair_makeup', 'catering', 'equipment_costs', 'location_fees', 'insurance', 'per_diems', 'health_safety', 'other_1', 'other_2', 'other_3'];
    $budgetItemLabels = $budgetSpendingCategoryOptions->mapWithKeys(fn ($option) => [$option->code => $option->displayName()])->all();
    $locationRequirementOptions = $specialLocationRequirementOptions->pluck('code')->all() ?: ['road_closures', 'police_presence', 'armed_forces', 'regular_aerial_filming', 'drone_filming', 'special_effects', 'construction_work', 'animals', 'weapons', 'other'];
    $locationRequirementLabels = $specialLocationRequirementOptions->mapWithKeys(fn ($option) => [$option->code => $option->displayName()])->all();
    $locationRequirementAuthorityCodes = $specialLocationRequirementOptions
        ->mapWithKeys(fn ($option): array => [(string) $option->code => $option->entities->pluck('code')->map(fn ($code): string => (string) $code)->values()->all()])
        ->all();
    $locationRequirementPrompts = $specialLocationRequirementOptions
        ->mapWithKeys(fn ($option): array => [(string) $option->code => $option->notesPrompt()])
        ->all();
    $governorateOptions = collect(data_get($locationLookupOptions ?? [], 'governorates', []));
    $locationTypeOptions = collect(data_get($locationLookupOptions ?? [], 'location_types', []));
    $locationTypesByGovernorate = (array) data_get($locationLookupOptions ?? [], 'location_types_by_governorate', []);
    $locationTypeLabels = (array) data_get($locationLookupOptions ?? [], 'location_type_labels', []);
    $locationTypeApprovalDays = (array) data_get($locationLookupOptions ?? [], 'location_type_approval_days', []);
    $flightTypeOptions = ['arrival', 'departure'];
    $projectNationalityOptions = collect(data_get($nationalityOptions ?? [], 'project', []));
    $directorNationalityOptions = collect(data_get($nationalityOptions ?? [], 'director', []));
    $internationalProducerNationalityOptions = collect(data_get($nationalityOptions ?? [], 'international_producer', []));
    $defaultWorkCategory = \App\Models\WorkCategory::defaultCode();
    $defaultReleaseMethod = \App\Models\ReleaseMethod::defaultCode();
    $workCategoryOptionCodes = $workCategoryOptions->pluck('code')->map(fn ($code): string => (string) $code)->all();
    $releaseMethodOptionCodes = $releaseMethodOptions->pluck('code')->map(fn ($code): string => (string) $code)->all();
    $selectedWorkCategory = old('work_category', $application->work_category ?: data_get($projectMeta, 'work_categories.0', $defaultWorkCategory));
    $selectedReleaseMethods = array_values(array_filter(
        (array) old('release_methods', data_get($projectMeta, 'release_methods', [$application->release_method ?: $defaultReleaseMethod])),
        fn ($code): bool => filled($code) && in_array((string) $code, $releaseMethodOptionCodes, true),
    ));
    $selectedWorkCategory = filled($selectedWorkCategory) ? (string) $selectedWorkCategory : (in_array($defaultWorkCategory, $workCategoryOptionCodes, true) ? $defaultWorkCategory : '');
    $workSummaryMinWordsByCategory = $workCategoryOptions
        ->mapWithKeys(fn ($option): array => [(string) $option->code => $option->workSummaryMinWords()])
        ->all();
    $selectedWorkSummaryMinWords = \App\Models\WorkCategory::workSummaryMinWordsFor($selectedWorkCategory);
    if (filled($selectedWorkCategory)) {
        $workSummaryMinWordsByCategory[$selectedWorkCategory] = $selectedWorkSummaryMinWords;
    }
    $selectedReleaseMethods = $selectedReleaseMethods ?: (in_array($defaultReleaseMethod, $releaseMethodOptionCodes, true) ? [$defaultReleaseMethod] : []);
    $schedulePhases = old('schedule_phases', data_get($scheduleMeta, 'phases', []));
    $budgetItems = old('budget_items', data_get($budgetMeta, 'items', []));
    $castCrewRows = old('cast_crew', data_get($annex, 'cast_crew', [[
        'name' => '', 'role' => '', 'nationality' => '', 'gender' => '', 'birth_date' => '',
        'identity_number' => '', 'individual_number' => '', 'identity_verification_status' => 'unverified',
    ]]));
    $maxCrewBirthDate = now()->subDay()->toDateString();
    $minimumFilmingLocationStartDate = \App\Support\JordanBusinessDays::today()->toDateString();
    $filmingLocationRows = old('filming_locations', data_get($annex, 'filming_locations', [['governorate' => '', 'location_name' => '', 'address' => '', 'nature' => '', 'location_type' => '', 'start_date' => '', 'end_date' => '']]));
    $specialLocationRequirementRows = old('special_location_requirements', data_get($annex, 'special_location_requirements', collect($locationRequirementOptions)->mapWithKeys(fn ($option) => [$option => ['locations' => [], 'notes' => '']])->all()));
    $locationRequirementSelectionForRow = static function (array $row) use ($specialLocationRequirementRows): array {
        $selected = collect((array) data_get($row, 'special_requirements', []))
            ->map(fn ($value): string => (string) $value)
            ->filter(fn (string $value): bool => filled($value))
            ->values()
            ->all();

        if ($selected !== []) {
            return $selected;
        }

        $locationName = trim((string) data_get($row, 'location_name'));

        if (blank($locationName)) {
            return [];
        }

        return collect((array) $specialLocationRequirementRows)
            ->filter(fn ($requirementRow): bool => in_array($locationName, (array) data_get($requirementRow, 'locations', []), true))
            ->keys()
            ->map(fn ($value): string => (string) $value)
            ->values()
            ->all();
    };
    $importedEquipmentRows = old('imported_equipment', data_get($annex, 'imported_equipment', [['shipping_company_name' => '', 'invoice_number' => '', 'bill_of_lading_number' => '', 'arrival_date' => '', 'departure_date' => '', 'customs_center' => '', 'attachment_path' => '', 'attachment_name' => '']]));
    $publicSecuritySupportRows = old('public_security_support', data_get($annex, 'public_security_support', [['day' => '', 'date' => '', 'time_from' => '', 'time_to' => '', 'location' => '', 'requirement' => '', 'notes' => '']]));
    $militarySupportRows = old('military_support', data_get($annex, 'military_support', [['day' => '', 'date' => '', 'time_from' => '', 'time_to' => '', 'location' => '', 'requirement' => '', 'notes' => '']]));
    $supportAuthorityOptions = $supportAuthorityEntities
        ->mapWithKeys(fn ($entity): array => [(string) $entity->code => $entity->displayName()])
        ->all();
    $emptyLocationSupportRequirement = ['authority' => '', 'requirement' => '', 'date' => '', 'time_from' => '', 'time_to' => '', 'notes' => ''];
    $locationSupportRequirementsForRow = static function (array $row, int $index = 0) use ($publicSecuritySupportRows, $militarySupportRows, $emptyLocationSupportRequirement): array {
        $current = collect((array) data_get($row, 'support_requirements', []))
            ->filter(fn ($requirement): bool => collect((array) $requirement)->filter(fn ($value): bool => filled($value))->isNotEmpty())
            ->map(fn ($requirement): array => array_merge($emptyLocationSupportRequirement, (array) $requirement))
            ->values();

        if ($current->isNotEmpty()) {
            return $current->all();
        }

        $locationName = trim((string) data_get($row, 'location_name'));

        if (blank($locationName)) {
            return [$emptyLocationSupportRequirement];
        }

        $legacyRows = collect();

        collect((array) $publicSecuritySupportRows)
            ->filter(fn ($supportRow): bool => trim((string) data_get($supportRow, 'location')) === $locationName)
            ->each(fn ($supportRow) => $legacyRows->push(array_merge($emptyLocationSupportRequirement, [
                'authority' => 'public_security',
                'requirement' => data_get($supportRow, 'requirement'),
                'date' => data_get($supportRow, 'date'),
                'time_from' => data_get($supportRow, 'time_from'),
                'time_to' => data_get($supportRow, 'time_to'),
                'notes' => data_get($supportRow, 'notes'),
            ])));

        collect((array) $militarySupportRows)
            ->filter(fn ($supportRow): bool => trim((string) data_get($supportRow, 'location')) === $locationName)
            ->each(fn ($supportRow) => $legacyRows->push(array_merge($emptyLocationSupportRequirement, [
                'authority' => 'military',
                'requirement' => data_get($supportRow, 'requirement'),
                'date' => data_get($supportRow, 'date'),
                'time_from' => data_get($supportRow, 'time_from'),
                'time_to' => data_get($supportRow, 'time_to'),
                'notes' => data_get($supportRow, 'notes'),
            ])));

        return $legacyRows->isNotEmpty() ? $legacyRows->values()->all() : [$emptyLocationSupportRequirement];
    };
    $locationSupportRequirementForRow = static fn (array $row, int $index = 0): array => $locationSupportRequirementsForRow($row, $index)[0] ?? $emptyLocationSupportRequirement;
    $locationSupportEditingState = \App\Support\LocationSupportRequirements::editingState(
        (array) $annex,
        (array) $filmingLocationRows,
        old('location_support_requirements'),
    );
    $filmingLocationRows = $locationSupportEditingState['locations'];
    $locationSupportRequirementRows = $locationSupportEditingState['requirements'];
    $airportPeopleRows = old('airport_people', data_get($annex, 'airport_people', [['full_name' => '', 'first_name' => '', 'second_name' => '', 'third_name' => '', 'family_name' => '', 'nationality' => '', 'mother_name' => '', 'identity_number' => '', 'profession' => '', 'address_phone' => '', 'entry_reason' => '', 'target_area' => '']]));
    $governmentalSceneRows = old('governmental_scenes', data_get($annex, 'governmental_scenes', [['site_name' => '', 'authority' => '', 'scene_description' => '', 'filming_date' => '']]));
    $selectedProjectNationalities = array_values(array_filter(
        (array) old('project_nationalities', $application->projectNationalityCodes()),
        fn ($code): bool => filled($code),
    ));
    $legacySelectedProjectNationality = old('project_nationality', $application->project_nationality);
    if (blank($selectedProjectNationalities) && filled($legacySelectedProjectNationality)) {
        $selectedProjectNationalities = [(string) $legacySelectedProjectNationality];
    }
    $canUseInternationalProjectSection = $canUseInternationalProjectSection ?? true;
    $requiresInternationalProjectSection = $canUseInternationalProjectSection && collect($selectedProjectNationalities)->contains(fn ($code): bool => $code !== 'jordanian');
    $selectedDirectorNationality = old('director_nationality', data_get($director, 'director_nationality'));
    $selectedInternationalProducerNationality = old('international_producer_nationality', data_get($international, 'international_producer_nationality'));
    $internationalAccountExists = filled(data_get($international, 'account.user_id')) || filled(data_get($international, 'account.email'));
    $locationTypeOptionsForGovernorate = static function ($governorateCode) use ($locationTypeOptions, $locationTypesByGovernorate) {
        $governorateCode = filled($governorateCode) ? (string) $governorateCode : null;

        if (! $governorateCode || ! isset($locationTypesByGovernorate[$governorateCode])) {
            return $locationTypeOptions;
        }

        return $locationTypeOptions->filter(fn ($locationType): bool => in_array($locationType->code, (array) $locationTypesByGovernorate[$governorateCode], true))->values();
    };
@endphp

@include('applications.partials.authority-change-requests', ['changeRequestViewer' => 'applicant'])

<form
    id="form-wizard1"
    method="POST"
    action="{{ $formAction }}"
    class="mt-3 text-center form-content"
    enctype="multipart/form-data"
    data-page-validation-message="{{ __('app.applications.page_validation_summary') }}"
    data-validation-focus-fieldset="{{ data_get(session('application_validation_focus'), 'fieldset') }}"
    data-validation-focus-tab="{{ data_get(session('application_validation_focus'), 'tab') }}"
    data-validation-focus-drawer="{{ data_get(session('application_validation_focus'), 'drawer') }}"
    data-has-server-validation-errors="{{ $errors->any() ? '1' : '0' }}"
>
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

    @if ($errors->any())
        <div class="alert alert-danger text-start" role="alert" data-server-validation-summary>
            <div class="fw-600 mb-2">{{ __('app.applications.submission_validation_redirected') }}</div>
            <ul class="mb-0 ps-4">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                                <select name="project_nationalities[]" class="form-select select2-basic-multiple" multiple required>
                                    @foreach ($selectedProjectNationalities as $selectedProjectNationality)
                                        @if (! $projectNationalityOptions->contains('code', $selectedProjectNationality))
                                            <option value="{{ $selectedProjectNationality }}" selected>{{ \App\Models\Nationality::labelFor($selectedProjectNationality) }}</option>
                                        @endif
                                    @endforeach
                                    @foreach ($projectNationalityOptions as $nationality)
                                        <option value="{{ $nationality->code }}" @selected(in_array($nationality->code, $selectedProjectNationalities, true))>{{ $nationality->displayName() }}</option>
                                    @endforeach
                                </select>
                                @error('project_nationalities')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('project_nationalities.*')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @if ($errors->has('project_nationality'))
                                    <div class="invalid-feedback d-block">{{ $errors->first('project_nationality') }}</div>
                                @endif
                                <input type="hidden" name="international_account_exists" value="{{ $internationalAccountExists ? '1' : '0' }}">
                                <input type="hidden" name="international_account_user_id" value="{{ data_get($international, 'account.user_id') }}">
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
                                                'budget' => __('app.applications.estimated_budget'),
                                            ] as $tabKey => $tabLabel)
                                                @php
                                                    $isInternationalProjectTab = $tabKey === 'international_projects';
                                                @endphp
                                                @continue($isInternationalProjectTab && ! $canUseInternationalProjectSection)
                                                <button class="nav-link {{ $loop->first ? 'active' : '' }} {{ $isInternationalProjectTab && ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-bs-toggle="pill" type="button" data-bs-target="#{{ $tabKey }}_tab" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" @if($isInternationalProjectTab) data-international-project-tab @endif>
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
                                                ] as $field => $label)
                                                    @php
                                                        $isLockedProducerField = in_array($field, $lockedProducerFieldNames, true);
                                                        $producerFieldValue = old($field, $isLockedProducerField ? ($lockedProducerFields[$field] ?? data_get($producer, $field)) : data_get($producer, $field));
                                                    @endphp
                                                    <div class="col-lg-{{ in_array($field, ['producer_name', 'production_company_name'], true) ? '12' : '6' }}">
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
                                                        <label class="form-label">{{ __('app.applications.director_email') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="email" name="director_email" value="{{ old('director_email', data_get($director, 'director_email')) }}" required>
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

                                        @if ($canUseInternationalProjectSection)
                                        <div class="tab-pane fade {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" id="international_projects_tab" role="tabpanel" data-international-project-section>
                                            <div class="row g-3">
                                                <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_name') }}</label>
                                                        <span class="text-danger {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-international-required-marker>*</span>
                                                        <input class="form-control" type="text" name="international_producer_name" value="{{ old('international_producer_name', data_get($international, 'international_producer_name')) }}" data-international-project-field @required($requiresInternationalProjectSection) @disabled(! $requiresInternationalProjectSection)>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_nationality') }}</label>
                                                        <span class="text-danger {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-international-required-marker>*</span>
                                                        <select class="form-select select2-basic-single" name="international_producer_nationality" data-international-project-field @required($requiresInternationalProjectSection) @disabled(! $requiresInternationalProjectSection)>
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
                                                        <span class="text-danger {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-international-required-marker>*</span>
                                                        <input class="form-control" type="text" name="international_producer_company" value="{{ old('international_producer_company', data_get($international, 'international_producer_company')) }}" data-international-project-field @required($requiresInternationalProjectSection) @disabled(! $requiresInternationalProjectSection)>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_email') }}</label>
                                                        <span class="text-danger {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-international-required-marker>*</span>
                                                        <input class="form-control" type="email" name="international_producer_email" value="{{ old('international_producer_email', data_get($international, 'international_producer_email')) }}" data-international-project-field @required($requiresInternationalProjectSection) @disabled(! $requiresInternationalProjectSection)>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_profile_url') }}</label>
                                                        <span class="text-danger {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-international-required-marker>*</span>
                                                        <input class="form-control" type="url" name="international_producer_profile_url" value="{{ old('international_producer_profile_url', data_get($international, 'international_producer_profile_url')) }}" data-international-project-field @required($requiresInternationalProjectSection) @disabled(! $requiresInternationalProjectSection)>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_address') }}</label>
                                                        <span class="text-danger {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-international-required-marker>*</span>
                                                        <input class="form-control" type="text" name="international_producer_address" value="{{ old('international_producer_address', data_get($international, 'international_producer_address')) }}" data-international-project-field @required($requiresInternationalProjectSection) @disabled(! $requiresInternationalProjectSection)>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_website') }}</label>
                                                        <span class="text-danger {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-international-required-marker>*</span>
                                                        <input class="form-control" type="url" name="international_producer_website" value="{{ old('international_producer_website', data_get($international, 'international_producer_website')) }}" data-international-project-field @required($requiresInternationalProjectSection) @disabled(! $requiresInternationalProjectSection)>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="border rounded p-3 bg-transparent">
                                                        <div class="row g-3">
                                                            <div class="col-lg-6">
                                                                <div class="form-group">
                                                                    <label class="form-label">{{ __('app.applications.international_liaison_email') }}</label>
                                                                    <span class="text-danger {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-international-required-marker>*</span>
                                                                    <input class="form-control" type="email" name="international_liaison_email" value="{{ old('international_liaison_email', data_get($international, 'international_liaison_email')) }}" data-international-project-field @required($requiresInternationalProjectSection) @disabled(! $requiresInternationalProjectSection)>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-6">
                                                                <div class="form-group">
                                                                    <label class="form-label">{{ __('app.applications.international_liaison_mobile') }}</label>
                                                                    <span class="text-danger {{ ! $requiresInternationalProjectSection ? 'd-none' : '' }}" data-international-required-marker>*</span>
                                                                    <input class="form-control" type="text" name="international_liaison_mobile" value="{{ old('international_liaison_mobile', data_get($international, 'international_liaison_mobile')) }}" data-international-project-field @required($requiresInternationalProjectSection) @disabled(! $requiresInternationalProjectSection)>
                                                                </div>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="alert alert-info mb-0" role="status">
                                                                    <div class="d-flex gap-2 align-items-start">
                                                                        <i class="ph ph-shield-check fs-4" aria-hidden="true"></i>
                                                                        <div>
                                                                            <strong class="d-block mb-1">
                                                                                {{ $internationalAccountExists
                                                                                    ? __('app.applications.foreign_producer_account_linked_title')
                                                                                    : __('app.applications.foreign_producer_invitation_title') }}
                                                                            </strong>
                                                                            <span>
                                                                                {{ $internationalAccountExists
                                                                                    ? __('app.applications.foreign_producer_account_linked_body')
                                                                                    : __('app.applications.foreign_producer_invitation_body') }}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif

                                        <div class="tab-pane fade" id="work_category_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.work_type') }}</label>
                                                <span class="text-danger">*</span>
                                                <select name="work_category" class="form-select select2-basic-single" required data-routing-field="work_category" data-work-category-summary-rule>
                                                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                    @if (filled($selectedWorkCategory) && ! in_array($selectedWorkCategory, $workCategoryOptionCodes, true))
                                                        <option value="{{ $selectedWorkCategory }}" selected>{{ \App\Models\WorkCategory::labelFor($selectedWorkCategory) }}</option>
                                                    @endif
                                                    @foreach ($workCategoryOptions as $option)
                                                        <option value="{{ $option->code }}" data-work-summary-min-words="{{ $option->workSummaryMinWords() }}" @selected($selectedWorkCategory === $option->code)>{{ $option->displayName() }}</option>
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
                                                        <input class="form-control" type="number" step="0.01" min="0" name="local_spend_estimate" value="{{ old('local_spend_estimate', data_get($budgetMeta, 'local_spend_estimate')) }}" required data-local-spend-estimate data-budget-breakdown-threshold="175000">
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="table-responsive">
                                                        <table class="table align-middle">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>#</th>
                                                                    <th>{{ __('app.applications.budget_item') }}</th>
                                                                    <th>{{ __('app.applications.units_count') }} <span class="text-danger d-none" data-budget-required-marker>*</span></th>
                                                                    <th>{{ __('app.applications.total_jod') }} <span class="text-danger d-none" data-budget-required-marker>*</span></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($budgetItemOptions as $item)
                                                                    <tr>
                                                                        <td>{{ $loop->iteration }}</td>
                                                                        <td class="fw-600">{{ $budgetItemLabels[$item] ?? __('app.applications.budget_items.'.$item) }}</td>
                                                                        <td><input type="number" min="0" class="form-control" name="budget_items[{{ $item }}][units]" value="{{ data_get($budgetItems, $item.'.units') }}" placeholder="{{ __('app.applications.enter_count') }}" data-budget-breakdown-field></td>
                                                                        <td><input type="number" step="0.01" min="0" class="form-control" name="budget_items[{{ $item }}][total]" value="{{ data_get($budgetItems, $item.'.total') }}" placeholder="{{ __('app.applications.enter_value') }}" data-budget-breakdown-field></td>
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
                                            ['target' => 'WorkContentSummary', 'label' => __('app.applications.annex_sections.work_content_summary'), 'filled' => filled(data_get($workContentSummary, 'synopsis')) && data_get($workContentSummary, 'confirmed'), 'required' => true],
                                            ['target' => 'CastCrewList', 'label' => __('app.applications.annex_sections.cast_crew'), 'filled' => collect($castCrewRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty(), 'required' => true],
                                            ['target' => 'LocationList', 'label' => __('app.applications.annex_sections.filming_locations'), 'filled' => collect($filmingLocationRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty(), 'required' => true],
                                            ['target' => 'RFCGuidelines', 'label' => __('app.applications.annex_sections.safety_guidelines'), 'filled' => data_get($safetyGuidelines, 'acknowledged') || filled(data_get($safetyGuidelines, 'notes')), 'required' => true],
                                            ['target' => 'ProductionTerms', 'label' => __('app.applications.annex_sections.production_terms'), 'filled' => (bool) data_get($productionTerms, 'accepted'), 'required' => true],
                                        ] as $formRow)
                                            <tr data-requirement-row data-requirement-target="{{ $formRow['target'] }}" data-requirement-required="1">
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
                                                    <div class="text-danger {{ $formRow['filled'] ? 'd-none' : '' }}" data-requirement-incomplete><i class="ph-fill ph-warning-circle fa-xl me-2 lh-lg"></i>{{ __('app.applications.form_incomplete_status') }}</div>
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
                                            ['target' => 'MinistryInteriorPersonalDetails', 'label' => __('app.applications.annex_sections.ministry_interior_personal_details'), 'filled' => \App\Support\MinistryInteriorPersonalDetails::hasAnyConfirmed($ministryInteriorPersonalDetails)],
                                            ['target' => 'EquipmentList', 'label' => __('app.applications.annex_sections.imported_equipment'), 'filled' => collect($importedEquipmentRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty() || collect(data_get($annex, 'equipment_travelers', []))->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty()],
                                            ['target' => 'FilmingAirports', 'label' => __('app.applications.annex_sections.airport_filming'), 'filled' => filled(data_get($airportFilming, 'airport_name')) || collect($airportPeopleRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty()],
                                            ['target' => 'FilmingGovernmental', 'label' => __('app.applications.annex_sections.governmental_scenes'), 'filled' => collect($governmentalSceneRows)->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())->isNotEmpty() || data_get($annex, 'governmental_scenes_confirmed')],
                                        ] as $formRow)
                                            <tr data-requirement-row data-requirement-target="{{ $formRow['target'] }}" data-requirement-optional="1">
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
                                                    <div class="text-danger d-none" data-requirement-incomplete><i class="ph-fill ph-warning-circle fa-xl me-2 lh-lg"></i>{{ __('app.applications.form_incomplete_status') }}</div>
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
                                        <div class="col-lg-12">
                                            <label class="form-label">{{ __('app.applications.annex_fields.synopsis') }}</label>
                                            <textarea class="form-control" name="work_content_summary_synopsis" rows="5" data-work-summary-input data-work-summary-min-words="{{ $selectedWorkSummaryMinWords }}" data-work-summary-counter="#work_content_summary_word_count_inline">{{ old('work_content_summary_synopsis', data_get($workContentSummary, 'synopsis')) }}</textarea>
                                            <div class="d-flex justify-content-between gap-3 flex-wrap small mt-2">
                                                <span class="text-muted">{{ __('app.applications.work_summary_arabic_only_hint') }}</span>
                                                <span id="work_content_summary_word_count_inline" class="text-muted" aria-live="polite"></span>
                                            </div>
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
	                                                            <th>{{ __('app.applications.annex_fields.nationality') }}</th>
	                                                            <th>{{ __('app.applications.annex_fields.person_name') }}</th>
	                                                            <th>{{ __('app.applications.annex_fields.role') }}</th>
	                                                            <th>{{ __('app.applications.annex_fields.gender') }}</th>
	                                                            <th>{{ __('app.applications.annex_fields.birth_date') }}</th>
	                                                            <th>{{ __('app.applications.annex_fields.identity_number') }}</th>
	                                                            <th class="d-none" data-cast-crew-passport-heading>{{ __('app.applications.annex_fields.passport_image') }}</th>
	                                                            <th>{{ __('app.applications.actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($castCrewRows as $index => $row)
                                                            @php
                                                                $castCrewNationalityText = trim((string) ($row['nationality'] ?? ''));
                                                                $legacyJordanianNationalities = ['jordanian', 'Jordanian', 'أردني', 'اردني'];
                                                                $castCrewNationalityValue = in_array($castCrewNationalityText, $legacyJordanianNationalities, true) ? 'jordanian' : $castCrewNationalityText;
                                                                $castCrewIsJordanian = $castCrewNationalityValue === 'jordanian';
                                                                $castCrewShowsPassportImage = filled($castCrewNationalityValue) && ! $castCrewIsJordanian;
                                                                $castCrewNameParts = [
                                                                    'first_name' => (string) ($row['first_name'] ?? ''),
                                                                    'second_name' => (string) ($row['second_name'] ?? ''),
                                                                    'third_name' => (string) ($row['third_name'] ?? ''),
                                                                    'family_name' => (string) ($row['family_name'] ?? ''),
                                                                ];

                                                                if ($castCrewIsJordanian && ! collect($castCrewNameParts)->filter(fn ($part) => filled($part))->isNotEmpty() && filled($row['name'] ?? null)) {
                                                                    $splitNameParts = preg_split('/\s+/', trim((string) $row['name'])) ?: [];
                                                                    $castCrewNameParts['first_name'] = $splitNameParts[0] ?? '';
                                                                    $castCrewNameParts['second_name'] = $splitNameParts[1] ?? '';
                                                                    $castCrewNameParts['third_name'] = $splitNameParts[2] ?? '';
                                                                    $castCrewNameParts['family_name'] = implode(' ', array_slice($splitNameParts, 3));
                                                                }
                                                            @endphp
                                                            <tr>
                                                                <td class="row-number">{{ $loop->iteration }}</td>
	                                                                <td>
	                                                                    <select class="form-select" name="cast_crew[{{ $index }}][nationality]" data-cast-crew-nationality required>
	                                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
	                                                                        @if (filled($castCrewNationalityValue) && ! $directorNationalityOptions->contains('code', $castCrewNationalityValue))
	                                                                            <option value="{{ $castCrewNationalityValue }}" selected>{{ \App\Models\Nationality::labelFor($castCrewNationalityValue) }}</option>
	                                                                        @endif
	                                                                        @foreach ($directorNationalityOptions as $nationality)
	                                                                            <option value="{{ $nationality->code }}" @selected($castCrewNationalityValue === $nationality->code)>{{ $nationality->displayName() }}</option>
	                                                                        @endforeach
	                                                                    </select>
	                                                                </td>
	                                                                <td class="cast-crew-name-cell">
	                                                                    <input type="hidden" name="cast_crew[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" data-cast-crew-name-output>
	                                                                    <div class="row g-2 cast-crew-jordanian-name {{ $castCrewIsJordanian ? '' : 'd-none' }}" data-cast-crew-jordanian-name>
	                                                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][first_name]" value="{{ $castCrewNameParts['first_name'] }}" placeholder="{{ __('app.applications.annex_fields.first_name') }}" data-cast-crew-name-part @required($castCrewIsJordanian) @disabled(! $castCrewIsJordanian)></div>
	                                                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][second_name]" value="{{ $castCrewNameParts['second_name'] }}" placeholder="{{ __('app.applications.annex_fields.second_name') }}" data-cast-crew-name-part @required($castCrewIsJordanian) @disabled(! $castCrewIsJordanian)></div>
	                                                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][third_name]" value="{{ $castCrewNameParts['third_name'] }}" placeholder="{{ __('app.applications.annex_fields.third_name') }}" data-cast-crew-name-part @required($castCrewIsJordanian) @disabled(! $castCrewIsJordanian)></div>
	                                                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][family_name]" value="{{ $castCrewNameParts['family_name'] }}" placeholder="{{ __('app.applications.annex_fields.family_name') }}" data-cast-crew-name-part @required($castCrewIsJordanian) @disabled(! $castCrewIsJordanian)></div>
	                                                                    </div>
	                                                                    <input type="text" class="form-control {{ $castCrewIsJordanian ? 'd-none' : '' }}" value="{{ $row['name'] ?? '' }}" data-cast-crew-full-name-input @required(! $castCrewIsJordanian)>
	                                                                </td>
	                                                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][role]" value="{{ $row['role'] ?? '' }}" required></td>
	                                                                <td>
	                                                                    <select class="form-select" name="cast_crew[{{ $index }}][gender]" required>
	                                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
	                                                                        @foreach (['male', 'female'] as $gender)
	                                                                            <option value="{{ $gender }}" @selected(($row['gender'] ?? '') === $gender)>{{ __('app.auth.gender_options.'.$gender) }}</option>
	                                                                        @endforeach
	                                                                    </select>
	                                                                </td>
	                                                                <td><input type="date" class="form-control" name="cast_crew[{{ $index }}][birth_date]" value="{{ $row['birth_date'] ?? '' }}" max="{{ $maxCrewBirthDate }}" required></td>
	                                                                <td>
	                                                                    <input type="text" class="form-control" name="cast_crew[{{ $index }}][identity_number]" value="{{ $row['identity_number'] ?? '' }}" placeholder="{{ $castCrewIsJordanian ? __('app.applications.annex_fields.national_id') : __('app.applications.annex_fields.passport_number') }}" inputmode="{{ $castCrewIsJordanian ? 'numeric' : 'text' }}" required @if ($castCrewIsJordanian) minlength="10" maxlength="10" pattern="\d{10}" @endif data-cast-crew-identity>
	                                                                    <div class="invalid-feedback" data-cast-crew-identity-feedback>{{ __('app.applications.cast_crew_national_id_digits') }}</div>
	                                                                </td>
	                                                                <td class="d-none" data-cast-crew-passport-cell>
	                                                                    <div class="{{ $castCrewShowsPassportImage ? '' : 'd-none' }}" data-cast-crew-passport-image>
	                                                                        <input type="file" class="form-control" name="cast_crew[{{ $index }}][passport_image]" accept="image/jpeg,image/png,.jpg,.jpeg,.png" @disabled(! $castCrewShowsPassportImage)>
	                                                                        <small class="form-text text-muted d-block mt-1">{{ __('app.applications.annex_fields.passport_image_note') }}</small>
	                                                                        @foreach (['path', 'name', 'mime_type', 'size', 'uploaded_at'] as $passportField)
	                                                                            <input type="hidden" name="cast_crew[{{ $index }}][passport_image_{{ $passportField }}]" value="{{ $row['passport_image_'.$passportField] ?? '' }}" @disabled(! $castCrewShowsPassportImage)>
	                                                                        @endforeach
	                                                                        @if (filled($row['passport_image_name'] ?? null))
	                                                                            <small class="text-muted d-block mt-1">{{ $row['passport_image_name'] }}</small>
	                                                                        @endif
	                                                                    </div>
	                                                                </td>
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
                                                    <i class="fa-solid fa-plus me-2"></i>{{ __('app.applications.add_filming_location') }}
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table application-location-card-table" id="filmingLocationsTable">
                                                    <tbody>
                                                        @foreach ($filmingLocationRows as $index => $row)
                                                            @include('applications.partials.filming-location-card', ['tableId' => 'filmingLocationsTable', 'index' => $index, 'row' => (array) $row, 'rowNumber' => $loop->iteration])
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
                                                            <th>{{ __('app.applications.annex_fields.shipping_company_name') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.invoice_number') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.bill_of_lading_number') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.arrival_date') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.departure_date') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.customs_center') }}</th>
                                                            <th>{{ __('app.applications.annex_fields.invoice_attachment') }}</th>
                                                            <th>{{ __('app.applications.actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($importedEquipmentRows as $index => $row)
                                                            <tr>
                                                                <td class="row-number">{{ $loop->iteration }}</td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][shipping_company_name]" value="{{ $row['shipping_company_name'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][invoice_number]" value="{{ $row['invoice_number'] ?? '' }}"></td>
                                                                <td><input type="text" class="form-control" name="imported_equipment[{{ $index }}][bill_of_lading_number]" value="{{ $row['bill_of_lading_number'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control" name="imported_equipment[{{ $index }}][arrival_date]" value="{{ $row['arrival_date'] ?? '' }}"></td>
                                                                <td><input type="date" class="form-control" name="imported_equipment[{{ $index }}][departure_date]" value="{{ $row['departure_date'] ?? '' }}"></td>
                                                                <td>
                                                                    @php
                                                                        $selectedCustomsCenter = (string) ($row['customs_center'] ?? ($row['entry_point'] ?? ''));
                                                                    @endphp
                                                                    <select class="form-select" name="imported_equipment[{{ $index }}][customs_center]">
                                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                                        @if (filled($selectedCustomsCenter) && ! $equipmentEntryPointOptions->contains('code', $selectedCustomsCenter))
                                                                            <option value="{{ $selectedCustomsCenter }}" selected>{{ $selectedCustomsCenter }}</option>
                                                                        @endif
                                                                        @foreach ($equipmentEntryPointOptions as $option)
                                                                            <option value="{{ $option->code }}" @selected($selectedCustomsCenter === $option->code)>{{ $option->displayName() }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <input type="file" class="form-control" name="imported_equipment[{{ $index }}][attachment]" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png">
                                                                    @foreach (['attachment_path', 'attachment_name', 'attachment_mime_type', 'attachment_size', 'attachment_uploaded_at'] as $attachmentField)
                                                                        <input type="hidden" name="imported_equipment[{{ $index }}][{{ $attachmentField }}]" value="{{ $row[$attachmentField] ?? '' }}">
                                                                    @endforeach
                                                                    @if (filled($row['attachment_name'] ?? null))
                                                                        <div class="small text-muted mt-1">{{ $row['attachment_name'] }}</div>
                                                                    @endif
                                                                </td>
                                                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#importedEquipmentTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
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
            .offcanvas.application-annex-offcanvas {
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

            .offcanvas.application-annex-offcanvas.show,
            .offcanvas.application-annex-offcanvas.showing {
                transform: none !important;
            }

            .cast-crew-name-cell,
            .airport-person-name-cell {
                min-width: 520px;
            }

            #filmingLocationsTable {
                min-width: 1680px;
            }

            .application-repeatable-footer {
                border-top: 1px solid var(--bs-border-color, #dee2e6);
                margin-top: .75rem;
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
        $applicationSpecialLocationRequirementOptions = collect($locationRequirementOptions)
            ->map(fn ($code): array => ['code' => $code, 'label' => $locationRequirementLabels[$code] ?? __('app.applications.special_location_requirements.'.$code)])
            ->values();
        $applicationSupportAuthorityOptions = collect($supportAuthorityOptions)
            ->map(fn ($label, $code): array => ['code' => $code, 'label' => $label])
            ->values();
    @endphp
    <script src="{{ asset('js/form-wizard.js') }}?v={{ filemtime(public_path('js/form-wizard.js')) }}"></script>
    <script>
        const applicationNationalityOptionsHtml = @js($applicationNationalityOptionsHtml);
        const applicationGovernorateOptionsHtml = @js($applicationGovernorateOptionsHtml);
        const applicationGenderOptionsHtml = @js($applicationGenderOptionsHtml);
        const applicationCrewBirthDateMax = @js($maxCrewBirthDate);
        const applicationFilmingLocationStartMin = @js($minimumFilmingLocationStartDate);
        const applicationLocationTypesByGovernorate = @json($locationTypesByGovernorate);
        const applicationLocationTypeLabels = @json($locationTypeLabels);
        const applicationLocationTypeApprovalDays = @json($locationTypeApprovalDays);
        const applicationWorkSummaryMinWordsByCategory = @json($workSummaryMinWordsByCategory);
        const applicationDefaultWorkSummaryMinWords = @json(\App\Models\WorkCategory::DEFAULT_WORK_SUMMARY_MIN_WORDS);
        const applicationLocationTypePlaceholder = @js(__('app.admin.select_placeholder'));
        const applicationSpecialLocationRequirementOptions = @json($applicationSpecialLocationRequirementOptions);
        const applicationSupportAuthorityOptions = @json($applicationSupportAuthorityOptions);
        const applicationLocationCardLabels = {
            locationNumber: @js(__('app.applications.location_number', ['number' => '__NUMBER__'])),
            governorate: @js(__('app.scouting.governorate')),
            locationType: @js(__('app.applications.annex_fields.location_type')),
            specialRequirement: @js(__('app.applications.special_requirement')),
            locationName: @js(__('app.applications.annex_fields.location_exact_name')),
            locationAddress: @js(__('app.applications.annex_fields.location_address')),
            locationNature: @js(__('app.applications.annex_fields.location_nature')),
            startDate: @js(__('app.scouting.start_date')),
            endDate: @js(__('app.scouting.end_date')),
            notes: @js(__('app.applications.annex_fields.notes')),
            supportTitle: @js(__('app.applications.location_support_requirements_title')),
            authority: @js(__('app.applications.annex_fields.authority_name')),
            requirement: @js(__('app.applications.annex_fields.requirement')),
            date: @js(__('app.applications.annex_fields.date')),
            timeFrom: @js(__('app.applications.annex_fields.time_from')),
            timeTo: @js(__('app.applications.annex_fields.time_to')),
            deleteLabel: @js(__('app.delete')),
            addLabel: @js(__('app.add')),
            addRequirementLabel: @js(__('app.applications.location_support_add_requirement')),
            supportDateRangeMessage: @js(__('app.applications.location_support_date_range')),
            supportNotesPrompt: @js(__('app.applications.location_support_notes_prompt')),
            approvalDaysNotice: @js(__('app.applications.location_type_approval_days_notice')),
        };
        const applicationEquipmentCategoryOptions = @json($equipmentClassificationOptions->map(fn ($option) => ['code' => $option->code, 'label' => $option->displayName()])->values());
        const applicationEquipmentEntryPointOptions = @json($equipmentEntryPointOptions->map(fn ($option) => ['code' => $option->code, 'label' => $option->displayName()])->values());
        const applicationWorkSummaryMessages = {
            counter: @js(__('app.applications.work_summary_word_counter')),
            instruction: @js(__('app.applications.work_summary_instruction')),
            minWords: @js(__('app.applications.work_summary_min_words_validation')),
            arabicOnly: @js(__('app.applications.work_summary_arabic_only_validation')),
        };
        const applicationRepeatableMessages = {
            add: @js(__('app.add')),
            rowCount: @js(__('app.applications.repeatable_row_count')),
            firstName: @js(__('app.applications.annex_fields.first_name')),
            secondName: @js(__('app.applications.annex_fields.second_name')),
            thirdName: @js(__('app.applications.annex_fields.third_name')),
            familyName: @js(__('app.applications.annex_fields.family_name')),
            nationalId: @js(__('app.applications.annex_fields.national_id')),
            passportNumber: @js(__('app.applications.annex_fields.passport_number')),
            passportImageNote: @js(__('app.applications.annex_fields.passport_image_note')),
            nationalIdDigits: @js(__('app.applications.cast_crew_national_id_digits')),
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

        function applicationSpecialLocationRequirementOptionsHtml(selectedValues) {
            const selected = new Set((selectedValues || []).map(function (value) {
                return String(value || '');
            }));

            return applicationSpecialLocationRequirementOptions.map(function (option) {
                const code = String(option.code || '');
                const selectedAttribute = selected.has(code) ? ' selected' : '';

                return '<option value="' + applicationEscapeHtml(code) + '"' + selectedAttribute + '>'
                    + applicationEscapeHtml(option.label || code)
                + '</option>';
            }).join('');
        }

        function applicationSupportAuthorityOptionsHtml(selectedValue) {
            const selected = String(selectedValue || '');

            return '<option value="">' + applicationLocationTypePlaceholder + '</option>'
                + applicationSupportAuthorityOptions.map(function (option) {
                    const code = String(option.code || '');
                    const selectedAttribute = selected === code ? ' selected' : '';

                    return '<option value="' + applicationEscapeHtml(code) + '"' + selectedAttribute + '>'
                        + applicationEscapeHtml(option.label || code)
                        + '</option>';
                }).join('');
        }

        function initializeApplicationEnhancedSelects(root) {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                return;
            }

            const $root = window.jQuery(root || document);

            $root.find('select.select2-basic-single, select.select2-basic-multiple')
                .add($root.filter('select.select2-basic-single, select.select2-basic-multiple'))
                .each(function () {
                    const $select = window.jQuery(this);
                    const $offcanvas = $select.closest('.offcanvas');
                    const options = { width: '100%' };

                    if ($select.data('select2')) {
                        $select.select2('destroy');
                    }

                    if ($offcanvas.length) {
                        options.dropdownParent = $offcanvas;
                    }

                    $select.select2(options);
                });
        }

        function filmingLocationSupportRequirementHtml(locationIndex, supportIndex) {
            return '<div class="application-location-support-row" data-location-support-requirement-row>'
                + '<div class="d-flex justify-content-between align-items-center gap-2 mb-3">'
                + '<span class="badge bg-light text-dark border">#' + (supportIndex + 1) + '</span>'
                + '<button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeFilmingLocationSupportRequirement(this)" aria-label="' + applicationEscapeHtml(applicationLocationCardLabels.deleteLabel) + '"><i class="ph-fill ph ph-trash-simple fs-6"></i></button>'
                + '</div>'
                + '<div class="row g-3">'
                + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.authority) + '</label><select class="form-select select2-basic-single" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][authority]">' + applicationSupportAuthorityOptionsHtml('') + '</select></div>'
                + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.requirement) + '</label><select class="form-select select2-basic-single" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][requirement]" data-location-support-requirement-select><option value="">' + applicationLocationTypePlaceholder + '</option>' + applicationSpecialLocationRequirementOptionsHtml([]) + '</select></div>'
                + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.date) + '</label><input type="date" class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][date]" data-location-support-date></div>'
                + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.timeFrom) + '</label><input type="time" class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][time_from]"></div>'
                + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.timeTo) + '</label><input type="time" class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][time_to]"></div>'
                + '<div class="col-md-6 col-xl-12"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.notes) + ' <span class="text-danger d-none" data-location-support-notes-required-marker>*</span></label><textarea class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][notes]" rows="2" data-location-support-notes></textarea><div class="form-text text-danger fw-semibold d-none" data-location-support-notes-help></div></div>'
                + '</div>'
                + '</div>';
        }

        function filmingLocationCardHtml(tableId, index) {
            const displayNumber = index + 1;
            const locationKey = 'location_' + Date.now() + '_' + index;

            return '<td>'
                + '<div class="application-location-card" data-filming-location-card>'
                + '<input type="hidden" name="filming_locations[' + index + '][location_key]" value="' + locationKey + '" data-location-key>'
                + '<div class="application-location-card__header">'
                + '<h5 class="mb-0">' + applicationEscapeHtml(applicationLocationCardLabels.locationNumber.replace('__NUMBER__', '')) + '<span class="row-number">' + displayNumber + '</span></h5>'
                + "<button type=\"button\" class=\"btn btn-sm btn-icon btn-danger-subtle rounded\" onclick=\"removeApplicationAnnexRow(this, '#" + tableId + "')\" aria-label=\"" + applicationEscapeHtml(applicationLocationCardLabels.deleteLabel) + "\"><i class=\"ph-fill ph ph-trash-simple fs-6\"></i></button>"
                + '</div>'
                + '<div class="application-location-card__section"><div class="row g-3">'
                + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.governorate) + ' <span class="text-danger">*</span></label><select class="form-select" name="filming_locations[' + index + '][governorate]" data-location-governorate required>' + applicationGovernorateOptionsHtml + '</select></div>'
                + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationType) + ' <span class="text-danger">*</span></label><select class="form-select" name="filming_locations[' + index + '][location_type]" data-location-type-select required>' + applicationLocationTypeOptionsHtml('', '') + '</select><div class="form-text text-warning fw-semibold d-none" data-location-type-approval-note></div></div>'
                + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationName) + ' <span class="text-danger">*</span></label><input type="text" class="form-control" name="filming_locations[' + index + '][location_name]" data-location-name required></div>'
                + '<div class="col-md-6"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationAddress) + ' <span class="text-danger">*</span></label><input type="text" class="form-control" name="filming_locations[' + index + '][address]" required></div>'
                + '<div class="col-md-6"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationNature) + ' <span class="text-danger">*</span></label><textarea class="form-control" name="filming_locations[' + index + '][nature]" rows="2" required></textarea></div>'
                + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.startDate) + ' <span class="text-danger">*</span></label><input type="date" class="form-control" name="filming_locations[' + index + '][start_date]" min="' + applicationEscapeHtml(applicationFilmingLocationStartMin) + '" data-location-start-date required></div>'
                + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.endDate) + ' <span class="text-danger">*</span></label><input type="date" class="form-control" name="filming_locations[' + index + '][end_date]" data-location-end-date required></div>'
                + '</div></div>'
                + '</div>'
                + '</td>';
        }

        function filmingLocationIndexForCard(card) {
            const field = card ? card.querySelector('[name^="filming_locations["]') : null;
            const match = field && field.name ? field.name.match(/^filming_locations\[([^\]]+)\]/) : null;

            return match ? match[1] : '0';
        }

        function renumberFilmingLocationSupportRequirements(card) {
            const locationIndex = filmingLocationIndexForCard(card);

            card.querySelectorAll('[data-location-support-requirement-row]').forEach(function (row, supportIndex) {
                const badge = row.querySelector('.badge');

                if (badge) {
                    badge.textContent = '#' + (supportIndex + 1);
                }

                row.querySelectorAll('[name]').forEach(function (field) {
                    field.name = field.name.replace(
                        /^filming_locations\[[^\]]+\]\[support_requirements\]\[[^\]]+\]/,
                        'filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + ']'
                    );
                });
            });
        }

        function updateFilmingLocationDateConstraints(card) {
            if (!card) {
                return;
            }

            const startDate = card.querySelector('[data-location-start-date]');
            const endDate = card.querySelector('[data-location-end-date]');
            const startValue = startDate ? String(startDate.value || '') : '';
            const endValue = endDate ? String(endDate.value || '') : '';

            if (startDate) {
                startDate.min = applicationLocationStartMinimumForCard(card);
            }

            if (endDate) {
                if (startValue) {
                    endDate.min = startValue;
                } else {
                    endDate.removeAttribute('min');
                }
            }

            card.querySelectorAll('[data-location-support-date]').forEach(function (supportDate) {
                supportDate.setCustomValidity('');

                if (startValue) {
                    supportDate.min = startValue;
                } else {
                    supportDate.removeAttribute('min');
                }

                if (endValue) {
                    supportDate.max = endValue;
                } else {
                    supportDate.removeAttribute('max');
                }

                if (supportDate.value && startValue && supportDate.value < startValue) {
                    supportDate.setCustomValidity(applicationLocationCardLabels.supportDateRangeMessage);
                }

                if (supportDate.value && endValue && supportDate.value > endValue) {
                    supportDate.setCustomValidity(applicationLocationCardLabels.supportDateRangeMessage);
                }
            });
        }

        function refreshFilmingLocationDateConstraints(root) {
            (root || document).querySelectorAll('.application-location-card').forEach(updateFilmingLocationDateConstraints);
        }

        function addFilmingLocationSupportRequirement(button) {
            const card = button.closest('.application-location-card');
            const container = card ? card.querySelector('[data-location-support-requirements]') : null;

            if (!card || !container) {
                return;
            }

            const locationIndex = filmingLocationIndexForCard(card);
            const supportIndex = container.querySelectorAll('[data-location-support-requirement-row]').length;

            container.insertAdjacentHTML('beforeend', filmingLocationSupportRequirementHtml(locationIndex, supportIndex));
            renumberFilmingLocationSupportRequirements(card);
            initializeApplicationEnhancedSelects(container.lastElementChild);
            updateFilmingLocationDateConstraints(card);
            refreshFilmingLocationSupportRequirementNotes(container.lastElementChild);
        }

        function removeFilmingLocationSupportRequirement(button) {
            const card = button.closest('.application-location-card');
            const container = card ? card.querySelector('[data-location-support-requirements]') : null;
            const row = button.closest('[data-location-support-requirement-row]');

            if (!card || !container || !row) {
                return;
            }

            row.remove();

            if (!container.querySelector('[data-location-support-requirement-row]')) {
                container.insertAdjacentHTML('beforeend', filmingLocationSupportRequirementHtml(filmingLocationIndexForCard(card), 0));
            }

            renumberFilmingLocationSupportRequirements(card);
            updateFilmingLocationDateConstraints(card);
            refreshFilmingLocationSupportRequirementNotes(card);
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
            refreshApplicationLocationApprovalNote(row);
        }

        function applicationParseYmd(value) {
            const parts = String(value || '').split('-').map(function (part) {
                return parseInt(part, 10);
            });

            if (parts.length !== 3 || parts.some(Number.isNaN)) {
                return null;
            }

            return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
        }

        function applicationFormatYmd(date) {
            const year = date.getUTCFullYear();
            const month = String(date.getUTCMonth() + 1).padStart(2, '0');
            const day = String(date.getUTCDate()).padStart(2, '0');

            return year + '-' + month + '-' + day;
        }

        function applicationAddJordanBusinessDays(startDate, days) {
            const date = applicationParseYmd(startDate);
            let remaining = Math.max(0, parseInt(days || '0', 10));

            if (!date) {
                return startDate;
            }

            while (remaining > 0) {
                date.setUTCDate(date.getUTCDate() + 1);

                if (date.getUTCDay() !== 5 && date.getUTCDay() !== 6) {
                    remaining -= 1;
                }
            }

            return applicationFormatYmd(date);
        }

        function applicationMaxYmd(firstDate, secondDate) {
            if (!firstDate) {
                return secondDate || '';
            }

            if (!secondDate) {
                return firstDate;
            }

            return secondDate > firstDate ? secondDate : firstDate;
        }

        function applicationLocationStartMinimumForType(locationType) {
            const days = parseInt(applicationLocationTypeApprovalDays[locationType] || '0', 10);

            if (days <= 0) {
                return applicationFilmingLocationStartMin;
            }

            return applicationMaxYmd(
                applicationFilmingLocationStartMin,
                applicationAddJordanBusinessDays(applicationFilmingLocationStartMin, days)
            );
        }

        function applicationLocationStartMinimumForCard(card) {
            const locationType = card ? card.querySelector('[data-location-type-select]') : null;

            return applicationLocationStartMinimumForType(locationType ? locationType.value : '');
        }

        function refreshApplicationLocationApprovalNote(row) {
            if (!row) {
                return;
            }

            const locationType = row.querySelector('[data-location-type-select]');
            const note = row.querySelector('[data-location-type-approval-note]');
            const startDate = row.querySelector('[data-location-start-date]');

            if (!locationType || !note) {
                return;
            }

            const days = parseInt(applicationLocationTypeApprovalDays[locationType.value] || '0', 10);
            const minimumStartDate = applicationLocationStartMinimumForType(locationType.value);

            if (startDate) {
                startDate.min = minimumStartDate;
            }

            if (days > 0) {
                note.textContent = applicationLocationCardLabels.approvalDaysNotice
                    .replace(':days', String(days))
                    .replace(':date', minimumStartDate);
                note.classList.remove('d-none');

                return;
            }

            note.textContent = '';
            note.classList.add('d-none');
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

        function applicationSupportRequirementLabel(row) {
            const requirement = row ? row.querySelector('[data-location-support-requirement-select]') : null;

            if (!requirement || !requirement.value) {
                return '';
            }

            const option = requirement.options[requirement.selectedIndex];

            return option ? String(option.text || '').trim() : String(requirement.value || '').trim();
        }

        function updateFilmingLocationSupportRequirementNotes(row) {
            const notes = row ? row.querySelector('[data-location-support-notes]') : null;
            const marker = row ? row.querySelector('[data-location-support-notes-required-marker]') : null;
            const help = row ? row.querySelector('[data-location-support-notes-help]') : null;
            const requirementLabel = applicationSupportRequirementLabel(row);

            if (!notes) {
                return;
            }

            if (!requirementLabel) {
                notes.required = false;
                notes.placeholder = '';
                notes.setCustomValidity('');

                if (marker) {
                    marker.classList.add('d-none');
                }

                if (help) {
                    help.textContent = '';
                    help.classList.add('d-none');
                }

                return;
            }

            const message = applicationLocationCardLabels.supportNotesPrompt.replace(':requirement', requirementLabel);

            notes.required = true;
            notes.placeholder = message;
            notes.setCustomValidity(String(notes.value || '').trim() ? '' : message);

            if (marker) {
                marker.classList.remove('d-none');
            }

            if (help) {
                help.textContent = message;
                help.classList.remove('d-none');
            }
        }

        function refreshFilmingLocationSupportRequirementNotes(root) {
            (root || document).querySelectorAll('[data-location-support-requirement-row]').forEach(updateFilmingLocationSupportRequirementNotes);
        }

        function refreshTravelerCustomsProjectName() {
            const projectName = document.querySelector('input[name="project_name"]');

            if (!projectName) {
                return;
            }

            document.querySelectorAll('[data-traveler-customs-project-name], [data-shipping-customs-project-name]').forEach(function (target) {
                target.textContent = String(projectName.value || '').trim();
            });
        }

        function castCrewJordanianNameInputsHtml(index) {
            const firstName = applicationEscapeHtml(applicationRepeatableMessages.firstName);
            const secondName = applicationEscapeHtml(applicationRepeatableMessages.secondName);
            const thirdName = applicationEscapeHtml(applicationRepeatableMessages.thirdName);
            const familyName = applicationEscapeHtml(applicationRepeatableMessages.familyName);

            return '<input type="hidden" name="cast_crew[' + index + '][name]" data-cast-crew-name-output>'
                + '<div class="row g-2 cast-crew-jordanian-name d-none" data-cast-crew-jordanian-name>'
                + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[' + index + '][first_name]" placeholder="' + firstName + '" data-cast-crew-name-part></div>'
                + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[' + index + '][second_name]" placeholder="' + secondName + '" data-cast-crew-name-part></div>'
                + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[' + index + '][third_name]" placeholder="' + thirdName + '" data-cast-crew-name-part></div>'
                + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[' + index + '][family_name]" placeholder="' + familyName + '" data-cast-crew-name-part></div>'
                + '</div>'
                + '<input type="text" class="form-control" data-cast-crew-full-name-input required>';
        }

        function isCastCrewJordanian(row) {
            const nationality = row?.querySelector('[data-cast-crew-nationality]');

            return String(nationality?.value || '').toLowerCase() === 'jordanian';
        }

        function castCrewIdentityInputHtml(index) {
            const passportNumber = applicationEscapeHtml(applicationRepeatableMessages.passportNumber);

            return '<input type="text" class="form-control" name="cast_crew[' + index + '][identity_number]" placeholder="' + passportNumber + '" data-cast-crew-identity>'
                + '<div class="invalid-feedback" data-cast-crew-identity-feedback>' + applicationEscapeHtml(applicationRepeatableMessages.nationalIdDigits) + '</div>';
        }

        function castCrewPassportImageInputHtml(index) {
            return '<div class="d-none" data-cast-crew-passport-image>'
                + '<input type="file" class="form-control" name="cast_crew[' + index + '][passport_image]" accept="image/jpeg,image/png,.jpg,.jpeg,.png" disabled>'
                + '<small class="form-text text-muted d-block mt-1">' + applicationEscapeHtml(applicationRepeatableMessages.passportImageNote) + '</small>'
                + '</div>';
        }

        function refreshCastCrewPassportColumn(table) {
            if (!table) {
                return;
            }

            const hasForeignMember = Array.from(table.querySelectorAll('tbody tr')).some(function (row) {
                const nationality = row.querySelector('[data-cast-crew-nationality]');
                const value = String(nationality?.value || '').trim();

                return value !== '' && !isCastCrewJordanian(row);
            });

            table.querySelector('[data-cast-crew-passport-heading]')?.classList.toggle('d-none', !hasForeignMember);
            table.querySelectorAll('[data-cast-crew-passport-cell]').forEach(function (cell) {
                cell.classList.toggle('d-none', !hasForeignMember);
            });
        }

        function updateCastCrewPassportImageMode(row) {
            if (!row) {
                return;
            }

            const nationality = row.querySelector('[data-cast-crew-nationality]');
            const container = row.querySelector('[data-cast-crew-passport-image]');
            const shouldShow = String(nationality?.value || '').trim() !== '' && !isCastCrewJordanian(row);

            if (!container) {
                return;
            }

            container.classList.toggle('d-none', !shouldShow);
            container.querySelectorAll('input').forEach(function (input) {
                input.disabled = !shouldShow;
            });

            refreshCastCrewPassportColumn(row.closest('table'));
        }

        function updateCastCrewIdentityMode(row) {
            if (!row) {
                return;
            }

            const identity = row.querySelector('[data-cast-crew-identity]');
            const feedback = row.querySelector('[data-cast-crew-identity-feedback]');
            const isJordanian = isCastCrewJordanian(row);
            const nationality = row.querySelector('[data-cast-crew-nationality]');
            const hasNationality = String(nationality?.value || '').trim() !== '';

            if (!identity) {
                return;
            }

            identity.placeholder = isJordanian
                ? applicationRepeatableMessages.nationalId
                : applicationRepeatableMessages.passportNumber;
            identity.title = identity.placeholder;
            identity.inputMode = isJordanian ? 'numeric' : 'text';
            identity.setCustomValidity('');
            identity.classList.remove('is-invalid');

            if (isJordanian) {
                identity.value = String(identity.value || '').replace(/\D/g, '').slice(0, 10);
                identity.required = true;
                identity.setAttribute('minlength', '10');
                identity.setAttribute('maxlength', '10');
                identity.setAttribute('pattern', '\\d{10}');

                const shouldShowFeedback = identity.dataset.validationTouched === 'true' || identity.value !== '';
                const isValid = /^\d{10}$/.test(identity.value);

                if (!isValid) {
                    identity.setCustomValidity(applicationRepeatableMessages.nationalIdDigits);
                    identity.classList.toggle('is-invalid', shouldShowFeedback);
                }
            } else {
                identity.required = hasNationality;
                identity.removeAttribute('minlength');
                identity.removeAttribute('maxlength');
                identity.removeAttribute('pattern');
            }

            if (feedback) {
                feedback.textContent = applicationRepeatableMessages.nationalIdDigits;
            }
        }

        function updateCastCrewNameMode(row) {
            if (!row) {
                return;
            }

            const jordanianName = row.querySelector('[data-cast-crew-jordanian-name]');
            const fullName = row.querySelector('[data-cast-crew-full-name-input]');
            const nameOutput = row.querySelector('[data-cast-crew-name-output]');
            const isJordanian = isCastCrewJordanian(row);
            const nationality = row.querySelector('[data-cast-crew-nationality]');
            const hasNationality = String(nationality?.value || '').trim() !== '';

            if (jordanianName) {
                jordanianName.classList.toggle('d-none', !isJordanian);
                jordanianName.querySelectorAll('[data-cast-crew-name-part]').forEach(function (input) {
                    input.disabled = !isJordanian;
                    input.required = isJordanian;
                });
            }

            if (fullName) {
                fullName.classList.toggle('d-none', isJordanian);
                fullName.required = hasNationality && !isJordanian;
            }

            if (nameOutput) {
                const name = isJordanian
                    ? Array.from(row.querySelectorAll('[data-cast-crew-name-part]')).map(function (input) {
                        return String(input.value || '').trim();
                    }).filter(Boolean).join(' ')
                    : String(fullName?.value || '').trim();

                nameOutput.value = name;
            }

            updateCastCrewIdentityMode(row);
            updateCastCrewPassportImageMode(row);
        }

        function refreshCastCrewNameModes(root) {
            (root || document).querySelectorAll('[data-cast-crew-nationality]').forEach(function (field) {
                updateCastCrewNameMode(field.closest('tr'));
            });
        }

        function airportPersonJordanianNameInputsHtml(index) {
            const firstName = applicationEscapeHtml(applicationRepeatableMessages.firstName);
            const secondName = applicationEscapeHtml(applicationRepeatableMessages.secondName);
            const thirdName = applicationEscapeHtml(applicationRepeatableMessages.thirdName);
            const familyName = applicationEscapeHtml(applicationRepeatableMessages.familyName);

            return '<input type="hidden" name="airport_people[' + index + '][full_name]" data-airport-person-name-output>'
                + '<div class="row g-2 airport-person-jordanian-name d-none" data-airport-person-jordanian-name>'
                + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="airport_people[' + index + '][first_name]" placeholder="' + firstName + '" data-airport-person-name-part></div>'
                + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="airport_people[' + index + '][second_name]" placeholder="' + secondName + '" data-airport-person-name-part></div>'
                + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="airport_people[' + index + '][third_name]" placeholder="' + thirdName + '" data-airport-person-name-part></div>'
                + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="airport_people[' + index + '][family_name]" placeholder="' + familyName + '" data-airport-person-name-part></div>'
                + '</div>'
                + '<input type="text" class="form-control" data-airport-person-full-name-input>';
        }

        function isAirportPersonJordanian(row) {
            const nationality = row?.querySelector('[data-airport-person-nationality]');

            return String(nationality?.value || '').toLowerCase() === 'jordanian';
        }

        function airportPersonIdentityInputHtml(index) {
            const passportNumber = applicationEscapeHtml(applicationRepeatableMessages.passportNumber);

            return '<input type="text" class="form-control" name="airport_people[' + index + '][identity_number]" placeholder="' + passportNumber + '" data-airport-person-identity>';
        }

        function updateAirportPersonIdentityMode(row) {
            if (!row) {
                return;
            }

            const identity = row.querySelector('[data-airport-person-identity]');
            const isJordanian = isAirportPersonJordanian(row);

            if (!identity) {
                return;
            }

            identity.placeholder = isJordanian
                ? applicationRepeatableMessages.nationalId
                : applicationRepeatableMessages.passportNumber;
            identity.title = identity.placeholder;
            identity.inputMode = isJordanian ? 'numeric' : 'text';
            identity.setCustomValidity('');

            if (isJordanian) {
                identity.value = String(identity.value || '').replace(/\D/g, '').slice(0, 10);
                identity.setAttribute('maxlength', '10');
                identity.setAttribute('pattern', '\\d{10}');

                if (identity.value && !/^\d{10}$/.test(identity.value)) {
                    identity.setCustomValidity(applicationRepeatableMessages.nationalIdDigits);
                }
            } else {
                identity.removeAttribute('maxlength');
                identity.removeAttribute('pattern');
            }
        }

        function updateAirportPersonNameMode(row) {
            if (!row) {
                return;
            }

            const jordanianName = row.querySelector('[data-airport-person-jordanian-name]');
            const fullName = row.querySelector('[data-airport-person-full-name-input]');
            const nameOutput = row.querySelector('[data-airport-person-name-output]');
            const isJordanian = isAirportPersonJordanian(row);

            if (jordanianName) {
                jordanianName.classList.toggle('d-none', !isJordanian);
                jordanianName.querySelectorAll('[data-airport-person-name-part]').forEach(function (input) {
                    input.disabled = !isJordanian;
                });
            }

            if (fullName) {
                fullName.classList.toggle('d-none', isJordanian);
            }

            if (nameOutput) {
                const name = isJordanian
                    ? Array.from(row.querySelectorAll('[data-airport-person-name-part]')).map(function (input) {
                        return String(input.value || '').trim();
                    }).filter(Boolean).join(' ')
                    : String(fullName?.value || '').trim();

                nameOutput.value = name;
            }

            updateAirportPersonIdentityMode(row);
        }

        function refreshAirportPersonNameModes(root) {
            (root || document).querySelectorAll('[data-airport-person-nationality]').forEach(function (field) {
                updateAirportPersonNameMode(field.closest('tr'));
            });
        }

        function updateApplicationAnnexRowCounters() {
            document.querySelectorAll('[data-row-count-for]').forEach(function (counter) {
                const selector = counter.dataset.rowCountFor;
                const tableBody = selector ? document.querySelector(selector + ' tbody') : null;
                const count = tableBody ? tableBody.querySelectorAll('tr').length : 0;

                counter.textContent = applicationRepeatableMessages.rowCount.replace(':count', String(count));
            });
        }

        function setupApplicationRepeatableTableFooters() {
            document.querySelectorAll('button[onclick^="addApplicationAnnexRow"]').forEach(function (button) {
                const match = button.getAttribute('onclick')?.match(/addApplicationAnnexRow\('([^']+)',\s*'([^']+)'\)/);

                if (!match) {
                    return;
                }

                const tableId = match[1];
                const fieldName = match[2];
                const table = document.getElementById(tableId);

                if (!table || table.dataset.repeatableFooterReady === '1') {
                    return;
                }

                table.dataset.repeatableFooterReady = '1';

                const footer = document.createElement('div');
                footer.className = 'application-repeatable-footer d-flex justify-content-between align-items-center gap-3 flex-wrap py-3';
                footer.innerHTML = '<span class="fw-600 text-muted" data-row-count-for="#' + applicationEscapeHtml(tableId) + '"></span>'
                    + '<button type="button" class="btn btn-success" onclick="addApplicationAnnexRow(\'' + applicationEscapeHtml(tableId) + '\', \'' + applicationEscapeHtml(fieldName) + '\')"><i class="fa-solid fa-plus me-2"></i>' + applicationEscapeHtml(applicationRepeatableMessages.add) + '</button>';

                table.closest('.table-responsive')?.insertAdjacentElement('afterend', footer);
            });
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
            refreshCastCrewPassportColumn(document.querySelector(selector));
            refreshSpecialLocationRequirementSelects();
            refreshEquipmentTravelerSelects();
            refreshFilmingLocationDateConstraints(document);
            updateApplicationAnnexRowCounters();
            updateRequirementStatuses();
            updateEquipmentTotals();
        }

        function supportScheduleRowHtml(fieldName, index, deleteCell) {
            return '<td class="row-number"></td>'
                + '<td><input type="text" class="form-control" name="' + fieldName + '[' + index + '][day]"></td>'
                + '<td><input type="date" class="form-control" name="' + fieldName + '[' + index + '][date]"></td>'
                + '<td><input type="time" class="form-control" name="' + fieldName + '[' + index + '][time_from]"></td>'
                + '<td><input type="time" class="form-control" name="' + fieldName + '[' + index + '][time_to]"></td>'
                + '<td><input type="text" class="form-control" name="' + fieldName + '[' + index + '][location]"></td>'
                + '<td><input type="text" class="form-control" name="' + fieldName + '[' + index + '][requirement]"></td>'
                + '<td><textarea class="form-control" name="' + fieldName + '[' + index + '][notes]" rows="2"></textarea></td>'
                + deleteCell;
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
	                    + '<td><select class="form-select" name="cast_crew[' + index + '][nationality]" data-cast-crew-nationality required>' + applicationNationalityOptionsHtml + '</select></td>'
	                    + '<td class="cast-crew-name-cell">' + castCrewJordanianNameInputsHtml(index) + '</td>'
	                    + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][role]" required></td>'
	                    + '<td><select class="form-select" name="cast_crew[' + index + '][gender]" required>' + applicationGenderOptionsHtml + '</select></td>'
	                    + '<td><input type="date" class="form-control" name="cast_crew[' + index + '][birth_date]" max="' + applicationCrewBirthDateMax + '" required></td>'
	                    + '<td>' + castCrewIdentityInputHtml(index) + '</td>'
	                    + '<td class="d-none" data-cast-crew-passport-cell>' + castCrewPassportImageInputHtml(index) + '</td>'
                    + deleteCell;
	            } else if (fieldName === 'filming_locations') {
	                row.innerHTML = filmingLocationCardHtml(tableId, index);
            } else if (fieldName === 'public_security_support' || fieldName === 'military_support') {
                row.innerHTML = supportScheduleRowHtml(fieldName, index, deleteCell);
            } else if (fieldName === 'imported_equipment') {
                const rowKey = 'new_' + Date.now() + '_' + index;
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><input type="hidden" name="imported_equipment[' + rowKey + '][transport_group]" value="shipping"><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][shipping_company_name]"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][invoice_number]"></td>'
                    + '<td><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][bill_of_lading_number]"></td>'
                    + '<td><input type="date" class="form-control" name="imported_equipment[' + rowKey + '][arrival_date]"></td>'
                    + '<td><input type="date" class="form-control" name="imported_equipment[' + rowKey + '][departure_date]"></td>'
                    + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][customs_center]">' + applicationLookupOptionsHtml(applicationEquipmentEntryPointOptions, '') + '</select></td>'
                    + '<td><input type="file" class="form-control" name="imported_equipment[' + rowKey + '][attachment]" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png"></td>'
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
                    + '<td><input type="file" class="form-control" name="equipment_travelers[' + index + '][passport_image]" accept="image/jpeg,image/png,.jpg,.jpeg,.png"><small class="form-text text-muted d-block mt-1">' + applicationEscapeHtml(applicationRepeatableMessages.passportImageNote) + '</small></td>'
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
                    + '<td><input type="number" min="0" class="form-control" name="imported_equipment[' + rowKey + '][quantity]" data-equipment-quantity></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[' + rowKey + '][unit_value]" data-equipment-unit-value></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[' + rowKey + '][total_value]" data-equipment-row-total readonly></td>'
                    + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][classification]">' + applicationLookupOptionsHtml(applicationEquipmentCategoryOptions, '') + '</select></td>'
                    + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][entry_point]">' + applicationLookupOptionsHtml(applicationEquipmentEntryPointOptions, '') + '</select></td>'
                    + deleteCell;
            } else if (fieldName === 'airport_people') {
                row.innerHTML = '<td class="row-number"></td>'
                    + '<td><select class="form-select" name="airport_people[' + index + '][nationality]" data-airport-person-nationality>' + applicationNationalityOptionsHtml + '</select></td>'
                    + '<td class="airport-person-name-cell">' + airportPersonJordanianNameInputsHtml(index) + '</td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][mother_name]"></td>'
                    + '<td>' + airportPersonIdentityInputHtml(index) + '</td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][profession]"></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][address_phone]"></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][entry_reason]"></td>'
                    + '<td><input type="text" class="form-control" name="airport_people[' + index + '][target_area]"></td>'
                    + deleteCell;
            }

            table.appendChild(row);
            renumberApplicationAnnexRows('#' + tableId);
            refreshCastCrewNameModes(row);
            refreshAirportPersonNameModes(row);
            row.querySelectorAll('.select2-basic-multiple, .select2-basic-single').forEach(refreshSelect2Control);
            refreshApplicationLocationTypeSelect(row);
            refreshSpecialLocationRequirementSelects();
            refreshEquipmentTravelerSelects();
            refreshFilmingLocationDateConstraints(row);
            updateApplicationAnnexRowCounters();
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

            const controls = Array.from(drawer.querySelectorAll('input, select, textarea'))
                .filter(function (control) {
                    return !control.disabled && control.type !== 'hidden' && !control.closest('.legacy-annex-inline');
                });
            const hasData = controls.some(controlHasRequirementValue);
            const isValid = controls.every(function (control) {
                return !control.willValidate || control.validity.valid;
            });

            return hasData && isValid;
        }

        function requirementFormHasData(targetId) {
            const drawer = document.getElementById(targetId);

            if (!drawer) {
                return false;
            }

            return Array.from(drawer.querySelectorAll('input, select, textarea'))
                .filter(function (control) {
                    return !control.disabled && control.type !== 'hidden' && !control.closest('.legacy-annex-inline');
                })
                .some(controlHasRequirementValue);
        }

        function updateRequirementStatuses() {
            document.querySelectorAll('[data-requirement-row]').forEach(function (row) {
                const status = row.querySelector('[data-requirement-filled]');
                const incompleteStatus = row.querySelector('[data-requirement-incomplete]');

                if (!status) {
                    return;
                }

                const isFilled = requirementFormIsFilled(row.dataset.requirementTarget);
                const hasData = requirementFormHasData(row.dataset.requirementTarget);
                const isOptional = row.dataset.requirementOptional === '1';

                status.classList.toggle('d-none', !isFilled);

                if (incompleteStatus) {
                    incompleteStatus.classList.toggle('d-none', isFilled || (isOptional && !hasData));
                }
            });
        }

        window.updateApplicationRequirementStatuses = updateRequirementStatuses;

        function updateEquipmentTotals() {
            document.querySelectorAll('[data-equipment-total]').forEach(function (target) {
                const table = document.querySelector(target.dataset.equipmentTotal);

                if (!table) {
                    target.textContent = '0';
                    return;
                }

                table.querySelectorAll('tbody tr').forEach(function (row) {
                    const quantityField = row.querySelector('[data-equipment-quantity]');
                    const unitValueField = row.querySelector('[data-equipment-unit-value]');
                    const totalField = row.querySelector('[data-equipment-row-total]');

                    if (!quantityField || !unitValueField || !totalField) {
                        return;
                    }

                    const hasQuantity = quantityField.value.trim() !== '';
                    const hasUnitValue = unitValueField.value.trim() !== '';
                    const quantity = Number.parseFloat(quantityField.value);
                    const unitValue = Number.parseFloat(unitValueField.value);

                    totalField.value = hasQuantity && hasUnitValue && Number.isFinite(quantity) && Number.isFinite(unitValue)
                        ? String(Math.round((quantity * unitValue + Number.EPSILON) * 100) / 100)
                        : '';
                });

                const total = Array.from(table.querySelectorAll('[data-equipment-row-total]'))
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
                    message: @json(__('app.applications.schedule_validation.shooting_start_after_preparation')),
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
                    min: 'shooting_start',
                    message: @json(__('app.applications.schedule_validation.post_production_start_after_shooting_start')),
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

                    const minimumValue = minField.value || '';

                    if (minimumValue) {
                        field.min = minimumValue;
                    } else {
                        field.removeAttribute('min');
                    }

                    if (field.value && minimumValue && field.value < minimumValue) {
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

        function selectedProjectNationalityCodes(form) {
            const select = form.querySelector('select[name="project_nationalities[]"]');

            if (!select) {
                return [];
            }

            return Array.from(select.selectedOptions || [])
                .map(function (option) {
                    return String(option.value || '').trim();
                })
                .filter(Boolean);
        }

        function projectRequiresInternationalSection(form) {
            const canUseInternationalProjectSection = @json($canUseInternationalProjectSection);

            if (!canUseInternationalProjectSection) {
                return false;
            }

            return selectedProjectNationalityCodes(form).some(function (code) {
                return code !== 'jordanian';
            });
        }

        function updateInternationalProjectSection(form) {
            const requiresInternational = projectRequiresInternationalSection(form);
            const accountExists = @json($internationalAccountExists);
            const tab = form.querySelector('[data-international-project-tab]');
            const section = form.querySelector('[data-international-project-section]');

            tab?.classList.toggle('d-none', !requiresInternational);
            section?.classList.toggle('d-none', !requiresInternational);

            form.querySelectorAll('[data-international-required-marker]').forEach(function (marker) {
                marker.classList.toggle('d-none', !requiresInternational);
            });

            form.querySelectorAll('[data-international-account-required-marker]').forEach(function (marker) {
                marker.classList.toggle('d-none', !requiresInternational || accountExists);
            });

            form.querySelectorAll('[data-international-project-field]').forEach(function (field) {
                field.disabled = !requiresInternational;
                field.required = requiresInternational && (!field.matches('[data-international-account-field]') || !accountExists);

                if (!field.required) {
                    field.setCustomValidity('');
                }

                if (field.tagName === 'SELECT' && window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    window.jQuery(field).prop('disabled', field.disabled).trigger('change.select2');
                }
            });

            if (!requiresInternational && tab?.classList.contains('active')) {
                const fallbackTab = form.querySelector('.streamit-tabs .nav-link:not(.d-none)');
                fallbackTab?.click();
            }
        }

        function scheduleInternationalProjectSectionUpdate(form) {
            window.setTimeout(function () {
                updateInternationalProjectSection(form);
            }, 0);
        }

        function updateBudgetBreakdownRequirements(form) {
            const spendField = form.querySelector('[data-local-spend-estimate]');

            if (!spendField) {
                return;
            }

            const threshold = Number.parseFloat(spendField.dataset.budgetBreakdownThreshold || '175000');
            const spendValue = Number.parseFloat(spendField.value || '0');
            const requiresBreakdown = Number.isFinite(spendValue) && Number.isFinite(threshold) && spendValue >= threshold;

            form.querySelectorAll('[data-budget-breakdown-field]').forEach(function (field) {
                field.required = requiresBreakdown;

                if (!requiresBreakdown) {
                    field.setCustomValidity('');
                }
            });

            form.querySelectorAll('[data-budget-required-marker]').forEach(function (marker) {
                marker.classList.toggle('d-none', !requiresBreakdown);
            });
        }

        function countApplicationArabicWords(value) {
            const matches = String(value || '').match(/[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF0-9٠-٩]+/g);

            return matches ? matches.length : 0;
        }

        function hasInvalidApplicationArabicCharacters(value) {
            return /[^\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF0-9٠-٩\s.,،؛;:!؟?\-()[\]"'\/%&]/u.test(String(value || ''));
        }

        function sanitizeApplicationArabicText(value) {
            return String(value || '').replace(/[^\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF0-9٠-٩\s.,،؛;:!؟?\-()[\]"'\/%&]/gu, '');
        }

        function sanitizeApplicationWorkSummaryInput(field) {
            const original = String(field.value || '');
            const sanitized = sanitizeApplicationArabicText(original);

            if (original === sanitized) {
                return false;
            }

            const start = field.selectionStart;
            const removedCount = original.length - sanitized.length;

            field.value = sanitized;

            if (typeof start === 'number') {
                const caret = Math.max(0, start - removedCount);
                field.setSelectionRange(caret, caret);
            }

            return true;
        }

        function insertApplicationWorkSummaryText(field, text) {
            const sanitized = sanitizeApplicationArabicText(text);

            if (!sanitized) {
                return;
            }

            const start = field.selectionStart ?? field.value.length;
            const end = field.selectionEnd ?? start;

            field.setRangeText(sanitized, start, end, 'end');
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function selectedApplicationWorkSummaryMinWords(form) {
            const workCategory = form?.querySelector('select[name="work_category"]');
            const selectedCode = String(workCategory?.value || '');
            const configuredMinimum = Number.parseInt(
                applicationWorkSummaryMinWordsByCategory[selectedCode] || '',
                10,
            );

            return Number.isFinite(configuredMinimum) && configuredMinimum > 0
                ? configuredMinimum
                : applicationDefaultWorkSummaryMinWords;
        }

        function refreshApplicationWorkSummaryRules(form) {
            if (!form) {
                return;
            }

            const minimumWords = selectedApplicationWorkSummaryMinWords(form);

            form.querySelectorAll('[data-work-summary-input]').forEach(function (field) {
                field.dataset.workSummaryMinWords = String(minimumWords);
                validateApplicationWorkSummary(field);
            });

            form.querySelectorAll('[data-work-summary-instruction]').forEach(function (instruction) {
                instruction.textContent = applicationWorkSummaryMessages.instruction.replace(':min', String(minimumWords));
            });
        }

        function validateApplicationWorkSummary(field) {
            if (!field) {
                return;
            }

            sanitizeApplicationWorkSummaryInput(field);

            const value = String(field.value || '');
            const minWords = Number.parseInt(field.dataset.workSummaryMinWords || '500', 10);
            const wordCount = countApplicationArabicWords(value);
            const hasValue = value.trim() !== '';
            const invalidArabic = hasValue && hasInvalidApplicationArabicCharacters(value);
            const belowMinimum = hasValue && Number.isFinite(minWords) && wordCount < minWords;
            const counter = field.dataset.workSummaryCounter ? document.querySelector(field.dataset.workSummaryCounter) : null;

            if (invalidArabic) {
                field.setCustomValidity(applicationWorkSummaryMessages.arabicOnly);
            } else if (belowMinimum) {
                field.setCustomValidity(applicationWorkSummaryMessages.minWords.replace(':min', String(minWords)));
            } else {
                field.setCustomValidity('');
            }

            if (counter) {
                counter.textContent = applicationWorkSummaryMessages.counter
                    .replace(':count', String(wordCount))
                    .replace(':min', String(minWords));
                counter.classList.toggle('text-success', hasValue && !invalidArabic && !belowMinimum);
                counter.classList.toggle('text-danger', hasValue && (invalidArabic || belowMinimum));
                counter.classList.toggle('text-muted', !hasValue);
            }
        }

        function bindApplicationWorkSummaryValidation(field) {
            if (field.dataset.workSummaryBound === '1') {
                return;
            }

            field.dataset.workSummaryBound = '1';
            validateApplicationWorkSummary(field);

            field.addEventListener('beforeinput', function (event) {
                if (event.inputType !== 'insertText' || !event.data || !hasInvalidApplicationArabicCharacters(event.data)) {
                    return;
                }

                event.preventDefault();
            });

            field.addEventListener('paste', function (event) {
                event.preventDefault();

                const text = event.clipboardData ? event.clipboardData.getData('text') : '';
                insertApplicationWorkSummaryText(field, text);
            });

            field.addEventListener('input', function () {
                sanitizeApplicationWorkSummaryInput(field);
                validateApplicationWorkSummary(field);
            });
        }

        const requestForm = document.getElementById('form-wizard1');

        if (requestForm) {
            const disableLegacyAnnexFields = function () {
                requestForm.querySelectorAll('.legacy-annex-inline input, .legacy-annex-inline select, .legacy-annex-inline textarea').forEach(function (field) {
                    field.disabled = true;
                });
            };

            requestForm.addEventListener('submit', function () {
                disableLegacyAnnexFields();
            });

            initializeScheduleDateValidation(requestForm);
            refreshApplicationWorkSummaryRules(requestForm);
            requestForm.querySelectorAll('[data-work-summary-input]').forEach(bindApplicationWorkSummaryValidation);
            setupApplicationRepeatableTableFooters();
            refreshCastCrewNameModes(requestForm);
            refreshAirportPersonNameModes(requestForm);
            refreshFilmingLocationSupportRequirementNotes(requestForm);
            refreshTravelerCustomsProjectName();
            updateInternationalProjectSection(requestForm);
            updateBudgetBreakdownRequirements(requestForm);

            const projectNationalitySelect = requestForm.querySelector('select[name="project_nationalities[]"]');

            if (projectNationalitySelect) {
                projectNationalitySelect.addEventListener('change', function () {
                    scheduleInternationalProjectSectionUpdate(requestForm);
                });

                if (window.jQuery) {
                    window.jQuery(projectNationalitySelect).on('change select2:select select2:unselect select2:close', function () {
                        scheduleInternationalProjectSectionUpdate(requestForm);
                    });
                }

                document.addEventListener('click', function (event) {
                    if (!event.target.closest('.select2-container') && !event.target.closest('.select2-results')) {
                        return;
                    }

                    scheduleInternationalProjectSectionUpdate(requestForm);
                }, true);
            }

            ['input', 'change'].forEach(function (eventName) {
                requestForm.addEventListener(eventName, function (event) {
                    if (event.target.matches('[data-location-governorate]')) {
                        refreshApplicationLocationTypeSelect(event.target.closest('tr'));
                    }

                    if (event.target.matches('[data-location-type-select]')) {
                        event.target.dataset.selectedType = event.target.value;
                        refreshApplicationLocationApprovalNote(event.target.closest('tr'));
                    }

                    if (event.target.matches('input[name^="filming_locations"][name$="[location_name]"]')) {
                        refreshSpecialLocationRequirementSelects();
                    }

                    if (event.target.matches('[data-location-start-date], [data-location-end-date], [data-location-support-date]')) {
                        updateFilmingLocationDateConstraints(event.target.closest('.application-location-card'));
                    }

                    if (event.target.matches('[data-location-support-requirement-select], [data-location-support-notes]')) {
                        updateFilmingLocationSupportRequirementNotes(event.target.closest('[data-location-support-requirement-row]'));
                    }

                    if (event.target.matches('input[name="project_name"]')) {
                        refreshTravelerCustomsProjectName();
                    }

                    if (event.target.matches('input[name^="equipment_travelers"][name$="[traveler_name]"]')) {
                        refreshEquipmentTravelerSelects();
                    }

                    if (event.target.matches('[data-local-spend-estimate]')) {
                        updateBudgetBreakdownRequirements(requestForm);
                    }

                    if (event.target.matches('[data-work-summary-input]')) {
                        validateApplicationWorkSummary(event.target);
                    }

                    if (event.target.matches('[data-work-category-summary-rule]')) {
                        refreshApplicationWorkSummaryRules(requestForm);
                    }

                    if (event.target.matches('[data-cast-crew-nationality], [data-cast-crew-name-part], [data-cast-crew-full-name-input], [data-cast-crew-identity]')) {
                        if (event.target.matches('[data-cast-crew-identity]') && eventName === 'input') {
                            event.target.dataset.validationTouched = 'true';
                        }

                        updateCastCrewNameMode(event.target.closest('tr'));
                    }

                    if (event.target.matches('[data-airport-person-nationality], [data-airport-person-name-part], [data-airport-person-full-name-input], [data-airport-person-identity]')) {
                        updateAirportPersonNameMode(event.target.closest('tr'));
                    }

                    if (!event.target.closest('.offcanvas')) {
                        return;
                    }

                    updateRequirementStatuses();
                    updateEquipmentTotals();
                });
            });

            if (window.jQuery) {
                window.jQuery(requestForm)
                    .off(
                        'change.applicationLocationSupportNotes select2:select.applicationLocationSupportNotes select2:unselect.applicationLocationSupportNotes select2:clear.applicationLocationSupportNotes',
                        '[data-location-support-requirement-select]'
                    )
                    .on(
                        'change.applicationLocationSupportNotes select2:select.applicationLocationSupportNotes select2:unselect.applicationLocationSupportNotes select2:clear.applicationLocationSupportNotes',
                        '[data-location-support-requirement-select]',
                        function () {
                            updateFilmingLocationSupportRequirementNotes(this.closest('[data-location-support-requirement-row]'));
                        }
                    );
            }
        }

        [
            '#castCrewTable',
            '#filmingLocationsTable',
            '#importedEquipmentTable',
            '#importedEquipmentShipmentTable',
            '#equipmentTravelersTable',
            '#importedEquipmentTravelerTable',
            '#governmentalScenesTable',
            '#castCrewRequestTable',
            '#filmingLocationsRequestTable',
            '#airportPeopleTable',
            '#governmentalScenesRequestTable',
        ].forEach(renumberApplicationAnnexRows);

        updateRequirementStatuses();
        updateApplicationAnnexRowCounters();
        updateEquipmentTotals();
        refreshApplicationLocationTypeSelects(document);
        refreshCastCrewNameModes(document);
        refreshAirportPersonNameModes(document);
        refreshSpecialLocationRequirementSelects();
        refreshEquipmentTravelerSelects();
        refreshFilmingLocationDateConstraints(document);

        window.addApplicationAnnexRow = addApplicationAnnexRow;
        window.removeApplicationAnnexRow = removeApplicationAnnexRow;
    </script>
@endpush
