@php
    $title = __('app.admin.approval_routing.create_title');
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
                <span class="position-relative">{{ __('app.admin.approval_routing.create_title') }}</span>
            </h2>
            <div class="text-muted">
                {{ $isDuplicateDraft ? __('app.admin.approval_routing.duplicate_intro', ['name' => $sourceRule?->name ?? __('app.dashboard.not_available')]) : __('app.admin.approval_routing.create_intro') }}
            </div>
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
                    @if ($isDuplicateDraft)
                        <div class="alert alert-warning mb-4">
                            {{ __('app.admin.approval_routing.duplicate_notice') }}
                        </div>
                    @endif
                    <form id="approval-routing-form" method="POST" action="{{ route('admin.approval-routing.store') }}">
                        @csrf
                        @include('admin.approval-routing.form')

                        <div class="mt-4">
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.approval_routing.create_action') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            @include('admin.approval-routing.partials.guide')

            <div id="approval-routing-preview" class="approval-routing-preview-shell">
                @include('admin.approval-routing.partials.preview', [
                    'previewReady' => false,
                    'approvalCode' => null,
                    'targetEntity' => null,
                    'conditions' => [
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
