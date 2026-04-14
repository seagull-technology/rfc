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

                                    <h3 class="mb-3 text-center">{{ __('app.auth.reset_password_heading') }}</h3>
                                    <p class="text-center mb-4">{{ __('app.auth.reset_password_intro') }}</p>

                                    <form method="POST" action="{{ route('password.store') }}">
                                        @csrf
                                        <input type="hidden" name="token" value="{{ $token }}">

                                        <div class="mb-3">
                                            <label for="email" class="mb-2">{{ __('app.auth.email') }}</label>
                                            <input
                                                required
                                                type="email"
                                                id="email"
                                                name="email"
                                                value="{{ old('email', $email) }}"
                                                class="mb-0 form-control @error('email') is-invalid @enderror"
                                            />
                                            @error('email')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="password" class="mb-2">{{ __('app.auth.password_label') }}</label>
                                            <input
                                                required
                                                type="password"
                                                id="password"
                                                name="password"
                                                class="mb-0 form-control @error('password') is-invalid @enderror"
                                            />
                                            @error('password')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="password_confirmation" class="mb-2">{{ __('app.auth.password_confirmation_label') }}</label>
                                            <input
                                                required
                                                type="password"
                                                id="password_confirmation"
                                                name="password_confirmation"
                                                class="mb-0 form-control"
                                            />
                                        </div>

                                        <div class="submit">
                                            <button type="submit" class="btn btn-danger w-100 custom-sign-btn">
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
@endsection
