@php
    $ministryInteriorPersonalDetailsReadOnly = (bool) ($ministryInteriorPersonalDetailsReadOnly ?? false);
    $ministryInteriorPersonalDetailsIdPrefix = $ministryInteriorPersonalDetailsIdPrefix ?? 'ministry_interior_personal_details';
    $ministryCountryOptions = collect($ministryNationalityOptions ?? data_get($nationalityOptions ?? [], 'director', []))
        ->values();
    $ministryNationalityOptions = $ministryCountryOptions
        ->reject(function ($nationality) {
            $code = mb_strtolower(trim((string) data_get($nationality, 'code')));
            $nameAr = trim((string) data_get($nationality, 'name_ar'));
            $nameEn = mb_strtolower(trim((string) data_get($nationality, 'name_en')));

            return in_array($code, ['jo', 'jor', 'jordanian'], true)
                || in_array($nameAr, ['أردني', 'أردنية', 'الأردن', 'المملكة الأردنية الهاشمية'], true)
                || in_array($nameEn, ['jordan', 'jordanian'], true);
        })
        ->values();
    $todayDate = now()->startOfDay()->toDateString();
    $minimumTravelDocumentExpiryDate = now()->startOfDay()->addMonthsNoOverflow(6)->toDateString();
    $submittedDetails = old('ministry_interior_personal_details', $ministryInteriorPersonalDetails ?? []);
    $ministryInteriorPersonalDetailsRows = \App\Support\MinistryInteriorPersonalDetails::rows($submittedDetails);
    $submittedRequest = old(
        'ministry_interior_personal_details_request',
        $ministryInteriorPersonalDetailsRequest
            ?? data_get($annex ?? [], 'ministry_interior_personal_details_request', [])
    );
    $requestType = (string) data_get($submittedRequest, 'type', 'normal');
    $urgentFeeAccepted = filter_var(data_get($submittedRequest, 'urgent_fee_accepted', false), FILTER_VALIDATE_BOOL);
    $palestinianNationalityCodes = $ministryNationalityOptions
        ->filter(function ($nationality) {
            return str_contains((string) data_get($nationality, 'name_ar'), 'فلسطين')
                || str_contains(mb_strtolower((string) data_get($nationality, 'name_en')), 'palestin');
        })
        ->pluck('code')
        ->map(fn ($code) => (string) $code)
        ->values();

    if (! $ministryInteriorPersonalDetailsReadOnly && $ministryInteriorPersonalDetailsRows === []) {
        $ministryInteriorPersonalDetailsRows = [[]];
    }
@endphp

