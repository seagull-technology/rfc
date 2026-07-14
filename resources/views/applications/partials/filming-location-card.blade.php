@php
    $row = (array) ($row ?? []);
    $index = $index ?? 0;
    $tableId = $tableId ?? 'filmingLocationsRequestTable';
    $selectedGovernorate = (string) ($row['governorate'] ?? '');
    $selectedLocationType = (string) ($row['location_type'] ?? '');
    $locationStartDate = (string) ($row['start_date'] ?? '');
    $locationEndDate = (string) ($row['end_date'] ?? '');
    $minimumFilmingLocationStartDate = $minimumFilmingLocationStartDate ?? now()->toDateString();
    $rowLocationTypeOptions = $locationTypeOptionsForGovernorate($selectedGovernorate);
    $selectedSpecialLocationRequirements = $locationRequirementSelectionForRow($row);
    $emptyLocationSupportRequirement = $emptyLocationSupportRequirement ?? ['authority' => '', 'requirement' => '', 'date' => '', 'time_from' => '', 'time_to' => '', 'notes' => ''];
    $supportRequirements = collect((array) data_get($row, 'support_requirements', []))
        ->filter(fn ($requirement): bool => collect((array) $requirement)->filter(fn ($value): bool => filled($value))->isNotEmpty())
        ->map(fn ($requirement): array => array_merge($emptyLocationSupportRequirement, (array) $requirement))
        ->values();

    if ($supportRequirements->isEmpty() && isset($locationSupportRequirementsForRow)) {
        $supportRequirements = collect($locationSupportRequirementsForRow($row, (int) $index))
            ->filter(fn ($requirement): bool => collect((array) $requirement)->filter(fn ($value): bool => filled($value))->isNotEmpty())
            ->map(fn ($requirement): array => array_merge($emptyLocationSupportRequirement, (array) $requirement))
            ->values();
    }

    if ($supportRequirements->isEmpty() && isset($locationSupportRequirementForRow)) {
        $legacySupportRequirement = $locationSupportRequirementForRow($row, (int) $index);
        if (collect((array) $legacySupportRequirement)->filter(fn ($value): bool => filled($value))->isNotEmpty()) {
            $supportRequirements = collect([array_merge($emptyLocationSupportRequirement, (array) $legacySupportRequirement)]);
        }
    }

    if ($supportRequirements->isEmpty()) {
        $supportRequirements = collect([$emptyLocationSupportRequirement]);
    }
@endphp

