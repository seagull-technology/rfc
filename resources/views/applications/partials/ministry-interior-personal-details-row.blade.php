@php
    $row = (array) ($row ?? []);
    $rowIndex = $rowIndex ?? 0;
    $inputIndex = $inputIndex ?? $rowIndex;
    $readOnly = (bool) ($ministryInteriorPersonalDetailsReadOnly ?? false);
    $inputPrefix = 'ministry_interior_personal_details['.$inputIndex.']';
    $idToken = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $inputIndex);
    $idPrefix = ($ministryInteriorPersonalDetailsIdPrefix ?? 'ministry_interior_personal_details').'_'.$idToken;
    $detailValue = static fn (string $key, mixed $default = null): mixed => data_get($row, $key, $default);
    $nationalityLabel = static fn ($value): string => filled($value)
        ? \App\Models\Nationality::labelFor((string) $value)
        : '';
    $genderLabel = static fn ($value): string => filled($value)
        ? __('app.auth.gender_options.'.(string) $value)
        : '';
    $confirmed = \App\Support\MinistryInteriorPersonalDetails::isConfirmed($row);
@endphp

<article class="ministry-personal-details-form__record" data-ministry-personal-details-row>
    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
        <h4 class="mb-0">
            {{ __('app.applications.ministry_interior_personal_details.person_record') }}
            <span data-ministry-personal-details-number>{{ is_numeric($rowIndex) ? ((int) $rowIndex + 1) : 1 }}</span>
        </h4>
        @unless ($readOnly)
            <button type="button" class="btn btn-sm btn-outline-danger" data-ministry-personal-details-remove title="{{ __('app.applications.ministry_interior_personal_details.remove_person') }}">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                <span class="visually-hidden">{{ __('app.applications.ministry_interior_personal_details.remove_person') }}</span>
            </button>
        @endunless
    </div>

    <div class="ministry-personal-details-form__section mb-3">
        <h5 class="mb-3">{{ __('app.applications.ministry_interior_personal_details.sections.identity') }}</h5>
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.personal_number') }}</label>
                <input type="text" class="form-control" name="{{ $inputPrefix }}[personal_number]" value="{{ $detailValue('personal_number') }}" @disabled($readOnly)>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.current_nationality') }} @unless($readOnly)<span class="text-danger">*</span>@endunless</label>
                @if ($readOnly)
                    <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue('current_nationality')) }}" disabled>
                @else
                    <select class="form-select" name="{{ $inputPrefix }}[current_nationality]">
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach ($ministryNationalityOptions as $nationality)
                            <option value="{{ $nationality->code }}" @selected((string) $detailValue('current_nationality') === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.gender') }} @unless($readOnly)<span class="text-danger">*</span>@endunless</label>
                @if ($readOnly)
                    <input type="text" class="form-control" value="{{ $genderLabel($detailValue('gender')) }}" disabled>
                @else
                    <select class="form-select" name="{{ $inputPrefix }}[gender]">
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach (['male', 'female'] as $gender)
                            <option value="{{ $gender }}" @selected((string) $detailValue('gender') === $gender)>{{ __('app.auth.gender_options.'.$gender) }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.current_full_name') }} @unless($readOnly)<span class="text-danger">*</span>@endunless</label>
                <input type="text" class="form-control" name="{{ $inputPrefix }}[current_full_name]" value="{{ $detailValue('current_full_name') }}" @disabled($readOnly)>
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.original_nationality') }} @unless($readOnly)<span class="text-danger">*</span>@endunless</label>
                @if ($readOnly)
                    <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue('original_nationality')) }}" disabled>
                @else
                    <select class="form-select" name="{{ $inputPrefix }}[original_nationality]">
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach ($ministryNationalityOptions as $nationality)
                            <option value="{{ $nationality->code }}" @selected((string) $detailValue('original_nationality') === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.original_full_name') }} @unless($readOnly)<span class="text-danger">*</span>@endunless</label>
                <input type="text" class="form-control" name="{{ $inputPrefix }}[original_full_name]" value="{{ $detailValue('original_full_name') }}" @disabled($readOnly)>
            </div>
        </div>
    </div>

    <div class="ministry-personal-details-form__section mb-3">
        <h5 class="mb-3">{{ __('app.applications.ministry_interior_personal_details.sections.passport_birth') }}</h5>
        <div class="row g-3">
            @foreach ([
                'passport_number' => ['col' => 'col-12 col-lg-4', 'type' => 'text'],
                'passport_type' => ['col' => 'col-12 col-lg-4', 'type' => 'text'],
                'passport_issue_place' => ['col' => 'col-12 col-lg-4', 'type' => 'text'],
                'passport_issue_date' => ['col' => 'col-12 col-lg-3', 'type' => 'date'],
                'passport_expiry_date' => ['col' => 'col-12 col-lg-3', 'type' => 'date'],
                'birth_place' => ['col' => 'col-12 col-lg-3', 'type' => 'text'],
                'birth_date' => ['col' => 'col-12 col-lg-3', 'type' => 'date'],
            ] as $field => $config)
                <div class="{{ $config['col'] }}">
                    <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} @unless($readOnly)<span class="text-danger">*</span>@endunless</label>
                    <input type="{{ $config['type'] }}" class="form-control" name="{{ $inputPrefix }}[{{ $field }}]" value="{{ $detailValue($field) }}" @disabled($readOnly) @if($field === 'birth_date') max="{{ now()->subDay()->toDateString() }}" @endif>
                </div>
            @endforeach
        </div>
    </div>

    <div class="ministry-personal-details-form__section mb-3">
        <h5 class="mb-3">{{ __('app.applications.ministry_interior_personal_details.sections.work_family') }}</h5>
        <div class="row g-3">
            @foreach (['education_qualification', 'profession', 'workplace', 'mother_full_name'] as $field)
                <div class="col-12 col-lg-3">
                    <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} @unless($readOnly)<span class="text-danger">*</span>@endunless</label>
                    <input type="text" class="form-control" name="{{ $inputPrefix }}[{{ $field }}]" value="{{ $detailValue($field) }}" @disabled($readOnly)>
                </div>
            @endforeach
            <div class="col-12 col-lg-4">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.mother_nationality') }} @unless($readOnly)<span class="text-danger">*</span>@endunless</label>
                @if ($readOnly)
                    <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue('mother_nationality')) }}" disabled>
                @else
                    <select class="form-select" name="{{ $inputPrefix }}[mother_nationality]">
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach ($ministryNationalityOptions as $nationality)
                            <option value="{{ $nationality->code }}" @selected((string) $detailValue('mother_nationality') === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.spouse_full_name') }}</label>
                <input type="text" class="form-control" name="{{ $inputPrefix }}[spouse_full_name]" value="{{ $detailValue('spouse_full_name') }}" @disabled($readOnly)>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.spouse_nationality') }}</label>
                @if ($readOnly)
                    <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue('spouse_nationality')) }}" disabled>
                @else
                    <select class="form-select" name="{{ $inputPrefix }}[spouse_nationality]">
                        <option value="">{{ __('app.select_option') }}</option>
                        @foreach ($ministryNationalityOptions as $nationality)
                            <option value="{{ $nationality->code }}" @selected((string) $detailValue('spouse_nationality') === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.spouse_birth_date') }}</label>
                <input type="date" class="form-control" name="{{ $inputPrefix }}[spouse_birth_date]" value="{{ $detailValue('spouse_birth_date') }}" max="{{ now()->subDay()->toDateString() }}" @disabled($readOnly)>
            </div>
            <div class="col-12 col-lg-8">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.spouse_mother_full_name') }}</label>
                <input type="text" class="form-control" name="{{ $inputPrefix }}[spouse_mother_full_name]" value="{{ $detailValue('spouse_mother_full_name') }}" @disabled($readOnly)>
            </div>
        </div>
    </div>

    <div class="ministry-personal-details-form__section">
        <h5 class="mb-3">{{ __('app.applications.ministry_interior_personal_details.sections.visit_residence') }}</h5>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.visit_residence_reason') }} @unless($readOnly)<span class="text-danger">*</span>@endunless</label>
                <textarea class="form-control" rows="3" name="{{ $inputPrefix }}[visit_residence_reason]" @disabled($readOnly)>{{ $detailValue('visit_residence_reason') }}</textarea>
            </div>
            @foreach ([
                'country_of_arrival' => ['type' => 'text', 'required' => true],
                'country_of_residence' => ['type' => 'text', 'required' => true],
                'residence_issue_date' => ['type' => 'date', 'required' => false],
                'residence_expiry_date' => ['type' => 'date', 'required' => false],
            ] as $field => $config)
                <div class="col-12 col-lg-3">
                    <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} @if($config['required'] && ! $readOnly)<span class="text-danger">*</span>@endif</label>
                    <input type="{{ $config['type'] }}" class="form-control" name="{{ $inputPrefix }}[{{ $field }}]" value="{{ $detailValue($field) }}" @disabled($readOnly)>
                </div>
            @endforeach
            <div class="col-12 col-lg-8">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.jordan_residence_address') }}</label>
                <input type="text" class="form-control" name="{{ $inputPrefix }}[jordan_residence_address]" value="{{ $detailValue('jordan_residence_address') }}" @disabled($readOnly)>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.signature') }}</label>
                <input type="text" class="form-control" value="{{ $detailValue('signature', auth()->user()?->displayName()) }}" disabled>
            </div>
        </div>

        <div class="alert alert-warning mt-4 mb-0">{{ __('app.applications.ministry_interior_personal_details.important_note') }}</div>

        @if ($readOnly)
            <div class="d-flex align-items-center gap-2 flex-wrap mt-3">
                <span class="badge bg-{{ $confirmed ? 'success' : 'secondary' }}">
                    {{ $confirmed ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}
                </span>
                @if (filled($detailValue('signed_at')))
                    <span class="text-muted small">{{ $detailValue('signed_at') }}</span>
                @endif
            </div>
        @else
            <div class="form-check mt-3">
                <input type="hidden" name="{{ $inputPrefix }}[confirmed]" value="0">
                <input class="form-check-input" type="checkbox" id="{{ $idPrefix }}_confirmed" name="{{ $inputPrefix }}[confirmed]" value="1" @checked($confirmed)>
                <label class="form-check-label fw-600" for="{{ $idPrefix }}_confirmed">
                    {{ __('app.applications.ministry_interior_personal_details.confirm_label') }} <span class="text-danger">*</span>
                </label>
            </div>
        @endif
    </div>
</article>
