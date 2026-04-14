@extends('layouts.auth', ['title' => __('app.auth.forgot_password_title')])

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

                                    <h3 class="mb-3 text-center">{{ __('app.auth.forgot_password_heading') }}</h3>
                                    <p class="text-center mb-4">{{ __('app.auth.forgot_password_intro') }}</p>

                                    <form method="POST" action="{{ route('password.email') }}">
                                        @csrf

                                        <div class="mb-3">
                                            <label for="email" class="mb-2">{{ __('app.auth.email') }}</label>
                                            <input
                                                placeholder="{{ __('app.auth.email') }}"
                                                autocomplete="email"
                                                required
                                                type="email"
                                                id="email"
                                                name="email"
                                                value="{{ old('email') }}"
                                                class="mb-0 form-control @error('email') is-invalid @enderror"
                                            />
                                            @error('email')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="submit">
                                            <button type="submit" class="btn btn-danger w-100 custom-sign-btn">
                                                {{ __('app.auth.send_reset_link') }}
                                            </button>
                                        </div>

                                        <div class="d-flex justify-content-center align-items-center gap-2 links my-3">
                                            <a href="{{ route('login') }}" class="text-danger">
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