<tr>
    <td>
        <div class="application-location-card">
            <div class="application-location-card__header">
                <h5 class="mb-0">{{ __('app.applications.location_number', ['number' => '']) }}<span class="row-number">{{ $rowNumber ?? $loop->iteration ?? ((int) $index + 1) }}</span></h5>
                <button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#{{ $tableId }}')" aria-label="{{ __('app.delete') }}">
                    <i class="ph-fill ph ph-trash-simple fs-6"></i>
                </button>
            </div>

            <div class="application-location-card__section">
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">{{ __('app.scouting.governorate') }}</label>
                        <select class="form-select" name="filming_locations[{{ $index }}][governorate]" data-location-governorate>
                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                            @if (filled($selectedGovernorate) && ! $governorateOptions->contains('code', $selectedGovernorate))
                                <option value="{{ $selectedGovernorate }}" selected>{{ \App\Models\Governorate::labelFor($selectedGovernorate) }}</option>
                            @endif
                            @foreach ($governorateOptions as $governorate)
                                <option value="{{ $governorate->code }}" @selected($selectedGovernorate === $governorate->code)>{{ $governorate->displayName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">{{ __('app.applications.annex_fields.location_type') }}</label>
                        <select class="form-select" name="filming_locations[{{ $index }}][location_type]" data-location-type-select data-selected-type="{{ $selectedLocationType }}">
                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                            @if (filled($selectedLocationType) && ! $rowLocationTypeOptions->contains('code', $selectedLocationType))
                                <option value="{{ $selectedLocationType }}" selected>{{ \App\Models\FilmingLocationType::labelFor($selectedLocationType) }}</option>
                            @endif
                            @foreach ($rowLocationTypeOptions as $locationType)
                                <option value="{{ $locationType->code }}" @selected($selectedLocationType === $locationType->code)>{{ $locationType->displayName() }}</option>
                            @endforeach
                        </select>
                        <div class="form-text text-warning fw-semibold d-none" data-location-type-approval-note></div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">{{ __('app.applications.annex_fields.location_exact_name') }}</label>
                        <input type="text" class="form-control" name="filming_locations[{{ $index }}][location_name]" value="{{ $row['location_name'] ?? '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('app.applications.annex_fields.location_address') }}</label>
                        <input type="text" class="form-control" name="filming_locations[{{ $index }}][address]" value="{{ $row['address'] ?? '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('app.applications.annex_fields.location_nature') }}</label>
                        <textarea class="form-control" name="filming_locations[{{ $index }}][nature]" rows="2">{{ $row['nature'] ?? '' }}</textarea>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">{{ __('app.scouting.start_date') }}</label>
                        <input type="date" class="form-control" name="filming_locations[{{ $index }}][start_date]" value="{{ $locationStartDate }}" min="{{ $minimumFilmingLocationStartDate }}" data-location-start-date>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">{{ __('app.scouting.end_date') }}</label>
                        <input type="date" class="form-control" name="filming_locations[{{ $index }}][end_date]" value="{{ $locationEndDate }}" @if (filled($locationStartDate)) min="{{ $locationStartDate }}" @endif data-location-end-date>
                    </div>
                </div>
                @foreach ($selectedSpecialLocationRequirements as $preservedSpecialRequirement)
                    <input type="hidden" name="filming_locations[{{ $index }}][special_requirements][]" value="{{ $preservedSpecialRequirement }}">
                @endforeach
            </div>

            <div class="application-location-card__section application-location-card__section--requirements">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h6 class="mb-0">{{ __('app.applications.location_support_requirements_title') }}</h6>
                    <button type="button" class="btn btn-sm btn-success" onclick="addFilmingLocationSupportRequirement(this)">
                        <i class="fa-solid fa-plus me-1"></i>{{ __('app.applications.location_support_add_requirement') }}
                    </button>
                </div>
                <div class="d-grid gap-3" data-location-support-requirements>
                    @foreach ($supportRequirements as $supportIndex => $supportRequirement)
                        @php
                            $selectedSupportAuthority = (string) data_get($supportRequirement, 'authority', '');
                            $selectedSupportRequirement = (string) data_get($supportRequirement, 'requirement', '');
                            $selectedSupportRequirementLabel = filled($selectedSupportRequirement)
                                ? ($locationRequirementLabels[$selectedSupportRequirement] ?? \App\Models\FormLookupOption::labelFor(\App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $selectedSupportRequirement))
                                : '';
                            $supportRequirementNotesPrompt = filled($selectedSupportRequirementLabel)
                                ? __('app.applications.location_support_notes_prompt', ['requirement' => $selectedSupportRequirementLabel])
                                : '';
                        @endphp
                        <div class="application-location-support-row" data-location-support-requirement-row>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                <span class="badge bg-light text-dark border">#{{ $supportIndex + 1 }}</span>
                                <button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeFilmingLocationSupportRequirement(this)" aria-label="{{ __('app.delete') }}">
                                    <i class="ph-fill ph ph-trash-simple fs-6"></i>
                                </button>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 col-xl-2">
                                    <label class="form-label">{{ __('app.applications.annex_fields.authority_name') }}</label>
                                    <select class="form-select select2-basic-single" name="filming_locations[{{ $index }}][support_requirements][{{ $supportIndex }}][authority]">
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @foreach ($supportAuthorityOptions as $authorityCode => $authorityLabel)
                                            <option value="{{ $authorityCode }}" @selected($selectedSupportAuthority === $authorityCode)>{{ $authorityLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 col-xl-3">
                                    <label class="form-label">{{ __('app.applications.annex_fields.requirement') }}</label>
                                    <select class="form-select select2-basic-single" name="filming_locations[{{ $index }}][support_requirements][{{ $supportIndex }}][requirement]" data-location-support-requirement-select>
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @if (filled($selectedSupportRequirement) && ! in_array($selectedSupportRequirement, $locationRequirementOptions, true))
                                            <option value="{{ $selectedSupportRequirement }}" selected>{{ $selectedSupportRequirement }}</option>
                                        @endif
                                        @foreach ($locationRequirementOptions as $option)
                                            <option value="{{ $option }}" @selected($selectedSupportRequirement === $option)>{{ $locationRequirementLabels[$option] ?? __('app.applications.special_location_requirements.'.$option) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 col-xl-2">
                                    <label class="form-label">{{ __('app.applications.annex_fields.date') }}</label>
                                    <input type="date" class="form-control" name="filming_locations[{{ $index }}][support_requirements][{{ $supportIndex }}][date]" value="{{ data_get($supportRequirement, 'date') }}" @if (filled($locationStartDate)) min="{{ $locationStartDate }}" @endif @if (filled($locationEndDate)) max="{{ $locationEndDate }}" @endif data-location-support-date>
                                </div>
                                <div class="col-md-6 col-xl-2">
                                    <label class="form-label">{{ __('app.applications.annex_fields.time_from') }}</label>
                                    <input type="time" class="form-control" name="filming_locations[{{ $index }}][support_requirements][{{ $supportIndex }}][time_from]" value="{{ data_get($supportRequirement, 'time_from') }}">
                                </div>
                                <div class="col-md-6 col-xl-2">
                                    <label class="form-label">{{ __('app.applications.annex_fields.time_to') }}</label>
                                    <input type="time" class="form-control" name="filming_locations[{{ $index }}][support_requirements][{{ $supportIndex }}][time_to]" value="{{ data_get($supportRequirement, 'time_to') }}">
                                </div>
                                <div class="col-md-6 col-xl-12">
                                    <label class="form-label">
                                        {{ __('app.applications.annex_fields.notes') }}
                                        <span class="text-danger @if (blank($selectedSupportRequirement)) d-none @endif" data-location-support-notes-required-marker>*</span>
                                    </label>
                                    <textarea class="form-control" name="filming_locations[{{ $index }}][support_requirements][{{ $supportIndex }}][notes]" rows="2" data-location-support-notes @if (filled($selectedSupportRequirement)) required @endif placeholder="{{ $supportRequirementNotesPrompt }}">{{ data_get($supportRequirement, 'notes') }}</textarea>
                                    <div class="form-text text-danger fw-semibold @if (blank($supportRequirementNotesPrompt)) d-none @endif" data-location-support-notes-help>{{ $supportRequirementNotesPrompt }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </td>
</tr>