@once
    <style nonce="{{ $cspNonce ?? '' }}">
        .ministry-personal-details-form {
            color: #1f2937;
            width: 100%;
        }

        .ministry-personal-details-form__notices {
            background: #f8f9fb;
            border-inline-start: 4px solid #721f1b;
            margin-bottom: 1.25rem;
            padding: 1rem 1.25rem;
        }

        .ministry-personal-details-form__notice {
            align-items: flex-start;
            display: flex;
            gap: .65rem;
        }

        .ministry-personal-details-form__notice + .ministry-personal-details-form__notice {
            margin-top: .55rem;
        }

        .ministry-personal-details-form__notice i {
            color: #721f1b;
            margin-top: .2rem;
        }

        .ministry-personal-details-form__record {
            background: #fff;
            border: 1px solid #d8dde5;
            border-radius: 4px;
            padding: clamp(1rem, 2.5vw, 2rem);
        }

        .ministry-personal-details-form__record + .ministry-personal-details-form__record {
            margin-top: 1.25rem;
        }

        .ministry-personal-details-form__record-header {
            align-items: center;
            border-bottom: 2px solid #721f1b;
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: .85rem;
        }

        .ministry-personal-details-form__section {
            border-bottom: 1px solid #e2e6ec;
            padding: .25rem 0 1.5rem;
        }

        .ministry-personal-details-form__section + .ministry-personal-details-form__section {
            padding-top: 1.5rem;
        }

        .ministry-personal-details-form__section:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .ministry-personal-details-form__section-title {
            color: #721f1b;
            font-size: 1.12rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .ministry-personal-details-form__lookup {
            align-items: flex-end;
            display: grid;
            gap: .75rem;
            grid-template-columns: minmax(220px, 1fr) auto;
        }

        .ministry-personal-details-form__lookup-status {
            font-size: .875rem;
            grid-column: 1 / -1;
            min-height: 1.25rem;
        }

        .ministry-personal-details-form__attachment {
            background: #f8f9fb;
            border: 1px solid #e0e4ea;
            border-radius: 4px;
            padding: 1rem;
        }

        .ministry-personal-details-form__attachment + .ministry-personal-details-form__attachment {
            margin-top: .75rem;
        }

        .ministry-personal-details-form .form-control:disabled,
        .ministry-personal-details-form .form-select:disabled {
            background: #f1f3f6;
            color: #343a40;
            opacity: 1;
        }

        .ministry-personal-details-form__empty {
            border: 1px dashed #c7ccd4;
            border-radius: 4px;
            color: #6b7280;
            padding: 2rem 1rem;
            text-align: center;
        }

        .ministry-personal-details-form__request {
            border: 1px solid #d8dde6;
            background: #fff;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .ministry-personal-details-form__request-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .ministry-personal-details-form__request-option {
            display: flex;
            align-items: center;
            gap: .65rem;
            min-height: 3.25rem;
            border: 1px solid #cfd5df;
            padding: .75rem 1rem;
            cursor: pointer;
        }

        .ministry-personal-details-form__urgent-warning {
            border-inline-start: 4px solid #9b241f;
            background: #fff2f1;
            color: #671815;
            padding: 1rem;
            margin-top: 1rem;
        }

        .ministry-personal-details-form__urgent-warning p {
            margin: 0;
        }

        .ministry-personal-details-form__urgent-modal[hidden] {
            display: none !important;
        }

        .ministry-personal-details-form__urgent-modal {
            align-items: center;
            background: rgb(15 18 23 / 68%);
            display: flex;
            inset: 0;
            justify-content: center;
            padding: 1rem;
            position: fixed;
            z-index: 1080;
        }

        .ministry-personal-details-form__urgent-dialog {
            background: #fff;
            border-top: 5px solid #70251f;
            box-shadow: 0 1.5rem 3rem rgb(0 0 0 / 25%);
            max-width: 40rem;
            padding: 1.5rem;
            width: min(100%, 40rem);
        }

        .ministry-personal-details-form__urgent-dialog h3 {
            color: #1f2937;
            font-size: 1.4rem;
            margin: 0 0 .75rem;
        }

        .ministry-personal-details-form__urgent-dialog p {
            color: #5b2320;
            line-height: 1.9;
            margin: 0 0 1rem;
        }

        .ministry-personal-details-form__urgent-dialog label {
            align-items: flex-start;
            border: 1px solid #d8dde6;
            display: flex;
            gap: .65rem;
            line-height: 1.7;
            padding: .85rem 1rem;
        }

        .ministry-personal-details-form__urgent-dialog input[type="checkbox"] {
            margin-top: .3rem;
        }

        @media (max-width: 767.98px) {
            .ministry-personal-details-form__request-options {
                grid-template-columns: 1fr;
            }

            .ministry-personal-details-form__lookup {
                grid-template-columns: 1fr;
            }

            .ministry-personal-details-form__lookup .btn {
                width: 100%;
            }
        }

        @media print {
            .ministry-personal-details-form__notices,
            .ministry-personal-details-form [data-ministry-personal-details-add],
            .ministry-personal-details-form [data-ministry-personal-details-remove],
            .ministry-personal-details-form [data-ministry-attachment-add],
            .ministry-personal-details-form [data-ministry-attachment-remove],
            .ministry-personal-details-form [data-ministry-personal-details-lookup] {
                display: none !important;
            }

            .ministry-personal-details-form__record {
                break-inside: avoid;
            }
        }
    </style>
@endonce

<div
    class="ministry-personal-details-form"
    @unless($ministryInteriorPersonalDetailsReadOnly)
        data-ministry-personal-details-editor
        data-next-index="{{ count($ministryInteriorPersonalDetailsRows) }}"
        data-palestinian-nationality-codes='@json($palestinianNationalityCodes)'
    @endunless
>
    <div class="ministry-personal-details-form__notices">
        @foreach (['passport', 'residence', 'normal_processing', 'urgent_processing'] as $notice)
            <div class="ministry-personal-details-form__notice">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>{{ __('app.applications.ministry_interior_personal_details.notices.'.$notice) }}</span>
            </div>
        @endforeach
    </div>

    <section class="ministry-personal-details-form__request">
        <h3>{{ __('app.applications.ministry_interior_personal_details.request_type.title') }}</h3>

        @if($ministryInteriorPersonalDetailsReadOnly)
            <p class="mb-0">
                <strong>{{ __('app.applications.ministry_interior_personal_details.request_type.'.$requestType) }}</strong>
            </p>
            @if($requestType === 'urgent')
                <div class="ministry-personal-details-form__urgent-warning mt-3">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>{{ __('app.applications.ministry_interior_personal_details.request_type.urgent_warning') }}</span>
                </div>
            @endif
        @else
            <div class="ministry-personal-details-form__request-options">
                @foreach (['normal', 'urgent'] as $type)
                    <label class="ministry-personal-details-form__request-option">
                        <input
                            type="radio"
                            name="ministry_interior_personal_details_request[type]"
                            value="{{ $type }}"
                            @checked($requestType === $type)
                            data-ministry-request-type
                        >
                        <span>{{ __('app.applications.ministry_interior_personal_details.request_type.'.$type) }}</span>
                    </label>
                @endforeach
            </div>

            <div
                class="ministry-personal-details-form__urgent-warning mt-3"
                data-ministry-urgent-warning
                @if($requestType !== 'urgent' || ! $urgentFeeAccepted) hidden @endif
            >
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span>{{ __('app.applications.ministry_interior_personal_details.request_type.urgent_warning') }}</span>
            </div>

            <div
                class="ministry-personal-details-form__urgent-modal"
                data-ministry-urgent-modal
                role="dialog"
                aria-modal="true"
                aria-labelledby="ministry-urgent-dialog-title"
                @if($requestType !== 'urgent' || $urgentFeeAccepted) hidden @endif
            >
                <section class="ministry-personal-details-form__urgent-dialog">
                    <h3 id="ministry-urgent-dialog-title">
                        {{ __('app.applications.ministry_interior_personal_details.request_type.urgent') }}
                    </h3>
                    <p>{{ __('app.applications.ministry_interior_personal_details.request_type.urgent_warning') }}</p>
                    <label>
                        <input
                            type="checkbox"
                            name="ministry_interior_personal_details_request[urgent_fee_accepted]"
                            value="1"
                            @checked($urgentFeeAccepted)
                            data-ministry-urgent-acceptance
                        >
                        <span>{{ __('app.applications.ministry_interior_personal_details.request_type.urgent_acceptance') }}</span>
                    </label>
                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary" data-ministry-urgent-cancel>
                            {{ __('app.applications.cancel_send_action') }}
                        </button>
                        <button type="button" class="btn btn-primary" data-ministry-urgent-confirm disabled>
                            {{ __('app.applications.submit_confirm_confirm') }}
                        </button>
                    </div>
                </section>
            </div>
        @endif
    </section>

    @unless($ministryInteriorPersonalDetailsReadOnly)
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
            <button type="button" class="btn btn-success" data-ministry-personal-details-add>
                <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>{{ __('app.applications.ministry_interior_personal_details.add_person') }}
            </button>
            <span class="text-muted" data-ministry-personal-details-count data-count-template="{{ __('app.applications.ministry_interior_personal_details.records_count', ['count' => '__COUNT__']) }}">
                {{ __('app.applications.ministry_interior_personal_details.records_count', ['count' => count($ministryInteriorPersonalDetailsRows)]) }}
            </span>
        </div>
    @endunless

    <div data-ministry-personal-details-rows>
        @forelse ($ministryInteriorPersonalDetailsRows as $rowIndex => $row)
            @include('applications.partials.ministry-interior-personal-details-row', [
                'row' => $row,
                'rowIndex' => $rowIndex,
                'inputIndex' => $rowIndex,
            ])
        @empty
            <div class="ministry-personal-details-form__empty" data-ministry-personal-details-empty>
                {{ __('app.applications.ministry_interior_personal_details.empty_state') }}
            </div>
        @endforelse
    </div>

    @unless($ministryInteriorPersonalDetailsReadOnly)
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mt-3">
            <button type="button" class="btn btn-success" data-ministry-personal-details-add>
                <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>{{ __('app.applications.ministry_interior_personal_details.add_person') }}
            </button>
            <span class="text-muted" data-ministry-personal-details-count data-count-template="{{ __('app.applications.ministry_interior_personal_details.records_count', ['count' => '__COUNT__']) }}">
                {{ __('app.applications.ministry_interior_personal_details.records_count', ['count' => count($ministryInteriorPersonalDetailsRows)]) }}
            </span>
        </div>

        <template data-ministry-personal-details-template>
            @include('applications.partials.ministry-interior-personal-details-row', [
                'row' => [],
                'rowIndex' => 0,
                'inputIndex' => '__INDEX__',
            ])
        </template>
    @endunless
</div>

@unless($ministryInteriorPersonalDetailsReadOnly)
    @include('applications.partials.ministry-interior-personal-details-script')
@endunless
