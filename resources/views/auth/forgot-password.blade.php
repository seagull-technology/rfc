@extends('layouts.auth', ['title' => __('app.auth.forgot_password_title')])

@section('content')
    <div class="wrapper">
        <section class="sign-in-page auth-visual-page">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-lg-5 col-md-12">
                        <div class="sign-user_card auth-visual-card">
                            <a class="auth-brand-logo-badge" href="{{ route('login') }}">
                                <img class="img-fluid logo auth-brand-logo" src="{{ asset('images/rfc-logo-white.png') }}" alt="{{ config('app.name') }}">
                            </a>
                            <div class="sign-in-page-data">
                                <div class="sign-in-from w-100 m-auto">
                                    @include('auth.partials.alerts')

                                    <h3 class="mb-3 text-center">{{ __('app.auth.forgot_password_heading') }}</h3>
                                    <p class="text-center mb-4">{{ __('app.auth.forgot_password_intro') }}</p>

                                    <form method="POST" action="{{ route('password.otp.send') }}" id="password-reset-request-form">
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

                                        <div class="auth-submit">
                                            <button type="submit" class="btn btn-danger w-100 custom-sign-btn">
                                                {{ __('app.auth.send_reset_code') }}
                                            </button>
                                        </div>

                                        <div class="d-flex justify-content-center align-items-center gap-2 links my-3">
                                            <a href="{{ route('login') }}" class="auth-secondary-link">
                                                {{ __('app.auth.back_to_login') }}
                                            </a>
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
