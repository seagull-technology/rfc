@php
    $title = __('app.admin.entities.create_title');
    $breadcrumb = __('app.admin.navigation.entities');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ __('app.admin.entities.create_title') }}</span>
            </h2>
            <div class="text-muted">{{ __('app.admin.entities.create_intro') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">{{ __('app.admin.navigation.dashboard') }}</a>
            <a class="btn btn-primary" href="{{ route('admin.entities.index') }}">{{ __('app.admin.navigation.entities') }}</a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.entities.create_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.entities.store') }}" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label for="group_id" class="form-label">{{ __('app.admin.entities.group') }}</label>
                            <select id="group_id" name="group_id" class="form-select" required>
                                <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}" @selected(old('group_id') == $group->id)>{{ $group->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="code" class="form-label">{{ __('app.admin.entities.code') }}</label>
                            <input id="code" name="code" type="text" class="form-control" value="{{ old('code') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="name_en" class="form-label">{{ __('app.admin.entities.name_en') }}</label>
                            <input id="name_en" name="name_en" type="text" class="form-control" value="{{ old('name_en') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="name_ar" class="form-label">{{ __('app.admin.entities.name_ar') }}</label>
                            <input id="name_ar" name="name_ar" type="text" class="form-control" value="{{ old('name_ar') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="registration_no" class="form-label">{{ __('app.auth.registration_number') }}</label>
                            <input id="registration_no" name="registration_no" type="text" class="form-control" value="{{ old('registration_no') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="national_id" class="form-label">{{ __('app.auth.organization_national_id') }}</label>
                            <input id="national_id" name="national_id" type="text" class="form-control" value="{{ old('national_id') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">{{ __('app.auth.email') }}</label>
                            <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">{{ __('app.auth.mobile_number') }}</label>
                            <input id="phone" name="phone" type="text" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="col-12">
                            <label for="status" class="form-label">{{ __('app.admin.entities.status') }}</label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>{{ __('app.statuses.active') }}</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>{{ __('app.statuses.inactive') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">{{ __('app.admin.entities.create_action') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
