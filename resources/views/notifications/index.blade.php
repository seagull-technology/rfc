@extends($notificationLayout)

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">{{ __('app.portal.notifications_all_title') }}</h1>
                <p class="text-muted mb-0">{{ __('app.portal.notifications_all_intro') }}</p>
            </div>
            <span class="badge bg-primary-subtle text-primary fs-6">
                {{ trans_choice('app.portal.notifications_count', $notifications->total(), ['count' => $notifications->total()]) }}
            </span>
        </div>

        <div class="card">
            <div class="list-group list-group-flush">
                @forelse ($notifications as $notification)
                    @php($notificationView = \App\Support\NotificationPresenter::present($notification))
                    <a href="{{ $notificationView['url'] }}"
                       class="list-group-item list-group-item-action p-3 p-md-4 {{ $notification->read_at === null ? 'bg-primary-subtle' : '' }}"
                       data-notification-list-item>
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h2 class="h6 mb-0">{{ $notificationView['title'] }}</h2>
                                    @if ($notification->read_at === null)
                                        <span class="badge bg-danger">{{ __('app.portal.notification_unread') }}</span>
                                    @endif
                                    @if ($notificationView['highlight_active'])
                                        <span class="badge bg-{{ $notificationView['highlight_class'] }}">{{ $notificationView['highlight_title'] }}</span>
                                    @endif
                                </div>
                                <p class="mb-1 text-body">{{ $notificationView['body'] }}</p>
                                @if ($notificationView['highlight_summary'])
                                    <p class="small text-muted mb-0">{{ $notificationView['highlight_summary'] }}</p>
                                @endif
                            </div>
                            <small class="text-muted text-nowrap">{{ $notification->created_at?->diffForHumans() }}</small>
                        </div>
                    </a>
                @empty
                    <div class="p-5 text-center text-muted">{{ __('app.portal.notifications_empty') }}</div>
                @endforelse
            </div>
        </div>

        @if ($notifications->hasPages())
            <div class="mt-4">
                {{ $notifications->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
