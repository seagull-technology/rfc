@extends('layouts.auth', ['title' => __('app.auth.register_title')])

@push('styles')
    @include('auth.partials.registration-styles')
@endpush

@php
    $studentLookupCompleted = old('registration_type') === 'student' && old('student_lookup_verified') === '1';
    $companyLookupCompleted = old('registration_type') === 'company' && old('company_lookup_verified') === '1';
    $studentBirthDateValue = old('registration_type') === 'student' ? (string) old('birth_date', '') : '';

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $studentBirthDateValue, $studentBirthDateParts)) {
        $studentBirthDateValue = $studentBirthDateParts[3].'/'.$studentBirthDateParts[2].'/'.$studentBirthDateParts[1];
    }
@endphp

@section('content')
    <div class="wrapper">
        <section class="sign-in-page registration-auth-page" style="background-image: url('{{ asset('images/loginBg.jpeg') }}')">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-12">
                        <div class="sign-user_card registration-card registration-card-wide">
                            <div class="registration-brand-hero">
                                <a class="registration-logo-link registration-logo-badge" href="{{ route('login') }}">
                                    <img class="img-fluid logo registration-logo" src="{{ asset('images/logo.svg') }}" alt="#">
                                </a>
                            </div>
                            <div class="sign-in-page-data registration-page-data">
                                <div class="sign-in-from w-100 m-auto">
                                    @include('auth.partials.alerts')

                                    <div class="registration-header">
                                        <h1 class="registration-title">{{ __('app.auth.register_title') }}</h1>
                                        <p class="registration-subtitle">{{ __('app.auth.register_intro') }}</p>
                                    </div>

                                    <ul class="nav nav-pills nav-fill registration-tabs" id="myTab-2" role="tablist">
                                        @foreach ($registrationTypes as $type => $config)
                                            <li class="nav-item">
                                                <a
                                                    class="nav-link {{ $activeRegistrationType === $type ? 'active' : '' }}"
                                                    id="{{ $config['tab_link_id'] }}"
                                                    data-bs-toggle="tab"
                                                    href="#{{ $config['tab_id'] }}"
                                                    role="tab"
                                                    aria-selected="{{ $activeRegistrationType === $type ? 'true' : 'false' }}"
                                                >
                                                    {{ $config['label'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <div class="tab-content registration-form-panel" id="myTabContent-3">
                                        <div class="tab-pane fade {{ $activeRegistrationType === 'student' ? 'show active' : '' }}" id="home-justify" role="tabpanel" aria-labelledby="home-tab-justify">
                                            <form method="POST" action="{{ route('register.store') }}" id="register-form-student" data-register-form="student" data-student-lookup-url="{{ route('register.student.lookup') }}" enctype="multipart/form-data">
                                                @csrf
                                                <input type="hidden" name="registration_type" value="student">
                                                <input type="hidden" name="student_lookup_verified" value="{{ $studentLookupCompleted ? '1' : '0' }}" data-student-lookup-verified>

                                                <div class="row">
                                                    <div class="col-12">
                                                        <div class="registration-identity-lookup registration-identity-lookup--student">
                                                            <div class="registration-identity-field">
                                                                <label class="form-label">{{ __('app.auth.national_id') }}</label>
                                                                <input type="text" name="national_id" class="form-control @error('national_id') is-invalid @enderror" placeholder="{{ __('app.auth.national_id_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('national_id') : '' }}" inputmode="numeric" pattern="\d{10}" maxlength="10" autocomplete="off" data-student-national-id required>
                                                                @error('national_id')
                                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                @enderror
                                                            </div>

                                                            <div class="registration-identity-field">
                                                                <label class="form-label">{{ __('app.auth.birth_date') }}</label>
                                                                <div class="registration-date-control">
                                                                    <input
                                                                        type="text"
                                                                        name="birth_date"
                                                                        class="form-control @error('birth_date') is-invalid @enderror"
                                                                        value="{{ $studentBirthDateValue }}"
                                                                        placeholder="{{ __('app.auth.birth_date_placeholder') }}"
                                                                        inputmode="numeric"
                                                                        pattern="\d{2}/\d{2}/\d{4}"
                                                                        maxlength="10"
                                                                        autocomplete="bday"
                                                                        data-student-birth-date
                                                                        data-student-birth-date-max="{{ now()->subDay()->toDateString() }}"
                                                                        required
                                                                    >
                                                                    <button type="button" class="registration-date-toggle" data-student-birth-date-open aria-label="{{ __('app.auth.open_date_picker') }}" title="{{ __('app.auth.open_date_picker') }}">
                                                                        <i class="ph ph-calendar-blank"></i>
                                                                    </button>
                                                                </div>
                                                                @error('birth_date')
                                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                @enderror
                                                            </div>

                                                            <button type="button" class="btn btn-danger registration-lookup-button registration-identity-submit" data-student-lookup-check>
                                                                <i class="ph ph-magnifying-glass"></i>
                                                                <span>{{ __('app.auth.check_national_id') }}</span>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div class="col-12">
                                                        <div class="registration-inline-feedback mb-3" data-student-lookup-message>{{ $errors->first('national_id') ?: $errors->first('birth_date') }}</div>
                                                    </div>

                                                    <div class="col-12 student-lookup-fields" data-student-lookup-fields @unless($studentLookupCompleted) hidden @endunless>
                                                        <div class="registration-verified-notice" role="status">
                                                            <i class="ph ph-lock-key"></i>
                                                            <span>{{ __('app.auth.verified_fields_locked') }}</span>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.full_name') }}</label>
                                                                    <input type="hidden" name="full_name" value="{{ old('registration_type') === 'student' ? old('full_name') : '' }}" data-student-lookup-hidden="full_name">
                                                                    <input type="text" class="form-control @error('full_name') is-invalid @enderror" placeholder="{{ __('app.auth.full_name_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('full_name') : '' }}" data-student-lookup-field="full_name" disabled>
                                                                    @error('full_name')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.gender') }}</label>
                                                                    <input type="hidden" name="gender" value="{{ old('registration_type') === 'student' ? old('gender') : '' }}" data-student-lookup-hidden="gender">
                                                                    <select class="form-control @error('gender') is-invalid @enderror" data-student-lookup-field="gender" disabled>
                                                                        <option value="">{{ __('app.auth.select_placeholder') }}</option>
                                                                        @foreach (['male', 'female'] as $gender)
                                                                            <option value="{{ $gender }}" @selected(old('registration_type') === 'student' && old('gender') === $gender)>{{ __('app.auth.gender_options.'.$gender) }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                    @error('gender')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.nationality') }}</label>
                                                                    <input type="hidden" name="nationality" value="{{ old('registration_type') === 'student' ? old('nationality') : '' }}" data-student-lookup-hidden="nationality">
                                                                    <input type="text" class="form-control @error('nationality') is-invalid @enderror" placeholder="{{ __('app.auth.nationality_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('nationality') : '' }}" data-student-lookup-field="nationality" disabled>
                                                                    @error('nationality')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.university_name') }}</label>
                                                                    <input type="hidden" name="university_name" value="{{ old('registration_type') === 'student' ? old('university_name') : '' }}" data-student-lookup-hidden="university_name">
                                                                    <input type="text" class="form-control @error('university_name') is-invalid @enderror" placeholder="{{ __('app.auth.university_name_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('university_name') : '' }}" data-student-lookup-field="university_name" disabled>
                                                                    @error('university_name')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.major') }}</label>
                                                                    <input type="hidden" name="major" value="{{ old('registration_type') === 'student' ? old('major') : '' }}" data-student-lookup-hidden="major">
                                                                    <input type="text" class="form-control @error('major') is-invalid @enderror" placeholder="{{ __('app.auth.major_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('major') : '' }}" data-student-lookup-field="major" disabled>
                                                                    @error('major')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 student-account-fields" data-student-account-fields @unless($studentLookupCompleted) hidden @endunless>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.email') }}</label>
                                                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="{{ __('app.auth.student_email_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('email') : '' }}" data-student-account-input @if($studentLookupCompleted) required @endif>
                                                                    @error('email')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.mobile_number') }}</label>
                                                                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" placeholder="{{ __('app.auth.phone_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('phone') : '' }}" inputmode="numeric" pattern="\d{10}" maxlength="10" data-student-account-input data-student-account-field="phone" @if($studentLookupCompleted) required @endif>
                                                                    @error('phone')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="student-address" class="form-label">{{ __('app.auth.address_labels.student') }}</label>
                                                                    <input id="student-address" type="text" name="address" class="form-control @error('address') is-invalid @enderror" placeholder="{{ __('app.auth.address_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('address') : '' }}" autocomplete="street-address" data-student-account-input @if($studentLookupCompleted) required @endif>
                                                                    @error('address')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="registration-logo-student" class="form-label custom-file-input">{{ __('app.auth.logo_label') }}</label>
                                                                    <input class="form-control @error('logo') is-invalid @enderror" type="file" id="registration-logo-student" name="logo" accept="image/png" data-student-account-input>
                                                                    @error('logo')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                    <div class="form-text">{{ __('app.auth.logo_help') }}</div>
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.password_label') }}</label>
                                                                    <div class="registration-password-control">
                                                                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ __('app.auth.password_placeholder') }}" data-password-strength data-student-account-input @if($studentLookupCompleted) required @endif>
                                                                        <button type="button" class="registration-password-toggle" data-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                                            <i class="ph ph-eye-slash"></i>
                                                                        </button>
                                                                    </div>
                                                                    @include('auth.partials.password-rules')
                                                                    @error('password')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.confirm_password') }}</label>
                                                                    <div class="registration-password-control">
                                                                        <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('app.auth.password_placeholder') }}" data-student-account-input @if($studentLookupCompleted) required @endif>
                                                                        <button type="button" class="registration-password-toggle" data-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                                            <i class="ph ph-eye-slash"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="registration-actions">
                                                    <button type="submit" id="register-submit-student" data-register-submit="student" class="btn btn-danger w-100" @disabled(! $studentLookupCompleted)>{{ $registrationTypes['student']['submit_label'] }}</button>
                                                </div>
                                            </form>
                                        </div>

                                        @foreach (['company', 'ngo', 'school'] as $type)
                                            <div class="tab-pane fade {{ $activeRegistrationType === $type ? 'show active' : '' }}" id="{{ $registrationTypes[$type]['tab_id'] }}" role="tabpanel" aria-labelledby="{{ $registrationTypes[$type]['tab_link_id'] }}">
                                                <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" id="register-form-{{ $type }}" data-register-form="{{ $type }}" @if($type === 'company') data-company-lookup-url="{{ route('register.company.lookup') }}" @endif>
                                                    @csrf
                                                    <input type="hidden" name="registration_type" value="{{ $type }}">
                                                    @if ($type === 'company')
                                                        <input type="hidden" name="company_lookup_verified" value="{{ $companyLookupCompleted ? '1' : '0' }}" data-company-lookup-verified>
                                                    @endif

                                                    <div class="row">
                                                        @if ($type === 'company')
                                                            <div class="col-12">
                                                                <div class="registration-identity-lookup registration-identity-lookup--company">
                                                                    <div class="registration-identity-field">
                                                                        <label class="form-label">{{ __('app.auth.organization_national_id') }}</label>
                                                                        <input type="text" name="registration_number" class="form-control @error('registration_number') is-invalid @enderror" placeholder="{{ __('app.auth.organization_national_id_placeholder') }}" value="{{ old('registration_type') === 'company' ? old('registration_number') : '' }}" inputmode="numeric" pattern="\d{1,10}" maxlength="10" autocomplete="off" data-company-registration-number required>
                                                                    </div>
                                                                    <button type="button" class="btn btn-danger registration-lookup-button registration-identity-submit" data-company-lookup-check>
                                                                            <i class="ph ph-magnifying-glass"></i>
                                                                            <span>{{ __('app.auth.check_registration_number') }}</span>
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <div class="col-12">
                                                                <div class="registration-inline-feedback mb-3" data-company-lookup-message>{{ $errors->first('registration_number') }}</div>
                                                                @error('registration_number')
                                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                @enderror
                                                            </div>

                                                            <div class="col-12 company-lookup-fields" data-company-lookup-fields @unless($companyLookupCompleted) hidden @endunless>
                                                                <div class="registration-verified-notice" role="status">
                                                                    <i class="ph ph-lock-key"></i>
                                                                    <span>{{ __('app.auth.verified_fields_locked') }}</span>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.entity_name_labels.company') }}</label>
                                                                            <input type="hidden" name="entity_name" value="{{ old('registration_type') === 'company' ? old('entity_name') : '' }}" data-company-lookup-hidden="entity_name">
                                                                            <input type="text" class="form-control @error('entity_name') is-invalid @enderror" placeholder="{{ __('app.auth.entity_name_placeholders.company') }}" value="{{ old('registration_type') === 'company' ? old('entity_name') : '' }}" data-company-lookup-field="entity_name" disabled>
                                                                            @error('entity_name')
                                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                            @enderror
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.company_registration_date') }}</label>
                                                                            <input type="hidden" name="company_registration_date" value="{{ old('registration_type') === 'company' ? old('company_registration_date') : '' }}" data-company-lookup-hidden="company_registration_date">
                                                                            <input type="date" class="form-control @error('company_registration_date') is-invalid @enderror" value="{{ old('registration_type') === 'company' ? old('company_registration_date') : '' }}" data-company-lookup-field="company_registration_date" disabled>
                                                                            @error('company_registration_date')
                                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                            @enderror
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.company_capital') }}</label>
                                                                            <input type="hidden" name="company_capital" value="{{ old('registration_type') === 'company' ? old('company_capital') : '' }}" data-company-lookup-hidden="company_capital">
                                                                            <input type="number" min="0" step="0.01" class="form-control @error('company_capital') is-invalid @enderror" placeholder="{{ __('app.auth.company_capital_placeholder') }}" value="{{ old('registration_type') === 'company' ? old('company_capital') : '' }}" data-company-lookup-field="company_capital" disabled>
                                                                            @error('company_capital')
                                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                            @enderror
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.organization_type') }}</label>
                                                                            <input type="hidden" name="organization_type" value="{{ old('registration_type') === 'company' ? old('organization_type') : '' }}" data-company-lookup-hidden="organization_type">
                                                                            <input type="text" class="form-control" value="{{ old('registration_type') === 'company' ? old('organization_type') : '' }}" data-company-lookup-field="organization_type" disabled>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.governorate') }}</label>
                                                                            <input type="hidden" name="governorate" value="{{ old('registration_type') === 'company' ? old('governorate') : '' }}" data-company-lookup-hidden="governorate">
                                                                            <input type="text" class="form-control" value="{{ old('registration_type') === 'company' ? old('governorate') : '' }}" data-company-lookup-field="governorate" disabled>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 company-account-fields" data-company-account-fields @unless($companyLookupCompleted) hidden @endunless>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.email') }}</label>
                                                                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="{{ __('app.auth.entity_email_placeholders.company') }}" value="{{ old('registration_type') === 'company' ? old('email') : '' }}" data-company-account-input @if($companyLookupCompleted) required @endif>
                                                                            @error('email')
                                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                            @enderror
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.mobile_number') }}</label>
                                                                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" placeholder="{{ __('app.auth.phone_placeholder') }}" value="{{ old('registration_type') === 'company' ? old('phone') : '' }}" inputmode="numeric" pattern="\d{10}" maxlength="10" data-company-account-input @if($companyLookupCompleted) required @endif>
                                                                            @error('phone')
                                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                            @enderror
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label for="company-address" class="form-label">{{ __('app.auth.address_labels.company') }}</label>
                                                                            <input id="company-address" type="text" name="address" class="form-control @error('address') is-invalid @enderror" placeholder="{{ __('app.auth.address_placeholder') }}" value="{{ old('registration_type') === 'company' ? old('address') : '' }}" autocomplete="street-address" data-company-account-input @if($companyLookupCompleted) required @endif>
                                                                            @error('address')
                                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                            @enderror
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <label for="registration-document-company" class="form-label custom-file-input">
                                                                            {{ __('app.auth.document_labels.company') }}
                                                                        </label>
                                                                        <input class="form-control @error('registration_document') is-invalid @enderror" type="file" id="registration-document-company" name="registration_document" data-company-account-input @if($companyLookupCompleted) required @endif>
                                                                        @error('registration_document')
                                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                        @enderror
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <label for="registration-logo-company" class="form-label custom-file-input">{{ __('app.auth.logo_label') }}</label>
                                                                        <input class="form-control @error('logo') is-invalid @enderror" type="file" id="registration-logo-company" name="logo" accept="image/png" data-company-account-input>
                                                                        @error('logo')
                                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                        @enderror
                                                                        <div class="form-text">{{ __('app.auth.logo_help') }}</div>
                                                                    </div>

                                                                    <div class="col-md-12">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.description_labels.company') }}</label>
                                                                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="5" placeholder="{{ __('app.auth.description_placeholders.company') }}">{{ old('registration_type') === 'company' ? old('description') : '' }}</textarea>
                                                                            @error('description')
                                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                            @enderror
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.password_label') }}</label>
                                                                            <div class="registration-password-control">
                                                                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ __('app.auth.password_placeholder') }}" data-password-strength data-company-account-input @if($companyLookupCompleted) required @endif>
                                                                                <button type="button" class="registration-password-toggle" data-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                                                    <i class="ph ph-eye-slash"></i>
                                                                                </button>
                                                                            </div>
                                                                            @include('auth.partials.password-rules')
                                                                            @error('password')
                                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                            @enderror
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">{{ __('app.auth.confirm_password') }}</label>
                                                                            <div class="registration-password-control">
                                                                                <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('app.auth.password_placeholder') }}" data-company-account-input @if($companyLookupCompleted) required @endif>
                                                                                <button type="button" class="registration-password-toggle" data-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                                                    <i class="ph ph-eye-slash"></i>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="col-md-12">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.entity_name_labels.'.$type) }}</label>
                                                                    <input type="text" name="entity_name" class="form-control @error('entity_name') is-invalid @enderror" placeholder="{{ __('app.auth.entity_name_placeholders.'.$type) }}" value="{{ old('registration_type') === $type ? old('entity_name') : '' }}" required>
                                                                    @error('entity_name')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.registration_number_labels.'.$type) }}</label>
                                                                    <input type="text" name="registration_number" class="form-control @error('registration_number') is-invalid @enderror" placeholder="{{ __('app.auth.registration_number_placeholders.'.$type) }}" value="{{ old('registration_type') === $type ? old('registration_number') : '' }}" required>
                                                                    @error('registration_number')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.email') }}</label>
                                                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="{{ __('app.auth.entity_email_placeholders.'.$type) }}" value="{{ old('registration_type') === $type ? old('email') : '' }}" required>
                                                                    @error('email')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.mobile_number') }}</label>
                                                                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" placeholder="{{ __('app.auth.phone_placeholder') }}" value="{{ old('registration_type') === $type ? old('phone') : '' }}" inputmode="numeric" pattern="\d{10}" maxlength="10" required>
                                                                    @error('phone')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="{{ $type }}-address" class="form-label">{{ __('app.auth.address_labels.'.$type) }}</label>
                                                                    <input id="{{ $type }}-address" type="text" name="address" class="form-control @error('address') is-invalid @enderror" placeholder="{{ __('app.auth.address_placeholder') }}" value="{{ old('registration_type') === $type ? old('address') : '' }}" autocomplete="street-address" required>
                                                                    @error('address')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-12">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.description_labels.'.$type) }}</label>
                                                                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="6" placeholder="{{ __('app.auth.description_placeholders.'.$type) }}">{{ old('registration_type') === $type ? old('description') : '' }}</textarea>
                                                                    @error('description')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-12">
                                                                <label for="registration-document-{{ $type }}" class="form-label custom-file-input">
                                                                    {{ __('app.auth.document_labels.'.$type) }}
                                                                </label>
                                                                <input class="form-control @error('registration_document') is-invalid @enderror" type="file" id="registration-document-{{ $type }}" name="registration_document" required>
                                                                @error('registration_document')
                                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                @enderror
                                                            </div>

                                                            <div class="col-md-12">
                                                                <label for="registration-logo-{{ $type }}" class="form-label custom-file-input">{{ __('app.auth.logo_label') }}</label>
                                                                <input class="form-control @error('logo') is-invalid @enderror" type="file" id="registration-logo-{{ $type }}" name="logo" accept="image/png">
                                                                @error('logo')
                                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                @enderror
                                                                <div class="form-text">{{ __('app.auth.logo_help') }}</div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.password_label') }}</label>
                                                                    <div class="registration-password-control">
                                                                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ __('app.auth.password_placeholder') }}" data-password-strength required>
                                                                        <button type="button" class="registration-password-toggle" data-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                                            <i class="ph ph-eye-slash"></i>
                                                                        </button>
                                                                    </div>
                                                                    @include('auth.partials.password-rules')
                                                                    @error('password')
                                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">{{ __('app.auth.confirm_password') }}</label>
                                                                    <div class="registration-password-control">
                                                                        <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('app.auth.password_placeholder') }}" required>
                                                                        <button type="button" class="registration-password-toggle" data-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                                            <i class="ph ph-eye-slash"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="registration-actions">
                                                        <button type="submit" id="register-submit-{{ $type }}" data-register-submit="{{ $type }}" class="btn btn-danger w-100" @if($type === 'company') @disabled(! $companyLookupCompleted) @endif>{{ $registrationTypes[$type]['submit_label'] }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="registration-secondary-action">
                                        <a href="{{ route('login') }}">{{ __('app.auth.back_to_login') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const registerMessages = {
                checkingNationalId: @json(__('app.auth.checking_national_id')),
                nationalIdDigits: @json(__('app.auth.national_id_digits')),
                birthDateRequired: @json(__('app.auth.student_birth_date_required')),
                birthDateInvalid: @json(__('app.auth.student_birth_date_invalid')),
                studentLookupSuccess: @json(__('app.auth.student_lookup_success')),
                studentLookupFailed: @json(__('app.auth.student_lookup_failed')),
                studentLookupRequired: @json(__('app.auth.student_lookup_required')),
                checkingRegistrationNumber: @json(__('app.auth.checking_registration_number')),
                registrationNumberRequired: @json(__('app.auth.registration_number_required')),
                organizationNationalIdDigits: @json(__('app.auth.organization_national_id_digits')),
                companyLookupSuccess: @json(__('app.auth.company_lookup_success')),
                companyLookupFailed: @json(__('app.auth.company_lookup_failed')),
                companyLookupRequired: @json(__('app.auth.company_lookup_required')),
                showPassword: @json(__('app.auth.show_password')),
                hidePassword: @json(__('app.auth.hide_password')),
                passwordInvalid: @json(__('app.auth.password_strength_invalid')),
            };
            const forms = document.querySelectorAll('[data-register-form]');

            if (!forms.length) {
                console.error('Register page initialization failed: no registration forms found.');
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const firstError = function (payload, fallback) {
                if (payload && payload.errors) {
                    const errorGroups = Object.values(payload.errors);

                    for (const group of errorGroups) {
                        if (Array.isArray(group) && group.length) {
                            return group[0];
                        }
                    }
                }

                return payload?.message || fallback || registerMessages.studentLookupFailed;
            };

            const digitsOnly = function (input) {
                input.addEventListener('input', function () {
                    const cleaned = input.value.replace(/\D+/g, '').slice(0, 10);

                    if (input.value !== cleaned) {
                        input.value = cleaned;
                    }
                });
            };

            document.querySelectorAll('input[pattern="\\d{10}"]').forEach(digitsOnly);

            const setupStudentBirthDateInput = function () {
                const input = document.querySelector('[data-student-birth-date]');
                const openButton = document.querySelector('[data-student-birth-date-open]');

                if (!input) {
                    return;
                }

                const initializePicker = function () {
                    if (input._flatpickr) {
                        return input._flatpickr;
                    }

                    if (typeof window.flatpickr !== 'function') {
                        return null;
                    }

                    return window.flatpickr(input, {
                        allowInput: true,
                        clickOpens: true,
                        dateFormat: 'd/m/Y',
                        disableMobile: true,
                        maxDate: input.dataset.studentBirthDateMax,
                    });
                };

                input.addEventListener('input', function () {
                    const digits = input.value.replace(/\D+/g, '').slice(0, 8);
                    const parts = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)].filter(Boolean);
                    const formatted = parts.join('/');

                    if (input.value !== formatted) {
                        input.value = formatted;
                    }
                });

                initializePicker();
                window.addEventListener('load', initializePicker, { once: true });

                openButton?.addEventListener('click', function (event) {
                    event.preventDefault();

                    const picker = initializePicker();

                    if (picker) {
                        picker.open();
                        return;
                    }

                    input.focus();
                });

                input.addEventListener('pointerdown', initializePicker);
                input.addEventListener('focus', initializePicker);
            };

            setupStudentBirthDateInput();

            const bindPasswordStrength = function (input) {
                const rules = input.closest('.mb-3')?.querySelector('[data-password-rules]');

                if (!rules) {
                    return;
                }

                const ruleItems = {
                    length: rules.querySelector('[data-password-rule="length"]'),
                    mixed: rules.querySelector('[data-password-rule="mixed"]'),
                    number: rules.querySelector('[data-password-rule="number"]'),
                    symbol: rules.querySelector('[data-password-rule="symbol"]'),
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

                    input.setCustomValidity(isStarted && !isValid ? registerMessages.passwordInvalid : '');
                };

                input.addEventListener('input', update);
                input.addEventListener('focus', update);
                update();
            };

            document.querySelectorAll('[data-password-strength]').forEach(bindPasswordStrength);

            document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
                const passwordInput = toggle.closest('.registration-password-control')?.querySelector('input');
                const icon = toggle.querySelector('i');

                if (!passwordInput || !icon) {
                    return;
                }

                toggle.addEventListener('click', function () {
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    const label = isPassword ? registerMessages.hidePassword : registerMessages.showPassword;

                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    icon.classList.toggle('ph-eye', isPassword);
                    icon.classList.toggle('ph-eye-slash', !isPassword);
                    toggle.setAttribute('aria-label', label);
                    toggle.setAttribute('title', label);
                });
            });

            const setupStudentLookup = function () {
                const form = document.getElementById('register-form-student');

                if (!form) {
                    return;
                }

                const nationalId = form.querySelector('[data-student-national-id]');
                const birthDate = form.querySelector('[data-student-birth-date]');
                const checkButton = form.querySelector('[data-student-lookup-check]');
                const message = form.querySelector('[data-student-lookup-message]');
                const verifiedInput = form.querySelector('[data-student-lookup-verified]');
                const profileSection = form.querySelector('[data-student-lookup-fields]');
                const accountSection = form.querySelector('[data-student-account-fields]');
                const submitButton = form.querySelector('[data-register-submit="student"]');
                const accountInputs = form.querySelectorAll('[data-student-account-input]');
                let verifiedNationalId = verifiedInput?.value === '1' ? nationalId?.value : '';
                let verifiedBirthDate = verifiedInput?.value === '1' ? birthDate?.value : '';
                let lookupFilledPhone = '';

                const focusBirthDate = function () {
                    if (birthDate?._flatpickr) {
                        birthDate._flatpickr.open();
                        return;
                    }

                    birthDate?.focus();
                };

                const hasValidBirthDate = function (value) {
                    const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value || '');

                    if (!match) {
                        return false;
                    }

                    const day = Number(match[1]);
                    const month = Number(match[2]);
                    const year = Number(match[3]);
                    const date = new Date(year, month - 1, day);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    return date.getFullYear() === year
                        && date.getMonth() === month - 1
                        && date.getDate() === day
                        && date < today;
                };

                const setMessage = function (text, state) {
                    if (!message) {
                        return;
                    }

                    message.textContent = text || '';
                    message.classList.toggle('is-error', state === 'error');
                    message.classList.toggle('is-success', state === 'success');
                };

                const setAccountRequired = function (isRequired) {
                    accountInputs.forEach(function (input) {
                        input.required = isRequired;
                    });
                };

                const fillLookupFields = function (data) {
                    Object.entries(data || {}).forEach(function ([key, value]) {
                        const safeValue = value || '';
                        const hidden = form.querySelector('[data-student-lookup-hidden="' + key + '"]');
                        const field = form.querySelector('[data-student-lookup-field="' + key + '"]');

                        if (hidden) {
                            hidden.value = safeValue;
                        }

                        if (field) {
                            field.value = safeValue;
                            field.disabled = true;
                        }
                    });

                    const phone = form.querySelector('[data-student-account-field="phone"]');
                    const apiPhone = data?.student_phone || '';

                    if (phone && apiPhone && (!phone.value || phone.value === lookupFilledPhone)) {
                        phone.value = apiPhone;
                        lookupFilledPhone = apiPhone;
                    }
                };

                const resetLookup = function () {
                    verifiedNationalId = '';
                    verifiedBirthDate = '';

                    const phone = form.querySelector('[data-student-account-field="phone"]');

                    if (phone && lookupFilledPhone && phone.value === lookupFilledPhone) {
                        phone.value = '';
                    }

                    lookupFilledPhone = '';

                    if (verifiedInput) {
                        verifiedInput.value = '0';
                    }

                    if (profileSection) {
                        profileSection.hidden = true;
                    }

                    if (accountSection) {
                        accountSection.hidden = true;
                    }

                    fillLookupFields({
                        full_name: '',
                        gender: '',
                        nationality: '',
                        university_name: '',
                        major: '',
                    });

                    setAccountRequired(false);

                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                };

                if (verifiedInput?.value === '1') {
                    setAccountRequired(true);
                } else {
                    resetLookup();
                }

                nationalId?.addEventListener('input', function () {
                    if (verifiedInput?.value === '1' && nationalId.value !== verifiedNationalId) {
                        resetLookup();
                        setMessage('', null);
                    }
                });

                const handleBirthDateChange = function () {
                    if (verifiedInput?.value === '1' && birthDate.value !== verifiedBirthDate) {
                        resetLookup();
                        setMessage('', null);
                    }
                };

                birthDate?.addEventListener('input', handleBirthDateChange);
                birthDate?.addEventListener('change', handleBirthDateChange);

                checkButton?.addEventListener('click', async function () {
                    const value = nationalId?.value || '';

                    if (!/^\d{10}$/.test(value)) {
                        resetLookup();
                        setMessage(registerMessages.nationalIdDigits, 'error');
                        nationalId?.focus();
                        return;
                    }

                    if (!birthDate?.value) {
                        resetLookup();
                        setMessage(registerMessages.birthDateRequired, 'error');
                        focusBirthDate();
                        return;
                    }

                    if (!hasValidBirthDate(birthDate.value)) {
                        resetLookup();
                        setMessage(registerMessages.birthDateInvalid, 'error');
                        focusBirthDate();
                        return;
                    }

                    checkButton.disabled = true;
                    setMessage(registerMessages.checkingNationalId, null);

                    try {
                        const response = await fetch(form.dataset.studentLookupUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                national_id: value,
                                birth_date: birthDate.value,
                            }),
                        });
                        const payload = await response.json();

                        if (!response.ok) {
                            resetLookup();
                            setMessage(firstError(payload, registerMessages.studentLookupFailed), 'error');
                            return;
                        }

                        fillLookupFields(payload.data || {});

                        if (verifiedInput) {
                            verifiedInput.value = '1';
                        }

                        verifiedNationalId = value;
                        verifiedBirthDate = birthDate.value;
                        profileSection.hidden = false;
                        accountSection.hidden = false;
                        setAccountRequired(true);

                        if (submitButton) {
                            submitButton.disabled = false;
                        }

                        setMessage(payload.message || registerMessages.studentLookupSuccess, 'success');
                    } catch (error) {
                        resetLookup();
                        setMessage(registerMessages.studentLookupFailed, 'error');
                    } finally {
                        checkButton.disabled = false;
                    }
                });

                form.addEventListener('submit', function (event) {
                    if (
                        verifiedInput?.value !== '1'
                        || nationalId?.value !== verifiedNationalId
                        || birthDate?.value !== verifiedBirthDate
                    ) {
                        event.preventDefault();
                        resetLookup();
                        setMessage(registerMessages.studentLookupRequired, 'error');
                        nationalId?.focus();
                    }
                });
            };

            setupStudentLookup();

            const setupCompanyLookup = function () {
                const form = document.getElementById('register-form-company');

                if (!form) {
                    return;
                }

                const registrationNumber = form.querySelector('[data-company-registration-number]');
                const checkButton = form.querySelector('[data-company-lookup-check]');
                const message = form.querySelector('[data-company-lookup-message]');
                const verifiedInput = form.querySelector('[data-company-lookup-verified]');
                const profileSection = form.querySelector('[data-company-lookup-fields]');
                const accountSection = form.querySelector('[data-company-account-fields]');
                const submitButton = form.querySelector('[data-register-submit="company"]');
                const accountInputs = form.querySelectorAll('[data-company-account-input]');
                let verifiedRegistrationNumber = verifiedInput?.value === '1' ? registrationNumber?.value : '';

                const normalizeRegistrationNumber = function (value) {
                    return (value || '').replace(/\D+/g, '');
                };

                const setMessage = function (text, state) {
                    if (!message) {
                        return;
                    }

                    message.textContent = text || '';
                    message.classList.toggle('is-error', state === 'error');
                    message.classList.toggle('is-success', state === 'success');
                };

                const setAccountRequired = function (isRequired) {
                    accountInputs.forEach(function (input) {
                        input.required = isRequired;
                    });
                };

                const fillLookupFields = function (data) {
                    Object.entries(data || {}).forEach(function ([key, value]) {
                        const safeValue = value || '';
                        const hidden = form.querySelector('[data-company-lookup-hidden="' + key + '"]');
                        const field = form.querySelector('[data-company-lookup-field="' + key + '"]');

                        if (hidden) {
                            hidden.value = safeValue;
                        }

                        if (field) {
                            field.value = safeValue;
                            field.disabled = true;
                        }
                    });
                };

                const resetLookup = function () {
                    verifiedRegistrationNumber = '';

                    if (verifiedInput) {
                        verifiedInput.value = '0';
                    }

                    if (profileSection) {
                        profileSection.hidden = true;
                    }

                    if (accountSection) {
                        accountSection.hidden = true;
                    }

                    fillLookupFields({
                        entity_name: '',
                        company_registration_date: '',
                        company_capital: '',
                        organization_type: '',
                        governorate: '',
                    });

                    setAccountRequired(false);

                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                };

                if (verifiedInput?.value === '1') {
                    setAccountRequired(true);
                } else {
                    resetLookup();
                }

                registrationNumber?.addEventListener('input', function () {
                    if (
                        verifiedInput?.value === '1'
                        && normalizeRegistrationNumber(registrationNumber.value) !== normalizeRegistrationNumber(verifiedRegistrationNumber)
                    ) {
                        resetLookup();
                        setMessage('', null);
                    }
                });

                checkButton?.addEventListener('click', async function () {
                    const value = normalizeRegistrationNumber(registrationNumber?.value || '');

                    if (!value) {
                        resetLookup();
                        setMessage(registerMessages.registrationNumberRequired, 'error');
                        registrationNumber?.focus();
                        return;
                    }

                    if (!/^\d{1,10}$/.test(value)) {
                        resetLookup();
                        setMessage(registerMessages.organizationNationalIdDigits, 'error');
                        registrationNumber?.focus();
                        return;
                    }

                    if (registrationNumber) {
                        registrationNumber.value = value;
                    }

                    checkButton.disabled = true;
                    setMessage(registerMessages.checkingRegistrationNumber, null);

                    try {
                        const response = await fetch(form.dataset.companyLookupUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ registration_number: value }),
                        });
                        const payload = await response.json();

                        if (!response.ok) {
                            resetLookup();
                            setMessage(firstError(payload, registerMessages.companyLookupFailed), 'error');
                            return;
                        }

                        fillLookupFields(payload.data || {});

                        if (payload.data?.registration_number && registrationNumber) {
                            registrationNumber.value = payload.data.registration_number;
                        }

                        if (verifiedInput) {
                            verifiedInput.value = '1';
                        }

                        verifiedRegistrationNumber = registrationNumber?.value || value;
                        profileSection.hidden = false;
                        accountSection.hidden = false;
                        setAccountRequired(true);

                        if (submitButton) {
                            submitButton.disabled = false;
                        }

                        setMessage(payload.message || registerMessages.companyLookupSuccess, 'success');
                    } catch (error) {
                        resetLookup();
                        setMessage(registerMessages.companyLookupFailed, 'error');
                    } finally {
                        checkButton.disabled = false;
                    }
                });

                form.addEventListener('submit', function (event) {
                    if (verifiedInput?.value !== '1') {
                        event.preventDefault();
                        resetLookup();
                        setMessage(registerMessages.companyLookupRequired, 'error');
                        registrationNumber?.focus();
                    }
                });
            };

            setupCompanyLookup();

            forms.forEach(function (form) {
                const type = form.dataset.registerForm;
                const submitButton = document.querySelector('[data-register-submit="' + type + '"]');

                if (!submitButton) {
                    console.error('Register page initialization failed: submit button missing for type:', type);
                    return;
                }

                const submitForm = function (source) {
                    console.log('Register submission requested from:', source, 'type:', type);

                    if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                        console.warn('Register form validation blocked submission for type:', type);
                        return;
                    }

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitButton);
                        return;
                    }

                    form.submit();
                };

                submitButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    submitForm('button-click');
                });

                form.addEventListener('submit', function () {
                    console.log('Register form submit event fired for type:', type);
                });
            });
        });
    </script>
@endpush
