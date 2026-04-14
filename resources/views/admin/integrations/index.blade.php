@php
    $title = __('app.admin.integrations.title');
    $breadcrumb = __('app.admin.navigation.integrations');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ __('app.admin.integrations.title') }}</span>
            </h2>
            <div class="text-muted">{{ __('app.admin.integrations.intro') }}</div>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">{{ __('app.admin.navigation.dashboard') }}</a>
    </div>

    @if (($results['company_registry']['error'] ?? null) === 'CONNECTION_FAILED')
        <div class="alert alert-warning mb-4">
            <strong>{{ __('app.admin.integrations.connection_issue_title') }}</strong><br>
            {{ __('app.admin.integrations.connection_issue_text') }}
        </div>
    @endif

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.integrations.sms_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.integrations.base_url') }}</small>
                            <div>{{ $smsConfig['base'] ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.integrations.username_configured') }}</small>
                            <div>{{ $smsConfig['username_configured'] ? __('app.admin.yes') : __('app.admin.no') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.integrations.password_configured') }}</small>
                            <div>{{ $smsConfig['password_configured'] ? __('app.admin.yes') : __('app.admin.no') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.integrations.sms_header') }}</small>
                            <div>{{ $smsConfig['header'] ?: __('app.dashboard.not_available') }}</div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.integrations.sms-test') }}" class="row g-3">
                        @csrf

                        <div class="col-md-5">
                            <label for="phone" class="form-label">{{ __('app.auth.mobile_number') }}</label>
                            <input id="phone" name="phone" type="text" class="form-control" value="{{ old('phone') }}" required>
                        </div>
                        <div class="col-md-7">
                            <label for="message" class="form-label">{{ __('app.admin.integrations.test_message') }}</label>
                            <input id="message" name="message" type="text" class="form-control" value="{{ old('message', 'RFC integration test message.') }}">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">{{ __('app.admin.integrations.send_sms_test') }}</button>
                        </div>
                    </form>

                    @if ($results['sms'])
                        <div class="border rounded p-3 mt-4">
                            <h6 class="mb-3">{{ __('app.admin.integrations.last_sms_result') }}</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.admin.integrations.result') }}</small>
                                    <div>{{ $results['sms']['ok'] ? __('app.admin.integrations.success') : __('app.admin.integrations.failed') }}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.admin.integrations.stage') }}</small>
                                    <div>{{ $results['sms']['stage'] ?? __('app.dashboard.not_available') }}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.admin.integrations.http_status') }}</small>
                                    <div>{{ $results['sms']['http'] ?? __('app.dashboard.not_available') }}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.admin.integrations.normalized_phone') }}</small>
                                    <div>{{ $results['sms']['msisdn'] ?? __('app.dashboard.not_available') }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.integrations.company_registry_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.integrations.lookup_enabled') }}</small>
                            <div>{{ $companyRegistryConfig['enabled'] ? __('app.admin.yes') : __('app.admin.no') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.integrations.host') }}</small>
                            <div>{{ $companyRegistryConfig['host'] ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.integrations.path') }}</small>
                            <div>{{ $companyRegistryConfig['path'] ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.integrations.client_credentials') }}</small>
                            <div>{{ $companyRegistryConfig['client_id_configured'] && $companyRegistryConfig['client_secret_configured'] ? __('app.admin.yes') : __('app.admin.no') }}</div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">{{ __('app.admin.integrations.basic_auth') }}</small>
                            <div>{{ $companyRegistryConfig['basic_auth_configured'] ? __('app.admin.yes') : __('app.admin.no') }}</div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.integrations.company-registry-test') }}" class="row g-3">
                        @csrf

                        <div class="col-md-6">
                            <label for="organization_national_id" class="form-label">{{ __('app.auth.organization_national_id') }}</label>
                            <input id="organization_national_id" name="organization_national_id" type="text" class="form-control" value="{{ old('organization_national_id') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="organization_registration_no" class="form-label">{{ __('app.auth.registration_number') }}</label>
                            <input id="organization_registration_no" name="organization_registration_no" type="text" class="form-control" value="{{ old('organization_registration_no') }}">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">{{ __('app.admin.integrations.run_registry_lookup') }}</button>
                        </div>
                    </form>

                    @if ($results['company_registry'])
                        <div class="border rounded p-3 mt-4">
                            <h6 class="mb-3">{{ __('app.admin.integrations.last_lookup_result') }}</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.admin.integrations.result') }}</small>
                                    <div>{{ ($results['company_registry']['ok'] ?? false) ? __('app.admin.integrations.success') : __('app.admin.integrations.failed') }}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.admin.integrations.error_code') }}</small>
                                    <div>{{ $results['company_registry']['error'] ?? __('app.dashboard.not_available') }}</div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted d-block">{{ __('app.admin.integrations.technical_message') }}</small>
                                    <div>{{ $results['company_registry']['technical_message'] ?? __('app.dashboard.not_available') }}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.auth.organization_tile_title') }}</small>
                                    <div>{{ $results['company_registry']['data']['organization_name'] ?? __('app.dashboard.not_available') }}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.auth.registration_number') }}</small>
                                    <div>{{ $results['company_registry']['data']['organization_registration_no'] ?? __('app.dashboard.not_available') }}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.auth.organization_email') }}</small>
                                    <div>{{ $results['company_registry']['data']['organization_email'] ?? __('app.dashboard.not_available') }}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">{{ __('app.auth.organization_phone') }}</small>
                                    <div>{{ $results['company_registry']['data']['organization_phone'] ?? __('app.dashboard.not_available') }}</div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted d-block">{{ __('app.admin.integrations.registration_candidates') }}</small>
                                    <div class="d-flex gap-2 flex-wrap">
                                        @forelse (($results['company_registry']['registration_candidates'] ?? []) as $candidate)
                                            <span class="badge bg-primary-subtle text-dark">{{ $candidate }}</span>
                                        @empty
                                            <span>{{ __('app.dashboard.not_available') }}</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
