@extends('layouts.auth', ['title' => __('app.dashboard.registration_status_title')])

@push('styles')
    @include('auth.partials.registration-styles')
@endpush

@section('content')
    <div class="wrapper">
        <section class="sign-in-page registration-auth-page" style="background-image: url('{{ asset('images/loginBg.jpeg') }}')">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-12">
                        <div class="sign-user_card registration-card registration-card-narrow">
                            <a class="registration-logo-link" href="{{ route('dashboard') }}">
                                <img class="img-fluid logo registration-logo" src="{{ asset('images/logo.svg') }}" alt="#">
                            </a>
                            <div class="sign-in-page-data registration-page-data">
                                <div class="sign-in-from w-100 m-auto">
                                    <div class="registration-header">
                                        <h1 class="registration-title">{{ __('app.dashboard.registration_status_title') }}</h1>
                                        <p class="registration-subtitle">{{ __('app.dashboard.registration_status_intro', ['status' => $entity?->localizedStatus() ?? $user->localizedStatus()]) }}</p>
                                    </div>

                                    <div class="registration-info-panel">
                                        <div class="registration-summary-grid">
                                            <div class="registration-summary-block">
                                                <span class="registration-block-label">{{ __('app.dashboard.current_entity') }}</span>
                                                <div class="fw-semibold">{{ $entity?->displayName() ?? __('app.dashboard.no_entity') }}</div>
                                            </div>

                                            <div class="registration-summary-block">
                                                <span class="registration-block-label">{{ __('app.dashboard.account_status') }}</span>
                                                <div class="fw-semibold">{{ $entity?->localizedStatus() ?? $user->localizedStatus() }}</div>
                                            </div>
                                        </div>

                                        <div class="alert alert-info registration-alert mt-4 mb-0">
                                            {{ __('app.dashboard.status_messages.'.($entity?->status ?? $user->status ?? 'pending_review')) }}
                                        </div>

                                        @if ($entity)
                                            <div class="registration-summary-block mt-4">
                                                <span class="registration-block-label">{{ __('app.dashboard.review_notes') }}</span>
                                                <div>{{ data_get($entity->metadata, 'review.note', __('app.dashboard.no_review_notes')) }}</div>
                                            </div>
                                        @endif

                                        @if ($latestRegistrationNotification)
                                            <div class="registration-update-block mt-4">
                                                <span class="registration-block-label">{{ __('app.dashboard.latest_registration_update') }}</span>
                                                <div class="fw-semibold">{{ data_get($latestRegistrationNotification->data, 'title', __('app.dashboard.no_registration_updates')) }}</div>
                                                <div class="mt-2">{{ data_get($latestRegistrationNotification->data, 'body', __('app.dashboard.no_registration_updates')) }}</div>
                                                <div class="text-muted mt-2 small">{{ $latestRegistrationNotification->created_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                                            </div>
                                        @endif

                                        @if ($reviewHistory->isNotEmpty())
                                            <div class="mt-4">
                                                <span class="registration-block-label">{{ __('app.dashboard.review_history_title') }}</span>
                                                <div class="registration-history-list">
                                                    @foreach ($reviewHistory as $historyItem)
                                                        @php($decision = data_get($historyItem, 'decision', 'needs_completion'))
                                                        <div class="registration-history-item">
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
                                        @endif

                                        <div class="registration-actions">
                                            @if ($entity && in_array($entity->registration_type, ['company', 'ngo', 'school'], true) && in_array($entity->status, ['needs_completion', 'rejected'], true))
                                                <a class="btn btn-danger" href="{{ route('registration.completion.edit') }}">{{ __('app.dashboard.complete_registration_action') }}</a>
                                            @endif

                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button class="btn btn-outline-secondary" type="submit">{{ __('app.dashboard.logout') }}</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
