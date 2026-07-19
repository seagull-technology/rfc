@extends('layouts.auth', ['title' => __('app.auth.reset_password_title')])

@section('content')
    <div class="wrapper">
        <section class="sign-in-page" style="background-image: url('{{ asset('images/loginBg.jpeg') }}')">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-lg-5 col-md-12">
                        <div class="sign-user_card">
                            <a href="{{ route('login') }}">
                                <img class="img-fluid logo" src="{{ asset('images/logo.svg') }}" alt="#">
                            </a>
                            <div class="sign-in-page-data">
                                <div class="sign-in-from w-100 m-auto">
                                    @include('auth.partials.alerts')

                                    <h3 class="mb-3 text-center">
                                        {{ $isInvitation ? __('app.auth.activate_account_heading') : __('app.auth.reset_password_heading') }}
                                    </h3>
                                    <p class="text-center mb-4">
                                        {{ $isInvitation ? __('app.auth.activate_account_intro') : __('app.auth.reset_password_intro') }}
                                    </p>

                                    <form
                                        method="POST"
                                        action="{{ route('password.store') }}"
                                        data-password-reset-form
                                    >
                                        @csrf
                                        <input type="hidden" name="token" value="{{ $token }}">

                                        <div class="mb-3">
                                            <label for="email" class="mb-2">{{ __('app.auth.email') }}</label>
                                            <input
                                                required
                                                type="email"
                                                id="email"
                                                name="email"
                                                value="{{ $isInvitation ? $email : old('email', $email) }}"
                                                @if ($isInvitation)
                                                    readonly
                                                    aria-readonly="true"
                                                @endif
                                                class="mb-0 form-control @if ($isInvitation) bg-body-secondary @endif @error('email') is-invalid @enderror"
                                            />
                                            @error('email')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3" data-secure-password-field>
                                            <label for="password" class="mb-2">{{ __('app.auth.password_label') }}</label>
                                            <div class="position-relative">
                                                <input
                                                    required
                                                    type="password"
                                                    id="password"
                                                    name="password"
                                                    autocomplete="new-password"
                                                    class="mb-0 form-control pe-5 @error('password') is-invalid @enderror"
                                                    data-secure-password
                                                />
                                                <button class="btn position-absolute top-50 end-0 translate-middle-y border-0" type="button" data-password-visibility aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                    <i class="ph ph-eye-slash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                            <ul class="small mt-2 mb-0 ps-3" data-password-rules>
                                                <li data-password-rule="length">{{ __('app.auth.password_rule_length') }}</li>
                                                <li data-password-rule="mixed">{{ __('app.auth.password_rule_mixed') }}</li>
                                                <li data-password-rule="number">{{ __('app.auth.password_rule_number') }}</li>
                                                <li data-password-rule="symbol">{{ __('app.auth.password_rule_symbol') }}</li>
                                            </ul>
                                            @error('password')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="password_confirmation" class="mb-2">{{ __('app.auth.password_confirmation_label') }}</label>
                                            <div class="position-relative">
                                                <input
                                                    required
                                                    type="password"
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    autocomplete="new-password"
                                                    class="mb-0 form-control pe-5"
                                                />
                                                <button class="btn position-absolute top-50 end-0 translate-middle-y border-0" type="button" data-password-visibility aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                    <i class="ph ph-eye-slash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="submit">
                                            <button
                                                type="submit"
                                                class="btn btn-danger w-100 custom-sign-btn"
                                                data-password-reset-submit
                                            >
                                                {{ __('app.auth.reset_password_submit') }}
                                            </button>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-password-reset-form]');
            const password = document.querySelector('[data-secure-password]');
            const passwordConfirmation = document.getElementById('password_confirmation');
            const submitButton = document.querySelector('[data-password-reset-submit]');
            let submitting = false;

            document.querySelectorAll('[data-password-visibility]').forEach((button) => {
                button.addEventListener('click', () => {
                    const input = button.parentElement?.querySelector('input');
                    const icon = button.querySelector('i');

                    if (! input) return;

                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    button.setAttribute('aria-label', show ? @js(__('app.auth.hide_password')) : @js(__('app.auth.show_password')));
                    button.setAttribute('title', show ? @js(__('app.auth.hide_password')) : @js(__('app.auth.show_password')));
                    icon?.classList.toggle('ph-eye', show);
                    icon?.classList.toggle('ph-eye-slash', ! show);
                });
            });

            const updateRules = () => {
                const value = password?.value ?? '';
                const checks = {
                    length: value.length >= 8,
                    mixed: /[a-z]/.test(value) && /[A-Z]/.test(value),
                    number: /\d/.test(value),
                    symbol: /[^A-Za-z0-9]/.test(value),
                };

                Object.entries(checks).forEach(([rule, passed]) => {
                    const item = document.querySelector(`[data-password-rule="${rule}"]`);
                    item?.classList.toggle('text-success', passed);
                    item?.classList.toggle('text-muted', ! passed);
                });
            };

            password?.addEventListener('input', updateRules);
            updateRules();

            const updateConfirmationValidity = () => {
                if (! passwordConfirmation) return true;

                const matches = passwordConfirmation.value === (password?.value ?? '');
                passwordConfirmation.setCustomValidity(
                    matches ? '' : @js(__('validation.confirmed', ['attribute' => __('app.auth.password_label')]))
                );

                return matches;
            };

            password?.addEventListener('input', updateConfirmationValidity);
            passwordConfirmation?.addEventListener('input', updateConfirmationValidity);

            const submitForm = () => {
                if (! form || submitting) return;

                updateConfirmationValidity();

                if (! form.reportValidity()) return;

                submitting = true;

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.setAttribute('aria-busy', 'true');
                }

                HTMLFormElement.prototype.submit.call(form);
            };

            submitButton?.addEventListener('click', (event) => {
                event.preventDefault();
                submitForm();
            });

            form?.addEventListener('submit', (event) => {
                event.preventDefault();
                submitForm();
            });
        });
    </script>
@endsection
