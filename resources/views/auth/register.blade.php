@extends('layouts.auth', ['title' => __('app.auth.register_title')])

@section('content')
    <div class="wrapper">
        <section class="sign-in-page" style="background-image: url('{{ asset('images/loginBg.jpeg') }}')">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-lg-10 col-md-12">
                        <div class="sign-user_card">
                            <a href="{{ route('login') }}">
                                <img class="img-fluid logo" src="{{ asset('images/logo.svg') }}" alt="#">
                            </a>
                            <div class="sign-in-page-data pt-5">
                                <div class="sign-in-from w-100 m-auto">
                                    @include('auth.partials.alerts')

                                    <ul class="nav nav-pills mb-3 nav-fill" id="myTab-2" role="tablist">
                                        @foreach ($registrationTypes as $type => $config)
                                            <li class="nav-item">
                                                <a
                                                    class="nav-link p-4 {{ $activeRegistrationType === $type ? 'active' : '' }}"
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

                                    <div class="tab-content p-5 px-6" id="myTabContent-3">
                                        <div class="tab-pane fade {{ $activeRegistrationType === 'student' ? 'show active' : '' }}" id="home-justify" role="tabpanel" aria-labelledby="home-tab-justify">
                                            <form method="POST" action="{{ route('register.store') }}" id="register-form-student" data-register-form="student">
                                                @csrf
                                                <input type="hidden" name="registration_type" value="student">

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">{{ __('app.auth.full_name') }}</label>
                                                            <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" placeholder="{{ __('app.auth.full_name_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('full_name') : '' }}" required>
                                                            @error('full_name')
                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">{{ __('app.auth.email') }}</label>
                                                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="{{ __('app.auth.student_email_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('email') : '' }}" required>
                                                            @error('email')
                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">{{ __('app.auth.national_id') }}</label>
                                                            <input type="text" name="national_id" class="form-control @error('national_id') is-invalid @enderror" placeholder="{{ __('app.auth.national_id_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('national_id') : '' }}" required>
                                                            @error('national_id')
                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">{{ __('app.auth.mobile_number') }}</label>
                                                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" placeholder="{{ __('app.auth.phone_placeholder') }}" value="{{ old('registration_type') === 'student' ? old('phone') : '' }}" required>
                                                            @error('phone')
                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">{{ __('app.auth.password_label') }}</label>
                                                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ __('app.auth.password_placeholder') }}" required>
                                                            @error('password')
                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">{{ __('app.auth.confirm_password') }}</label>
                                                            <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('app.auth.password_placeholder') }}" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="submit mt-3">
                                                    <button type="submit" id="register-submit-student" data-register-submit="student" class="btn btn-danger w-100">{{ $registrationTypes['student']['submit_label'] }}</button>
                                                </div>
                                            </form>
                                        </div>

                                        @foreach (['company', 'ngo', 'school'] as $type)
                                            <div class="tab-pane fade {{ $activeRegistrationType === $type ? 'show active' : '' }}" id="{{ $registrationTypes[$type]['tab_id'] }}" role="tabpanel" aria-labelledby="{{ $registrationTypes[$type]['tab_link_id'] }}">
                                                <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" id="register-form-{{ $type }}" data-register-form="{{ $type }}">
                                                    @csrf
                                                    <input type="hidden" name="registration_type" value="{{ $type }}">

                                                    <div class="row">
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
                                                                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" placeholder="{{ __('app.auth.phone_placeholder') }}" value="{{ old('registration_type') === $type ? old('phone') : '' }}" required>
                                                                @error('phone')
                                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">{{ __('app.auth.address_labels.'.$type) }}</label>
                                                                <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" placeholder="{{ __('app.auth.address_placeholder') }}" value="{{ old('registration_type') === $type ? old('address') : '' }}" required>
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

                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">{{ __('app.auth.password_label') }}</label>
                                                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ __('app.auth.password_placeholder') }}" required>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">{{ __('app.auth.confirm_password') }}</label>
                                                                <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('app.auth.password_placeholder') }}" required>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="submit mt-3">
                                                        <button type="submit" id="register-submit-{{ $type }}" data-register-submit="{{ $type }}" class="btn btn-danger w-100">{{ $registrationTypes[$type]['submit_label'] }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        @endforeach
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
            const forms = document.querySelectorAll('[data-register-form]');

            if (!forms.length) {
                console.error('Register page initialization failed: no registration forms found.');
                return;
            }

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

                    if (submitButton.dataset.submitting === 'true') {
                        console.warn('Register form submission already in progress for type:', type);
                        return;
                    }

                    submitButton.dataset.submitting = 'true';
                    submitButton.disabled = true;

                    window.setTimeout(function () {
                        submitButton.disabled = false;
                        submitButton.dataset.submitting = 'false';
                    }, 8000);

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
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
