@php
    $letters = collect($officialLetters ?? []);
@endphp

<div class="card mt-4">
    <div class="card-header">
        <div class="header-title">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.official_letters.title') }}</span>
            </h2>
        </div>
    </div>
    <div class="card-body p-3 mb-0">
        <ul class="list-inline p-0 m-0">
            @forelse ($letters as $letter)
                <li class="mb-3 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center">
                            <img src="{{ asset('images/envelope.png') }}" class="avatar-50 p-1 rounded-circle img-fluid bg-light" alt="{{ __('app.official_letters.title') }}" loading="lazy">
                            <div class="ms-3">
                                <h5 class="mt-2 mb-1">
                                    <span class="fw-600">{{ $letter->targetEntity?->displayName() ?? __('app.dashboard.not_available') }}</span>
                                </h5>
                                <div class="text-muted small">
                                    {{ $letter->letter_date?->format('Y-m-d') ?: __('app.dashboard.not_available') }}
                                    @if ($letter->serial_number)
                                        <span class="mx-1">|</span>{{ $letter->serial_number }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success">{{ $letter->localizedStatus() }}</span>
                            <button class="btn btn-sm btn-icon btn-info-subtle rounded" type="button" data-bs-toggle="offcanvas" data-bs-target="#applicantOfficialLetterView{{ $letter->getKey() }}" aria-controls="applicantOfficialLetterView{{ $letter->getKey() }}" title="{{ __('app.official_letters.view_action') }}">
                                <i class="ph ph-eye fs-6"></i>
                            </button>
                        </div>
                    </div>
                    <div class="px-4 mt-3">
                        <p class="mb-0 fw-semibold">{{ $letter->subject }}</p>
                        <p class="mb-0 text-muted text-break">{{ str($letter->body)->limit(180) }}</p>
                    </div>
                </li>
            @empty
                <li class="text-center text-muted py-4">{{ __('app.official_letters.empty_state') }}</li>
            @endforelse
        </ul>
    </div>
</div>

@foreach ($letters as $letter)
    <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="applicantOfficialLetterView{{ $letter->getKey() }}">
        <div class="offcanvas-header">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.official_letters.view_action') }}</span>
            </h2>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.official_letters.close_action') }}"></button>
        </div>
        <div class="offcanvas-body">
            <div class="letter-container px-3">
                <div class="header-logo text-center mb-4">
                    <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" style="max-height: 110px;">
                </div>
                <div class="meta pt-2">
                    <div><span class="form-label px-2">{{ __('app.official_letters.target_entity') }}:</span> <span>{{ $letter->targetEntity?->displayName() ?? __('app.dashboard.not_available') }}</span></div>
                    <div><span class="form-label px-2">{{ __('app.official_letters.letter_date') }}:</span> <span>{{ $letter->letter_date?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                    <div><span class="form-label px-2">{{ __('app.official_letters.serial_number') }}:</span> <span>{{ $letter->serial_number ?: __('app.dashboard.not_available') }}</span></div>
                    <div><span class="form-label px-2">{{ $letter->recipient_prefix ?: __('app.official_letters.recipient_prefix') }}:</span> <span>{{ $letter->recipient_name }}</span></div>
                </div>
                <div class="subject mt-3">
                    <span class="form-label px-2">{{ __('app.official_letters.subject') }}:</span>
                    <span>{{ $letter->subject }}</span>
                </div>
                <div class="content mt-4 text-break" style="white-space: pre-line;">{{ $letter->body }}</div>
                @if (filled($letter->attachments))
                    <div class="attachments pt-4">
                        <h6 class="fw-bold mb-3">{{ __('app.official_letters.attachments') }}</h6>
                        <ul>
                            @foreach ($letter->attachments as $attachment)
                                <li>{{ $attachment }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="text-center pt-4 mb-1">{{ __('app.official_letters.formal_thanks') }}</p>
                <p class="text-center mb-0">{{ __('app.official_letters.formal_respect') }}</p>
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
