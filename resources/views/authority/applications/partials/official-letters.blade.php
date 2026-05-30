@php
    $letters = collect($officialLetters ?? []);
@endphp

<div class="card request-pane-card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="header-title">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.official_letters.title') }}</span>
                </h2>
            </div>
            <span class="badge bg-light text-dark border">
                {{ $letters->count() }} {{ __('app.official_letters.tab') }}
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped mb-0 documents-table official-letters-table">
                <colgroup>
                    <col style="width: 22%;">
                    <col style="width: 18%;">
                    <col style="width: 26%;">
                    <col style="width: 12%;">
                    <col style="width: 11%;">
                    <col style="width: 11%;">
                </colgroup>
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
                        @php
                            $letterStatusClass = $letter->status === 'issued' ? 'success' : 'secondary';
                        @endphp
                        <tr>
                            <td>{{ $letter->targetEntity?->displayName() ?? __('app.dashboard.not_available') }}</td>
                            <td>{{ $letter->serial_number ?: __('app.dashboard.not_available') }}</td>
                            <td>{{ $letter->subject }}</td>
                            <td>{{ $letter->letter_date?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                            <td><span class="badge bg-{{ $letterStatusClass }}">{{ $letter->localizedStatus() }}</span></td>
                            <td class="official-letter-action-cell">
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
    @php
        $letterStatusClass = $letter->status === 'issued' ? 'success' : 'secondary';
    @endphp
    <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="authorityOfficialLetterView{{ $letter->getKey() }}">
        <div class="offcanvas-header">
            <div>
                <h2 class="episode-playlist-title wp-heading-inline mb-2">
                    <span class="position-relative">{{ __('app.official_letters.view_action') }}</span>
                </h2>
                <span class="badge bg-{{ $letterStatusClass }}">{{ $letter->localizedStatus() }}</span>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.official_letters.close_action') }}"></button>
        </div>
        <div class="offcanvas-body">
            <div class="letter-container px-3">
                <div class="header-logo text-center mb-4">
                    <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" style="max-height: 110px;">
                </div>
                <div class="meta pt-2">
                    <div class="mb-2"><span class="form-label px-2">{{ __('app.official_letters.letter_date') }}:</span> <span>{{ $letter->letter_date?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                    <div class="mb-2"><span class="form-label px-2">{{ __('app.official_letters.serial_number') }}:</span> <span>{{ $letter->serial_number ?: __('app.dashboard.not_available') }}</span></div>
                    <div class="mb-2"><span class="form-label px-2">{{ __('app.official_letters.target_entity') }}:</span> <span>{{ $letter->targetEntity?->displayName() ?? __('app.dashboard.not_available') }}</span></div>
                    <div class="mb-2"><span class="form-label px-2">{{ $letter->recipient_prefix ?: __('app.official_letters.recipient_prefix') }}:</span> <span>{{ $letter->recipient_name }}</span></div>
                </div>
                <div class="subject mt-3">
                    <span class="form-label px-2">{{ __('app.official_letters.subject') }}:</span>
                    <span>{{ $letter->subject }}</span>
                </div>
                <div class="content mt-4 text-break" style="white-space: pre-line;">{{ $letter->body }}</div>
                @if (filled($letter->attachments))
                    <div class="attachments pt-4">
                        <h6 class="fw-bold mb-3">{{ __('app.official_letters.attachments') }}</h6>
                        <ul class="list-unstyled mb-0">
                            @foreach ($letter->attachments as $attachment)
                                <li class="d-flex align-items-center gap-2 mb-2">
                                    <i class="ph ph-file-text fs-5 text-danger"></i>
                                    <span>{{ $attachment }}</span>
                                </li>
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
