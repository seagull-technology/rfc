<div class="card">
    <div class="card-header"><div class="iq-header-title"><h3 class="card-title">{{ __('app.documents.title') }}</h3></div></div>
    <div class="card-body">
        <div class="table-responsive border rounded py-3">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.documents.title_label') }}</th>
                        <th>{{ __('app.documents.status') }}</th>
                        <th>{{ __('app.documents.last_action') }}</th>
                        <th>{{ __('app.admin.applications.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td>
                                {{ $document->title }}<br>
                                <span class="text-muted">{{ $document->localizedType() }}</span><br>
                                <span class="text-muted">{{ $document->uploadedBy?->displayName() ?? __('app.dashboard.not_available') }}</span>
                            </td>
                            <td>
                                {{ $document->localizedStatus() }}<br>
                                <span class="text-muted">{{ $document->note ?: __('app.dashboard.not_available') }}</span>
                            </td>
                            <td>{{ ($document->reviewed_at ?? $document->created_at)?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="d-grid gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.applications.documents.download', [$application, $document]) }}">{{ __('app.documents.download_action') }}</a>
                                    <form method="POST" action="{{ route('admin.applications.documents.review', [$application, $document]) }}" class="d-grid gap-2">
                                        @csrf
                                        <select name="status" class="form-select form-select-sm">
                                            @foreach (['submitted', 'needs_revision', 'approved', 'rejected'] as $status)
                                                <option value="{{ $status }}" @selected($document->status === $status)>{{ __('app.documents.statuses.'.$status) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="note" class="form-control form-control-sm" value="{{ $document->note }}" placeholder="{{ __('app.documents.note') }}">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('app.documents.review_action') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">{{ __('app.documents.empty_state') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
