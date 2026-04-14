@extends('layouts.auth', ['title' => __('app.auth.login_title')])

@push('styles')
    <style>
        .login-locale-switcher {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
        }

        .login-locale-switcher a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 88px;
            padding: 9px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(29, 29, 29, 0.36);
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        .login-locale-switcher a.is-active {
            border-color: #b52f1d;
            background: #b52f1d;
        }

        .login-locale-switcher a:hover {
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.32);
        }
    </style>
@endpush

@section('content')
    <div class="wrapper">
        <section class="sign-in-page" style="background-image: url('{{ asset('images/loginBg.jpeg') }}')">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-lg-5 col-md-12">
                        <div class="sign-user_card">
                            <div class="login-locale-switcher">
                                <a
                                    href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL('en', route('login', [], false), [], true) }}"
                                    class="{{ app()->getLocale() === 'en' ? 'is-active' : '' }}"
                                >
                                    {{ __('app.meta.english') }}
                                </a>
                                <a
                                    href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL('ar', route('login', [], false), [], true) }}"
                                    class="{{ app()->getLocale() === 'ar' ? 'is-active' : '' }}"
                                >
                                    {{ __('app.meta.arabic') }}
                                </a>
                            </div>
                            <a href="#">
                                <img class="img-fluid logo" src="{{ asset('images/logo.svg') }}" alt="#">
                            </a>
                            <div class="sign-in-page-data">
                                <div class="sign-in-from w-100 m-auto">
                                    @include('auth.partials.alerts')

                                    <h3 class="mb-3 text-center">{{ __('app.auth.login_heading') }}</h3>
                                    <form method="POST" action="{{ route('login.store') }}" id="login-form">
                                        @csrf

                                        <div class="mb-3">
                                            <label for="identifier" class="mb-2">{{ __('app.auth.login_identifier_label') }}</label>
                                            <input
                                                placeholder="{{ __('app.auth.login_identifier_placeholder') }}"
                                                autocomplete="off"
                                                required
                                                type="text"
                                                id="identifier"
                                                name="identifier"
                                                value="{{ old('identifier') }}"
                                                class="mb-0 form-control @error('identifier') is-invalid @enderror"
                                            />
                                            @error('identifier')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="password" class="mb-2">{{ __('app.auth.password_label') }}</label>
                                            <div class="input-group custom-input-group mb-3">
                                                <input
                                                    placeholder="{{ __('app.auth.password_placeholder') }}"
                                                    required
                                                    type="password"
                                                    id="password"
                                                    name="password"
                                                    class="mb-0 form-control @error('password') is-invalid @enderror"
                                                >
                                                <span class="input-group-text"><i class="ph ph-eye-slash" id="togglePassword"></i></span>
                                            </div>
                                            @error('password')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="forgot-password">
                                            <a href="#">{{ __('app.auth.forgot_password') }}</a>
                                        </div>

                                        <div class="submit">
                                            <input type="hidden" name="action" value="streamit_login">
                                            <button type="submit" id="login-submit-button" class="btn btn-danger w-100 custom-sign-btn">
                                                {{ __('app.auth.login_submit') }}
                                            </button>
                                        </div>

                                        <div class="css_prefix-separator">
                                            <span class="or-section">{{ __('app.auth.login_with') }}</span>
                                        </div>

                                        <div class="d-flex justify-content-center align-items-center">
                                            <a href="javascript:void(0)" class="text-center">
                                                <img src="{{ asset('images/sanad.png') }}" class="w-30" style="width: 30%;" alt="Sanad">
                                            </a>
                                        </div>

                                        <div class="login-form-bottom">
                                            <div class="d-flex justify-content-center align-items-center gap-2 links my-3">
                                                {{ __('app.auth.new_user_question') }}
                                                <a href="{{ route('register') }}" class="st-sub-card setting-dropdown">
                                                    <h6 class="text-danger m-0">{{ __('app.auth.create_account') }}</h6>
                                                </a>
                                            </div>
                                        </div>
                                    </form>
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
            const toggle = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const form = document.getElementById('login-form');
            const submitButton = document.getElementById('login-submit-button');

            if (!toggle || !passwordInput) {
                console.error('Login page initialization failed: password toggle elements are missing.');
            } else {
                toggle.addEventListener('click', function () {
                    const isPassword = passwordInput.getAttribute('type') === 'password';

                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    toggle.classList.toggle('ph-eye');
                    toggle.classList.toggle('ph-eye-slash');
                });
            }

            if (!form || !submitButton) {
                console.error('Login page initialization failed: form elements are missing.');
                return;
            }

            const submitForm = function (source) {
                console.log('Login submission requested from:', source);

                if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                    console.warn('Login form validation blocked submission.');
                    return;
                }

                if (submitButton.dataset.submitting === 'true') {
                    console.warn('Login form submission already in progress.');
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
                console.log('Login form submit event fired.');
            });
        });
    </script>
@endpush
