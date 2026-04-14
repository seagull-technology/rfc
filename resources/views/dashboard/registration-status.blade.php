@extends('layouts.auth', ['title' => __('app.dashboard.registration_status_title')])

@push('styles')
    <style>
        .registration-status-layout .status-block-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: .75rem;
        }

        .registration-status-layout .status-history-list {
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }

        .registration-status-layout .status-history-item {
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: .5rem;
            padding: .875rem 1rem;
            background: rgba(255, 255, 255, .04);
        }
    </style>
@endpush

@section('content')
    <div class="wrapper">
        <section class="sign-in-page" style="background-image: url('{{ asset('images/loginBg.jpeg') }}')">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-lg-6 col-md-12">
                        <div class="sign-user_card registration-status-layout">
                            <a href="{{ route('dashboard') }}">
                                <img class="img-fluid logo" src="{{ asset('images/logo.svg') }}" alt="#">
                            </a>
                            <div class="sign-in-page-data">
                                <div class="sign-in-from w-100 m-auto">
                                    <h3 class="mb-3 text-center">{{ __('app.dashboard.registration_status_title') }}</h3>
                                    <p class="text-center">{{ __('app.dashboard.registration_status_intro', ['status' => $entity?->localizedStatus() ?? $user->localizedStatus()]) }}</p>

                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="card bg-body">
                                                <div class="card-body">
                                                    <strong>{{ __('app.dashboard.current_entity') }}</strong>
                                                    <div class="mt-2">{{ $entity?->displayName() ?? __('app.dashboard.no_entity') }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="card bg-body">
                                                <div class="card-body">
                                                    <strong>{{ __('app.dashboard.account_status') }}</strong>
                                                    <div class="mt-2">{{ $entity?->localizedStatus() ?? $user->localizedStatus() }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info mt-4">
                                        {{ __('app.dashboard.status_messages.'.($entity?->status ?? $user->status ?? 'pending_review')) }}
                                    </div>

                                    @if ($entity)
                                        <div class="mt-3">
                                            <strong>{{ __('app.dashboard.review_notes') }}</strong>
                                            <div class="mt-2">
                                                {{ data_get($entity->metadata, 'review.note', __('app.dashboard.no_review_notes')) }}
                                            </div>
                                        </div>
                                    @endif

                                    @if ($latestRegistrationNotification)
                                        <div class="card bg-body mt-4">
                                            <div class="card-body">
                                                <div class="status-block-title">{{ __('app.dashboard.latest_registration_update') }}</div>
                                                <div class="fw-semibold">{{ data_get($latestRegistrationNotification->data, 'title', __('app.dashboard.no_registration_updates')) }}</div>
                                                <div class="mt-2">{{ data_get($latestRegistrationNotification->data, 'body', __('app.dashboard.no_registration_updates')) }}</div>
                                                <div class="text-muted mt-2 small">{{ $latestRegistrationNotification->created_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($reviewHistory->isNotEmpty())
                                        <div class="card bg-body mt-4">
                                            <div class="card-body">
                                                <div class="status-block-title">{{ __('app.dashboard.review_history_title') }}</div>
                                                <div class="status-history-list">
                                                    @foreach ($reviewHistory as $historyItem)
                                                        @php($decision = data_get($historyItem, 'decision', 'needs_completion'))
                                                        <div class="status-history-item">
                                                            <div class="fw-semibold">{{ __('app.admin.entities.review_actions.'.$decision) }}</div>
                                                            <div class="text-muted small mt-1">
                                                                {{ data_get($historyItem, 'reviewed_at', __('app.dashboard.not_available')) }}
                                                                |
                                                                {{ $reviewerNames[data_get($historyItem, 'reviewed_by_user_id')] ?? __('app.dashboard.not_available') }}
                                                            </div>
                                                            <div class="mt-2">{{ data_get($historyItem, 'note', __('app.dashboard.no_review_notes')) }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($entity && in_array($entity->registration_type, ['company', 'ngo', 'school'], true) && in_array($entity->status, ['needs_completion', 'rejected'], true))
                                        <div class="mt-4">
                                            <a class="btn btn-danger w-100" href="{{ route('registration.completion.edit') }}">{{ __('app.dashboard.complete_registration_action') }}</a>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                                        @csrf
                                        <button class="btn btn-danger w-100" type="submit">{{ __('app.dashboard.logout') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
