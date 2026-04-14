<div class="card">
    <div class="card-header">
        <div class="header-title">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.correspondence.title') }}</span>
            </h2>
        </div>
    </div>
    <div class="card-body">
        <div class="border rounded p-3 bg-light mb-4">
            <form method="POST" action="{{ route('admin.applications.correspondence.store', $application) }}" enctype="multipart/form-data" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label" for="admin_subject">{{ __('app.correspondence.subject') }}</label>
                    <input id="admin_subject" name="subject" type="text" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="admin_attachment">{{ __('app.correspondence.attachment') }}</label>
                    <input id="admin_attachment" name="attachment" type="file" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label" for="admin_message">{{ __('app.correspondence.message') }}</label>
                    <textarea id="admin_message" name="message" rows="4" class="form-control" required></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-danger" type="submit">{{ __('app.correspondence.send_action') }}</button>
                </div>
            </form>
        </div>

        <ul class="list-inline p-0 m-0">
            @forelse ($correspondences as $message)
                <li class="mb-3">
                    <div class="border rounded p-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <h6 class="mb-1">{{ $message->sender_name }}</h6>
                                <div class="text-muted small">{{ $message->localizedSenderType() }} | {{ $message->created_at?->format('Y-m-d H:i') }}</div>
                                @if ($message->subject)
                                    <div class="mt-2 fw-semibold">{{ $message->subject }}</div>
                                @endif
                            </div>
                            @if ($message->attachment_path)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.applications.correspondence.download', [$application, $message]) }}">{{ __('app.correspondence.download_attachment') }}</a>
                            @endif
                        </div>
                        <div class="mt-3 text-break">{{ $message->message }}</div>
                    </div>
                </li>
            @empty
                <li class="text-muted border rounded p-3">{{ __('app.correspondence.empty_state') }}</li>
            @endforelse
        </ul>
    </div>
</div>
