<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="header-title">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.correspondence.title') }}</span>
                </h2>
            </div>
            @can('applications.review')
                <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminCorrespondenceCreate" aria-controls="adminCorrespondenceCreate">
                    <i class="fa-solid fa-plus me-2"></i>{{ __('app.correspondence.new_message_action') }}
                </button>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <div class="list-group admin-correspondence-list">
            @forelse ($correspondences as $message)
                <button class="list-group-item list-group-item-action" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminCorrespondenceView{{ $message->getKey() }}" aria-controls="adminCorrespondenceView{{ $message->getKey() }}" aria-label="{{ __('app.correspondence.view_message_action') }}: {{ $message->subject ?: __('app.correspondence.message_fallback_subject') }}">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div class="admin-correspondence-summary text-start">
                            <div class="fw-semibold text-break">{{ $message->subject ?: __('app.correspondence.message_fallback_subject') }}</div>
                            <div class="small text-muted admin-correspondence-meta">
                                <span>{{ $message->sender_name }}</span>
                                <span>{{ $message->localizedSenderType() }}</span>
                                <span>{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                        <span class="btn btn-sm btn-icon btn-info-subtle rounded" title="{{ __('app.correspondence.view_message_action') }}">
                            <i class="ph ph-eye fs-6"></i>
                        </span>
                    </div>
                </button>
            @empty
                <div class="text-muted border rounded p-3">{{ __('app.correspondence.empty_state') }}</div>
            @endforelse
        </div>
    </div>
</div>

@can('applications.review')
    <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="adminCorrespondenceCreate">
        <div class="offcanvas-header">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.correspondence.new_message_title') }}</span>
            </h2>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.official_letters.close_action') }}"></button>
        </div>
        <form method="POST" action="{{ route('admin.applications.correspondence.store', $application) }}" enctype="multipart/form-data">
            <div class="offcanvas-body">
                @csrf
                <div class="section-form">
                    <div class="mb-3">
                        <label class="form-label" for="admin_correspondence_subject">{{ __('app.correspondence.subject') }}</label>
                        <input id="admin_correspondence_subject" name="subject" type="text" class="form-control" value="{{ old('subject') }}" placeholder="{{ __('app.correspondence.subject_placeholder') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="admin_correspondence_message">{{ __('app.correspondence.message') }}</label>
                        <textarea id="admin_correspondence_message" name="message" rows="6" class="form-control" required placeholder="{{ __('app.correspondence.message_placeholder') }}">{{ old('message') }}</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="admin_correspondence_attachment">{{ __('app.correspondence.attachment') }}</label>
                        <input id="admin_correspondence_attachment" name="attachment" type="file" class="form-control">
                    </div>
                </div>
            </div>
            <div class="offcanvas-footer border-top">
                <div class="d-flex gap-3 p-3 justify-content-end">
                    <button class="btn btn-danger d-flex align-items-center gap-2" type="submit">
                        <i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.correspondence.send_action') }}
                    </button>
                    <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas">
                        <i class="ph ph-caret-double-left"></i>{{ __('app.official_letters.close_action') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endcan

@foreach ($correspondences as $message)
    <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="adminCorrespondenceView{{ $message->getKey() }}">
        <div class="offcanvas-header">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.correspondence.message_content_title') }}</span>
            </h2>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.official_letters.close_action') }}"></button>
        </div>
        <div class="offcanvas-body">
            <div class="section-form">
                <div class="mb-3">
                    <label class="form-label">{{ __('app.correspondence.subject') }}</label>
                    <div class="form-control bg-light admin-message-readonly">{{ $message->subject ?: __('app.correspondence.message_fallback_subject') }}</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">{{ __('app.correspondence.sender') }}</label>
                        <div class="form-control bg-light admin-message-readonly">{{ $message->sender_name }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('app.correspondence.sender_type') }}</label>
                        <div class="form-control bg-light admin-message-readonly">{{ $message->localizedSenderType() }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('app.correspondence.sent_at') }}</label>
                        <div class="form-control bg-light admin-message-readonly">{{ $message->created_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label">{{ __('app.correspondence.message') }}</label>
                    <div class="form-control bg-light text-break admin-message-readonly admin-message-body">{{ $message->message }}</div>
                </div>
                @if ($message->attachment_path)
                    <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('admin.applications.correspondence.download', [$application, $message]) }}">
                        <i class="ph ph-file-arrow-down"></i>{{ __('app.correspondence.download_attachment') }}
                    </a>
                @endif
            </div>
        </div>
        <div class="offcanvas-footer border-top">
            <div class="d-flex gap-3 p-3 justify-content-end">
                <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas">
                    <i class="ph ph-caret-double-left"></i>{{ __('app.official_letters.close_action') }}
                </button>
            </div>
        </div>
    </div>
@endforeach
