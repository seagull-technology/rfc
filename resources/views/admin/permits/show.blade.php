@php
    $title = $permit->permit_number;
    $breadcrumb = __('app.admin.navigation.permits');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ $permit->permit_number }}</span>
            </h2>
            <div class="text-muted">{{ $permit->application?->project_name }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="{{ route('admin.applications.show', $permit->application) }}">{{ __('app.admin.navigation.applications') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('admin.applications.final-letter.print', $permit->application) }}" target="_blank">{{ __('app.final_decision.print_letter') }}</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><div class="iq-header-title"><h3 class="card-title">{{ __('app.permits.summary_title') }}</h3></div></div>
                <div class="card-body">
                    <div class="mb-3"><small class="text-muted d-block">{{ __('app.permits.permit_number') }}</small><div>{{ $permit->permit_number }}</div></div>
                    <div class="mb-3"><small class="text-muted d-block">{{ __('app.permits.status') }}</small><div>{{ $permit->localizedStatus() }}</div></div>
                    <div class="mb-3"><small class="text-muted d-block">{{ __('app.permits.issued_at') }}</small><div>{{ $permit->issued_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div></div>
                    <div class="mb-3"><small class="text-muted d-block">{{ __('app.final_decision.issued_by') }}</small><div>{{ $permit->issuedBy?->displayName() ?? __('app.dashboard.not_available') }}</div></div>
                    <div class="mb-3"><small class="text-muted d-block">{{ __('app.permits.verification_link') }}</small><div><a href="{{ $permit->verificationUrl() }}" target="_blank">{{ __('app.permits.open_verification') }}</a></div></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><div class="iq-header-title"><h3 class="card-title">{{ __('app.permit_audits.title') }}</h3></div></div>
                <div class="card-body">
                    <div class="table-responsive border rounded py-3">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('app.permit_audits.action') }}</th>
                                    <th>{{ __('app.permit_audits.channel') }}</th>
                                    <th>{{ __('app.permit_audits.status') }}</th>
                                    <th>{{ __('app.permit_audits.message') }}</th>
                                    <th>{{ __('app.permit_audits.happened_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($permit->audits as $audit)
                                    <tr>
                                        <td>{{ $audit->localizedAction() }}</td>
                                        <td>{{ $audit->localizedChannel() }}</td>
                                        <td>{{ $audit->localizedStatus() }}</td>
                                        <td>{{ $audit->message ?: __('app.dashboard.not_available') }}</td>
                                        <td>{{ $audit->happened_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">{{ __('app.permit_audits.empty_state') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
