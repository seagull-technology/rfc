@extends('layouts.auth', ['title' => __('app.auth.registration_submitted_title')])

@push('styles')
    <style nonce="{{ $cspNonce ?? '' }}">
        .registration-submitted-icon {
            align-items: center;
            background: rgba(25, 135, 84, .16);
            border: 1px solid rgba(117, 224, 162, .55);
            border-radius: 50%;
            color: #8ce0af;
            display: inline-flex;
            font-size: 3rem;
            height: 5rem;
            justify-content: center;
            margin: 1.5rem auto;
            width: 5rem;
        }

        .registration-submitted-copy {
            font-size: 1rem;
            line-height: 1.85;
            margin: 0 auto 1.75rem;
            max-width: 34rem;
        }
    </style>
@endpush

@section('content')
    <div class="wrapper">
        <section class="sign-in-page auth-visual-page">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-lg-6 col-md-10 col-12">
                        <div class="sign-user_card auth-visual-card text-center">
                            <a class="auth-brand-logo-badge" href="{{ route('login') }}">
                                <img class="img-fluid logo auth-brand-logo" src="{{ asset('images/rfc-logo-white.png') }}" alt="{{ config('app.name') }}">
                            </a>

                            <div class="registration-submitted-icon" aria-hidden="true">
                                <i class="ph ph-check-circle"></i>
                            </div>

                            <h1 class="h3 mb-3">{{ __('app.auth.registration_submitted_heading') }}</h1>
                            <p class="registration-submitted-copy">{{ __('app.auth.registration_submitted_next') }}</p>

                            <a href="{{ route('login') }}" class="btn btn-danger w-100 custom-sign-btn">
                                {{ __('app.auth.registration_submitted_login') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
