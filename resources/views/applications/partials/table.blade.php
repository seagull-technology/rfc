@php
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

<div class="table-responsive">
    <table class="table mb-0">
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
                        <div class="text-muted">{{ __('app.applications.work_categories.'.$application->work_category) }}</div>
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
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('applications.show', $application) }}" class="btn btn-sm btn-icon btn-primary rounded-pill" data-bs-toggle="tooltip" title="{{ __('app.applications.view_action') }}">
                                <span class="btn-inner">
                                    <i class="fa-solid fa-eye"></i>
                                </span>
                            </a>
                            @if ($application->canBeEditedByApplicant())
                                <a href="{{ route('applications.edit', $application) }}" class="btn btn-sm btn-icon btn-secondary rounded-pill" data-bs-toggle="tooltip" title="{{ __('app.applications.edit_action') }}">
                                    <span class="btn-inner">
                                        <i class="fa-solid fa-pen"></i>
                                    </span>
                                </a>
                            @endif
                            @if ($application->canBeSubmittedByApplicant())
                                <form method="POST" action="{{ route('applications.submit', $application) }}">
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
