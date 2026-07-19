@php
    $locationTableId = $locationTableId ?? 'filmingLocationsRequestTable';
    $locationSupportRequirementRows = collect($locationSupportRequirementRows ?? [\App\Support\LocationSupportRequirements::emptyRequirement()])->values();
    $filmingLocationRows = collect($filmingLocationRows ?? [])->values();
@endphp

<section class="location-support-editor mt-4" data-location-support-editor data-location-table="{{ $locationTableId }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <h5 class="mb-0">{{ __('app.applications.location_support_requirements_title') }}</h5>
        <button type="button" class="btn btn-success" data-location-support-add>
            <i class="fa-solid fa-plus me-2"></i>{{ __('app.applications.location_support_add_requirement') }}
        </button>
    </div>

    <div class="d-grid gap-3" data-location-support-rows>
        @foreach ($locationSupportRequirementRows as $requirementIndex => $requirement)
            @php
                $requirement = array_merge(\App\Support\LocationSupportRequirements::emptyRequirement(), (array) $requirement);
                $selectedAuthority = (string) ($requirement['authority'] ?? '');
                $selectedRequirement = (string) ($requirement['requirement'] ?? '');
                $scheduleMode = (string) ($requirement['schedule_mode'] ?? \App\Support\LocationSupportRequirements::SCHEDULE_SHARED);
                $assignments = collect((array) ($requirement['assignments'] ?? []))->keyBy('location_key');
                $selectedRequirementLabel = filled($selectedRequirement)
                    ? ($locationRequirementLabels[$selectedRequirement] ?? \App\Models\FormLookupOption::labelFor(\App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $selectedRequirement))
                    : '';
                $selectedAuthorityLabel = $supportAuthorityOptions[$selectedAuthority]
                    ?? (app()->getLocale() === 'ar' ? ($requirement['authority_name_ar'] ?? null) : ($requirement['authority_name_en'] ?? null))
                    ?? $selectedAuthority;
                $notesPrompt = $locationRequirementPrompts[$selectedRequirement] ?? null;
                $notesPrompt = filled($notesPrompt)
                    ? $notesPrompt
                    : (filled($selectedRequirementLabel)
                        ? __('app.applications.location_support_notes_prompt', ['requirement' => $selectedRequirementLabel])
                        : '');
            @endphp
            <article class="location-support-editor__row" data-location-support-row>
                <input type="hidden" name="location_support_requirements[{{ $requirementIndex }}][requirement_key]" value="{{ $requirement['requirement_key'] }}" data-requirement-key>

                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <span class="badge bg-light text-dark border" data-location-support-number>#{{ $requirementIndex + 1 }}</span>
                    <button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" data-location-support-remove aria-label="{{ __('app.delete') }}">
                        <i class="ph-fill ph ph-trash-simple fs-6"></i>
                    </button>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('app.applications.annex_fields.authority_name') }}</label>
                        <select class="form-select select2-basic-single" name="location_support_requirements[{{ $requirementIndex }}][authority]" data-location-support-authority>
                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                            @if (filled($selectedAuthority) && ! array_key_exists($selectedAuthority, $supportAuthorityOptions))
                                <option value="{{ $selectedAuthority }}" selected>{{ $selectedAuthorityLabel }}</option>
                            @endif
                            @foreach ($supportAuthorityOptions as $authorityCode => $authorityLabel)
                                <option value="{{ $authorityCode }}" @selected($selectedAuthority === $authorityCode)>{{ $authorityLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('app.applications.annex_fields.requirement') }}</label>
                        <select class="form-select select2-basic-single" name="location_support_requirements[{{ $requirementIndex }}][requirement]" data-location-support-requirement>
                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                            @if (filled($selectedRequirement) && ! in_array($selectedRequirement, $locationRequirementOptions, true))
                                <option value="{{ $selectedRequirement }}" selected>{{ $selectedRequirementLabel }}</option>
                            @endif
                            @foreach ($locationRequirementOptions as $option)
                                @if ($selectedAuthority === '' || in_array($selectedAuthority, $locationRequirementAuthorityCodes[$option] ?? [], true) || $selectedRequirement === $option)
                                    <option value="{{ $option }}" data-authority-codes="{{ implode(',', $locationRequirementAuthorityCodes[$option] ?? []) }}" data-notes-prompt="{{ $locationRequirementPrompts[$option] ?? '' }}" @selected($selectedRequirement === $option)>{{ $locationRequirementLabels[$option] ?? __('app.applications.special_location_requirements.'.$option) }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label d-block">{{ __('app.applications.location_support_schedule_mode') }}</label>
                        <div class="btn-group flex-wrap" role="group">
                            <input type="radio" class="btn-check" name="location_support_requirements[{{ $requirementIndex }}][schedule_mode]" id="location_support_mode_shared_{{ $locationTableId }}_{{ $requirementIndex }}" value="shared" @checked($scheduleMode !== 'per_location') data-location-support-mode>
                            <label class="btn btn-outline-primary" for="location_support_mode_shared_{{ $locationTableId }}_{{ $requirementIndex }}">{{ __('app.applications.location_support_schedule_shared') }}</label>
                            <input type="radio" class="btn-check" name="location_support_requirements[{{ $requirementIndex }}][schedule_mode]" id="location_support_mode_per_location_{{ $locationTableId }}_{{ $requirementIndex }}" value="per_location" @checked($scheduleMode === 'per_location') data-location-support-mode>
                            <label class="btn btn-outline-primary" for="location_support_mode_per_location_{{ $locationTableId }}_{{ $requirementIndex }}">{{ __('app.applications.location_support_schedule_per_location') }}</label>
                        </div>
                    </div>

                    <div class="col-12" data-location-support-shared-schedule>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('app.applications.annex_fields.date') }}</label>
                                <input type="date" class="form-control" name="location_support_requirements[{{ $requirementIndex }}][shared_date]" value="{{ $requirement['shared_date'] }}" data-location-support-shared-date>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('app.applications.annex_fields.time_from') }}</label>
                                <input type="time" class="form-control" name="location_support_requirements[{{ $requirementIndex }}][shared_time_from]" value="{{ $requirement['shared_time_from'] }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('app.applications.annex_fields.time_to') }}</label>
                                <input type="time" class="form-control" name="location_support_requirements[{{ $requirementIndex }}][shared_time_to]" value="{{ $requirement['shared_time_to'] }}">
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">{{ __('app.applications.location_support_locations') }}</label>
                        <div class="d-grid gap-2" data-location-support-assignments>
                            @forelse ($filmingLocationRows as $locationIndex => $location)
                                @php
                                    $locationKey = (string) ($location['location_key'] ?? 'location_'.($locationIndex + 1));
                                    $assignment = (array) $assignments->get($locationKey, []);
                                    $isSelected = (bool) ($assignment['selected'] ?? false);
                                    $locationLabel = trim((string) ($location['location_name'] ?? '')) ?: __('app.applications.location_support_unnamed_location', ['number' => $locationIndex + 1]);
                                @endphp
                                <div class="location-support-editor__assignment" data-location-support-assignment data-location-key="{{ $locationKey }}">
                                    <input type="hidden" name="location_support_requirements[{{ $requirementIndex }}][assignments][{{ $locationIndex }}][location_key]" value="{{ $locationKey }}" data-assignment-location-key>
                                    <input type="hidden" name="location_support_requirements[{{ $requirementIndex }}][assignments][{{ $locationIndex }}][selected]" value="0">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="location_support_requirements[{{ $requirementIndex }}][assignments][{{ $locationIndex }}][selected]" value="1" id="location_support_assignment_{{ $locationTableId }}_{{ $requirementIndex }}_{{ $locationIndex }}" @checked($isSelected) data-location-support-selected>
                                        <label class="form-check-label fw-semibold" for="location_support_assignment_{{ $locationTableId }}_{{ $requirementIndex }}_{{ $locationIndex }}" data-assignment-label>{{ $locationLabel }}</label>
                                    </div>
                                    <div class="small text-muted mb-2" data-assignment-range>{{ __('app.applications.location_support_location_range', ['start' => ($location['start_date'] ?? null) ?: '-', 'end' => ($location['end_date'] ?? null) ?: '-']) }}</div>
                                    <div class="row g-2" data-location-support-location-schedule>
                                        <div class="col-md-4"><input type="date" class="form-control" name="location_support_requirements[{{ $requirementIndex }}][assignments][{{ $locationIndex }}][date]" value="{{ $assignment['date'] ?? '' }}" @if (filled($location['start_date'] ?? null)) min="{{ $location['start_date'] }}" @endif @if (filled($location['end_date'] ?? null)) max="{{ $location['end_date'] }}" @endif data-assignment-date></div>
                                        <div class="col-md-4"><input type="time" class="form-control" name="location_support_requirements[{{ $requirementIndex }}][assignments][{{ $locationIndex }}][time_from]" value="{{ $assignment['time_from'] ?? '' }}"></div>
                                        <div class="col-md-4"><input type="time" class="form-control" name="location_support_requirements[{{ $requirementIndex }}][assignments][{{ $locationIndex }}][time_to]" value="{{ $assignment['time_to'] ?? '' }}"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="alert alert-light border mb-0" data-location-support-empty>{{ __('app.applications.location_support_no_locations') }}</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">
                            {{ __('app.applications.annex_fields.notes') }}
                            <span class="text-danger @if (blank($selectedRequirement)) d-none @endif" data-location-support-notes-required>*</span>
                        </label>
                        <textarea class="form-control" name="location_support_requirements[{{ $requirementIndex }}][notes]" rows="3" data-location-support-notes @if (filled($selectedRequirement)) required @endif placeholder="{{ $notesPrompt }}">{{ $requirement['notes'] }}</textarea>
                        <div class="form-text text-danger fw-semibold @if (blank($notesPrompt)) d-none @endif" data-location-support-notes-help>{{ $notesPrompt }}</div>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>
