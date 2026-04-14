@extends('layouts.auth', ['title' => __('app.auth.individual_register_title')])

@section('content')
    <h1>{{ __('app.auth.individual_register_title') }}</h1>
    <p>{{ __('app.auth.individual_register_intro') }}</p>

    <form method="POST" action="{{ route('register.individual.store') }}" class="grid">
        @csrf

        <div class="grid-2">
            <div>
                <label for="name">{{ __('app.auth.full_name') }}</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required>
            </div>

            <div>
                <label for="username">{{ __('app.auth.username') }}</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" required>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label for="email">{{ __('app.auth.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            </div>

            <div>
                <label for="phone">{{ __('app.auth.mobile_number') }}</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone') }}" required>
            </div>
        </div>

        <div>
            <label for="national_id">{{ __('app.auth.national_id') }}</label>
            <input id="national_id" name="national_id" type="text" value="{{ old('national_id') }}" required>
        </div>

        <div class="grid-2">
            <div>
                <label for="password">{{ __('app.auth.password') }}</label>
                <input id="password" name="password" type="password" required>
            </div>

            <div>
                <label for="password_confirmation">{{ __('app.auth.confirm_password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required>
            </div>
        </div>

        <div class="actions">
            <button class="btn" type="submit">{{ __('app.auth.create_individual_account') }}</button>
            <a class="btn btn-secondary" href="{{ route('register') }}">{{ __('app.auth.back') }}</a>
        </div>
    </form>
@endsection
