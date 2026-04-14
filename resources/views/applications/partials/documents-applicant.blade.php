<div class="card">
    <div class="card-header">
        <div class="header-title">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.documents.title') }}</span>
            </h2>
        </div>
    </div>
    <div class="card-body">
        @if ($application->canReceiveApplicantDocuments())
            <form method="POST" action="{{ route('applications.documents.store', $application) }}" enctype="multipart/form-data" class="row g-3 mb-4">
                @csrf
                <div class="col-lg-4">
                    <label class="form-label" for="document_type">{{ __('app.documents.type') }}</label>
                    <select id="document_type" name="document_type" class="form-control select2-basic-single" required>
                        @foreach (['site_request', 'work_content_summary', 'cast_crew_list', 'location_list', 'security_clearance', 'other'] as $documentType)
                            <option value="{{ $documentType }}">{{ __('app.documents.types.'.$documentType) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="title">{{ __('app.documents.title_label') }}</label>
                    <input id="title" name="title" type="text" class="form-control" required>
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="file">{{ __('app.documents.file') }}</label>
                    <input id="file" name="file" type="file" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="document_note">{{ __('app.documents.note') }}</label>
                    <textarea id="document_note" name="note" rows="4" class="form-control"></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-danger" type="submit">{{ __('app.documents.upload_action') }}</button>
                </div>
            </form>
        @endif

        <ul class="list-inline p-0 m-0">
            @forelse ($documents as $document)
                <li class="mb-3 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center">
                            <img src="{{ asset('images/logo.svg') }}" class="avatar-50 p-1 bg-white rounded-circle img-fluid" alt="document">
                            <div class="ms-3">
                                <h6 class="mt-2 mb-1">{{ $document->title }}</h6>
                                <div class="text-muted">{{ $document->localizedType() }}</div>
                                <div class="text-muted">{{ $document->localizedStatus() }}</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <small class="text-muted">{{ ($document->reviewed_at ?? $document->created_at)?->format('Y-m-d H:i') }}</small>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.documents.download', [$application, $document]) }}">{{ __('app.documents.download_action') }}</a>
                        </div>
                    </div>
                    @if ($document->note)
                        <div class="mt-3">{{ $document->note }}</div>
                    @endif
                </li>
            @empty
                <li class="text-muted">{{ __('app.documents.empty_state') }}</li>
            @endforelse
        </ul>
    </div>
</div>
