@php
    $currentApplicantUser = auth()->user();
    $canUpdateApplicantApplication = static fn ($application): bool => (bool) (
        $currentApplicantUser?->can('applications.update.entity')
        || (
            $currentApplicantUser?->can('applications.update.own')
            && (int) $application->submitted_by_user_id === (int) $currentApplicantUser->getKey()
        )
    );
    $canSubmitApplicantApplication = static fn ($application): bool => (bool) (
        $currentApplicantUser?->can('applications.submit')
        && (
            $currentApplicantUser?->can('applications.view.entity')
            || (
                $currentApplicantUser
                && (int) $application->submitted_by_user_id === (int) $currentApplicantUser->getKey()
            )
        )
    );
    $statusClass = static fn (string $status): string => match ($status) {
        'draft' => 'secondary',
        'submitted' => 'warning',
        'under_review' => 'info',
        'needs_clarification' => 'danger',
        'approved' => 'success',
        'rejected' => 'dark',
        default => 'secondary',
    };
@endphp

<div class="table-responsive applicant-request-table-scroll">
    <table class="table mb-0 applicant-request-table">
        <colgroup>
            <col style="width: 150px">
            <col style="width: 300px">
            <col style="width: 130px">
            <col style="width: 150px">
            <col style="width: 110px">
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('app.applications.request_number') }}</th>
                <th>{{ __('app.applications.project_name') }}</th>
                <th>{{ __('app.applications.submitted_at_label') }}</th>
                <th>{{ __('app.applications.status') }}</th>
                <th>{{ __('app.applications.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $application)
                <tr>
                    <td>{{ $application->code }}</td>
                    <td>
                        <div class="fw-semibold">{{ $application->project_name }}</div>
                        <div class="text-muted">{{ \App\Models\WorkCategory::labelFor($application->work_category) }}</div>
                    </td>
                    <td>
                        {{ optional($application->submitted_at ?? $application->created_at)->format('Y-m-d') }}
                    </td>
                    <td>
                        <span class="badge bg-{{ $statusClass($application->status) }}">{{ $application->localizedStatus() }}</span>
                        @if ($application->review_note)
                            <div class="text-muted mt-2 small">{{ $application->review_note }}</div>
                        @endif
                    </td>
                    <td class="applicant-request-actions-cell">
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('applications.show', $application) }}" class="btn btn-sm btn-icon btn-primary rounded-pill" data-bs-toggle="tooltip" title="{{ __('app.applications.view_action') }}">
                                <span class="btn-inner">
                                    <i class="fa-solid fa-eye"></i>
                                </span>
                            </a>
                            @if ($application->canBeEditedByApplicant() && $canUpdateApplicantApplication($application))
                                <a href="{{ route('applications.edit', $application) }}" class="btn btn-sm btn-icon btn-secondary rounded-pill" data-bs-toggle="tooltip" title="{{ __('app.applications.edit_action') }}">
                                    <span class="btn-inner">
                                        <i class="fa-solid fa-pen"></i>
                                    </span>
                                </a>
                            @endif
                            @if ($application->canBeSubmittedByApplicant() && $canSubmitApplicantApplication($application))
                                <form method="POST" action="{{ route('applications.submit', $application) }}"
                                    data-application-submit-confirm
                                    data-confirm-title="{{ __('app.applications.submit_confirm_title') }}"
                                    data-confirm-text="{{ __('app.applications.submit_confirm_body') }}"
                                    data-confirm-button="{{ __('app.applications.submit_confirm_confirm') }}"
                                    data-cancel-button="{{ __('app.applications.submit_confirm_cancel') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-icon btn-success rounded-pill" type="submit" data-bs-toggle="tooltip" title="{{ __('app.applications.submit_action') }}">
                                        <span class="btn-inner">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">{{ __('app.applications.empty_state') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('applications.partials.submit-confirmation-script')
