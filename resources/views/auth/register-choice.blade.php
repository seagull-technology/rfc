@extends('layouts.auth', ['title' => __('app.auth.register_title')])

@section('content')
    <h1>{{ __('app.auth.register_title') }}</h1>
    <p>{{ __('app.auth.register_intro') }}</p>

    <div class="grid-2">
        <a class="tile" href="{{ route('register.individual.create') }}">
            <strong>{{ __('app.auth.individual_tile_title') }}</strong>
            {{ __('app.auth.individual_tile_text') }}
        </a>

        <a class="tile" href="{{ route('register.organization.create') }}">
            <strong>{{ __('app.auth.organization_tile_title') }}</strong>
            {{ __('app.auth.organization_tile_text') }}
        </a>
    </div>

    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('login') }}">{{ __('app.auth.back_to_login') }}</a>
    </div>
@endsection
