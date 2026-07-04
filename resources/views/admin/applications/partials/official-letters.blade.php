@php
    $letters = collect($officialLetters ?? []);
    $officialLetterApprovals = collect($officialLetterApprovals ?? []);
    $mode = $mode ?? 'issue';
    $isDirectoryMode = $mode === 'directory';
    $letterDomScope = $isDirectoryMode ? 'Directory' : '';
    $officialLetterCreateId = 'officialLetterCreate'.$letterDomScope;
    $officialLetterViewId = static fn ($letter): string => 'officialLetterView'.$letterDomScope.$letter->getKey();
    $officialLetterEditId = static fn ($letter): string => 'officialLetterEdit'.$letterDomScope.$letter->getKey();
    $officialLetterSendId = static fn ($letter): string => 'officialLetterSend'.$letterDomScope.$letter->getKey();
    $letterCanSend = static fn ($letter): bool => $letter->status !== 'issued'
        && ($letter->isApplicantLetter() || filled($letter->target_entity_id))
        && filled($letter->recipient_name)
        && filled($letter->subject)
        && filled($letter->body);
    $letterEntityAvatar = static function ($letter): string {
        $code = (string) ($letter->targetEntity?->code ?? '');

        return match (true) {
            str_contains($code, 'public-security') => asset('images/111.jpeg'),
            str_contains($code, 'interior'), str_contains($code, 'airport') => asset('images/22.jpeg'),
            default => asset('images/logo.svg'),
        };
    };
    $groupedLetters = $letters
        ->groupBy(fn ($letter): string => $letter->isApplicantLetter()
            ? 'applicant'
            : ($letter->target_entity_id ? 'entity-'.$letter->target_entity_id : 'unassigned'))
        ->map(function ($rows) use ($letterEntityAvatar): array {
            $first = $rows->first();

            return [
                'entity_name' => $first?->recipientDisplayName() ?? __('app.dashboard.not_available'),
                'avatar' => $first ? $letterEntityAvatar($first) : asset('images/logo.svg'),
                'letters' => $rows->values(),
            ];
        })
        ->values();
