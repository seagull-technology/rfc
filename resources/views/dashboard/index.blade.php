@extends('layouts.auth', ['title' => __('app.dashboard.title')])

@section('content')
    <h1>{{ __('app.dashboard.title') }}</h1>
    <p>{{ __('app.dashboard.intro') }}</p>

    <div class="grid">
        <div class="tile">
            <strong>{{ __('app.dashboard.signed_in_user') }}</strong>
            {{ $user->name }}<br>
            {{ $user->email }}<br>
            {{ $user->national_id ?? __('app.dashboard.no_national_id') }}
        </div>

        <div class="tile">
            <strong>{{ __('app.dashboard.current_entity') }}</strong>
            {{ $entity?->{app()->getLocale() === 'ar' ? 'name_ar' : 'name_en'} ?? __('app.dashboard.no_entity') }}<br>
            {{ __('app.dashboard.group') }}: {{ $group?->{app()->getLocale() === 'ar' ? 'name_ar' : 'name_en'} ?? __('app.dashboard.not_available') }}
        </div>

        <div class="tile">
            <strong>{{ __('app.dashboard.assigned_roles') }}</strong>
            {{ $roles->isNotEmpty() ? $roles->join(', ') : __('app.dashboard.no_roles') }}
        </div>

        <div class="tile">
            <strong>{{ __('app.dashboard.next_steps') }}</strong>
            {{ __('app.dashboard.next_steps_text') }}
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="actions" style="margin-top: 20px;">
        @csrf
        <button class="btn btn-secondary" type="submit">{{ __('app.dashboard.logout') }}</button>
    </form>
@endsection
