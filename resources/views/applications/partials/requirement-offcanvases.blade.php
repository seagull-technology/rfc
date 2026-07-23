@php
    $annexSaveButtonAttributes = ($submitAnnexForms ?? false)
        ? 'type="submit"'
        : 'type="button" data-bs-dismiss="offcanvas"';
    $annexSaveButtonAttributes .= ' data-annex-save';
    $annexSaveButtonAttributes .= ($annexSaveDisabled ?? false) ? ' disabled' : '';
    $productionTerms = $productionTerms ?? data_get($annex ?? [], 'production_terms', []);
    $ministryInteriorPersonalDetails = $ministryInteriorPersonalDetails ?? data_get($annex ?? [], 'ministry_interior_personal_details', []);
    $safetyGuidelinesRequired = ($requireSafetyGuidelines ?? true) ? 'required' : '';
    $filledFilter = static fn ($row): bool => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty();
    $formLookupOptions = $formLookupOptions ?? [];
    $equipmentClassificationOptions = $equipmentClassificationOptions ?? collect(data_get($formLookupOptions, 'equipment_categories', []));
    $equipmentEntryPointOptions = $equipmentEntryPointOptions ?? collect(data_get($formLookupOptions, 'equipment_entry_points', []));
    $airportOptions = $airportOptions ?? collect(data_get($formLookupOptions, 'airports', []));
    $specialLocationRequirementOptions = $specialLocationRequirementOptions ?? collect(data_get($formLookupOptions, 'special_location_requirements', []));
    $locationRequirementOptions = $locationRequirementOptions ?? ($specialLocationRequirementOptions->pluck('code')->all() ?: ['road_closures', 'police_presence', 'armed_forces', 'regular_aerial_filming', 'drone_filming', 'special_effects', 'construction_work', 'animals', 'weapons', 'other']);
    $locationRequirementLabels = $locationRequirementLabels ?? $specialLocationRequirementOptions->mapWithKeys(fn ($option) => [$option->code => $option->displayName()])->all();
    $maxCrewBirthDate = $maxCrewBirthDate ?? now()->subDay()->toDateString();
    $specialLocationRequirementRows = $specialLocationRequirementRows ?? old('special_location_requirements', data_get($annex ?? [], 'special_location_requirements', collect($locationRequirementOptions)->mapWithKeys(fn ($option) => [$option => ['locations' => [], 'notes' => '']])->all()));
    $locationRequirementSelectionForRow = $locationRequirementSelectionForRow ?? static function (array $row) use ($specialLocationRequirementRows): array {
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
    $publicSecuritySupportRows = $publicSecuritySupportRows ?? old('public_security_support', data_get($annex ?? [], 'public_security_support', [['day' => '', 'date' => '', 'time_from' => '', 'time_to' => '', 'location' => '', 'requirement' => '', 'notes' => '']]));
    $militarySupportRows = $militarySupportRows ?? old('military_support', data_get($annex ?? [], 'military_support', [['day' => '', 'date' => '', 'time_from' => '', 'time_to' => '', 'location' => '', 'requirement' => '', 'notes' => '']]));
    $supportAuthorityOptions = $supportAuthorityOptions ?? [
        'public_security' => __('app.applications.support_authorities.public_security'),
        'military' => __('app.applications.support_authorities.military'),
    ];
    $emptyLocationSupportRequirement = ['authority' => '', 'requirement' => '', 'date' => '', 'time_from' => '', 'time_to' => '', 'notes' => ''];
    $locationSupportRequirementsForRow = $locationSupportRequirementsForRow ?? static function (array $row, int $index = 0) use ($publicSecuritySupportRows, $militarySupportRows, $emptyLocationSupportRequirement): array {
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
    $locationSupportRequirementForRow = $locationSupportRequirementForRow ?? static fn (array $row, int $index = 0): array => $locationSupportRequirementsForRow($row, $index)[0] ?? $emptyLocationSupportRequirement;
    $equipmentFlightRows = collect($equipmentFlightRows ?? old('equipment_flights', data_get($annex ?? [], 'equipment_flights', [['flight_type' => '', 'flight_number' => '', 'flight_date' => '', 'flight_time' => '', 'departure_city' => '', 'arrival_city' => '']])))
        ->values()
        ->whenEmpty(fn ($rows) => $rows->push(['flight_type' => '', 'flight_number' => '', 'flight_date' => '', 'flight_time' => '', 'departure_city' => '', 'arrival_city' => '']));
    $equipmentTravelerRows = collect($equipmentTravelerRows ?? old('equipment_travelers', data_get($annex ?? [], 'equipment_travelers', [['traveler_name' => '', 'arrival_date' => '', 'arrival_flight_number' => '', 'departure_date' => '', 'departure_flight_number' => '', 'passport_image_path' => '', 'passport_image_name' => '']])))
        ->values()
        ->whenEmpty(fn ($rows) => $rows->push(['traveler_name' => '', 'arrival_date' => '', 'arrival_flight_number' => '', 'departure_date' => '', 'departure_flight_number' => '', 'passport_image_path' => '', 'passport_image_name' => '']));
    $importedEquipmentRows = collect($importedEquipmentRows)
        ->values()
        ->whenEmpty(fn ($rows) => $rows->push(['transport_group' => 'shipping', 'shipping_company_name' => '', 'invoice_number' => '', 'bill_of_lading_number' => '', 'arrival_date' => '', 'departure_date' => '', 'customs_center' => '', 'attachment_path' => '', 'attachment_name' => '']));
    $shippingEquipmentRows = collect($importedEquipmentRows)
        ->filter(fn ($row) => data_get($row, 'transport_group', 'shipping') !== 'traveler')
        ->values()
        ->whenEmpty(fn ($rows) => $rows->push(['transport_group' => 'shipping', 'shipping_company_name' => '', 'invoice_number' => '', 'bill_of_lading_number' => '', 'arrival_date' => '', 'departure_date' => '', 'customs_center' => '', 'attachment_path' => '', 'attachment_name' => '']));
    $travelerEquipmentRows = collect($importedEquipmentRows)
        ->filter(fn ($row) => data_get($row, 'transport_group') === 'traveler')
        ->values()
        ->whenEmpty(fn ($rows) => $rows->push(['transport_group' => 'traveler', 'item' => '', 'serial_number' => '', 'traveler_name' => '', 'quantity' => '', 'unit_value' => '', 'total_value' => '', 'classification' => '', 'entry_point' => '']));
    $castCrewNationalityOptions = collect($directorNationalityOptions ?? data_get($nationalityOptions ?? [], 'director', []));
    $airportPeopleNationalityOptions = $castCrewNationalityOptions;
    $travelerCustomsProjectName = old('project_name', data_get($application ?? null, 'project_name', ''));
    $savedWorkCategory = old(
        'work_category',
        data_get($application ?? null, 'work_category')
            ?: data_get($application ?? null, 'metadata.project.work_categories.0')
    );
    $selectedWorkSummaryMinWords = $selectedWorkSummaryMinWords
        ?? \App\Models\WorkCategory::workSummaryMinWordsFor($savedWorkCategory);
@endphp

@once
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

        .offcanvas.application-annex-offcanvas .offcanvas-header {
            flex: 0 0 auto;
        }

        .offcanvas.application-annex-offcanvas .offcanvas-body {
            background: #fff;
            flex: 1 1 auto;
            overflow-y: auto;
        }

        .offcanvas.application-annex-offcanvas .offcanvas-footer {
            background: #fff;
            bottom: 0;
            flex: 0 0 auto;
            position: sticky;
            z-index: 2;
        }

        .offcanvas.application-annex-offcanvas .table-responsive {
            overflow-x: auto;
            padding-bottom: .25rem;
        }

        .offcanvas.application-annex-offcanvas .table {
            min-width: 1120px;
            table-layout: auto;
        }

        .offcanvas.application-annex-offcanvas .table th,
        .offcanvas.application-annex-offcanvas .table td {
            vertical-align: middle;
            white-space: nowrap;
        }

        .offcanvas.application-annex-offcanvas .table textarea,
        .offcanvas.application-annex-offcanvas .table .select2-container {
            white-space: normal;
        }

        .offcanvas.application-annex-offcanvas .table .form-control,
        .offcanvas.application-annex-offcanvas .table .form-select {
            min-width: 11rem;
        }

        .offcanvas.application-annex-offcanvas .table .btn-icon {
            min-width: 2.5rem;
        }

        .offcanvas.application-annex-offcanvas .table .row-number {
            min-width: 3rem;
            width: 3rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable {
            min-width: 2050px;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(1),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(1) {
            min-width: 3.5rem;
            width: 3.5rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(2),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(2) {
            min-width: 12rem;
            width: 12rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(3),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(3) {
            min-width: 34rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(4),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(4) {
            min-width: 14rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(5),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(5) {
            min-width: 12rem;
            width: 12rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(6),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(6) {
            min-width: 13rem;
            width: 13rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(7),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(7) {
            min-width: 16rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(8),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(8) {
            min-width: 18rem;
            width: 18rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(9),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(9) {
            min-width: 7rem;
            width: 7rem;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(10),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(10) {
            min-width: 18rem;
            width: 18rem;
            white-space: normal;
        }

        .offcanvas.application-annex-offcanvas #castCrewRequestTable th:nth-child(11),
        .offcanvas.application-annex-offcanvas #castCrewRequestTable td:nth-child(11) {
            min-width: 7rem;
            width: 7rem;
        }

        .cast-crew-verification-panel {
            align-items: flex-start;
            display: flex;
            flex-direction: column;
            gap: .55rem;
        }

        .cast-crew-verification-panel .badge {
            font-size: .75rem;
            line-height: 1.35;
            white-space: normal;
        }

        .cast-crew-verification-message {
            font-size: .75rem;
            line-height: 1.5;
            margin: 0;
        }

        .cast-crew-api-locked {
            background-color: #eef1f5 !important;
            cursor: not-allowed;
        }

        .offcanvas.application-annex-offcanvas #airportPeopleTable {
            min-width: 1680px;
        }

        .offcanvas.application-annex-offcanvas .airport-person-name-cell {
            min-width: 34rem;
        }

        .offcanvas.application-annex-offcanvas #filmingLocationsRequestTable {
            min-width: 0;
        }

        .application-location-card-table {
            min-width: 0 !important;
        }

        .application-location-card-table > tbody > tr > td {
            border: 0;
            padding: 0 0 1rem;
            white-space: normal !important;
        }

        .application-location-card {
            background: #fff;
            border: 1px solid #dfe3ea;
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(17, 24, 39, .05);
            padding: 1rem;
        }

        .application-location-card__header {
            align-items: center;
            border-bottom: 1px solid #edf0f4;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: .85rem;
        }

        .application-location-card__section + .application-location-card__section {
            border-top: 1px solid #edf0f4;
            margin-top: 1rem;
            padding-top: 1rem;
        }

        .application-location-support-row {
            background: #f8fafc;
            border: 1px solid #e3e7ee;
            border-radius: 6px;
            padding: 1rem;
        }

        .application-location-card .form-control,
        .application-location-card .form-select,
        .application-location-card .select2-container {
            min-width: 0 !important;
        }
    </style>
@endonce

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="ProductionTerms">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.annex_sections.production_terms') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="section-form">
            @include('applications.partials.production-terms-form', [
                'productionTerms' => $productionTerms,
                'productionTermsReadOnly' => false,
            ])
        </div>
    </div>
    <div class="offcanvas-footer border-top">
        <div class="d-flex gap-3 p-3 justify-content-end">
            <button {!! $annexSaveButtonAttributes !!} class="btn btn-danger d-flex align-items-center gap-2"><i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.save') }}</button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i class="ph ph-caret-double-left"></i>{{ __('app.close') }}</button>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="WorkContentSummary">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.annex_sections.work_content_summary') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="section-form">
            <div class="form-group">
                <p class="text-danger fontSize13 fw-600">
                    <i class="ph ph-info fa-xl me-2 lh-lg"></i>
                    <span data-work-summary-instruction>{{ __('app.applications.work_summary_instruction', ['min' => $selectedWorkSummaryMinWords]) }}</span>
                </p>
                <label class="form-label" for="work_content_summary_synopsis_drawer">{{ __('app.applications.annex_fields.synopsis') }} <span class="text-danger">*</span></label>
                <textarea class="form-control" id="work_content_summary_synopsis_drawer" name="work_content_summary_synopsis" rows="15" required data-work-summary-input data-work-summary-min-words="{{ $selectedWorkSummaryMinWords }}" data-work-summary-counter="#work_content_summary_word_count_drawer">{{ old('work_content_summary_synopsis', data_get($workContentSummary, 'synopsis')) }}</textarea>
                <div class="d-flex justify-content-between gap-3 flex-wrap small mt-2">
                    <span class="text-muted">{{ __('app.applications.work_summary_arabic_only_hint') }}</span>
                    <span id="work_content_summary_word_count_drawer" class="text-muted" aria-live="polite"></span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="work_content_summary_attachment_drawer">{{ __('app.applications.annex_fields.work_summary_english_attachment') }}</label>
                <p class="text-muted small mb-2">
                    <i class="ph ph-info me-1"></i>{{ __('app.applications.annex_fields.work_summary_english_attachment_note') }}
                </p>
                <input
                    type="file"
                    class="form-control"
                    id="work_content_summary_attachment_drawer"
                    name="work_content_summary_attachment"
                    accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                >
                @foreach (['path', 'name', 'mime_type', 'size', 'uploaded_at'] as $attachmentField)
                    <input type="hidden" name="work_content_summary_attachment_{{ $attachmentField }}" value="{{ old('work_content_summary_attachment_'.$attachmentField, data_get($workContentSummary, 'attachment_'.$attachmentField)) }}">
                @endforeach
                @if (filled(data_get($workContentSummary, 'attachment_name')))
                    <div class="small text-muted mt-2 text-break">
                        <i class="ph ph-file-text me-1"></i>{{ data_get($workContentSummary, 'attachment_name') }}
                    </div>
                @endif
            </div>
            <div class="form-check form-group">
                <input type="hidden" name="work_content_summary_confirmed" value="0">
                <input type="checkbox" class="form-check-input" id="work_content_summary_confirmed_drawer" name="work_content_summary_confirmed" value="1" required @checked(old('work_content_summary_confirmed', data_get($workContentSummary, 'confirmed', false)))>
                <label class="form-label" for="work_content_summary_confirmed_drawer">{{ __('app.applications.annex_fields.content_confirmation') }}</label>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer border-top">
        <div class="d-flex gap-3 p-3 justify-content-end">
            <button {!! $annexSaveButtonAttributes !!} class="btn btn-danger d-flex align-items-center gap-2"><i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.save') }}</button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i class="ph ph-caret-double-left"></i>{{ __('app.close') }}</button>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="CastCrewList">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.annex_sections.cast_crew') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="section-form">
            <p class="text-danger fontSize13 fw-600">
                <i class="ph ph-info fa-xl me-2 lh-lg"></i>
                <span>{{ __('app.applications.cast_crew_instruction') }}</span>
            </p>
            <div class="d-flex flex-wrap justify-content-end gap-2 py-3">
                <button type="button" class="btn btn-outline-primary" data-cast-crew-verify-all>
                    <i class="ph ph-shield-check me-2"></i>{{ __('app.applications.cast_crew_verification.verify_all') }}
                </button>
                <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('castCrewRequestTable', 'cast_crew')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
            </div>
            <div class="alert alert-info d-none" role="status" data-cast-crew-bulk-status></div>
            <div class="table-responsive">
                <table class="table align-middle" id="castCrewRequestTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
	                            <th>{{ __('app.applications.annex_fields.nationality') }}</th>
	                            <th>{{ __('app.applications.annex_fields.person_name') }}</th>
	                            <th>{{ __('app.applications.annex_fields.role') }}</th>
	                            <th>{{ __('app.applications.annex_fields.gender') }}</th>
	                            <th>{{ __('app.applications.annex_fields.birth_date') }}</th>
	                            <th>{{ __('app.applications.annex_fields.identity_number') }}</th>
	                            <th>{{ __('app.applications.annex_fields.individual_number') }}</th>
	                            <th class="d-none" data-cast-crew-passport-heading>{{ __('app.applications.annex_fields.passport_image') }}</th>
	                            <th>{{ __('app.applications.cast_crew_verification.verification') }}</th>
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
                                $castCrewNameParts = preg_split('/\s+/', trim((string) ($row['name'] ?? '')), 4) ?: [];
                                $castCrewFirstName = $row['first_name'] ?? ($castCrewNameParts[0] ?? '');
                                $castCrewSecondName = $row['second_name'] ?? ($castCrewNameParts[1] ?? '');
                                $castCrewThirdName = $row['third_name'] ?? ($castCrewNameParts[2] ?? '');
                                $castCrewFamilyName = $row['family_name'] ?? ($castCrewNameParts[3] ?? '');
                                $storedCastCrewVerificationStatus = $row['identity_verification_status'] ?? 'unverified';
                                $castCrewVerificationStatus = in_array($storedCastCrewVerificationStatus, ['verified', 'pending', 'manual', 'unverified'], true)
                                    ? $storedCastCrewVerificationStatus
                                    : 'unverified';
                                $castCrewVerificationBadge = match ($castCrewVerificationStatus) {
                                    'verified' => 'success',
                                    'pending' => 'warning text-dark',
                                    'manual' => 'secondary',
                                    default => 'light text-dark border',
                                };
                                $castCrewVerificationSource = $row['identity_verification_source'] ?? '';
                                $castCrewVerificationSourceLabel = filled($castCrewVerificationSource)
                                    ? __('app.applications.cast_crew_verification.sources.'.$castCrewVerificationSource)
                                    : '';
                                $castCrewVerifiedAt = $row['identity_verified_at'] ?? '';
                            @endphp
                            <tr>
                                <td class="row-number">{{ $loop->iteration }}</td>
                                <td>
                                    <select class="form-select" name="cast_crew[{{ $index }}][nationality]" data-cast-crew-nationality required>
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @if (filled($castCrewNationalityValue) && ! $castCrewNationalityOptions->contains('code', $castCrewNationalityValue))
                                            <option value="{{ $castCrewNationalityValue }}" selected>{{ \App\Models\Nationality::labelFor($castCrewNationalityValue) }}</option>
                                        @endif
                                        @foreach ($castCrewNationalityOptions as $nationality)
                                            <option value="{{ $nationality->code }}" @selected($castCrewNationalityValue === $nationality->code)>{{ $nationality->displayName() }}</option>
	                                        @endforeach
	                                    </select>
	                                </td>
                                <td class="cast-crew-name-cell">
                                    <input type="hidden" name="cast_crew[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" data-cast-crew-name-output>
                                    <div class="row g-2 cast-crew-jordanian-name {{ $castCrewIsJordanian ? '' : 'd-none' }}" data-cast-crew-jordanian-name>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][first_name]" value="{{ $castCrewFirstName }}" placeholder="{{ __('app.applications.annex_fields.first_name') }}" data-cast-crew-name-part data-cast-crew-api-field @required($castCrewIsJordanian) @disabled(! $castCrewIsJordanian)></div>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][second_name]" value="{{ $castCrewSecondName }}" placeholder="{{ __('app.applications.annex_fields.second_name') }}" data-cast-crew-name-part data-cast-crew-api-field @required($castCrewIsJordanian) @disabled(! $castCrewIsJordanian)></div>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][third_name]" value="{{ $castCrewThirdName }}" placeholder="{{ __('app.applications.annex_fields.third_name') }}" data-cast-crew-name-part data-cast-crew-api-field @required($castCrewIsJordanian) @disabled(! $castCrewIsJordanian)></div>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][family_name]" value="{{ $castCrewFamilyName }}" placeholder="{{ __('app.applications.annex_fields.family_name') }}" data-cast-crew-name-part data-cast-crew-api-field @required($castCrewIsJordanian) @disabled(! $castCrewIsJordanian)></div>
                                    </div>
                                    <input type="text" class="form-control {{ $castCrewIsJordanian ? 'd-none' : '' }}" value="{{ $row['name'] ?? '' }}" data-cast-crew-full-name-input data-cast-crew-api-field @required(! $castCrewIsJordanian)>
                                </td>
                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][role]" value="{{ $row['role'] ?? '' }}" required></td>
	                                <td>
	                                    <select class="form-select" name="cast_crew[{{ $index }}][gender]" data-cast-crew-gender data-cast-crew-api-field required>
	                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
	                                        @foreach (['male', 'female'] as $gender)
	                                            <option value="{{ $gender }}" @selected(($row['gender'] ?? '') === $gender)>{{ __('app.auth.gender_options.'.$gender) }}</option>
	                                        @endforeach
	                                    </select>
	                                </td>
	                                <td><input type="date" class="form-control" name="cast_crew[{{ $index }}][birth_date]" value="{{ $row['birth_date'] ?? '' }}" max="{{ $maxCrewBirthDate }}" data-cast-crew-birth-date data-cast-crew-api-field required></td>
	                                <td>
	                                    <input type="text" class="form-control" name="cast_crew[{{ $index }}][identity_number]" value="{{ $row['identity_number'] ?? '' }}" placeholder="{{ $castCrewIsJordanian ? __('app.applications.annex_fields.national_id') : __('app.applications.annex_fields.passport_number') }}" inputmode="{{ $castCrewIsJordanian ? 'numeric' : 'text' }}" required @if ($castCrewIsJordanian) minlength="10" maxlength="10" pattern="\d{10}" @endif data-cast-crew-identity>
	                                    <div class="invalid-feedback" data-cast-crew-identity-feedback>{{ __('app.applications.cast_crew_national_id_digits') }}</div>
	                                </td>
	                                <td>
	                                    <div class="{{ $castCrewIsJordanian ? 'd-none' : '' }}" data-cast-crew-individual-number-wrap>
	                                        <input type="text" class="form-control" name="cast_crew[{{ $index }}][individual_number]" value="{{ $row['individual_number'] ?? '' }}" inputmode="numeric" maxlength="20" pattern="\d{1,20}" data-cast-crew-individual-number @disabled($castCrewIsJordanian)>
	                                        <small class="form-text text-muted d-block mt-1">{{ __('app.applications.cast_crew_verification.foreign_optional_help') }}</small>
	                                    </div>
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
                                <td>
                                    <input type="hidden" name="cast_crew[{{ $index }}][verification_token]" value="" data-cast-crew-verification-token>
                                    <input type="hidden" name="cast_crew[{{ $index }}][identity_verification_status]" value="{{ $castCrewVerificationStatus }}" data-cast-crew-verification-status>
                                    <input type="hidden" name="cast_crew[{{ $index }}][identity_verification_source]" value="{{ $castCrewVerificationSource }}" data-cast-crew-verification-source>
                                    <input type="hidden" name="cast_crew[{{ $index }}][identity_verified_at]" value="{{ $castCrewVerifiedAt }}" data-cast-crew-verified-at>
                                    <input type="hidden" name="cast_crew[{{ $index }}][identity_verification_category]" value="{{ $castCrewIsJordanian ? 'jordanian' : 'foreign' }}" data-cast-crew-verification-category>
                                    <div class="cast-crew-verification-panel">
                                        <span class="badge bg-{{ $castCrewVerificationBadge }}" data-cast-crew-verification-badge>{{ __('app.applications.cast_crew_verification.statuses.'.$castCrewVerificationStatus) }}</span>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-cast-crew-verify>
                                            <i class="ph ph-shield-check me-1"></i>{{ __('app.applications.cast_crew_verification.verify_identity') }}
                                        </button>
                                        <p class="cast-crew-verification-message {{ filled($castCrewVerificationSource) || filled($castCrewVerifiedAt) ? 'text-muted' : 'd-none' }}" data-cast-crew-verification-message>
                                            @if (filled($castCrewVerificationSource)){{ __('app.applications.cast_crew_verification.source', ['source' => $castCrewVerificationSourceLabel]) }}@endif
                                            @if (filled($castCrewVerifiedAt))<br>{{ __('app.applications.cast_crew_verification.verified_at', ['date' => $castCrewVerifiedAt]) }}@endif
                                        </p>
                                    </div>
                                </td>
                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#castCrewRequestTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer border-top">
        <div class="d-flex gap-3 p-3 justify-content-end">
            <button {!! $annexSaveButtonAttributes !!} class="btn btn-danger d-flex align-items-center gap-2"><i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.save') }}</button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i class="ph ph-caret-double-left"></i>{{ __('app.close') }}</button>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="LocationList">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.annex_sections.filming_locations') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="section-form">
            <p class="text-danger fontSize13 fw-600">
                <i class="ph ph-info fa-xl me-2 lh-lg"></i>
                <span>{{ __('app.applications.location_damage_instruction') }}</span>
            </p>
            <div class="d-flex justify-content-end py-3">
                <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('filmingLocationsRequestTable', 'filming_locations')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.applications.add_filming_location') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table application-location-card-table" id="filmingLocationsRequestTable">
                    <tbody>
                        @foreach ($filmingLocationRows as $index => $row)
                            @include('applications.partials.filming-location-card', ['tableId' => 'filmingLocationsRequestTable', 'index' => $index, 'row' => (array) $row, 'rowNumber' => $loop->iteration])
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('applications.partials.location-support-requirements-editor', [
                'locationTableId' => 'filmingLocationsRequestTable',
            ])
        </div>
    </div>
    <div class="offcanvas-footer border-top">
        <div class="d-flex gap-3 p-3 justify-content-end">
            <button {!! $annexSaveButtonAttributes !!} class="btn btn-danger d-flex align-items-center gap-2"><i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.save') }}</button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i class="ph ph-caret-double-left"></i>{{ __('app.close') }}</button>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="RFCGuidelines">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.safety_guidelines_full_title') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="section-form">
            <h4>{{ __('app.applications.safety_summary_title') }}</h4>
            <ul>
                <li>{{ __('app.applications.safety_points.safe_workplace') }}</li>
                <li>{{ __('app.applications.safety_points.daily_meetings') }}</li>
                <li>{{ __('app.applications.safety_points.authorized_equipment') }}</li>
                <li>{{ __('app.applications.safety_points.report_incidents') }}</li>
                <li>{{ __('app.applications.safety_points.ppe') }}</li>
                <li>{{ __('app.applications.safety_points.emergency_plan') }}</li>
            </ul>
            @foreach (trans('app.applications.safety_sections') as $section)
                <h4 class="mt-4">{{ $section['title'] }}</h4>
                <ul>
                    @foreach ($section['points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            @endforeach
            <hr>
            <div class="form-check form-group">
                <input type="hidden" name="safety_guidelines_acknowledged" value="0">
                <input type="checkbox" class="form-check-input" id="safety_guidelines_acknowledged_drawer" name="safety_guidelines_acknowledged" value="1" {{ $safetyGuidelinesRequired }} @checked(old('safety_guidelines_acknowledged', data_get($safetyGuidelines, 'acknowledged', false)))>
                <label class="form-label" for="safety_guidelines_acknowledged_drawer">{{ __('app.applications.annex_fields.safety_acknowledgement') }}</label>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('app.applications.annex_fields.safety_notes') }}</label>
                <textarea class="form-control" name="safety_guidelines_notes" rows="4">{{ old('safety_guidelines_notes', data_get($safetyGuidelines, 'notes')) }}</textarea>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer border-top">
        <div class="d-flex gap-3 p-3 justify-content-end">
            <button {!! $annexSaveButtonAttributes !!} class="btn btn-danger d-flex align-items-center gap-2"><i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.save') }}</button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i class="ph ph-caret-double-left"></i>{{ __('app.close') }}</button>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="MinistryInteriorPersonalDetails" data-project-needs-form="ministry-personal-details">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.annex_sections.ministry_interior_personal_details') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="section-form">
            @include('applications.partials.ministry-interior-personal-details-form', [
                'ministryInteriorPersonalDetails' => $ministryInteriorPersonalDetails,
                'ministryInteriorPersonalDetailsReadOnly' => false,
                'ministryInteriorPersonalDetailsIdPrefix' => 'ministry_interior_personal_details_drawer',
                'ministryNationalityOptions' => $castCrewNationalityOptions,
            ])
        </div>
    </div>
    <div class="offcanvas-footer border-top">
        <div class="d-flex gap-3 p-3 justify-content-end">
            <button {!! $annexSaveButtonAttributes !!} class="btn btn-danger d-flex align-items-center gap-2"><i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.save') }}</button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i class="ph ph-caret-double-left"></i>{{ __('app.close') }}</button>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="EquipmentList" data-project-needs-form="equipment">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.annex_sections.imported_equipment') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="nav nav-pills mb-0 nav-fill" role="tablist">
            <li class="nav-item">
                <a class="nav-link active p-4 fontSize20" data-bs-toggle="pill" href="#equipment-shipping-pane" role="tab" aria-selected="true">{{ __('app.applications.shipping_equipment_tab') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link p-4 fontSize20" data-bs-toggle="pill" href="#equipment-traveler-pane" role="tab" aria-selected="false">{{ __('app.applications.traveler_equipment_tab') }}</a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active border px-4 py-3" id="equipment-shipping-pane" role="tabpanel">
                <div class="border-bottom pb-4 mb-4 text-danger fontSize13 fw-600 lh-lg">
                    <p class="mb-2">
                        <i class="ph ph-info fa-xl me-2 lh-lg"></i>
                        <span>{{ __('app.applications.shipping_customs_instruction_before_project') }}</span>
                        <strong class="fw-bold" data-shipping-customs-project-name>{{ $travelerCustomsProjectName }}</strong>
                        <span>{{ __('app.applications.shipping_customs_instruction_after_project') }}</span>
                    </p>
                    <ol class="mb-2 pe-4">
                        @foreach (__('app.applications.shipping_customs_requirements') as $requirement)
                            <li class="mb-1">{{ $requirement }}</li>
                        @endforeach
                    </ol>
                    <p class="mb-0">{{ __('app.applications.shipping_customs_conclusion') }}</p>
                </div>
                <div class="section-form">
                    <div class="d-flex justify-content-end mb-3">
                        <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('importedEquipmentShipmentTable', 'imported_equipment')">
                            <i class="fa fa-plus me-2"></i>{{ __('app.add') }}
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle" id="importedEquipmentShipmentTable">
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
                                @foreach ($shippingEquipmentRows as $index => $row)
                                    @php
                                        $rowKey = is_string($index) ? $index : 'shipping_'.$index;
                                    @endphp
                                    <tr>
                                        <td class="row-number">{{ $loop->iteration }}</td>
                                        <td><input type="hidden" name="imported_equipment[{{ $rowKey }}][transport_group]" value="shipping"><input type="text" class="form-control" name="imported_equipment[{{ $rowKey }}][shipping_company_name]" value="{{ $row['shipping_company_name'] ?? '' }}"></td>
                                        <td><input type="text" class="form-control" name="imported_equipment[{{ $rowKey }}][invoice_number]" value="{{ $row['invoice_number'] ?? '' }}"></td>
                                        <td><input type="text" class="form-control" name="imported_equipment[{{ $rowKey }}][bill_of_lading_number]" value="{{ $row['bill_of_lading_number'] ?? '' }}"></td>
                                        <td><input type="date" class="form-control" name="imported_equipment[{{ $rowKey }}][arrival_date]" value="{{ $row['arrival_date'] ?? '' }}"></td>
                                        <td><input type="date" class="form-control" name="imported_equipment[{{ $rowKey }}][departure_date]" value="{{ $row['departure_date'] ?? '' }}"></td>
                                        <td>
                                            @php
                                                $selectedCustomsCenter = (string) ($row['customs_center'] ?? ($row['entry_point'] ?? ''));
                                            @endphp
                                            <select class="form-select" name="imported_equipment[{{ $rowKey }}][customs_center]">
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
                                            <input type="file" class="form-control" name="imported_equipment[{{ $rowKey }}][attachment]" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png">
                                            @foreach (['attachment_path', 'attachment_name', 'attachment_mime_type', 'attachment_size', 'attachment_uploaded_at'] as $attachmentField)
                                                <input type="hidden" name="imported_equipment[{{ $rowKey }}][{{ $attachmentField }}]" value="{{ $row[$attachmentField] ?? '' }}">
                                            @endforeach
                                            @if (filled($row['attachment_name'] ?? null))
                                                <div class="small text-muted mt-1">{{ $row['attachment_name'] }}</div>
                                            @endif
                                        </td>
                                        <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#importedEquipmentShipmentTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('importedEquipmentShipmentTable', 'imported_equipment')">
                            <i class="fa fa-plus me-2"></i>{{ __('app.add') }}
                        </button>
                    </div>
                    <div class="form-check form-group mt-4">
                        <input type="hidden" name="shipping_equipment_acknowledged" value="0">
                        <input type="checkbox" class="form-check-input" id="shipping_equipment_acknowledged" name="shipping_equipment_acknowledged" value="1" @checked(old('shipping_equipment_acknowledged', data_get($annex, 'shipping_equipment_acknowledged', false)))>
                        <label class="form-label" for="shipping_equipment_acknowledged">{{ __('app.applications.shipping_equipment_acknowledgement') }} <span class="text-danger">*</span></label>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade border px-4 py-3" id="equipment-traveler-pane" role="tabpanel">
                <p class="text-danger fontSize13 fw-600 mb-2 lh-lg">
                    <i class="ph ph-info fa-xl me-2 lh-lg"></i>
                    <span>{{ __('app.applications.traveler_customs_instruction_before_project') }}</span>
                    <strong class="fw-bold" data-traveler-customs-project-name>{{ $travelerCustomsProjectName }}</strong>
                    <span>{{ __('app.applications.traveler_customs_instruction_after_project') }}</span>
                </p>
                <h2 class="episode-playlist-title wp-heading-inline py-4 fontSize20"><span class="position-relative">{{ __('app.applications.travelers_list_title') }}</span></h2>
                <div class="d-flex justify-content-end py-3">
                    <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('equipmentTravelersTable', 'equipment_travelers')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle" id="equipmentTravelersTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>{{ __('app.applications.annex_fields.traveler_name') }}</th>
                                <th>{{ __('app.applications.annex_fields.arrival_date') }}</th>
                                <th>{{ __('app.applications.annex_fields.flight_number') }}</th>
                                <th>{{ __('app.applications.annex_fields.departure_date') }}</th>
                                <th>{{ __('app.applications.annex_fields.departure_flight_number') }}</th>
                                <th>{{ __('app.applications.annex_fields.passport_image') }}</th>
                                <th>{{ __('app.applications.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($equipmentTravelerRows as $index => $row)
                                <tr>
                                    <td class="row-number">{{ $loop->iteration }}</td>
                                    <td><input type="text" class="form-control" name="equipment_travelers[{{ $index }}][traveler_name]" value="{{ $row['traveler_name'] ?? '' }}"></td>
                                    <td><input type="date" class="form-control" name="equipment_travelers[{{ $index }}][arrival_date]" value="{{ $row['arrival_date'] ?? '' }}"></td>
                                    <td><input type="text" class="form-control" name="equipment_travelers[{{ $index }}][arrival_flight_number]" value="{{ $row['arrival_flight_number'] ?? '' }}"></td>
                                    <td><input type="date" class="form-control" name="equipment_travelers[{{ $index }}][departure_date]" value="{{ $row['departure_date'] ?? '' }}"></td>
                                    <td><input type="text" class="form-control" name="equipment_travelers[{{ $index }}][departure_flight_number]" value="{{ $row['departure_flight_number'] ?? '' }}"></td>
                                    <td>
                                        <input type="file" class="form-control" name="equipment_travelers[{{ $index }}][passport_image]" accept="image/jpeg,image/png,.jpg,.jpeg,.png">
                                        <small class="form-text text-muted d-block mt-1">{{ __('app.applications.annex_fields.passport_image_note') }}</small>
                                        @foreach (['passport_image_path', 'passport_image_name', 'passport_image_mime_type', 'passport_image_size', 'passport_image_uploaded_at'] as $passportField)
                                            <input type="hidden" name="equipment_travelers[{{ $index }}][{{ $passportField }}]" value="{{ $row[$passportField] ?? '' }}">
                                        @endforeach
                                        @if (filled($row['passport_image_name'] ?? null))
                                            <div class="small text-muted mt-1 text-break">{{ $row['passport_image_name'] }}</div>
                                        @endif
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#equipmentTravelersTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h2 class="episode-playlist-title wp-heading-inline py-4 fontSize20"><span class="position-relative">{{ __('app.applications.equipment_list_title') }}</span></h2>
                <div class="d-flex justify-content-end py-3">
                    <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('importedEquipmentTravelerTable', 'imported_equipment_traveler')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle" id="importedEquipmentTravelerTable">
                        <thead class="table-light">
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
                                <th>{{ __('app.applications.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($travelerEquipmentRows as $index => $row)
                                @php
                                    $rowKey = is_string($index) ? $index : 'traveler_'.$index;
                                    $selectedTravelerName = $row['traveler_name'] ?? '';
                                    $travelerNameOptions = collect($equipmentTravelerRows)
                                        ->map(function ($travelerRow, $travelerIndex) {
                                            return [
                                                'key' => (string) $travelerIndex,
                                                'label' => filled($travelerRow['traveler_name'] ?? null)
                                                    ? $travelerRow['traveler_name']
                                                    : __('app.applications.traveler_number', ['number' => $travelerIndex + 1]),
                                            ];
                                        });
                                @endphp
                                <tr>
                                    <td class="row-number">{{ $loop->iteration }}</td>
                                    <td><input type="hidden" name="imported_equipment[{{ $rowKey }}][transport_group]" value="traveler"><input type="text" class="form-control" name="imported_equipment[{{ $rowKey }}][item]" value="{{ $row['item'] ?? '' }}"></td>
                                    <td><input type="text" class="form-control" name="imported_equipment[{{ $rowKey }}][serial_number]" value="{{ $row['serial_number'] ?? '' }}"></td>
                                    <td>
                                        <select class="form-select" name="imported_equipment[{{ $rowKey }}][traveler_name]" data-equipment-traveler-select>
                                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                            @foreach ($travelerNameOptions as $traveler)
                                                <option value="{{ $traveler['label'] }}" data-traveler-key="{{ $traveler['key'] }}" @selected($selectedTravelerName === $traveler['label'])>{{ $traveler['label'] }}</option>
                                            @endforeach
                                            @if (filled($selectedTravelerName) && ! $travelerNameOptions->contains(fn ($traveler) => $traveler['label'] === $selectedTravelerName))
                                                <option value="{{ $selectedTravelerName }}" selected>{{ $selectedTravelerName }}</option>
                                            @endif
                                        </select>
                                    </td>
                                    <td><input type="number" min="0" class="form-control" name="imported_equipment[{{ $rowKey }}][quantity]" value="{{ $row['quantity'] ?? '' }}" data-equipment-quantity></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[{{ $rowKey }}][unit_value]" value="{{ $row['unit_value'] ?? '' }}" data-equipment-unit-value></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[{{ $rowKey }}][total_value]" value="{{ $row['total_value'] ?? '' }}" data-equipment-row-total readonly></td>
                                    <td>
                                        @php
                                            $selectedClassification = (string) ($row['classification'] ?? '');
                                        @endphp
                                        <select class="form-select" name="imported_equipment[{{ $rowKey }}][classification]">
                                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                            @if (filled($selectedClassification) && ! $equipmentClassificationOptions->contains('code', $selectedClassification))
                                                <option value="{{ $selectedClassification }}" selected>{{ $selectedClassification }}</option>
                                            @endif
                                            @foreach ($equipmentClassificationOptions as $option)
                                                <option value="{{ $option->code }}" @selected($selectedClassification === $option->code)>{{ $option->displayName() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @php
                                            $selectedEntryPoint = (string) ($row['entry_point'] ?? '');
                                        @endphp
                                        <select class="form-select" name="imported_equipment[{{ $rowKey }}][entry_point]">
                                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                            @if (filled($selectedEntryPoint) && ! $equipmentEntryPointOptions->contains('code', $selectedEntryPoint))
                                                <option value="{{ $selectedEntryPoint }}" selected>{{ $selectedEntryPoint }}</option>
                                            @endif
                                            @foreach ($equipmentEntryPointOptions as $option)
                                                <option value="{{ $option->code }}" @selected($selectedEntryPoint === $option->code)>{{ $option->displayName() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#importedEquipmentTravelerTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end align-items-center gap-2 fw-600 mt-2">
                    <span>{{ __('app.applications.equipment_total_label') }}</span>
                    <span data-equipment-total="#importedEquipmentTravelerTable">0</span>
                    <span>{{ __('app.applications.usd') }}</span>
                </div>
                <div class="form-check form-group">
                    <input type="hidden" name="traveler_equipment_acknowledged" value="0">
                    <input type="checkbox" class="form-check-input" id="traveler_equipment_acknowledged" name="traveler_equipment_acknowledged" value="1" @checked(old('traveler_equipment_acknowledged', data_get($annex, 'traveler_equipment_acknowledged', false)))>
                    <label class="form-label" for="traveler_equipment_acknowledged">{{ __('app.applications.traveler_equipment_acknowledgement') }} <span class="text-danger">*</span></label>
                </div>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer border-top">
        <div class="d-flex gap-3 p-3 justify-content-end">
            <button {!! $annexSaveButtonAttributes !!} class="btn btn-danger d-flex align-items-center gap-2"><i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.save') }}</button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i class="ph ph-caret-double-left"></i>{{ __('app.close') }}</button>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="FilmingAirports" data-project-needs-form="airport">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.annex_sections.airport_filming') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="section-form">
            <div class="row g-3 mb-4">
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
            </div>
            <div class="d-flex justify-content-end align-items-center gap-2 py-3 flex-wrap">
                <button
                    type="button"
                    class="btn btn-outline-primary"
                    data-import-cast-crew-to-airport
                    data-empty-message="{{ __('app.applications.import_cast_crew_to_airport_empty') }}"
                    data-success-message="{{ __('app.applications.import_cast_crew_to_airport_success') }}"
                    data-no-new-message="{{ __('app.applications.import_cast_crew_to_airport_no_new') }}"
                    onclick="importApplicationCastCrewToAirportPeople(this)"
                >
                    <i class="ph ph-download-simple me-2"></i>{{ __('app.applications.import_cast_crew_to_airport') }}
                </button>
                <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('airportPeopleTable', 'airport_people')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
                <span class="small text-muted w-100 text-end" data-airport-crew-import-status aria-live="polite"></span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="airportPeopleTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>{{ __('app.applications.annex_fields.nationality') }}</th>
                            <th>{{ __('app.applications.annex_fields.person_name') }}</th>
                            <th>{{ __('app.applications.annex_fields.mother_name') }}</th>
                            <th>{{ __('app.applications.annex_fields.identity_number') }}</th>
                            <th>{{ __('app.applications.annex_fields.profession') }}</th>
                            <th>{{ __('app.applications.annex_fields.address_phone') }}</th>
                            <th>{{ __('app.applications.annex_fields.entry_reason') }}</th>
                            <th>{{ __('app.applications.annex_fields.target_area') }}</th>
                            <th>{{ __('app.applications.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($airportPeopleRows as $index => $row)
                            @php
                                $airportPersonNationalityText = trim((string) ($row['nationality'] ?? ''));
                                $legacyJordanianNationalities = ['jordanian', 'Jordanian', 'أردني', 'اردني'];
                                $airportPersonNationalityValue = in_array($airportPersonNationalityText, $legacyJordanianNationalities, true) ? 'jordanian' : $airportPersonNationalityText;
                                $airportPersonIsJordanian = $airportPersonNationalityValue === 'jordanian';
                                $airportPersonNameParts = [
                                    'first_name' => (string) ($row['first_name'] ?? ''),
                                    'second_name' => (string) ($row['second_name'] ?? ''),
                                    'third_name' => (string) ($row['third_name'] ?? ''),
                                    'family_name' => (string) ($row['family_name'] ?? ''),
                                ];

                                if ($airportPersonIsJordanian && ! collect($airportPersonNameParts)->filter(fn ($part) => filled($part))->isNotEmpty() && filled($row['full_name'] ?? null)) {
                                    $splitNameParts = preg_split('/\s+/', trim((string) $row['full_name'])) ?: [];
                                    $airportPersonNameParts['first_name'] = $splitNameParts[0] ?? '';
                                    $airportPersonNameParts['second_name'] = $splitNameParts[1] ?? '';
                                    $airportPersonNameParts['third_name'] = $splitNameParts[2] ?? '';
                                    $airportPersonNameParts['family_name'] = implode(' ', array_slice($splitNameParts, 3));
                                }
                            @endphp
                            <tr>
                                <td class="row-number">{{ $loop->iteration }}</td>
                                <td>
                                    <select class="form-select" name="airport_people[{{ $index }}][nationality]" data-airport-person-nationality>
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @if (filled($airportPersonNationalityValue) && ! $airportPeopleNationalityOptions->contains('code', $airportPersonNationalityValue))
                                            <option value="{{ $airportPersonNationalityValue }}" selected>{{ \App\Models\Nationality::labelFor($airportPersonNationalityValue) }}</option>
                                        @endif
                                        @foreach ($airportPeopleNationalityOptions as $nationality)
                                            <option value="{{ $nationality->code }}" @selected($airportPersonNationalityValue === $nationality->code)>{{ $nationality->displayName() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="airport-person-name-cell">
                                    <input type="hidden" name="airport_people[{{ $index }}][full_name]" value="{{ $row['full_name'] ?? '' }}" data-airport-person-name-output>
                                    <div class="row g-2 airport-person-jordanian-name {{ $airportPersonIsJordanian ? '' : 'd-none' }}" data-airport-person-jordanian-name>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="airport_people[{{ $index }}][first_name]" value="{{ $airportPersonNameParts['first_name'] }}" placeholder="{{ __('app.applications.annex_fields.first_name') }}" data-airport-person-name-part @disabled(! $airportPersonIsJordanian)></div>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="airport_people[{{ $index }}][second_name]" value="{{ $airportPersonNameParts['second_name'] }}" placeholder="{{ __('app.applications.annex_fields.second_name') }}" data-airport-person-name-part @disabled(! $airportPersonIsJordanian)></div>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="airport_people[{{ $index }}][third_name]" value="{{ $airportPersonNameParts['third_name'] }}" placeholder="{{ __('app.applications.annex_fields.third_name') }}" data-airport-person-name-part @disabled(! $airportPersonIsJordanian)></div>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="airport_people[{{ $index }}][family_name]" value="{{ $airportPersonNameParts['family_name'] }}" placeholder="{{ __('app.applications.annex_fields.family_name') }}" data-airport-person-name-part @disabled(! $airportPersonIsJordanian)></div>
                                    </div>
                                    <input type="text" class="form-control {{ $airportPersonIsJordanian ? 'd-none' : '' }}" value="{{ $row['full_name'] ?? '' }}" data-airport-person-full-name-input>
                                </td>
                                <td><input type="text" class="form-control" name="airport_people[{{ $index }}][mother_name]" value="{{ $row['mother_name'] ?? '' }}"></td>
                                <td><input type="text" class="form-control" name="airport_people[{{ $index }}][identity_number]" value="{{ $row['identity_number'] ?? '' }}" placeholder="{{ $airportPersonIsJordanian ? __('app.applications.annex_fields.national_id') : __('app.applications.annex_fields.passport_number') }}" inputmode="{{ $airportPersonIsJordanian ? 'numeric' : 'text' }}" @if ($airportPersonIsJordanian) maxlength="10" pattern="\d{10}" @endif data-airport-person-identity></td>
                                <td><input type="text" class="form-control" name="airport_people[{{ $index }}][profession]" value="{{ $row['profession'] ?? '' }}"></td>
                                <td><input type="text" class="form-control" name="airport_people[{{ $index }}][address_phone]" value="{{ $row['address_phone'] ?? '' }}"></td>
                                <td><input type="text" class="form-control" name="airport_people[{{ $index }}][entry_reason]" value="{{ $row['entry_reason'] ?? '' }}"></td>
                                <td><input type="text" class="form-control" name="airport_people[{{ $index }}][target_area]" value="{{ $row['target_area'] ?? '' }}"></td>
                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#airportPeopleTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('app.applications.annex_fields.notes') }}</label>
                <textarea class="form-control" name="airport_filming_notes" rows="4">{{ old('airport_filming_notes', data_get($airportFilming, 'notes')) }}</textarea>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer border-top">
        <div class="d-flex gap-3 p-3 justify-content-end">
            <button {!! $annexSaveButtonAttributes !!} class="btn btn-danger d-flex align-items-center gap-2"><i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.save') }}</button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i class="ph ph-caret-double-left"></i>{{ __('app.close') }}</button>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function normalizedValue(value) {
                    return String(value || '').trim().replace(/\s+/g, ' ');
                }

                function namedControlValue(row, suffix) {
                    return normalizedValue(row.querySelector('[name$="[' + suffix + ']"]')?.value);
                }

                function personKey(person) {
                    const identity = normalizedValue(person.identityNumber).toLowerCase();

                    if (identity !== '') {
                        return 'identity:' + identity;
                    }

                    return 'name:' + normalizedValue(person.nationality).toLowerCase()
                        + ':' + normalizedValue(person.fullName).toLowerCase();
                }

                function castCrewPerson(row) {
                    const nationalityControl = row.querySelector('[data-cast-crew-nationality]');
                    const nationality = normalizedValue(nationalityControl?.value);
                    const nameParts = ['first_name', 'second_name', 'third_name', 'family_name'].map(function (part) {
                        return namedControlValue(row, part);
                    });
                    const splitName = nameParts.filter(Boolean).join(' ');
                    const fullName = splitName
                        || normalizedValue(row.querySelector('[data-cast-crew-full-name-input]')?.value)
                        || normalizedValue(row.querySelector('[data-cast-crew-name-output]')?.value);
                    const identityNumber = namedControlValue(row, 'identity_number');

                    if (fullName === '' && identityNumber === '') {
                        return null;
                    }

                    return {
                        nationality: nationality,
                        nationalityLabel: normalizedValue(nationalityControl?.selectedOptions?.[0]?.textContent),
                        fullName: fullName,
                        nameParts: nameParts,
                        identityNumber: identityNumber,
                        profession: namedControlValue(row, 'role'),
                    };
                }

                function airportPerson(row) {
                    const nameParts = ['first_name', 'second_name', 'third_name', 'family_name'].map(function (part) {
                        return namedControlValue(row, part);
                    });

                    return {
                        nationality: normalizedValue(row.querySelector('[data-airport-person-nationality]')?.value),
                        fullName: nameParts.filter(Boolean).join(' ')
                            || normalizedValue(row.querySelector('[data-airport-person-full-name-input]')?.value)
                            || normalizedValue(row.querySelector('[data-airport-person-name-output]')?.value),
                        identityNumber: namedControlValue(row, 'identity_number'),
                    };
                }

                function airportRowIsEmpty(row) {
                    return [
                        row.querySelector('[data-airport-person-nationality]')?.value,
                        row.querySelector('[data-airport-person-name-output]')?.value,
                        row.querySelector('[data-airport-person-full-name-input]')?.value,
                        namedControlValue(row, 'first_name'),
                        namedControlValue(row, 'second_name'),
                        namedControlValue(row, 'third_name'),
                        namedControlValue(row, 'family_name'),
                        namedControlValue(row, 'mother_name'),
                        namedControlValue(row, 'identity_number'),
                        namedControlValue(row, 'profession'),
                        namedControlValue(row, 'address_phone'),
                        namedControlValue(row, 'entry_reason'),
                        namedControlValue(row, 'target_area'),
                    ].every(function (value) {
                        return normalizedValue(value) === '';
                    });
                }

                function ensureSelectValue(select, value, label) {
                    if (!select || value === '') {
                        return;
                    }

                    const hasOption = Array.from(select.options).some(function (option) {
                        return option.value === value;
                    });

                    if (!hasOption) {
                        select.add(new Option(label || value, value));
                    }

                    select.value = value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                function fillAirportRow(row, person) {
                    const nationality = row.querySelector('[data-airport-person-nationality]');
                    ensureSelectValue(nationality, person.nationality, person.nationalityLabel);

                    const isJordanian = normalizedValue(person.nationality).toLowerCase() === 'jordanian';
                    const sourceParts = person.nameParts.filter(Boolean).length > 0
                        ? person.nameParts
                        : normalizedValue(person.fullName).split(' ');

                    if (isJordanian) {
                        ['first_name', 'second_name', 'third_name', 'family_name'].forEach(function (part, index) {
                            const field = row.querySelector('[name$="[' + part + ']"]');

                            if (field) {
                                field.value = index === 3 ? sourceParts.slice(3).join(' ') : (sourceParts[index] || '');
                            }
                        });
                    } else {
                        const fullName = row.querySelector('[data-airport-person-full-name-input]');

                        if (fullName) {
                            fullName.value = person.fullName;
                        }
                    }

                    const nameOutput = row.querySelector('[data-airport-person-name-output]');
                    const identity = row.querySelector('[data-airport-person-identity]');
                    const profession = row.querySelector('[name$="[profession]"]');

                    if (nameOutput) {
                        nameOutput.value = person.fullName;
                    }

                    if (identity) {
                        identity.value = person.identityNumber;
                    }

                    if (profession) {
                        profession.value = person.profession;
                    }

                    const nameTrigger = isJordanian
                        ? row.querySelector('[data-airport-person-name-part]')
                        : row.querySelector('[data-airport-person-full-name-input]');
                    nameTrigger?.dispatchEvent(new Event('input', { bubbles: true }));
                    identity?.dispatchEvent(new Event('input', { bubbles: true }));
                }

                function showImportStatus(button, message, isSuccess) {
                    const status = button.closest('.section-form')?.querySelector('[data-airport-crew-import-status]');

                    if (!status) {
                        return;
                    }

                    status.textContent = message;
                    status.classList.toggle('text-success', isSuccess);
                    status.classList.toggle('text-muted', !isSuccess);
                }

                window.importApplicationCastCrewToAirportPeople = function (button) {
                    const sourceTable = document.getElementById('castCrewRequestTable') || document.getElementById('castCrewTable');
                    const destinationTable = document.getElementById('airportPeopleTable');

                    if (!sourceTable || !destinationTable) {
                        showImportStatus(button, button.dataset.emptyMessage || '', false);
                        return;
                    }

                    const people = Array.from(sourceTable.querySelectorAll('tbody tr'))
                        .map(castCrewPerson)
                        .filter(Boolean);

                    if (people.length === 0) {
                        showImportStatus(button, button.dataset.emptyMessage || '', false);
                        return;
                    }

                    const destinationBody = destinationTable.querySelector('tbody');
                    const existingKeys = new Set(Array.from(destinationBody.querySelectorAll('tr'))
                        .filter(function (row) { return !airportRowIsEmpty(row); })
                        .map(function (row) { return personKey(airportPerson(row)); }));
                    const emptyRows = Array.from(destinationBody.querySelectorAll('tr')).filter(airportRowIsEmpty);
                    let importedCount = 0;

                    people.forEach(function (person) {
                        const key = personKey(person);

                        if (existingKeys.has(key)) {
                            return;
                        }

                        let row = emptyRows.shift();

                        if (!row) {
                            window.addApplicationAnnexRow?.('airportPeopleTable', 'airport_people');
                            row = destinationBody.lastElementChild;
                        }

                        if (!row) {
                            return;
                        }

                        fillAirportRow(row, person);
                        existingKeys.add(key);
                        importedCount++;
                    });

                    if (importedCount === 0) {
                        showImportStatus(button, button.dataset.noNewMessage || '', false);
                        return;
                    }

                    const message = String(button.dataset.successMessage || '').replace(':count', importedCount);
                    showImportStatus(button, message, true);
                };
            })();
        </script>
    @endpush
@endonce

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="FilmingGovernmental" data-project-needs-form="governmental-scenes">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.annex_sections.governmental_scenes') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="section-form">
            <div class="d-flex justify-content-end py-3">
                <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('governmentalScenesRequestTable', 'governmental_scenes')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="governmentalScenesRequestTable">
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
                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#governmentalScenesRequestTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-check form-group">
                <input type="hidden" name="governmental_scenes_confirmed" value="0">
                <input type="checkbox" class="form-check-input" id="governmental_scenes_confirmed" name="governmental_scenes_confirmed" value="1" @checked(old('governmental_scenes_confirmed', data_get($annex, 'governmental_scenes_confirmed', false)))>
                <label class="form-label" for="governmental_scenes_confirmed">{{ __('app.applications.governmental_scenes_acknowledgement') }}</label>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer border-top">
        <div class="d-flex gap-3 p-3 justify-content-end">
            <button {!! $annexSaveButtonAttributes !!} class="btn btn-danger d-flex align-items-center gap-2"><i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.save') }}</button>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i class="ph ph-caret-double-left"></i>{{ __('app.close') }}</button>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                const validationMessage = @js(__('app.applications.requirement_validation_summary'));
                const requiredMinistryFields = [
                    'nationality_category', 'current_nationality', 'first_name', 'father_name',
                    'grandfather_name', 'family_name', 'gender', 'marital_status', 'passport_number',
                    'passport_type', 'passport_issue_place', 'passport_issue_date', 'passport_expiry_date',
                    'birth_place', 'birth_date', 'education_qualification', 'mother_full_name',
                    'mother_nationality', 'country_of_arrival', 'country_of_residence',
                    'jordan_governorate', 'jordan_residence_address', 'entry_method',
                    'departure_document', 'departure_method', 'confirmed'
                ];

                function controlHasValue(control) {
                    if (!control || control.disabled || control.type === 'hidden' || control.readOnly) {
                        return false;
                    }

                    if (control.type === 'checkbox' || control.type === 'radio') {
                        return control.checked;
                    }

                    if (control.type === 'file') {
                        return (control.files?.length || 0) > 0;
                    }

                    return String(control.value || '').trim() !== '';
                }

                function controlsHaveData(container) {
                    return Array.from(container?.querySelectorAll('input, select, textarea') || []).some(controlHasValue);
                }

                function setControlRequired(control, required) {
                    if (!control || control.disabled || control.type === 'hidden' || control.readOnly) {
                        return;
                    }

                    control.required = Boolean(required);

                    if (!required) {
                        control.classList.remove('is-invalid');
                        control.setCustomValidity?.('');
                    }
                }

                function namedRowControl(row, field) {
                    return row.querySelector('[name$="[' + field + ']"], [data-optional-required-field="' + field + '"]');
                }

                function rowHasStoredFile(row, prefix) {
                    return ['path', 'name'].some(function (suffix) {
                        const field = row.querySelector('[name$="[' + prefix + '_' + suffix + ']"]');
                        return String(field?.value || '').trim() !== '';
                    });
                }

                function syncMinistryPersonalDetails(drawer) {
                    drawer.querySelectorAll('[data-ministry-personal-details-row]').forEach(function (row) {
                        const started = controlsHaveData(row);

                        requiredMinistryFields.forEach(function (field) {
                            setControlRequired(namedRowControl(row, field), started);
                        });

                        const nonJordanian = namedRowControl(row, 'nationality_category')?.value !== 'jordanian';
                        ['residence_expiry_date', 'schengen_us_visa', 'previous_jordan_residence', 'investment_card', 'free_zones_card']
                            .forEach(function (field) {
                                setControlRequired(namedRowControl(row, field), started && nonJordanian);
                            });

                        const married = namedRowControl(row, 'marital_status')?.value === 'married';
                        ['spouse_nationality', 'spouse_full_name', 'spouse_birth_date', 'spouse_mother_full_name']
                            .forEach(function (field) {
                                setControlRequired(namedRowControl(row, field), started && married);
                            });

                        row.querySelectorAll('[data-ministry-attachment-row]:not([hidden])').forEach(function (attachment) {
                            const attachmentStarted = controlsHaveData(attachment) || attachment.dataset.stored === 'true';
                            setControlRequired(namedRowControl(attachment, 'document_type'), attachmentStarted);
                            setControlRequired(
                                namedRowControl(attachment, 'file'),
                                attachmentStarted && attachment.dataset.stored !== 'true'
                            );
                        });
                    });
                }

                function syncShippingEquipment(drawer) {
                    const pane = drawer.querySelector('#equipment-shipping-pane');
                    const acknowledgement = pane?.querySelector('[name="shipping_equipment_acknowledged"][type="checkbox"]');
                    const rows = Array.from(pane?.querySelectorAll('#importedEquipmentShipmentTable tbody tr') || []);
                    const sectionStarted = Boolean(acknowledgement?.checked) || rows.some(controlsHaveData);

                    rows.forEach(function (row, index) {
                        const rowStarted = controlsHaveData(row) || (sectionStarted && index === 0);

                        ['shipping_company_name', 'invoice_number', 'arrival_date', 'customs_center'].forEach(function (field) {
                            setControlRequired(namedRowControl(row, field), rowStarted);
                        });

                        const attachment = namedRowControl(row, 'attachment');
                        setControlRequired(attachment, rowStarted && !rowHasStoredFile(row, 'attachment'));
                    });

                    setControlRequired(acknowledgement, sectionStarted);
                }

                function syncTravelerEquipment(drawer) {
                    const pane = drawer.querySelector('#equipment-traveler-pane');
                    const acknowledgement = pane?.querySelector('[name="traveler_equipment_acknowledged"][type="checkbox"]');
                    const travelerRows = Array.from(pane?.querySelectorAll('#equipmentTravelersTable tbody tr') || []);
                    const equipmentRows = Array.from(pane?.querySelectorAll('#importedEquipmentTravelerTable tbody tr') || []);
                    const sectionStarted = Boolean(acknowledgement?.checked)
                        || travelerRows.some(controlsHaveData)
                        || equipmentRows.some(controlsHaveData);

                    travelerRows.forEach(function (row, index) {
                        const rowStarted = controlsHaveData(row) || (sectionStarted && index === 0);

                        ['traveler_name', 'arrival_date', 'arrival_flight_number', 'departure_date', 'departure_flight_number'].forEach(function (field) {
                            setControlRequired(namedRowControl(row, field), rowStarted);
                        });

                        const passportImage = namedRowControl(row, 'passport_image');
                        setControlRequired(passportImage, rowStarted && !rowHasStoredFile(row, 'passport_image'));
                    });

                    equipmentRows.forEach(function (row, index) {
                        const rowStarted = controlsHaveData(row) || (sectionStarted && index === 0);

                        ['item', 'serial_number', 'traveler_name', 'quantity', 'unit_value', 'classification', 'entry_point'].forEach(function (field) {
                            setControlRequired(namedRowControl(row, field), rowStarted);
                        });
                    });

                    setControlRequired(acknowledgement, sectionStarted);
                }

                function syncAirportFilming(drawer) {
                    const started = controlsHaveData(drawer);

                    ['airport_filming_airport_name', 'airport_filming_area', 'airport_filming_date', 'airport_filming_crew_count'].forEach(function (name) {
                        setControlRequired(drawer.querySelector('[name="' + name + '"]'), started);
                    });

                    drawer.querySelectorAll('#airportPeopleTable tbody tr').forEach(function (row) {
                        const nationality = row.querySelector('[data-airport-person-nationality]');
                        const jordanian = String(nationality?.value || '') === 'jordanian';

                        setControlRequired(nationality, started);
                        row.querySelectorAll('[data-airport-person-name-part]').forEach(function (control) {
                            setControlRequired(control, started && jordanian);
                        });
                        setControlRequired(row.querySelector('[data-airport-person-full-name-input]'), started && !jordanian);

                        ['mother_name', 'identity_number', 'profession', 'address_phone', 'entry_reason', 'target_area'].forEach(function (field) {
                            setControlRequired(namedRowControl(row, field), started);
                        });
                    });
                }

                function syncGovernmentalScenes(drawer) {
                    const started = controlsHaveData(drawer);

                    drawer.querySelectorAll('#governmentalScenesRequestTable tbody tr').forEach(function (row) {
                        ['site_name', 'authority', 'scene_description', 'filming_date'].forEach(function (field) {
                            setControlRequired(namedRowControl(row, field), started);
                        });
                    });

                    setControlRequired(drawer.querySelector('[name="governmental_scenes_confirmed"][type="checkbox"]'), started);
                }

                function syncProjectNeedsValidation(drawer) {
                    if (!drawer?.hasAttribute('data-project-needs-form')) {
                        return;
                    }

                    switch (drawer.dataset.projectNeedsForm) {
                        case 'ministry-personal-details':
                            syncMinistryPersonalDetails(drawer);
                            break;
                        case 'equipment':
                            syncShippingEquipment(drawer);
                            syncTravelerEquipment(drawer);
                            break;
                        case 'airport':
                            syncAirportFilming(drawer);
                            break;
                        case 'governmental-scenes':
                            syncGovernmentalScenes(drawer);
                            break;
                    }

                    window.syncApplicationRequiredFieldMarkers?.();
                }

                function drawerControls(drawer) {
                    return Array.from(drawer.querySelectorAll('input, select, textarea')).filter(function (control) {
                        return !control.disabled
                            && control.type !== 'hidden'
                            && !control.closest('.legacy-annex-inline');
                    });
                }

                function drawerValidationSummary(drawer) {
                    let summary = drawer.querySelector('[data-annex-validation-summary]');

                    if (summary) {
                        return summary;
                    }

                    summary = document.createElement('div');
                    summary.className = 'alert alert-danger text-start d-none';
                    summary.setAttribute('role', 'alert');
                    summary.setAttribute('data-annex-validation-summary', '');
                    summary.textContent = validationMessage;

                    const body = drawer.querySelector('.offcanvas-body');
                    body?.prepend(summary);

                    return summary;
                }

                function validateAnnexDrawer(drawer, reportFirstInvalid) {
                    syncProjectNeedsValidation(drawer);
                    const controls = drawerControls(drawer);
                    const invalidControls = controls.filter(function (control) {
                        const invalid = typeof control.checkValidity === 'function' && !control.checkValidity();
                        control.classList.toggle('is-invalid', invalid);

                        return invalid;
                    });
                    const summary = drawerValidationSummary(drawer);

                    summary.classList.toggle('d-none', invalidControls.length === 0);

                    if (invalidControls.length === 0) {
                        return true;
                    }

                    if (reportFirstInvalid) {
                        const firstInvalid = invalidControls[0];
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus({ preventScroll: true });
                        firstInvalid.reportValidity?.();
                    }

                    return false;
                }

                document.addEventListener('click', function (event) {
                    const saveButton = event.target.closest('[data-annex-save]');

                    if (!saveButton || saveButton.disabled) {
                        return;
                    }

                    const drawer = saveButton.closest('.application-annex-offcanvas');

                    if (!drawer || validateAnnexDrawer(drawer, true)) {
                        window.updateApplicationRequirementStatuses?.();
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                }, true);

                ['input', 'change'].forEach(function (eventName) {
                    document.addEventListener(eventName, function (event) {
                        const drawer = event.target.closest?.('.application-annex-offcanvas');

                        if (!drawer) {
                            return;
                        }

                        syncProjectNeedsValidation(drawer);

                        if (typeof event.target.checkValidity === 'function' && event.target.checkValidity()) {
                            event.target.classList.remove('is-invalid');
                        }

                        validateAnnexDrawer(drawer, false);
                        window.updateApplicationRequirementStatuses?.();
                    });
                });

                document.addEventListener('invalid', function (event) {
                    const drawer = event.target.closest?.('.application-annex-offcanvas');

                    if (drawer) {
                        drawerValidationSummary(drawer).classList.remove('d-none');
                    }
                }, true);

                document.addEventListener('shown.bs.offcanvas', function (event) {
                    syncProjectNeedsValidation(event.target);
                    validateAnnexDrawer(event.target, false);
                    window.updateApplicationRequirementStatuses?.();
                });

                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('[data-project-needs-form]').forEach(syncProjectNeedsValidation);
                    window.updateApplicationRequirementStatuses?.();
                });

                window.syncProjectNeedsAnnexValidation = syncProjectNeedsValidation;
            })();
        </script>
    @endpush
@endonce

@include('applications.partials.location-support-requirements-scripts')
@include('applications.partials.required-field-markers')
