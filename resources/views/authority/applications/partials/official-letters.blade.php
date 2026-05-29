@php
    $letters = collect($officialLetters ?? []);
@endphp

<div class="card">
    <div class="card-header">
        <div class="header-title">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.official_letters.title') }}</span>
            </h2>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.official_letters.target_entity') }}</th>
                        <th>{{ __('app.official_letters.serial_number') }}</th>
                        <th>{{ __('app.official_letters.subject') }}</th>
                        <th>{{ __('app.official_letters.letter_date') }}</th>
                        <th>{{ __('app.official_letters.status') }}</th>
                        <th>{{ __('app.official_letters.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($letters as $letter)
                        <tr>
                            <td>{{ $letter->targetEntity?->displayName() ?? __('app.dashboard.not_available') }}</td>
                            <td>{{ $letter->serial_number ?: __('app.dashboard.not_available') }}</td>
                            <td>{{ $letter->subject }}</td>
                            <td>{{ $letter->letter_date?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                            <td><span class="badge bg-success">{{ $letter->localizedStatus() }}</span></td>
                            <td>
                                <button class="btn btn-sm btn-icon btn-info-subtle rounded" type="button" data-bs-toggle="offcanvas" data-bs-target="#authorityOfficialLetterView{{ $letter->getKey() }}" aria-controls="authorityOfficialLetterView{{ $letter->getKey() }}" title="{{ __('app.official_letters.view_action') }}">
                                    <i class="ph ph-eye fs-6"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('app.official_letters.empty_state') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@foreach ($letters as $letter)
    <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="authorityOfficialLetterView{{ $letter->getKey() }}">
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
