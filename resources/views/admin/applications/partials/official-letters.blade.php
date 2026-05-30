@php
    $letters = collect($officialLetters ?? []);
    $officialLetterApprovals = collect($officialLetterApprovals ?? []);
@endphp

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="header-title">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.official_letters.title') }}</span>
                </h2>
            </div>
            @can('applications.review')
                @if ($officialLetterApprovals->isNotEmpty())
                    <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#officialLetterCreate" aria-controls="officialLetterCreate">
                        <i class="fa-solid fa-plus me-2"></i>{{ __('app.official_letters.create_action') }}
                    </button>
                @else
                    <button class="btn btn-danger" type="button" disabled title="{{ __('app.official_letters.no_routed_authorities') }}">
                        <i class="fa-solid fa-plus me-2"></i>{{ __('app.official_letters.create_action') }}
                    </button>
                @endif
            @endcan
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped mb-0 admin-official-letters-table">
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
                        <tr>
                            <td>{{ $letter->targetEntity?->displayName() ?? __('app.dashboard.not_available') }}</td>
                            <td>{{ $letter->serial_number ?: __('app.dashboard.not_available') }}</td>
                            <td>{{ $letter->subject }}</td>
                            <td>{{ $letter->letter_date?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                            <td><span class="badge bg-{{ $letter->status === 'issued' ? 'success' : 'secondary' }}">{{ $letter->localizedStatus() }}</span></td>
                            <td class="admin-official-letter-action-cell">
                                <div class="d-flex gap-2 flex-wrap justify-content-center">
                                    <button class="btn btn-sm btn-icon btn-info-subtle rounded" type="button" data-bs-toggle="offcanvas" data-bs-target="#officialLetterView{{ $letter->getKey() }}" aria-controls="officialLetterView{{ $letter->getKey() }}" title="{{ __('app.official_letters.view_action') }}">
                                        <i class="ph ph-eye fs-6"></i>
                                    </button>
                                    @can('applications.review')
                                        <button class="btn btn-sm btn-icon btn-success-subtle rounded" type="button" data-bs-toggle="offcanvas" data-bs-target="#officialLetterEdit{{ $letter->getKey() }}" aria-controls="officialLetterEdit{{ $letter->getKey() }}" title="{{ __('app.official_letters.edit_action') }}">
                                            <i class="ph ph-pencil-simple fs-6"></i>
                                        </button>
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
    </div>
</div>

@can('applications.review')
    @if ($officialLetterApprovals->isNotEmpty())
        <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="officialLetterCreate">
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

@foreach ($letters as $letter)
    <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="officialLetterView{{ $letter->getKey() }}">
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
                    <div><span class="form-label px-2">{{ __('app.official_letters.target_entity') }}:</span> <span>{{ $letter->targetEntity?->displayName() ?? __('app.dashboard.not_available') }}</span></div>
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
        <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="officialLetterEdit{{ $letter->getKey() }}">
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
    @endcan
@endforeach
