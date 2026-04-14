@extends('layouts.auth', ['title' => __('app.auth.verify_title')])

@section('content')
    <div class="wrapper">
        <section class="sign-in-page" style="background-image: url('{{ asset('images/loginBg.jpeg') }}')">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-lg-5 col-md-12">
                        <div class="sign-user_card">
                            <a href="{{ route('home') }}">
                                <img class="img-fluid logo" src="{{ asset('images/logo.svg') }}" alt="#">
                            </a>
                            <div class="sign-in-page-data">
                                <div class="sign-in-from w-100 m-auto">
                                    @include('auth.partials.alerts')

                                    <h3 class="mb-3 text-center">{{ __('app.auth.verify_heading') }}</h3>
                                    <form class="mt-4" method="POST" action="{{ route('otp.store') }}" id="otp-form">
                                        @csrf
                                        <input type="hidden" name="code" id="otp-code" value="{{ old('code') }}">

                                        <div class="form-group mt-3 text-center">
                                            <label class="form-label mb-3">
                                                {{ __('app.auth.verify_intro', ['phone' => $maskedPhone]) }}
                                            </label>
                                            <div class="d-flex justify-content-center gap-2">
                                                @foreach (str_split(str_pad(old('code', ''), 5)) as $index => $digit)
                                                    <input type="text" maxlength="1" class="form-control text-center otp-input" value="{{ trim($digit) }}" data-index="{{ $index }}" />
                                                @endforeach
                                            </div>
                                        </div>

                                        @if ($debugCode)
                                            <div class="alert alert-success mt-3">{{ __('app.auth.debug_code') }} <strong>{{ $debugCode }}</strong></div>
                                        @endif

                                        <button type="submit" class="btn btn-danger w-100 custom-sign-btn mt-4">
                                            {{ __('app.auth.verify_submit') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-center links">
                                    {{ __('app.auth.no_account_question') }}
                                    <a href="{{ route('register') }}" class="text-danger {{ app()->getLocale() === 'ar' ? 'me-2' : 'ms-2' }}">{{ __('app.auth.create_account') }}</a>
                                </div>

                                <div class="d-flex justify-content-center links">
                                    <form method="POST" action="{{ route('otp.resend') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-link f-link text-danger p-0">{{ __('app.auth.resend_code') }}</button>
                                    </form>
                                </div>

                                <div class="d-flex justify-content-center links mt-2">
                                    <a href="{{ route('login') }}" class="f-link text-danger">{{ __('app.auth.back_to_login') }}</a>
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
        });
    </script>
@endpush
