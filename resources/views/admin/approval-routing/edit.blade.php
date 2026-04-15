@php
    $title = __('app.admin.approval_routing.edit_title');
    $breadcrumb = __('app.admin.navigation.approval_routing');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@push('styles')
    <style>
        .approval-routing-preview-shell {
            position: sticky;
            top: 1.5rem;
        }
    </style>
@endpush

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ __('app.admin.approval_routing.edit_title') }}</span>
            </h2>
            <div class="text-muted">{{ __('app.admin.approval_routing.edit_intro') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">{{ __('app.admin.navigation.dashboard') }}</a>
            <a class="btn btn-primary" href="{{ route('admin.approval-routing.index') }}">{{ __('app.admin.navigation.approval_routing') }}</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <form id="approval-routing-form" method="POST" action="{{ route('admin.approval-routing.update', $rule) }}">
                        @csrf
                        @include('admin.approval-routing.form')

                        <div class="mt-4 d-flex gap-2 flex-wrap">
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.approval_routing.update_action') }}</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.approval-routing.index') }}">{{ __('app.admin.filters.clear_action') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            @include('admin.approval-routing.partials.guide')

            <div class="card mb-4">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.approval_routing.risk_detail_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    @if ($relatedConflicts->isEmpty())
                        <div class="text-muted">{{ __('app.admin.approval_routing.risk_detail_empty') }}</div>
                    @else
                        <div class="text-muted mb-3">{{ __('app.admin.approval_routing.risk_detail_intro') }}</div>
                        <div class="d-flex flex-column gap-3">
                            @foreach ($relatedConflicts as $conflict)
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between gap-2 flex-wrap align-items-center mb-2">
                                        <span class="badge bg-{{ $conflict['type'] === 'shadowed_rule' ? 'warning text-dark' : 'danger' }}">
                                            {{ __('app.admin.approval_routing.conflict_types.'.$conflict['type']) }}
                                        </span>
                                        <span class="small text-muted">{{ __('app.admin.approval_routing.risk_roles.'.$conflict['role']) }}</span>
                                    </div>
                                    <div class="small mb-2">
                                        <span class="fw-semibold">{{ __('app.admin.approval_routing.conflict_competing_rule') }}:</span>
                                        <a href="{{ route('admin.approval-routing.edit', $conflict['other_rule']) }}">{{ $conflict['other_rule']->name }}</a>
                                    </div>
                                    <div class="small text-muted mb-3">
                                        {{ __('app.admin.approval_routing.conflict_recommendations.'.$conflict['type']) }}
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.approval-routing.show', $conflict['other_rule']) }}">{{ __('app.admin.dashboard.table_action') }}</a>
                                        <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.approval-routing.create', ['duplicate_rule_id' => $rule->getKey()]) }}">{{ __('app.admin.approval_routing.duplicate_action') }}</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div id="approval-routing-preview" class="approval-routing-preview-shell">
                @include('admin.approval-routing.partials.preview', [
                    'previewReady' => filled($rule->approval_code),
                    'approvalCode' => $rule->approval_code,
                    'targetEntity' => $rule->targetEntity ?? null,
                    'conditions' => $rule->conditions ?? [
                        'project_nationalities' => [],
                        'work_categories' => [],
                        'release_methods' => [],
                    ],
                    'matchedApplicationsCount' => 0,
                    'matchedApplications' => collect(),
                    'matchedStats' => ['drafts' => 0, 'active' => 0, 'resolved' => 0],
                    'duplicateRule' => null,
                    'overlapRules' => collect(),
                ])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('approval-routing-form');
            const previewContainer = document.getElementById('approval-routing-preview');

            if (!form || !previewContainer) {
                return;
            }

            const previewUrl = @json(route('admin.approval-routing.preview'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            let timer = null;

            const loadPreview = function () {
                const formData = new FormData(form);

                fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Preview request failed');
                        }

                        return response.json();
                    })
                    .then(function (payload) {
                        previewContainer.innerHTML = payload.html;
                    })
                    .catch(function () {
                        previewContainer.innerHTML = '<div class="card mb-0"><div class="card-body text-muted">{{ __('app.admin.approval_routing.preview_failed') }}</div></div>';
                    });
            };

            const queuePreview = function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(loadPreview, 250);
            };

            form.addEventListener('change', queuePreview);
            form.addEventListener('input', function (event) {
                if (event.target.matches('input[type="text"], input[type="number"], select')) {
                    queuePreview();
                }
            });

            loadPreview();
        })();
    </script>
@endpush
