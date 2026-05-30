<div class="workflow-dashboard-layout">
    <div class="row">
        <div class="col-12">
            <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4 px-0">
                <div>
                    <h2 class="episode-playlist-title wp-heading-inline mb-1">
                        <span class="position-relative">{{ __('app.admin.applications.title') }}</span>
                    </h2>
                    <div class="text-muted">{{ __('app.admin.applications.intro') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-image-12 workflow-dashboard-hero">
        <div class="card-body">
            <div class="text-center">
                <div>
                    <img src="{{ asset('images/logo.svg') }}" alt="profile-img" class="rounded-pill avatar-130 img-fluid bg-white p-2" loading="lazy">
                </div>
                <div class="mt-3">
                    <h3 class="d-inline-block text-white">{{ $profileEntityName }}</h3>
                    <div class="text-white-50">{{ $workflowRoleLabels->isNotEmpty() ? $workflowRoleLabels->join(' | ') : __('app.dashboard.no_roles') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="card workflow-dashboard-card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.admin.dashboard.metrics.applications') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $workflowVisibleCount }}</h2>
                            {{ $workflowVisiblePercent }}%
                        </div>
                        <div class="border bg-danger-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="metric icon">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-danger-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $workflowVisiblePercent }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card workflow-dashboard-card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.admin.dashboard.metrics.waiting_authorities') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['workflow_waiting_authorities'] }}</h2>
                            {{ $workflowWaitingAuthoritiesPercent }}%
                        </div>
                        <div class="border bg-warning-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="metric icon">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-warning-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $workflowWaitingAuthoritiesPercent }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card workflow-dashboard-card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.admin.dashboard.metrics.ready_final_decision') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['workflow_ready_final_decision'] }}</h2>
                            {{ $workflowFinalDecisionPercent }}%
                        </div>
                        <div class="border bg-success-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="metric icon">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-success-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $workflowFinalDecisionPercent }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card workflow-dashboard-summary">
                <div class="card-header">
                    <div class="header-title">
                        <h2 class="episode-playlist-title wp-heading-inline">
                            <span class="position-relative">{{ __('app.admin.dashboard.workflow_context_title') }}</span>
                        </h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="summary-item">
                        <div class="summary-label">{{ __('app.dashboard.signed_in_user') }}</div>
                        <div>{{ $admin->displayName() }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">{{ __('app.dashboard.current_entity') }}</div>
                        <div>{{ $profileEntityName }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">{{ __('app.dashboard.account_status') }}</div>
                        <div>{{ $admin->localizedStatus() }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">{{ __('app.dashboard.assigned_roles') }}</div>
                        <div>{{ $workflowRoleLabels->isNotEmpty() ? $workflowRoleLabels->join(', ') : __('app.dashboard.no_roles') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-dashboard">
                <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                    <h2 class="episode-playlist-title wp-heading-inline">
                        <span class="position-relative">{{ __('app.admin.dashboard.workflow_queue_title') }}</span>
                    </h2>
                </div>
                <div class="card-body pt-0">
                    <div class="mt-4 table-responsive">
                        <div class="table-responsive rounded py-4">
                            <table class="table" data-toggle="data-table">
                                <thead>
                                    <tr class="ligth">
                                        <th>#</th>
                                        <th>{{ __('app.admin.dashboard.workflow_type') }}</th>
                                        <th>{{ __('app.applications.project_name') }}</th>
                                        <th>{{ __('app.admin.dashboard.workflow_checkpoint') }}</th>
                                        <th>{{ __('app.applications.updated_at') }}</th>
                                        <th>{{ __('app.admin.applications.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($workflowQueue as $queueItem)
                                        <tr class="workflow-queue-item">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $queueItem['type'] }}</td>
                                            <td>
                                                {{ $queueItem['project_name'] }}<br>
                                                <span class="text-muted">{{ $queueItem['entity'] }}</span>
                                                @if ($queueItem['applicant_response']['active'])
                                                    <div class="response-flag">
                                                        <span class="badge bg-primary">{{ $queueItem['applicant_response']['title'] }}</span>
                                                        <span class="small text-muted">{{ $queueItem['applicant_response']['summary'] }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $queueItem['checkpoint']['class'] }}">{{ $queueItem['checkpoint']['label'] }}</span><br>
                                                <span class="text-muted">{{ $queueItem['status_label'] }}</span>
                                            </td>
                                            <td>{{ $queueItem['updated_at']?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                            <td>
                                                <div class="flex align-items-center list-user-action">
                                                    <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ $queueItem['url'] }}">
                                                        <i class="ph ph-eye fs-6"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">{{ __('app.admin.dashboard.workflow_queue_empty') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
