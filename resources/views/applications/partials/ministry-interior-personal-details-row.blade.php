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
    $personalNumberLabel = __('app.applications.ministry_interior_personal_details.fields.individual_number');
    $travelDocumentHolder = $nationalityCategory === 'travel_document';
    $travelDocumentTypeOptions = [
        'foreign_travel_document',
        'passport',
        'palestinian_refugee_syrian_passport',
    ];
    $effectiveNationality = (string) ($travelDocumentHolder
        ? $detailValue('original_nationality')
        : $detailValue('current_nationality'));
    $countryOfResidence = (string) $detailValue('country_of_residence');
    $residenceRequired = filled($effectiveNationality)
        && filled($countryOfResidence)
        && $effectiveNationality !== $countryOfResidence;
    $schengenUsVisa = (string) $detailValue('schengen_us_visa');
    $isPalestinian = in_array($effectiveNationality, $ministryPalestinianNationalityCodes ?? [], true);
    $passportAttachment = (array) ($attachments->firstWhere('document_type', 'passport_copy') ?? []);
    $residenceAttachment = (array) ($attachments->firstWhere('document_type', 'foreign_residence') ?? []);
    $additionalAttachments = $attachments
        ->reject(fn (array $attachment): bool => in_array((string) ($attachment['document_type'] ?? ''), ['passport_copy', 'foreign_residence'], true))
        ->values();
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
    data-next-attachment-index="{{ $additionalAttachments->count() + 2 }}"
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
                    @foreach (['arab', 'foreign', 'travel_document'] as $option)
                        <option value="{{ $option }}" @selected($nationalityCategory === $option)>{{ $optionLabel('nationality_category', $option) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-xl-5">
                <div class="ministry-personal-details-form__lookup">
                    <div>
                        <label class="form-label" for="{{ $idPrefix }}_personal_number" data-ministry-personal-number-label>{{ $personalNumberLabel }}</label>
                        <input type="text" inputmode="numeric" maxlength="10" pattern="[0-9]{10}" class="form-control" id="{{ $idPrefix }}_personal_number" name="{{ $inputPrefix }}[personal_number]" value="{{ $detailValue('personal_number') }}" @disabled($readOnly)>
                        <div class="form-text">{{ __('app.applications.ministry_interior_personal_details.personal_number_help') }}</div>
                    </div>
                    @unless ($readOnly)
                        <button type="button" class="btn btn-primary" data-ministry-personal-details-lookup>
                            <i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>{{ __('app.applications.ministry_interior_personal_details.lookup_button') }}
                        </button>
                        <div class="ministry-personal-details-form__lookup-status" aria-live="polite" data-ministry-personal-details-lookup-status></div>
                    @endunless
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-4" data-ministry-current-nationality @if ($travelDocumentHolder) hidden @endif>
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
                        @foreach ($ministryCountryOptions as $nationality)
                            <option value="{{ $nationality->code }}" @selected((string) $detailValue('mother_nationality') === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-xl-4">
                <label class="form-label" for="{{ $idPrefix }}_education_qualification">{{ __('app.applications.ministry_interior_personal_details.fields.education_qualification') }} {!! $requiredMark !!}</label>
                <input type="text" class="form-control" id="{{ $idPrefix }}_education_qualification" name="{{ $inputPrefix }}[education_qualification]" value="{{ $detailValue('education_qualification') }}" @disabled($readOnly)>
            </div>

            <div class="col-12" data-ministry-original-nationality @if (! $travelDocumentHolder) hidden @endif>
                <div class="ministry-personal-details-form__conditional-panel">
                    <h6>{{ __('app.applications.ministry_interior_personal_details.sections.original_nationality') }}</h6>
                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <label class="form-label" for="{{ $idPrefix }}_travel_document_type">{{ __('app.applications.ministry_interior_personal_details.fields.travel_document_type') }} {!! $requiredMark !!}</label>
                            @if ($readOnly)
                                <input type="text" class="form-control" value="{{ $optionLabel('travel_document_type', $detailValue('travel_document_type')) }}" disabled>
                            @else
                                <select class="form-select" id="{{ $idPrefix }}_travel_document_type" name="{{ $inputPrefix }}[travel_document_type]">
                                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                    @foreach ($travelDocumentTypeOptions as $travelDocumentType)
                                        <option value="{{ $travelDocumentType }}" @selected((string) $detailValue('travel_document_type') === $travelDocumentType)>{{ $optionLabel('travel_document_type', $travelDocumentType) }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label" for="{{ $idPrefix }}_original_nationality">{{ __('app.applications.ministry_interior_personal_details.fields.original_nationality') }} {!! $requiredMark !!}</label>
                            @if ($readOnly)
                                <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue('original_nationality')) }}" disabled>
                            @else
                                <select class="form-select" id="{{ $idPrefix }}_original_nationality" name="{{ $inputPrefix }}[original_nationality]">
                                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                    @foreach ($ministryNationalityOptions as $nationality)
                                        <option value="{{ $nationality->code }}" @selected((string) $detailValue('original_nationality') === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label" for="{{ $idPrefix }}_original_document_country">{{ __('app.applications.ministry_interior_personal_details.fields.original_document_country') }} {!! $requiredMark !!}</label>
                            @if ($readOnly)
                                <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue('original_document_country')) }}" disabled>
                            @else
                                <select class="form-select" id="{{ $idPrefix }}_original_document_country" name="{{ $inputPrefix }}[original_document_country]">
                                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                    @foreach ($ministryCountryOptions as $country)
                                        <option value="{{ $country->code }}" @selected((string) $detailValue('original_document_country') === (string) $country->code)>{{ $country->displayName() }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        @foreach (['original_first_name', 'original_father_name', 'original_grandfather_name', 'original_family_name'] as $field)
                            <div class="col-12 col-sm-6 col-xl-3">
                                <label class="form-label" for="{{ $idPrefix }}_{{ $field }}">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} {!! $requiredMark !!}</label>
                                <input type="text" class="form-control" id="{{ $idPrefix }}_{{ $field }}" name="{{ $inputPrefix }}[{{ $field }}]" value="{{ $detailValue($field) }}" @disabled($readOnly)>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ministry-personal-details-form__section">
        <h5 class="ministry-personal-details-form__section-title">{{ __('app.applications.ministry_interior_personal_details.sections.residency') }}</h5>
        <div class="row g-3">
            @foreach (['country_of_arrival', 'country_of_residence'] as $field)
                <div class="col-12 col-md-6">
                    <label class="form-label" for="{{ $idPrefix }}_{{ $field }}">{{ __('app.applications.ministry_interior_personal_details.fields.'.$field) }} {!! $requiredMark !!}</label>
                    @if ($readOnly)
                        <input type="text" class="form-control" value="{{ $nationalityLabel($detailValue($field)) }}" disabled>
                    @else
                        <select class="form-select" id="{{ $idPrefix }}_{{ $field }}" name="{{ $inputPrefix }}[{{ $field }}]">
                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                            @foreach ($ministryCountryOptions as $nationality)
                                <option value="{{ $nationality->code }}" @selected((string) $detailValue($field) === (string) $nationality->code)>{{ $nationality->displayName() }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endforeach

            <div class="col-12" data-ministry-residency-extra @if (! $residenceRequired) hidden @endif>
                <div class="ministry-personal-details-form__conditional-panel">
                    <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="note">
                        <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
                        <span>{{ __('app.applications.ministry_interior_personal_details.residence_document_notice') }}</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="{{ $idPrefix }}_residence_expiry_date">{{ __('app.applications.ministry_interior_personal_details.fields.residence_expiry_date') }} {!! $requiredMark !!}</label>
                            <input type="date" class="form-control" id="{{ $idPrefix }}_residence_expiry_date" name="{{ $inputPrefix }}[residence_expiry_date]" value="{{ $detailValue('residence_expiry_date') }}" min="{{ $minimumTravelDocumentExpiryDate }}" @disabled($readOnly)>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="{{ $idPrefix }}_schengen_us_visa">{{ __('app.applications.ministry_interior_personal_details.fields.schengen_us_visa') }} {!! $requiredMark !!}</label>
                <select class="form-select" id="{{ $idPrefix }}_schengen_us_visa" name="{{ $inputPrefix }}[schengen_us_visa]" @disabled($readOnly)>
                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                    @foreach (['yes', 'no'] as $option)
                        <option value="{{ $option }}" @selected($schengenUsVisa === $option)>{{ $optionLabel('yes_no', $option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6" data-ministry-visa-expiry @if ($schengenUsVisa !== 'yes') hidden @endif>
                <label class="form-label" for="{{ $idPrefix }}_schengen_us_visa_expiry_date">{{ __('app.applications.ministry_interior_personal_details.fields.schengen_us_visa_expiry_date') }} {!! $requiredMark !!}</label>
                <input type="date" class="form-control" id="{{ $idPrefix }}_schengen_us_visa_expiry_date" name="{{ $inputPrefix }}[schengen_us_visa_expiry_date]" value="{{ $detailValue('schengen_us_visa_expiry_date') }}" min="{{ $todayDate }}" @disabled($readOnly)>
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
                    @foreach (['ordinary', 'diplomatic', 'travel_document'] as $option)
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
                    <input
                        type="{{ $type }}"
                        class="form-control"
                        id="{{ $idPrefix }}_{{ $field }}"
                        name="{{ $inputPrefix }}[{{ $field }}]"
                        value="{{ $detailValue($field) }}"
                        @if ($field === 'passport_issue_date') max="{{ $todayDate }}" @endif
                        @if ($field === 'passport_expiry_date') min="{{ $minimumTravelDocumentExpiryDate }}" @endif
                        @disabled($readOnly)
                    >
                </div>
            @endforeach
            <div class="col-12 col-md-6" data-ministry-palestinian-passport-id @if (! $isPalestinian) hidden @endif>
                <label class="form-label" for="{{ $idPrefix }}_palestinian_passport_id">{{ __('app.applications.ministry_interior_personal_details.fields.palestinian_passport_id') }} {!! $requiredMark !!}</label>
                <input type="text" class="form-control" id="{{ $idPrefix }}_palestinian_passport_id" name="{{ $inputPrefix }}[palestinian_passport_id]" value="{{ $detailValue('palestinian_passport_id') }}" @disabled($readOnly)>
            </div>
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
                        @foreach ($ministryCountryOptions as $nationality)
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
            @include('applications.partials.ministry-interior-personal-details-attachment-row', [
                'attachment' => $passportAttachment,
                'attachmentIndex' => 0,
                'fixedDocumentType' => 'passport_copy',
            ])

            <div data-ministry-residence-attachment @if (! $residenceRequired) hidden @endif>
                @include('applications.partials.ministry-interior-personal-details-attachment-row', [
                    'attachment' => $residenceAttachment,
                    'attachmentIndex' => 1,
                    'fixedDocumentType' => 'foreign_residence',
                ])
            </div>

            @foreach ($additionalAttachments as $attachmentIndex => $attachment)
                @include('applications.partials.ministry-interior-personal-details-attachment-row', [
                    'attachment' => $attachment,
                    'attachmentIndex' => $attachmentIndex + 2,
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
