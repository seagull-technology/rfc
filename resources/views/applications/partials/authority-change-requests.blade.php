@php
    $changeRequestViewer = $changeRequestViewer ?? 'applicant';
    $changeRequests = collect($changeRequestItems ?? $application->authorityChangeRequests ?? [])
        ->whereIn('status', [
            \App\Models\ApplicationAuthorityChangeRequest::STATUS_REQUESTED,
            \App\Models\ApplicationAuthorityChangeRequest::STATUS_RESUBMITTED,
        ])
        ->sortByDesc(fn ($item) => $item->requested_at?->timestamp ?? $item->id)
        ->values();
    $changeRequestsByApproval = $changeRequests->groupBy('application_authority_approval_id');
    $hasRequestedCorrections = $changeRequests->contains(
        fn ($item): bool => $item->status === \App\Models\ApplicationAuthorityChangeRequest::STATUS_REQUESTED,
    );
    $introKey = $hasRequestedCorrections
        ? 'intro_'.$changeRequestViewer
        : 'intro_'.$changeRequestViewer.'_resubmitted';
    $attachmentRoute = static function ($item) use ($application, $changeRequestViewer): string {
        return match ($changeRequestViewer) {
            'authority' => route('authority.applications.change-requests.attachment.download', [$application, $item]),
            'admin' => route('admin.applications.change-requests.attachment.download', [$application, $item]),
            default => route('applications.change-requests.attachment.download', [$application, $item]),
        };
    };
@endphp

@if ($changeRequests->isNotEmpty())
    <section class="authority-change-request-summary" aria-labelledby="authority-change-request-title">
        <div class="authority-change-request-heading">
            <div>
                <h2 id="authority-change-request-title">{{ __('app.authority_change_requests.title') }}</h2>
                <p>{{ __('app.authority_change_requests.'.$introKey) }}</p>
            </div>
            <span class="authority-change-request-count">{{ trans_choice('app.authority_change_requests.items_count', $changeRequests->count(), ['count' => $changeRequests->count()]) }}</span>
        </div>

        @foreach ($changeRequestsByApproval as $approvalRequests)
            @php
                $approval = $approvalRequests->first()?->approval;
                $authorityName = $approval?->entity?->displayName() ?? $approval?->localizedAuthority() ?? __('app.authority_change_requests.unknown_authority');
            @endphp
            <div class="authority-change-request-group">
                <div class="authority-change-request-authority">
                    <i class="ph ph-buildings"></i>
                    <span>{{ $authorityName }}</span>
                </div>
                <div class="authority-change-request-items">
                    @foreach ($approvalRequests as $item)
                        <article class="authority-change-request-item">
                            <div class="authority-change-request-item-header">
                                <strong>{{ $item->section_label ?: \App\Support\ApplicationCorrectionSections::label($item->section_key) }}</strong>
                                <span class="badge {{ $item->status === \App\Models\ApplicationAuthorityChangeRequest::STATUS_RESUBMITTED ? 'bg-info' : 'bg-warning text-dark' }}">
                                    {{ __('app.authority_change_requests.statuses.'.$item->status) }}
                                </span>
                            </div>
                            <p>{{ $item->details }}</p>
                            <div class="authority-change-request-meta">
                                @if ($item->requested_at)
                                    <span><i class="ph ph-calendar-blank"></i>{{ $item->requested_at->format('Y-m-d H:i') }}</span>
                                @endif
                                @if ($item->requestedBy)
                                    <span><i class="ph ph-user"></i>{{ $item->requestedBy->displayName() }}</span>
                                @endif
                                @if ($item->attachment_path)
                                    <a href="{{ $attachmentRoute($item) }}">
                                        <i class="ph ph-paperclip"></i>{{ __('app.authority_change_requests.download_attachment') }}
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>

    @once
        @push('styles')
            <style>
                .authority-change-request-summary {
                    background: #fff8e6;
                    border: 1px solid #e8c675;
                    border-radius: 6px;
                    margin-bottom: 1.5rem;
                    padding: 1rem;
                }

                .authority-change-request-heading,
                .authority-change-request-item-header,
                .authority-change-request-meta {
                    align-items: center;
                    display: flex;
                    flex-wrap: wrap;
                    gap: .5rem 1rem;
                    justify-content: space-between;
                }

                .authority-change-request-heading h2 {
                    font-size: 1.1rem;
                    margin: 0 0 .25rem;
                }

                .authority-change-request-heading p,
                .authority-change-request-item p {
                    margin: 0;
                }

                .authority-change-request-count {
                    background: #6f1d17;
                    color: #fff;
                    font-weight: 700;
                    padding: .35rem .65rem;
                }

                .authority-change-request-group {
                    border-top: 1px solid rgba(111, 29, 23, .16);
                    margin-top: 1rem;
                    padding-top: 1rem;
                }

                .authority-change-request-authority {
                    align-items: center;
                    display: flex;
                    font-weight: 800;
                    gap: .5rem;
                    margin-bottom: .75rem;
                }

                .authority-change-request-items {
                    display: grid;
                    gap: .65rem;
                }

                .authority-change-request-item {
                    background: #fff;
                    border-inline-start: 4px solid #c38a14;
                    padding: .85rem;
                }

                .authority-change-request-item p {
                    line-height: 1.7;
                    margin-top: .5rem;
                    white-space: pre-line;
                }

                .authority-change-request-meta {
                    color: #667085;
                    font-size: .8rem;
                    justify-content: flex-start;
                    margin-top: .65rem;
                }

                .authority-change-request-meta span,
                .authority-change-request-meta a {
                    align-items: center;
                    display: inline-flex;
                    gap: .3rem;
                }
            </style>
        @endpush
    @endonce
@endif
