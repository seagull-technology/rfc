@php
    $annexSaveButtonAttributes = ($submitAnnexForms ?? false)
        ? 'type="submit"'
        : 'type="button" data-bs-dismiss="offcanvas"';
    $annexSaveButtonAttributes .= ($annexSaveDisabled ?? false) ? ' disabled' : '';
    $safetyGuidelinesRequired = ($requireSafetyGuidelines ?? true) ? 'required' : '';
    $filledFilter = static fn ($row): bool => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty();
    $formLookupOptions = $formLookupOptions ?? [];
    $equipmentClassificationOptions = $equipmentClassificationOptions ?? collect(data_get($formLookupOptions, 'equipment_categories', []));
    $equipmentShippingMethodOptions = $equipmentShippingMethodOptions ?? collect(data_get($formLookupOptions, 'equipment_shipping_methods', []));
    $equipmentEntryPointOptions = $equipmentEntryPointOptions ?? collect(data_get($formLookupOptions, 'equipment_entry_points', []));
    $airportOptions = $airportOptions ?? collect(data_get($formLookupOptions, 'airports', []));
    $specialLocationRequirementOptions = $specialLocationRequirementOptions ?? collect(data_get($formLookupOptions, 'special_location_requirements', []));
    $locationRequirementOptions = $locationRequirementOptions ?? ($specialLocationRequirementOptions->pluck('code')->all() ?: ['road_closures', 'police_presence', 'armed_forces', 'regular_aerial_filming', 'drone_filming', 'special_effects', 'construction_work', 'animals', 'weapons', 'other']);
    $locationRequirementLabels = $locationRequirementLabels ?? $specialLocationRequirementOptions->mapWithKeys(fn ($option) => [$option->code => $option->displayName()])->all();
    $militaryBorderLocationTypeOptions = $militaryBorderLocationTypeOptions ?? collect(data_get($formLookupOptions, 'military_border_location_types', []));
    $militaryLocationTypeLabels = $militaryLocationTypeLabels ?? $militaryBorderLocationTypeOptions->mapWithKeys(fn ($option) => [$option->code => $option->displayName()])->all();
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
    $shippingEquipmentRows = collect($importedEquipmentRows)
        ->filter(fn ($row) => data_get($row, 'transport_group', 'shipping') !== 'traveler')
        ->values()
        ->whenEmpty(fn ($rows) => $rows->push(['transport_group' => 'shipping', 'item' => '', 'serial_number' => '', 'flight_reference' => '', 'quantity' => '', 'unit_value' => '', 'total_value' => '', 'classification' => '', 'shipping_method' => '', 'entry_point' => '']));
    $travelerEquipmentRows = collect($importedEquipmentRows)
        ->filter(fn ($row) => data_get($row, 'transport_group') === 'traveler')
        ->values()
        ->whenEmpty(fn ($rows) => $rows->push(['transport_group' => 'traveler', 'item' => '', 'serial_number' => '', 'traveler_name' => '', 'quantity' => '', 'unit_value' => '', 'total_value' => '', 'classification' => '', 'shipping_method' => '', 'entry_point' => '']));
    $castCrewNationalityOptions = collect($directorNationalityOptions ?? data_get($nationalityOptions ?? [], 'director', []));
    $airportPeopleNationalityOptions = $castCrewNationalityOptions;
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
            min-width: 1480px;
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
            min-width: 7rem;
            width: 7rem;
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
                    <span>{{ __('app.applications.work_summary_instruction') }}</span>
                </p>
                <textarea class="form-control" name="work_content_summary_synopsis" rows="15" required data-work-summary-input data-work-summary-min-words="500" data-work-summary-counter="#work_content_summary_word_count_drawer">{{ old('work_content_summary_synopsis', data_get($workContentSummary, 'synopsis')) }}</textarea>
                <div class="d-flex justify-content-between gap-3 flex-wrap small mt-2">
                    <span class="text-muted">{{ __('app.applications.work_summary_arabic_only_hint') }}</span>
                    <span id="work_content_summary_word_count_drawer" class="text-muted" aria-live="polite"></span>
                </div>
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
            <div class="d-flex justify-content-end py-3">
                <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('castCrewRequestTable', 'cast_crew')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
            </div>
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
	                            <th>{{ __('app.applications.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($castCrewRows as $index => $row)
                            @php
                                $castCrewNameParts = preg_split('/\s+/', trim((string) ($row['name'] ?? '')), 4) ?: [];
                                $castCrewFirstName = $row['first_name'] ?? ($castCrewNameParts[0] ?? '');
                                $castCrewSecondName = $row['second_name'] ?? ($castCrewNameParts[1] ?? '');
                                $castCrewThirdName = $row['third_name'] ?? ($castCrewNameParts[2] ?? '');
                                $castCrewFamilyName = $row['family_name'] ?? ($castCrewNameParts[3] ?? '');
                            @endphp
                            <tr>
                                <td class="row-number">{{ $loop->iteration }}</td>
                                <td>
                                    <select class="form-select" name="cast_crew[{{ $index }}][nationality]" data-cast-crew-nationality>
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @if (filled($row['nationality'] ?? null) && ! $castCrewNationalityOptions->contains('code', $row['nationality']))
                                            <option value="{{ $row['nationality'] }}" selected>{{ \App\Models\Nationality::labelFor($row['nationality']) }}</option>
                                        @endif
                                        @foreach ($castCrewNationalityOptions as $nationality)
                                            <option value="{{ $nationality->code }}" @selected(($row['nationality'] ?? '') === $nationality->code)>{{ $nationality->displayName() }}</option>
	                                        @endforeach
	                                    </select>
	                                </td>
                                <td class="cast-crew-name-cell">
                                    <input type="hidden" name="cast_crew[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" data-cast-crew-name-output>
                                    <div class="row g-2 cast-crew-jordanian-name d-none" data-cast-crew-jordanian-name>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][first_name]" value="{{ $castCrewFirstName }}" placeholder="{{ __('app.applications.annex_fields.first_name') }}" data-cast-crew-name-part></div>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][second_name]" value="{{ $castCrewSecondName }}" placeholder="{{ __('app.applications.annex_fields.second_name') }}" data-cast-crew-name-part></div>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][third_name]" value="{{ $castCrewThirdName }}" placeholder="{{ __('app.applications.annex_fields.third_name') }}" data-cast-crew-name-part></div>
                                        <div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[{{ $index }}][family_name]" value="{{ $castCrewFamilyName }}" placeholder="{{ __('app.applications.annex_fields.family_name') }}" data-cast-crew-name-part></div>
                                    </div>
                                    <input type="text" class="form-control" value="{{ $row['name'] ?? '' }}" data-cast-crew-full-name-input>
                                </td>
                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][role]" value="{{ $row['role'] ?? '' }}"></td>
	                                <td>
	                                    <select class="form-select" name="cast_crew[{{ $index }}][gender]">
	                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
	                                        @foreach (['male', 'female'] as $gender)
	                                            <option value="{{ $gender }}" @selected(($row['gender'] ?? '') === $gender)>{{ __('app.auth.gender_options.'.$gender) }}</option>
	                                        @endforeach
	                                    </select>
	                                </td>
	                                <td><input type="date" class="form-control" name="cast_crew[{{ $index }}][birth_date]" value="{{ $row['birth_date'] ?? '' }}" max="{{ $maxCrewBirthDate }}"></td>
	                                <td><input type="text" class="form-control" name="cast_crew[{{ $index }}][identity_number]" value="{{ $row['identity_number'] ?? '' }}"></td>
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
                <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('filmingLocationsRequestTable', 'filming_locations')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
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

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="EquipmentList">
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
                <h2 class="episode-playlist-title wp-heading-inline py-4 fontSize20"><span class="position-relative">{{ __('app.applications.flight_details_title') }}</span></h2>
                <div class="d-flex justify-content-end mb-2">
                    <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('equipmentFlightsTable', 'equipment_flights')"><i class="fa fa-plus me-2"></i>{{ __('app.add') }}</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle" id="equipmentFlightsTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>{{ __('app.applications.annex_fields.flight_type') }}</th>
                                <th>{{ __('app.applications.annex_fields.flight_number') }}</th>
                                <th>{{ __('app.applications.annex_fields.date') }}</th>
                                <th>{{ __('app.applications.annex_fields.time') }}</th>
                                <th>{{ __('app.applications.annex_fields.departure_city') }}</th>
                                <th>{{ __('app.applications.annex_fields.arrival_city') }}</th>
                                <th>{{ __('app.applications.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($equipmentFlightRows as $index => $row)
                                <tr>
                                    <td class="row-number">{{ $loop->iteration }}</td>
                                    <td>
                                        <select class="form-select" name="equipment_flights[{{ $index }}][flight_type]">
                                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                            @foreach ($flightTypeOptions as $option)
                                                <option value="{{ $option }}" @selected(($row['flight_type'] ?? '') === $option)>{{ __('app.applications.flight_types.'.$option) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control" name="equipment_flights[{{ $index }}][flight_number]" value="{{ $row['flight_number'] ?? '' }}"></td>
                                    <td><input type="date" class="form-control" name="equipment_flights[{{ $index }}][flight_date]" value="{{ $row['flight_date'] ?? '' }}"></td>
                                    <td><input type="time" class="form-control" name="equipment_flights[{{ $index }}][flight_time]" value="{{ $row['flight_time'] ?? '' }}"></td>
                                    <td><input type="text" class="form-control" name="equipment_flights[{{ $index }}][departure_city]" value="{{ $row['departure_city'] ?? '' }}"></td>
                                    <td><input type="text" class="form-control" name="equipment_flights[{{ $index }}][arrival_city]" value="{{ $row['arrival_city'] ?? '' }}"></td>
                                    <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#equipmentFlightsTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h2 class="episode-playlist-title wp-heading-inline py-4 fontSize20"><span class="position-relative">{{ __('app.applications.equipment_list_title') }}</span></h2>
                <div class="d-flex justify-content-end py-3">
                    <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('importedEquipmentShippingTable', 'imported_equipment_shipping')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle" id="importedEquipmentShippingTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>{{ __('app.applications.annex_fields.equipment_item') }}</th>
                                <th>{{ __('app.applications.annex_fields.serial_number') }}</th>
                                <th>{{ __('app.applications.annex_fields.flight') }}</th>
                                <th>{{ __('app.applications.annex_fields.quantity') }}</th>
                                <th>{{ __('app.applications.annex_fields.unit_value_usd') }}</th>
                                <th>{{ __('app.applications.annex_fields.total_value') }}</th>
                                <th>{{ __('app.applications.annex_fields.classification') }}</th>
                                <th>{{ __('app.applications.annex_fields.shipping_method') }}</th>
                                <th>{{ __('app.applications.annex_fields.entry_point') }}</th>
                                <th>{{ __('app.applications.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shippingEquipmentRows as $index => $row)
                                @php
                                    $rowKey = 'shipping_'.$index;
                                @endphp
                                <tr>
                                    <td class="row-number">{{ $loop->iteration }}</td>
                                    <td><input type="hidden" name="imported_equipment[{{ $rowKey }}][transport_group]" value="shipping"><input type="text" class="form-control" name="imported_equipment[{{ $rowKey }}][item]" value="{{ $row['item'] ?? '' }}"></td>
                                    <td><input type="text" class="form-control" name="imported_equipment[{{ $rowKey }}][serial_number]" value="{{ $row['serial_number'] ?? '' }}"></td>
                                    <td><input type="text" class="form-control" name="imported_equipment[{{ $rowKey }}][flight_reference]" value="{{ $row['flight_reference'] ?? '' }}"></td>
                                    <td><input type="number" min="0" class="form-control" name="imported_equipment[{{ $rowKey }}][quantity]" value="{{ $row['quantity'] ?? '' }}"></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[{{ $rowKey }}][unit_value]" value="{{ $row['unit_value'] ?? '' }}"></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[{{ $rowKey }}][total_value]" value="{{ $row['total_value'] ?? '' }}"></td>
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
                                            $selectedShippingMethod = (string) ($row['shipping_method'] ?? '');
                                        @endphp
                                        <select class="form-select" name="imported_equipment[{{ $rowKey }}][shipping_method]">
                                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                            @if (filled($selectedShippingMethod) && ! $equipmentShippingMethodOptions->contains('code', $selectedShippingMethod))
                                                <option value="{{ $selectedShippingMethod }}" selected>{{ $selectedShippingMethod }}</option>
                                            @endif
                                            @foreach ($equipmentShippingMethodOptions as $option)
                                                <option value="{{ $option->code }}" @selected($selectedShippingMethod === $option->code)>{{ $option->displayName() }}</option>
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
                                    <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#importedEquipmentShippingTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
	                    </table>
	                </div>
	                <div class="d-flex justify-content-end align-items-center gap-2 fw-600 mt-2">
	                    <span>{{ __('app.applications.equipment_total_label') }}</span>
	                    <span data-equipment-total="#importedEquipmentShippingTable">0</span>
	                    <span>{{ __('app.applications.usd') }}</span>
	                </div>
	            </div>
	            <div class="tab-pane fade border px-4 py-3" id="equipment-traveler-pane" role="tabpanel">
	                @foreach (trans('app.applications.traveler_customs_instructions') as $instruction)
	                    <p class="text-danger fontSize13 fw-600 mb-2"><i class="ph ph-info fa-xl me-2 lh-lg"></i><span>{{ $instruction }}</span></p>
	                @endforeach
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
                                <th>{{ __('app.applications.annex_fields.shipping_method') }}</th>
                                <th>{{ __('app.applications.annex_fields.entry_point') }}</th>
                                <th>{{ __('app.applications.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($travelerEquipmentRows as $index => $row)
                                @php
                                    $rowKey = 'traveler_'.$index;
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
                                    <td><input type="number" min="0" class="form-control" name="imported_equipment[{{ $rowKey }}][quantity]" value="{{ $row['quantity'] ?? '' }}"></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[{{ $rowKey }}][unit_value]" value="{{ $row['unit_value'] ?? '' }}"></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[{{ $rowKey }}][total_value]" value="{{ $row['total_value'] ?? '' }}"></td>
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
                                            $selectedShippingMethod = (string) ($row['shipping_method'] ?? '');
                                        @endphp
                                        <select class="form-select" name="imported_equipment[{{ $rowKey }}][shipping_method]">
                                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                            @if (filled($selectedShippingMethod) && ! $equipmentShippingMethodOptions->contains('code', $selectedShippingMethod))
                                                <option value="{{ $selectedShippingMethod }}" selected>{{ $selectedShippingMethod }}</option>
                                            @endif
                                            @foreach ($equipmentShippingMethodOptions as $option)
                                                <option value="{{ $option->code }}" @selected($selectedShippingMethod === $option->code)>{{ $option->displayName() }}</option>
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
                    <label class="form-label" for="traveler_equipment_acknowledged">{{ __('app.applications.traveler_equipment_acknowledgement') }}</label>
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

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="EquipmentMilitaryBorder">
    <div class="offcanvas-header">
        <h2 class="episode-playlist-title wp-heading-inline mb-0"><span class="position-relative">{{ __('app.applications.annex_sections.military_border_equipment') }}</span></h2>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="section-form">
            <h2 class="episode-playlist-title wp-heading-inline py-4 fontSize20"><span class="position-relative">{{ __('app.applications.military_border_locations_title') }}</span></h2>
            <div class="d-flex justify-content-end py-3">
                <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('militaryBorderLocationsTable', 'military_border_locations')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="militaryBorderLocationsTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>{{ __('app.scouting.governorate') }}</th>
                            <th>{{ __('app.applications.annex_fields.location_exact_name') }}</th>
                            <th>{{ __('app.applications.annex_fields.location_address') }}</th>
                            <th>{{ __('app.applications.annex_fields.location_nature') }}</th>
                            <th>{{ __('app.applications.annex_fields.location_type') }}</th>
                            <th>{{ __('app.scouting.start_date') }}</th>
                            <th>{{ __('app.scouting.end_date') }}</th>
                            <th>{{ __('app.applications.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($militaryBorderLocationRows as $index => $row)
                            @php
                                $selectedMilitaryGovernorate = (string) ($row['governorate'] ?? '');
                            @endphp
                            <tr>
                                <td class="row-number">{{ $loop->iteration }}</td>
                                <td>
                                    <select class="form-select" name="military_border_locations[{{ $index }}][governorate]">
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @if (filled($selectedMilitaryGovernorate) && ! $governorateOptions->contains('code', $selectedMilitaryGovernorate))
                                            <option value="{{ $selectedMilitaryGovernorate }}" selected>{{ \App\Models\Governorate::labelFor($selectedMilitaryGovernorate) }}</option>
                                        @endif
                                        @foreach ($governorateOptions as $governorate)
                                            <option value="{{ $governorate->code }}" @selected($selectedMilitaryGovernorate === $governorate->code)>{{ $governorate->displayName() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" class="form-control" name="military_border_locations[{{ $index }}][location_name]" value="{{ $row['location_name'] ?? '' }}"></td>
                                <td><input type="text" class="form-control" name="military_border_locations[{{ $index }}][address]" value="{{ $row['address'] ?? '' }}"></td>
                                <td><textarea class="form-control" name="military_border_locations[{{ $index }}][nature]" rows="2">{{ $row['nature'] ?? '' }}</textarea></td>
                                <td>
                                    <select class="form-select" name="military_border_locations[{{ $index }}][location_type]">
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @foreach ($militaryLocationTypeOptions as $option)
                                            <option value="{{ $option }}" @selected(($row['location_type'] ?? '') === $option)>{{ $militaryLocationTypeLabels[$option] ?? __('app.applications.military_location_types.'.$option) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="date" class="form-control" name="military_border_locations[{{ $index }}][start_date]" value="{{ $row['start_date'] ?? '' }}"></td>
                                <td><input type="date" class="form-control" name="military_border_locations[{{ $index }}][end_date]" value="{{ $row['end_date'] ?? '' }}"></td>
                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#militaryBorderLocationsTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h2 class="episode-playlist-title wp-heading-inline py-4 fontSize20"><span class="position-relative">{{ __('app.applications.equipment_list_title') }}</span></h2>
            <div class="d-flex justify-content-end py-3">
                <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('militaryBorderEquipmentRequestTable', 'military_border_equipment')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="militaryBorderEquipmentRequestTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>{{ __('app.applications.annex_fields.equipment_item') }}</th>
                            <th>{{ __('app.applications.annex_fields.serial_number') }}</th>
                            <th>{{ __('app.scouting.location_name') }}</th>
                            <th>{{ __('app.applications.annex_fields.quantity') }}</th>
                            <th>{{ __('app.applications.annex_fields.unit_value_usd') }}</th>
                            <th>{{ __('app.applications.annex_fields.total_value') }}</th>
                            <th>{{ __('app.applications.annex_fields.classification') }}</th>
                            <th>{{ __('app.applications.annex_fields.entry_method') }}</th>
                            <th>{{ __('app.applications.annex_fields.entry_point') }}</th>
                            <th>{{ __('app.applications.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($militaryBorderEquipmentRows as $index => $row)
                            <tr>
                                <td class="row-number">{{ $loop->iteration }}</td>
                                <td><input type="text" class="form-control" name="military_border_equipment[{{ $index }}][item]" value="{{ $row['item'] ?? ($row['equipment'] ?? '') }}"></td>
                                <td><input type="text" class="form-control" name="military_border_equipment[{{ $index }}][serial_number]" value="{{ $row['serial_number'] ?? '' }}"></td>
                                <td><input type="text" class="form-control" name="military_border_equipment[{{ $index }}][location_reference]" value="{{ $row['location_reference'] ?? ($row['location_name'] ?? '') }}"></td>
                                <td><input type="number" min="0" class="form-control" name="military_border_equipment[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? '' }}"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control" name="military_border_equipment[{{ $index }}][unit_value]" value="{{ $row['unit_value'] ?? '' }}"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control" name="military_border_equipment[{{ $index }}][total_value]" value="{{ $row['total_value'] ?? '' }}"></td>
                                <td>
                                    @php
                                        $selectedClassification = (string) ($row['classification'] ?? '');
                                    @endphp
                                    <select class="form-select" name="military_border_equipment[{{ $index }}][classification]">
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @if (filled($selectedClassification) && ! $equipmentClassificationOptions->contains('code', $selectedClassification))
                                            <option value="{{ $selectedClassification }}" selected>{{ $selectedClassification }}</option>
                                        @endif
                                        @foreach ($equipmentClassificationOptions as $option)
                                            <option value="{{ $option->code }}" @selected($selectedClassification === $option->code)>{{ $option->displayName() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" class="form-control" name="military_border_equipment[{{ $index }}][entry_method]" value="{{ $row['entry_method'] ?? '' }}"></td>
                                <td>
                                    @php
                                        $selectedEntryPoint = (string) ($row['entry_point'] ?? '');
                                    @endphp
                                    <select class="form-select" name="military_border_equipment[{{ $index }}][entry_point]">
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @if (filled($selectedEntryPoint) && ! $equipmentEntryPointOptions->contains('code', $selectedEntryPoint))
                                            <option value="{{ $selectedEntryPoint }}" selected>{{ $selectedEntryPoint }}</option>
                                        @endif
                                        @foreach ($equipmentEntryPointOptions as $option)
                                            <option value="{{ $option->code }}" @selected($selectedEntryPoint === $option->code)>{{ $option->displayName() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#militaryBorderEquipmentRequestTable')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-check form-group">
                <input type="hidden" name="military_border_equipment_acknowledged" value="0">
                <input type="checkbox" class="form-check-input" id="military_border_equipment_acknowledged" name="military_border_equipment_acknowledged" value="1" @checked(old('military_border_equipment_acknowledged', data_get($annex, 'military_border_equipment_acknowledged', false)))>
                <label class="form-label" for="military_border_equipment_acknowledged">{{ __('app.applications.military_border_equipment_acknowledgement') }}</label>
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

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="FilmingAirports">
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
            <div class="d-flex justify-content-end py-3">
                <button type="button" class="btn btn-success" onclick="addApplicationAnnexRow('airportPeopleTable', 'airport_people')"><i class="fa-solid fa-plus me-2"></i>{{ __('app.add') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="airportPeopleTable">
                    <thead class="table-light">
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
                            <th>{{ __('app.applications.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($airportPeopleRows as $index => $row)
                            <tr>
                                <td class="row-number">{{ $loop->iteration }}</td>
                                <td><input type="text" class="form-control" name="airport_people[{{ $index }}][full_name]" value="{{ $row['full_name'] ?? '' }}"></td>
                                <td>
                                    <select class="form-select" name="airport_people[{{ $index }}][nationality]">
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @if (filled($row['nationality'] ?? null) && ! $airportPeopleNationalityOptions->contains('code', $row['nationality']))
                                            <option value="{{ $row['nationality'] }}" selected>{{ \App\Models\Nationality::labelFor($row['nationality']) }}</option>
                                        @endif
                                        @foreach ($airportPeopleNationalityOptions as $nationality)
                                            <option value="{{ $nationality->code }}" @selected(($row['nationality'] ?? '') === $nationality->code)>{{ $nationality->displayName() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" class="form-control" name="airport_people[{{ $index }}][mother_name]" value="{{ $row['mother_name'] ?? '' }}"></td>
                                <td><input type="text" class="form-control" name="airport_people[{{ $index }}][identity_number]" value="{{ $row['identity_number'] ?? '' }}"></td>
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

<div class="offcanvas offcanvas-end application-annex-offcanvas" tabindex="-1" id="FilmingGovernmental">
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
