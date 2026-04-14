@php
    $resolvedApprovals = $authorityApprovals->whereNotIn('status', ['pending', 'in_review'])->count();
    $pendingApprovals = $authorityApprovals->whereIn('status', ['pending', 'in_review'])->count();
    $rejectedApprovals = $authorityApprovals->where('status', 'rejected')->count();
@endphp

<div class="card">
    <div class="card-header"><div class="iq-header-title"><h3 class="card-title">{{ __('app.final_decision.title') }}</h3></div></div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">{{ __('app.final_decision.summary.required_approvals') }}</small>
                <div>{{ $authorityApprovals->count() }}</div>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">{{ __('app.final_decision.summary.resolved_approvals') }}</small>
                <div>{{ $resolvedApprovals }}</div>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">{{ __('app.final_decision.summary.pending_approvals') }}</small>
                <div>{{ $pendingApprovals }}</div>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">{{ __('app.final_decision.summary.current_status') }}</small>
                <div>{{ $application->finalDecisionIssued() ? __('app.statuses.'.$application->final_decision_status) : __('app.final_decision.pending_label') }}</div>
            </div>
        </div>

        @if ($application->finalDecisionIssued())
            <div class="alert alert-{{ $application->final_decision_status === 'approved' ? 'success' : 'danger' }} mb-4">
                <div class="fw-semibold">{{ __('app.final_decision.issued_summary') }}</div>
                <div>{{ __('app.final_decision.issued_by') }}: {{ $application->finalDecisionIssuedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                <div>{{ __('app.final_decision.issued_at') }}: {{ optional($application->final_decision_issued_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                <div>{{ __('app.final_decision.permit_number') }}: {{ $application->final_permit_number ?: __('app.dashboard.not_available') }}</div>
                @if ($application->final_decision_note)
                    <div class="mt-2">{{ $application->final_decision_note }}</div>
                @endif
                <div class="mt-3 d-flex gap-2 flex-wrap">
                    @if ($application->final_letter_path)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.applications.final-letter.download', $application) }}">{{ __('app.final_decision.download_letter') }}</a>
                    @endif
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.applications.final-letter.print', $application) }}" target="_blank">{{ __('app.final_decision.print_letter') }}</a>
                </div>
            </div>
        @endif

        @if (! $application->canBeFinallyDecided())
            <div class="alert alert-warning mb-0">{{ __('app.final_decision.not_ready') }}</div>
        @else
            @if ($rejectedApprovals > 0)
                <div class="alert alert-warning">{{ __('app.final_decision.rejected_approval_warning') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.applications.finalize', $application) }}" enctype="multipart/form-data" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label for="final-decision" class="form-label">{{ __('app.final_decision.decision') }}</label>
                    <select id="final-decision" name="decision" class="form-select" required>
                        @foreach (['approved', 'rejected'] as $decision)
                            <option value="{{ $decision }}" @selected(old('decision', $application->final_decision_status) === $decision)>{{ __('app.statuses.'.$decision) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="permit_number" class="form-label">{{ __('app.final_decision.permit_number') }}</label>
                    <input id="permit_number" name="permit_number" type="text" class="form-control" value="{{ old('permit_number', $application->final_permit_number) }}" placeholder="{{ __('app.final_decision.permit_placeholder') }}">
                </div>
                <div class="col-12">
                    <label for="final-decision-note" class="form-label">{{ __('app.final_decision.note') }}</label>
                    <textarea id="final-decision-note" name="note" rows="4" class="form-control">{{ old('note', $application->final_decision_note) }}</textarea>
                </div>
                <div class="col-12">
                    <label for="final_letter" class="form-label">{{ __('app.final_decision.letter') }}</label>
                    <input id="final_letter" name="final_letter" type="file" class="form-control" accept=".pdf,.doc,.docx">
                    @if ($application->final_letter_name)
                        <small class="text-muted d-block mt-2">{{ $application->final_letter_name }}</small>
                    @endif
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">{{ __('app.final_decision.submit') }}</button>
                </div>
            </form>
        @endif
    </div>
</div>