@endphp

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="header-title">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ $isDirectoryMode ? __('app.official_letters.directory_title') : __('app.official_letters.issue_title') }}</span>
                </h2>
            </div>
            @if (! $isDirectoryMode)
                @can('applications.review')
                    @if ($officialLetterApprovals->isNotEmpty())
                        <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $officialLetterCreateId }}" aria-controls="{{ $officialLetterCreateId }}">
                            <i class="fa-solid fa-plus me-2"></i>{{ __('app.official_letters.create_action') }}
                        </button>
                    @else
                        <button class="btn btn-danger" type="button" disabled title="{{ __('app.official_letters.no_routed_authorities') }}">
                            <i class="fa-solid fa-plus me-2"></i>{{ __('app.official_letters.create_action') }}
                        </button>
                    @endif
                @endcan
            @endif
        </div>
    </div>
    <div class="card-body">
        @if ($isDirectoryMode)
            <div class="official-letter-directory">
                @forelse ($groupedLetters as $group)
                    <div class="official-letter-entity-card bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <img src="{{ $group['avatar'] }}" alt="" class="official-letter-entity-avatar">
                            <h5 class="mb-0">{{ $group['entity_name'] }}</h5>
                        </div>
                        <hr>

                        <div class="list-group bg-secondary-subtle official-letter-list">
                            @foreach ($group['letters'] as $letter)
                                @php
                                    $letterIsSent = $letter->status === 'issued';
                                @endphp
                                <div class="list-group-item official-letter-list-item">
                                    <button class="official-letter-open-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $officialLetterViewId($letter) }}" aria-controls="{{ $officialLetterViewId($letter) }}">
                                        <img src="{{ asset('images/envelope.png') }}" alt="" class="official-letter-envelope">
                                        <span class="official-letter-list-title">{{ $letter->subject }}</span>
                                    </button>

                                    <div class="official-letter-list-meta">
                                        <span class="text-muted">{{ $letter->serial_number ?: __('app.dashboard.not_available') }}</span>
                                        <span class="text-muted">{{ $letter->letter_date?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span>
                                        <span class="badge bg-{{ $letterIsSent ? 'success' : 'secondary' }}">{{ $letter->localizedStatus() }}</span>
                                    </div>

                                    <div class="official-letter-list-actions">
                                        <button class="btn btn-sm btn-icon btn-info-subtle rounded" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $officialLetterViewId($letter) }}" aria-controls="{{ $officialLetterViewId($letter) }}" title="{{ __('app.official_letters.view_action') }}">
                                            <i class="ph ph-eye fs-6"></i>
                                        </button>
                                        <a class="btn btn-sm btn-icon btn-secondary-subtle rounded" href="{{ route('admin.applications.official-letters.print', [$application, $letter]) }}" target="_blank" title="{{ __('app.official_letters.print_action') }}">
                                            <i class="ph ph-printer fs-6"></i>
                                        </a>
                                        @can('applications.review')
                                            @if (! $letterIsSent)
                                                <button class="btn btn-sm btn-icon btn-success-subtle rounded" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $officialLetterEditId($letter) }}" aria-controls="{{ $officialLetterEditId($letter) }}" title="{{ __('app.official_letters.edit_action') }}">
                                                    <i class="ph ph-pencil-simple fs-6"></i>
                                                </button>
                                                @if ($letterCanSend($letter))
                                                    <button class="btn btn-sm btn-icon btn-danger-subtle rounded" type="submit" form="{{ $officialLetterSendId($letter) }}" title="{{ __('app.official_letters.send_action') }}">
                                                        <i class="ph ph-paper-plane-tilt fs-6"></i>
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-icon btn-danger-subtle rounded" type="button" disabled title="{{ __('app.official_letters.send_requires_complete') }}">
                                                        <i class="ph ph-paper-plane-tilt fs-6"></i>
                                                    </button>
                                                @endif
                                            @endif
                                        @endcan
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="text-muted border rounded p-4">{{ __('app.official_letters.empty_state') }}</div>
                @endforelse
            </div>
        @else
            <div class="table-responsive rounded py-4 admin-application-table-scroll">
                <table class="table table-striped mb-0 request-narrow-table admin-detail-table admin-official-letters-table">
                    <colgroup>
                        <col style="width: 20%;">
                        <col style="width: 16%;">
                        <col style="width: 25%;">
                        <col style="width: 11%;">
                        <col style="width: 10%;">
                        <col style="width: 18%;">
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
                                $letterIsSent = $letter->status === 'issued';
                            @endphp
                            <tr>
                                <td>{{ $letter->recipientDisplayName() }}</td>
                                <td>{{ $letter->serial_number ?: __('app.dashboard.not_available') }}</td>
                                <td>{{ $letter->subject }}</td>
                                <td>{{ $letter->letter_date?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                <td><span class="badge bg-{{ $letterIsSent ? 'success' : 'secondary' }}">{{ $letter->localizedStatus() }}</span></td>
                                <td class="admin-official-letter-action-cell">
                                    <div class="d-flex gap-2 flex-wrap justify-content-center">
                                        <button class="btn btn-sm btn-icon btn-info-subtle rounded" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $officialLetterViewId($letter) }}" aria-controls="{{ $officialLetterViewId($letter) }}" title="{{ __('app.official_letters.view_action') }}">
                                            <i class="ph ph-eye fs-6"></i>
                                        </button>
                                        <a class="btn btn-sm btn-icon btn-secondary-subtle rounded" href="{{ route('admin.applications.official-letters.print', [$application, $letter]) }}" target="_blank" title="{{ __('app.official_letters.print_action') }}">
                                            <i class="ph ph-printer fs-6"></i>
                                        </a>
                                        @can('applications.review')
                                            @if (! $letterIsSent)
                                                <button class="btn btn-sm btn-icon btn-success-subtle rounded" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $officialLetterEditId($letter) }}" aria-controls="{{ $officialLetterEditId($letter) }}" title="{{ __('app.official_letters.edit_action') }}">
                                                    <i class="ph ph-pencil-simple fs-6"></i>
                                                </button>
                                                @if ($letterCanSend($letter))
                                                    <button class="btn btn-sm btn-icon btn-danger-subtle rounded" type="submit" form="{{ $officialLetterSendId($letter) }}" title="{{ __('app.official_letters.send_action') }}">
                                                        <i class="ph ph-paper-plane-tilt fs-6"></i>
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-icon btn-danger-subtle rounded" type="button" disabled title="{{ __('app.official_letters.send_requires_complete') }}">
                                                        <i class="ph ph-paper-plane-tilt fs-6"></i>
                                                    </button>
                                                @endif
                                            @endif
                                        @endcan
                                    </div>
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
        @endif
    </div>
