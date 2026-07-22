@php
    $row = (array) ($row ?? []);
    $rowIndex = $rowIndex ?? 0;
    $inputIndex = $inputIndex ?? $rowIndex;
    $readOnly = (bool) ($ministryInteriorPersonalDetailsReadOnly ?? false);
    $inputPrefix = 'ministry_interior_personal_details['.$inputIndex.']';
    $idToken = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $inputIndex);
    $idPrefix = ($ministryInteriorPersonalDetailsIdPrefix ?? 'ministry_interior_personal_details').'_'.$idToken;
    $detailValue = static fn (string $key, mixed $default = null): mixed => data_get($row, $key, $default);
    $confirmed = \App\Support\MinistryInteriorPersonalDetails::isConfirmed($row);
    $nameParts = preg_split('/\s+/u', trim((string) $detailValue('current_full_name')), 4) ?: [];
    $attachments = collect((array) $detailValue('attachments', []))->filter(fn ($item) => is_array($item))->values();
    $maritalStatus = (string) $detailValue('marital_status');
    $nationalityCategory = (string) $detailValue('nationality_category');
    $personalNumberLabel = match ($nationalityCategory) {
        'jordanian' => __('app.applications.ministry_interior_personal_details.fields.national_number'),
        'arab', 'foreign' => __('app.applications.ministry_interior_personal_details.fields.individual_number'),
        default => __('app.applications.ministry_interior_personal_details.fields.personal_number'),
    };
    $personalNumberMaxlength = $nationalityCategory === 'jordanian' ? 10 : 20;
    $personalNumberPattern = $nationalityCategory === 'jordanian' ? '[0-9]{10}' : '[0-9]{1,20}';
    $fieldValue = static function (string $key, mixed $fallback = null) use ($detailValue): mixed {
        $value = $detailValue($key);
        return filled($value) ? $value : $fallback;
    };
    $optionLabel = static function (string $group, mixed $value): string {
        if (! filled($value)) {
            return '';
        }

        $key = 'app.applications.ministry_interior_personal_details.options.'.$group.'.'.$value;
        $label = __($key);

        return $label === $key ? (string) $value : $label;
    };
    $nationalityLabel = static fn ($value): string => filled($value)
        ? \App\Models\Nationality::labelFor((string) $value)
        : '';
    $genderLabel = static fn ($value): string => filled($value)
        ? __('app.auth.gender_options.'.(string) $value)
        : '';
    $requiredMark = $readOnly ? '' : '<span class="text-danger">*</span>';
@endphp

<article
    class="ministry-personal-details-form__record"
    data-ministry-personal-details-row
    data-next-attachment-index="{{ $attachments->count() }}"
