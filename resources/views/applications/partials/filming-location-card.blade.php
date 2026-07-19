@php
    $row = (array) ($row ?? []);
    $index = $index ?? 0;
    $tableId = $tableId ?? 'filmingLocationsRequestTable';
    $selectedGovernorate = (string) ($row['governorate'] ?? '');
    $selectedLocationType = (string) ($row['location_type'] ?? '');
    $locationKey = (string) ($row['location_key'] ?? 'location_'.((int) $index + 1));
    $locationStartDate = (string) ($row['start_date'] ?? '');
    $locationEndDate = (string) ($row['end_date'] ?? '');
    $minimumFilmingLocationStartDate = $minimumFilmingLocationStartDate ?? now()->toDateString();
    $rowLocationTypeOptions = $locationTypeOptionsForGovernorate($selectedGovernorate);
    $selectedSpecialLocationRequirements = $locationRequirementSelectionForRow($row);
@endphp

<tr>
    <td>
        <div class="application-location-card" data-filming-location-card>
            <input type="hidden" name="filming_locations[{{ $index }}][location_key]" value="{{ $locationKey }}" data-location-key>
            <div class="application-location-card__header">
                <h5 class="mb-0">{{ __('app.applications.location_number', ['number' => '']) }}<span class="row-number">{{ $rowNumber ?? $loop->iteration ?? ((int) $index + 1) }}</span></h5>
                <button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, '#{{ $tableId }}')" aria-label="{{ __('app.delete') }}">
                    <i class="ph-fill ph ph-trash-simple fs-6"></i>
                </button>
            </div>

            <div class="application-location-card__section">
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">{{ __('app.scouting.governorate') }} <span class="text-danger">*</span></label>
                        <select class="form-select" name="filming_locations[{{ $index }}][governorate]" data-location-governorate required>
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
                        <label class="form-label">{{ __('app.applications.annex_fields.location_type') }} <span class="text-danger">*</span></label>
                        <select class="form-select" name="filming_locations[{{ $index }}][location_type]" data-location-type-select data-selected-type="{{ $selectedLocationType }}" required>
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
                        <label class="form-label">{{ __('app.applications.annex_fields.location_exact_name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="filming_locations[{{ $index }}][location_name]" value="{{ $row['location_name'] ?? '' }}" data-location-name required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('app.applications.annex_fields.location_address') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="filming_locations[{{ $index }}][address]" value="{{ $row['address'] ?? '' }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('app.applications.annex_fields.location_nature') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="filming_locations[{{ $index }}][nature]" rows="2" required>{{ $row['nature'] ?? '' }}</textarea>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">{{ __('app.scouting.start_date') }} <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="filming_locations[{{ $index }}][start_date]" value="{{ $locationStartDate }}" min="{{ $minimumFilmingLocationStartDate }}" data-location-start-date required>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">{{ __('app.scouting.end_date') }} <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="filming_locations[{{ $index }}][end_date]" value="{{ $locationEndDate }}" @if (filled($locationStartDate)) min="{{ $locationStartDate }}" @endif data-location-end-date required>
                    </div>
                </div>
                @foreach ($selectedSpecialLocationRequirements as $preservedSpecialRequirement)
                    <input type="hidden" name="filming_locations[{{ $index }}][special_requirements][]" value="{{ $preservedSpecialRequirement }}">
                @endforeach
            </div>

        </div>
    </td>
</tr>