</div>

@if (! $isDirectoryMode)
@can('applications.review')
    @if ($officialLetterApprovals->isNotEmpty())
        <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="{{ $officialLetterCreateId }}">
            <div class="offcanvas-header">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.official_letters.create_action') }}</span>
                </h2>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.official_letters.close_action') }}"></button>
            </div>
            <form method="POST" action="{{ route('admin.applications.official-letters.store', $application) }}" class="official-letter-offcanvas-form">
                <div class="offcanvas-body">
                    @include('admin.applications.partials.official-letter-form', ['letter' => null, 'formId' => 'official_letter_create', 'officialLetterApprovals' => $officialLetterApprovals])
                </div>
                <div class="offcanvas-footer border-top">
                    <div class="d-flex gap-3 p-3 justify-content-end">
                        <button type="submit" class="btn btn-danger d-flex align-items-center gap-2">
                            <i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.official_letters.save_action') }}
                        </button>
                        <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas">
                            <i class="ph ph-caret-double-left"></i>{{ __('app.official_letters.close_action') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif
@endcan
@endif

@foreach ($letters as $letter)
    <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="{{ $officialLetterViewId($letter) }}">
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
                    <div><span class="form-label px-2">{{ __('app.official_letters.target_entity') }}:</span> <span>{{ $letter->recipientDisplayName() }}</span></div>
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

    @can('applications.review')
        @if ($letter->status !== 'issued')
            <form id="{{ $officialLetterSendId($letter) }}" method="POST" action="{{ route('admin.applications.official-letters.send', [$application, $letter]) }}"
                data-application-submit-confirm
                data-confirm-title="{{ __('app.official_letters.send_confirm_title') }}"
                data-confirm-text="{{ $letter->isApplicantLetter() ? __('app.official_letters.send_confirm_body_applicant', ['entity' => $letter->recipientDisplayName()]) : __('app.official_letters.send_confirm_body', ['entity' => $letter->recipientDisplayName()]) }}"
                data-confirm-button="{{ __('app.official_letters.send_confirm_button') }}"
                data-cancel-button="{{ __('app.official_letters.cancel_send_action') }}">
                @csrf
            </form>

            <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="{{ $officialLetterEditId($letter) }}">
                <div class="offcanvas-header">
                    <h2 class="episode-playlist-title wp-heading-inline">
                        <span class="position-relative">{{ __('app.official_letters.edit_action') }}</span>
                    </h2>
                    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.official_letters.close_action') }}"></button>
                </div>
                <form method="POST" action="{{ route('admin.applications.official-letters.update', [$application, $letter]) }}" class="official-letter-offcanvas-form">
                    <div class="offcanvas-body">
                        @include('admin.applications.partials.official-letter-form', ['letter' => $letter, 'formId' => 'official_letter_'.$letter->getKey(), 'method' => 'PUT', 'officialLetterApprovals' => $officialLetterApprovals])
                    </div>
                    <div class="offcanvas-footer border-top">
                        <div class="d-flex gap-3 p-3 justify-content-end">
                            <button type="submit" class="btn btn-danger d-flex align-items-center gap-2">
                                <i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.official_letters.save_action') }}
                            </button>
                            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas">
                                <i class="ph ph-caret-double-left"></i>{{ __('app.official_letters.close_action') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    @endcan
@endforeach

@include('applications.partials.submit-confirmation-script')
