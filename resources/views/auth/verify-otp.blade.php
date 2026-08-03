@extends('layouts.auth', ['title' => $pageTitle ?? __('app.auth.verify_title')])

@php
    $resendSeconds = max(0, (int) ($resendAvailableIn ?? 0));
    $resendCountdown = sprintf('%02d:%02d', intdiv($resendSeconds, 60), $resendSeconds % 60);
@endphp

@section('content')
    <div class="wrapper">
        <section class="sign-in-page auth-visual-page">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-lg-5 col-md-12">
                        <div class="sign-user_card auth-visual-card">
                            <a class="auth-brand-logo-badge" href="{{ route('home') }}">
                                <img class="img-fluid logo auth-brand-logo" src="{{ asset('images/rfc-logo-white.png') }}" alt="{{ config('app.name') }}">
                            </a>
                            <div class="sign-in-page-data">
                                <div class="sign-in-from w-100 m-auto">
                                    @include('auth.partials.alerts')

                                    <h3 class="mb-3 text-center">{{ $heading ?? __('app.auth.verify_heading') }}</h3>
                                    <form class="mt-4" method="POST" action="{{ $formAction ?? route('otp.store') }}" id="otp-form">
                                        @csrf
                                        <input type="hidden" name="code" id="otp-code" value="{{ old('code') }}">

                                        <div class="form-group mt-3 text-center">
                                            <label class="form-label mb-3">
                                                {{ $intro ?? __('app.auth.verify_intro', ['phone' => $maskedPhone]) }}
                                            </label>
                                            @php
                                                $oldOtpCode = preg_replace('/\D/', '', (string) old('code', ''));
                                                $oldOtpCode = substr($oldOtpCode, 0, 5);
                                                $otpDigits = str_split(str_pad($oldOtpCode, 5));
                                                $otpFocusIndex = strlen($oldOtpCode) >= 5 ? 0 : strlen($oldOtpCode);
                                            @endphp
                                            <div class="d-flex justify-content-center gap-2">
                                                @foreach ($otpDigits as $index => $digit)
                                                    <input
                                                        type="text"
                                                        maxlength="1"
                                                        inputmode="numeric"
                                                        pattern="[0-9]*"
                                                        autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}"
                                                        class="form-control text-center otp-input"
                                                        value="{{ trim($digit) }}"
                                                        data-index="{{ $index }}"
                                                        @if ($index === $otpFocusIndex) autofocus data-otp-autofocus="true" @endif
                                                    />
                                                @endforeach
                                            </div>
                                        </div>

                                        @if ($debugCode)
                                            <div class="alert alert-success mt-3">{{ __('app.auth.debug_code') }} <strong>{{ $debugCode }}</strong></div>
                                        @endif

                                        <button type="submit" class="btn btn-danger w-100 custom-sign-btn mt-4">
                                            {{ $submitLabel ?? __('app.auth.verify_submit') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="mt-2">
                                @if ($showRegistrationLink ?? true)
                                    <div class="d-flex justify-content-center links">
                                        {{ __('app.auth.no_account_question') }}
                                        <a href="{{ route('register') }}" class="auth-secondary-link {{ app()->getLocale() === 'ar' ? 'me-2' : 'ms-2' }}">{{ __('app.auth.create_account') }}</a>
                                    </div>
                                @endif

                                <div class="d-flex flex-column align-items-center justify-content-center links">
                                    <form
                                        method="POST"
                                        action="{{ $resendAction ?? route('otp.resend') }}"
                                        id="otp-resend-form"
                                        data-resend-seconds="{{ $resendSeconds }}"
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            class="btn btn-link f-link auth-secondary-link p-0"
                                            id="otp-resend-button"
                                            @disabled($resendSeconds > 0)
                                        >
                                            <span id="otp-resend-label">{{ __('app.auth.resend_code') }}</span>
                                        </button>
                                    </form>
                                    <div
                                        id="otp-resend-countdown"
                                        class="auth-resend-countdown"
                                        @if ($resendSeconds <= 0) hidden @endif
                                    >
                                        {{ __('app.auth.resend_available_in') }}
                                        <span id="otp-resend-time" dir="ltr">{{ $resendCountdown }}</span>
                                    </div>
                                    @error('resend')
                                        <div class="alert alert-danger py-2 mt-2 mb-0">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-flex justify-content-center links mt-2">
                                    <a href="{{ route('login') }}" class="f-link auth-secondary-link">{{ __('app.auth.back_to_login') }}</a>
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
    <script nonce="{{ $cspNonce ?? '' }}">
        document.addEventListener('DOMContentLoaded', function () {
            const inputs = Array.from(document.querySelectorAll('.otp-input'));
            const hiddenCode = document.getElementById('otp-code');
            const form = document.getElementById('otp-form');

            const syncCode = function () {
                hiddenCode.value = inputs.map(function (input) {
                    return input.value.trim();
                }).join('');
            };

            inputs.forEach(function (input, index) {
                input.addEventListener('input', function () {
                    input.value = input.value.replace(/\D/g, '').slice(0, 1);
                    syncCode();

                    if (input.value && inputs[index + 1]) {
                        inputs[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && !input.value && inputs[index - 1]) {
                        inputs[index - 1].focus();
                    }
                });
            });

            form.addEventListener('submit', syncCode);
            syncCode();

            const focusTarget = inputs.find(function (input) {
                return input.dataset.otpAutofocus === 'true';
            }) || inputs[0];

            if (focusTarget) {
                window.setTimeout(function () {
                    focusTarget.focus({ preventScroll: true });
                    focusTarget.select();
                }, 0);
            }

            const resendForm = document.getElementById('otp-resend-form');
            const resendButton = document.getElementById('otp-resend-button');
            const resendLabel = document.getElementById('otp-resend-label');
            const countdown = document.getElementById('otp-resend-countdown');
            const countdownTime = document.getElementById('otp-resend-time');

            if (resendForm && resendButton && countdown && countdownTime) {
                const initialSeconds = Math.max(
                    0,
                    Number.parseInt(resendForm.dataset.resendSeconds || '0', 10) || 0
                );
                const availableAt = Date.now() + (initialSeconds * 1000);
                let timerId = null;

                const renderCountdown = function () {
                    const seconds = Math.max(0, Math.ceil((availableAt - Date.now()) / 1000));
                    const minutes = String(Math.floor(seconds / 60)).padStart(2, '0');
                    const remainder = String(seconds % 60).padStart(2, '0');

                    countdownTime.textContent = minutes + ':' + remainder;
                    resendButton.disabled = seconds > 0;
                    countdown.hidden = seconds <= 0;

                    if (seconds <= 0 && timerId !== null) {
                        window.clearInterval(timerId);
                        timerId = null;
                    }
                };

                renderCountdown();

                if (initialSeconds > 0) {
                    timerId = window.setInterval(renderCountdown, 250);
                }

                resendForm.addEventListener('submit', function () {
                    if (resendButton.disabled) {
                        return;
                    }

                    resendButton.disabled = true;

                    if (resendLabel) {
                        resendLabel.textContent = @json(__('app.auth.resending_code'));
                    }
                });
            }
        });
    </script>
@endpush
