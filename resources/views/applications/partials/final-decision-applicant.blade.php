<div class="card">
    <div class="card-header">
        <div class="header-title">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.final_decision.title') }}</span>
            </h2>
        </div>
    </div>
    <div class="card-body">
        @if ($application->finalDecisionIssued())
            <div class="mb-4">
                <div class="mb-2"><span class="fw-600">{{ __('app.final_decision.decision') }}:</span><span class="ms-2">{{ __('app.statuses.'.$application->final_decision_status) }}</span></div>
                <div class="mb-2"><span class="fw-600">{{ __('app.final_decision.issued_at') }}:</span><span class="ms-2">{{ optional($application->final_decision_issued_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</span></div>
                <div class="mb-2"><span class="fw-600">{{ __('app.final_decision.issued_by') }}:</span><span class="ms-2">{{ $application->finalDecisionIssuedBy?->displayName() ?? __('app.dashboard.not_available') }}</span></div>
                <div class="mb-2"><span class="fw-600">{{ __('app.final_decision.permit_number') }}:</span><span class="ms-2">{{ $application->final_permit_number ?: __('app.dashboard.not_available') }}</span></div>
                @if ($application->final_decision_note)
                    <div>{{ $application->final_decision_note }}</div>
                @endif
            </div>

            <div class="d-flex gap-2 flex-wrap">
                @if ($application->final_letter_path)
                    <a class="btn btn-outline-primary" href="{{ route('applications.final-letter.download', $application) }}">{{ __('app.final_decision.download_letter') }}</a>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('applications.final-letter.print', $application) }}" target="_blank">{{ __('app.final_decision.print_letter') }}</a>
            </div>
        @else
            <div class="text-muted">{{ __('app.final_decision.pending_message') }}</div>
        @endif
    </div>
</div>