>
    <div class="ministry-personal-details-form__record-header">
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

    <input type="hidden" name="{{ $inputPrefix }}[current_full_name]" value="{{ \App\Support\MinistryInteriorPersonalDetails::displayName($row) }}">

    <section class="ministry-personal-details-form__section">
        <h5 class="ministry-personal-details-form__section-title">{{ __('app.applications.ministry_interior_personal_details.sections.personal') }}</h5>
        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="{{ $idPrefix }}_nationality_category">{{ __('app.applications.ministry_interior_personal_details.fields.nationality_category') }} {!! $requiredMark !!}</label>
                <select class="form-select" id="{{ $idPrefix }}_nationality_category" name="{{ $inputPrefix }}[nationality_category]" @disabled($readOnly)>
                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                    @foreach (['jordanian', 'arab', 'foreign'] as $option)
                        <option value="{{ $option }}" @selected($nationalityCategory === $option)>{{ $optionLabel('nationality_category', $option) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-xl-5">
                <div class="ministry-personal-details-form__lookup">
                    <div>
                        <label class="form-label" for="{{ $idPrefix }}_personal_number" data-ministry-personal-number-label>{{ $personalNumberLabel }}</label>
                        <input type="text" inputmode="numeric" maxlength="{{ $personalNumberMaxlength }}" pattern="{{ $personalNumberPattern }}" class="form-control" id="{{ $idPrefix }}_personal_number" name="{{ $inputPrefix }}[personal_number]" value="{{ $detailValue('personal_number') }}" @disabled($readOnly)>
                    </div>
                    @unless ($readOnly)
                        <button type="button" class="btn btn-primary" data-ministry-personal-details-lookup>
                            <i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>{{ __('app.applications.ministry_interior_personal_details.lookup_button') }}
                        </button>
                        <div class="ministry-personal-details-form__lookup-status" aria-live="polite" data-ministry-personal-details-lookup-status></div>
                    @endunless
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-4">
                <label class="form-label" for="{{ $idPrefix }}_current_nationality">{{ __('app.applications.ministry_interior_personal_details.fields.current_nationality') }} {!! $requiredMark !!}</label>
                @if ($readOnly)
                    <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue('current_nationality')) }}" disabled>
                @else
                    <select class="form-select" id="{{ $idPrefix }}_current_nationality" name="{{ $inputPrefix }}[current_nationality]">
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach ($ministryNationalityOptions as $nationality)
                            <option value="{{ $nationality->code }}" @selected((string) $detailValue('current_nationality') === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            @foreach ([
                'first_name' => $fieldValue('first_name', $nameParts[0] ?? null),
                'father_name' => $fieldValue('father_name', $nameParts[1] ?? null),
                'grandfather_name' => $fieldValue('grandfather_name', $nameParts[2] ?? null),
                'family_name' => $fieldValue('family_name', $nameParts[3] ?? null),
            ] as $field => $value)
                <div class="col-12 col-sm-6 col-xl-3">
                    <label class="form-label" for="{{ $idPrefix }}_{{ $field }}">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} {!! $requiredMark !!}</label>
                    <input type="text" class="form-control" id="{{ $idPrefix }}_{{ $field }}" name="{{ $inputPrefix }}[{{ $field }}]" value="{{ $value }}" @disabled($readOnly)>
                </div>
            @endforeach

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="{{ $idPrefix }}_birth_place">{{ __('app.applications.ministry_interior_personal_details.fields.birth_place') }} {!! $requiredMark !!}</label>
                <input type="text" class="form-control" id="{{ $idPrefix }}_birth_place" name="{{ $inputPrefix }}[birth_place]" value="{{ $detailValue('birth_place') }}" @disabled($readOnly)>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="{{ $idPrefix }}_birth_date">{{ __('app.applications.ministry_interior_personal_details.fields.birth_date') }} {!! $requiredMark !!}</label>
                <input type="date" class="form-control" id="{{ $idPrefix }}_birth_date" name="{{ $inputPrefix }}[birth_date]" value="{{ $detailValue('birth_date') }}" max="{{ now()->subDay()->toDateString() }}" @disabled($readOnly)>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="{{ $idPrefix }}_gender">{{ __('app.applications.ministry_interior_personal_details.fields.gender') }} {!! $requiredMark !!}</label>
                @if ($readOnly)
                    <input type="text" class="form-control" value="{{ $genderLabel($detailValue('gender')) }}" disabled>
                @else
                    <select class="form-select" id="{{ $idPrefix }}_gender" name="{{ $inputPrefix }}[gender]">
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach (['male', 'female'] as $gender)
                            <option value="{{ $gender }}" @selected((string) $detailValue('gender') === $gender)>{{ __('app.auth.gender_options.'.$gender) }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="{{ $idPrefix }}_marital_status">{{ __('app.applications.ministry_interior_personal_details.fields.marital_status') }} {!! $requiredMark !!}</label>
                <select class="form-select" id="{{ $idPrefix }}_marital_status" name="{{ $inputPrefix }}[marital_status]" @disabled($readOnly)>
                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                    @foreach (['single', 'married', 'divorced', 'widowed'] as $option)
                        <option value="{{ $option }}" @selected($maritalStatus === $option)>{{ $optionLabel('marital_status', $option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <label class="form-label" for="{{ $idPrefix }}_mother_full_name">{{ __('app.applications.ministry_interior_personal_details.fields.mother_full_name') }} {!! $requiredMark !!}</label>
                <input type="text" class="form-control" id="{{ $idPrefix }}_mother_full_name" name="{{ $inputPrefix }}[mother_full_name]" value="{{ $detailValue('mother_full_name') }}" @disabled($readOnly)>
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <label class="form-label" for="{{ $idPrefix }}_mother_nationality">{{ __('app.applications.ministry_interior_personal_details.fields.mother_nationality') }} {!! $requiredMark !!}</label>
                @if ($readOnly)
                    <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue('mother_nationality')) }}" disabled>
                @else
                    <select class="form-select" id="{{ $idPrefix }}_mother_nationality" name="{{ $inputPrefix }}[mother_nationality]">
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach ($ministryNationalityOptions as $nationality)
                            <option value="{{ $nationality->code }}" @selected((string) $detailValue('mother_nationality') === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-xl-4">
                <label class="form-label" for="{{ $idPrefix }}_education_qualification">{{ __('app.applications.ministry_interior_personal_details.fields.education_qualification') }} {!! $requiredMark !!}</label>
                <input type="text" class="form-control" id="{{ $idPrefix }}_education_qualification" name="{{ $inputPrefix }}[education_qualification]" value="{{ $detailValue('education_qualification') }}" @disabled($readOnly)>
            </div>
        </div>
    </section>

    <section class="ministry-personal-details-form__section">
        <h5 class="ministry-personal-details-form__section-title">{{ __('app.applications.ministry_interior_personal_details.sections.residency') }}</h5>
        <div class="row g-3">
            @foreach (['country_of_arrival', 'country_of_residence'] as $field)
                <div class="col-12 col-md-6">
                    <label class="form-label" for="{{ $idPrefix }}_{{ $field }}">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} {!! $requiredMark !!}</label>
                    <input type="text" class="form-control" id="{{ $idPrefix }}_{{ $field }}" name="{{ $inputPrefix }}[{{ $field }}]" value="{{ $detailValue($field) }}" @disabled($readOnly)>
                </div>
            @endforeach

            <div class="col-12" data-ministry-residency-extra @if($nationalityCategory === 'jordanian') hidden @endif>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="{{ $idPrefix }}_residence_expiry_date">{{ __('app.applications.ministry_interior_personal_details.fields.residence_expiry_date') }} {!! $requiredMark !!}</label>
                        <input type="date" class="form-control" id="{{ $idPrefix }}_residence_expiry_date" name="{{ $inputPrefix }}[residence_expiry_date]" value="{{ $detailValue('residence_expiry_date') }}" @disabled($readOnly || $nationalityCategory === 'jordanian')>
                    </div>
                    @foreach (['schengen_us_visa', 'previous_jordan_residence', 'investment_card', 'free_zones_card'] as $field)
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="{{ $idPrefix }}_{{ $field }}">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} {!! $requiredMark !!}</label>
                            <select class="form-select" id="{{ $idPrefix }}_{{ $field }}" name="{{ $inputPrefix }}[{{ $field }}]" @disabled($readOnly || $nationalityCategory === 'jordanian')>
                                <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                @foreach (['yes', 'no'] as $option)
                                    <option value="{{ $option }}" @selected((string) $detailValue($field) === $option)>{{ $optionLabel('yes_no', $option) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($field === 'previous_jordan_residence')
                            <div class="col-12" data-ministry-residence-document-notice @if((string) $detailValue($field) !== 'yes') hidden @endif>
                                <div class="alert alert-info d-flex align-items-start gap-2 mb-0" role="note">
                                    <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
                                    <span>{{ __('app.applications.ministry_interior_personal_details.residence_document_notice') }}</span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="ministry-personal-details-form__section">
        <h5 class="ministry-personal-details-form__section-title">{{ __('app.applications.ministry_interior_personal_details.sections.jordan_address') }}</h5>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="{{ $idPrefix }}_jordan_governorate">{{ __('app.applications.ministry_interior_personal_details.fields.jordan_governorate') }} {!! $requiredMark !!}</label>
                <select class="form-select" id="{{ $idPrefix }}_jordan_governorate" name="{{ $inputPrefix }}[jordan_governorate]" @disabled($readOnly)>
                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                    @foreach ($ministryGovernorateOptions as $governorate)
                        <option value="{{ $governorate->code }}" @selected((string) $detailValue('jordan_governorate') === (string) $governorate->code)>{{ $governorate->displayName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label" for="{{ $idPrefix }}_jordan_residence_address">{{ __('app.applications.ministry_interior_personal_details.fields.jordan_residence_address') }} {!! $requiredMark !!}</label>
                <input type="text" class="form-control" id="{{ $idPrefix }}_jordan_residence_address" name="{{ $inputPrefix }}[jordan_residence_address]" value="{{ $detailValue('jordan_residence_address') }}" @disabled($readOnly)>
            </div>
        </div>
    </section>

    <section class="ministry-personal-details-form__section">
        <h5 class="ministry-personal-details-form__section-title">{{ __('app.applications.ministry_interior_personal_details.sections.passport') }}</h5>
        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="{{ $idPrefix }}_passport_type">{{ __('app.applications.ministry_interior_personal_details.fields.passport_type') }} {!! $requiredMark !!}</label>
                <select class="form-select" id="{{ $idPrefix }}_passport_type" name="{{ $inputPrefix }}[passport_type]" @disabled($readOnly)>
                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                    @foreach (['ordinary', 'diplomatic', 'service', 'temporary', 'travel_document', 'other'] as $option)
                        <option value="{{ $option }}" @selected((string) $detailValue('passport_type') === $option)>{{ $optionLabel('passport_type', $option) }}</option>
                    @endforeach
                </select>
            </div>
            @foreach ([
                'passport_number' => 'text',
                'passport_issue_place' => 'text',
                'passport_issue_date' => 'date',
                'passport_expiry_date' => 'date',
            ] as $field => $type)
                <div class="col-12 col-md-6 @if(in_array($field, ['passport_issue_date', 'passport_expiry_date'])) col-xl-3 @else col-xl-3 @endif">
                    <label class="form-label" for="{{ $idPrefix }}_{{ $field }}">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} {!! $requiredMark !!}</label>
                    <input type="{{ $type }}" class="form-control" id="{{ $idPrefix }}_{{ $field }}" name="{{ $inputPrefix }}[{{ $field }}]" value="{{ $detailValue($field) }}" @disabled($readOnly)>
                </div>
            @endforeach
        </div>
    </section>

    <section class="ministry-personal-details-form__section" data-ministry-spouse-section @if($maritalStatus !== 'married') hidden @endif>
        <h5 class="ministry-personal-details-form__section-title">{{ __('app.applications.ministry_interior_personal_details.sections.spouse') }}</h5>
        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="{{ $idPrefix }}_spouse_nationality">{{ __('app.applications.ministry_interior_personal_details.fields.spouse_nationality') }} {!! $requiredMark !!}</label>
                @if ($readOnly)
                    <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue('spouse_nationality')) }}" disabled>
                @else
                    <select class="form-select" id="{{ $idPrefix }}_spouse_nationality" name="{{ $inputPrefix }}[spouse_nationality]" @disabled($maritalStatus !== 'married')>
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach ($ministryNationalityOptions as $nationality)
                            <option value="{{ $nationality->code }}" @selected((string) $detailValue('spouse_nationality') === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            @foreach (['spouse_full_name' => 'text', 'spouse_birth_date' => 'date', 'spouse_mother_full_name' => 'text'] as $field => $type)
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="{{ $idPrefix }}_{{ $field }}">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} {!! $requiredMark !!}</label>
                    <input type="{{ $type }}" class="form-control" id="{{ $idPrefix }}_{{ $field }}" name="{{ $inputPrefix }}[{{ $field }}]" value="{{ $detailValue($field) }}" @if($type === 'date') max="{{ now()->subDay()->toDateString() }}" @endif @disabled($readOnly || $maritalStatus !== 'married')>
                </div>
            @endforeach
        </div>
    </section>

    <section class="ministry-personal-details-form__section">
        <h5 class="ministry-personal-details-form__section-title">{{ __('app.applications.ministry_interior_personal_details.sections.travel') }}</h5>
        <div class="row g-3">
            @foreach ([
                'entry_method' => ['lawful_entry', 'visa', 'other'],
                'departure_document' => ['foreign_travel_document', 'passport', 'palestinian_refugee_syrian_passport'],
                'departure_method' => ['facilitation_letter', 'lawful_entry_departure', 'unhcr', 'voluntary_migration', 'other'],
            ] as $field => $options)
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="{{ $idPrefix }}_{{ $field }}">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} {!! $requiredMark !!}</label>
                    <select class="form-select" id="{{ $idPrefix }}_{{ $field }}" name="{{ $inputPrefix }}[{{ $field }}]" @disabled($readOnly)>
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach ($options as $option)
                            <option value="{{ $option }}" @selected((string) $detailValue($field) === $option)>{{ $optionLabel($field, $option) }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
    </section>

    <section class="ministry-personal-details-form__section">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
            <div>
                <h5 class="ministry-personal-details-form__section-title mb-1">{{ __('app.applications.ministry_interior_personal_details.sections.attachments') }}</h5>
                <p class="text-muted small mb-0">{{ __('app.applications.ministry_interior_personal_details.attachments_constraints') }}</p>
            </div>
            @unless ($readOnly)
                <button type="button" class="btn btn-success btn-sm" data-ministry-attachment-add>
                    <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>{{ __('app.applications.ministry_interior_personal_details.add_attachment') }}
                </button>
            @endunless
        </div>

        <div data-ministry-attachment-rows>
            @foreach ($attachments as $attachmentIndex => $attachment)
                @include('applications.partials.ministry-interior-personal-details-attachment-row', [
                    'attachment' => $attachment,
                    'attachmentIndex' => $attachmentIndex,
                ])
            @endforeach
        </div>

        @unless ($readOnly)
            <template data-ministry-attachment-template>
                @include('applications.partials.ministry-interior-personal-details-attachment-row', [
                    'attachment' => [],
                    'attachmentIndex' => '__ATTACHMENT_INDEX__',
                ])
            </template>
        @endunless
    </section>

    <section class="ministry-personal-details-form__section">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-6">
                <label class="form-label">{{ __('app.applications.ministry_interior_personal_details.fields.signature') }}</label>
                <input type="text" class="form-control" value="{{ $detailValue('signature', auth()->user()?->displayName()) }}" disabled>
            </div>
            <div class="col-12 col-lg-6">
                @if ($readOnly)
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-{{ $confirmed ? 'success' : 'secondary' }}">
                            {{ $confirmed ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}
                        </span>
                        @if (filled($detailValue('signed_at')))
                            <span class="text-muted small">{{ $detailValue('signed_at') }}</span>
                        @endif
                    </div>
                @else
                    <div class="form-check">
                        <input type="hidden" name="{{ $inputPrefix }}[confirmed]" value="0">
                        <input class="form-check-input" type="checkbox" id="{{ $idPrefix }}_confirmed" name="{{ $inputPrefix }}[confirmed]" value="1" @checked($confirmed)>
                        <label class="form-check-label fw-semibold" for="{{ $idPrefix }}_confirmed">
                            {{ __('app.applications.ministry_interior_personal_details.confirm_label') }} <span class="text-danger">*</span>
                        </label>
                    </div>
                @endif
            </div>
        </div>
    </section>
</article>
