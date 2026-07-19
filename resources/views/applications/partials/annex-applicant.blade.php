@php
    $metadata = $application->metadata ?? [];
    $annex = data_get($metadata, 'annex', []);
    $productionTerms = data_get($annex, 'production_terms', []);
    $ministryInteriorPersonalDetails = data_get($annex, 'ministry_interior_personal_details', []);
    $workContentSummary = data_get($annex, 'work_content_summary', []);
    $safetyGuidelines = data_get($annex, 'safety_guidelines', []);
    $airportFilming = data_get($annex, 'airport_filming', []);
    $governorateOptions = collect(data_get($locationLookupOptions ?? [], 'governorates', []));
    $locationTypeOptions = collect(data_get($locationLookupOptions ?? [], 'location_types', []));
    $locationTypesByGovernorate = (array) data_get($locationLookupOptions ?? [], 'location_types_by_governorate', []);
    $locationTypeLabels = (array) data_get($locationLookupOptions ?? [], 'location_type_labels', []);
    $formLookupOptions = $formLookupOptions ?? [];
    $specialLocationRequirementOptions = collect(data_get($formLookupOptions, 'special_location_requirements', []));
    $flightTypeOptions = ['arrival', 'departure'];
    $locationRequirementOptions = $specialLocationRequirementOptions->pluck('code')->all() ?: ['road_closures', 'police_presence', 'armed_forces', 'regular_aerial_filming', 'drone_filming', 'special_effects', 'construction_work', 'animals', 'weapons', 'other'];
    $locationRequirementLabels = $specialLocationRequirementOptions->mapWithKeys(fn ($option) => [$option->code => $option->displayName()])->all();
    $castCrewRows = old('cast_crew', data_get($annex, 'cast_crew', [['name' => '', 'role' => '', 'nationality' => '', 'gender' => '', 'birth_date' => '', 'identity_number' => '']]));
    $filmingLocationRows = old('filming_locations', data_get($annex, 'filming_locations', [['governorate' => '', 'location_name' => '', 'address' => '', 'nature' => '', 'location_type' => '', 'start_date' => '', 'end_date' => '']]));
    $specialLocationRequirementRows = old('special_location_requirements', data_get($annex, 'special_location_requirements', collect($locationRequirementOptions)->mapWithKeys(fn ($option) => [$option => ['locations' => [], 'notes' => '']])->all()));
    $importedEquipmentRows = old('imported_equipment', data_get($annex, 'imported_equipment', [['shipping_company_name' => '', 'invoice_number' => '', 'bill_of_lading_number' => '', 'arrival_date' => '', 'departure_date' => '', 'customs_center' => '', 'attachment_path' => '', 'attachment_name' => '']]));
    $publicSecuritySupportRows = old('public_security_support', data_get($annex, 'public_security_support', [['day' => '', 'date' => '', 'time_from' => '', 'time_to' => '', 'location' => '', 'requirement' => '', 'notes' => '']]));
    $militarySupportRows = old('military_support', data_get($annex, 'military_support', [['day' => '', 'date' => '', 'time_from' => '', 'time_to' => '', 'location' => '', 'requirement' => '', 'notes' => '']]));
    $locationSupportEditingState = \App\Support\LocationSupportRequirements::editingState(
        (array) $annex,
        (array) $filmingLocationRows,
        old('location_support_requirements'),
    );
    $filmingLocationRows = $locationSupportEditingState['locations'];
    $locationSupportRequirementRows = $locationSupportEditingState['requirements'];
    $airportPeopleRows = old('airport_people', data_get($annex, 'airport_people', [['full_name' => '', 'nationality' => '', 'mother_name' => '', 'identity_number' => '', 'profession' => '', 'address_phone' => '', 'entry_reason' => '', 'target_area' => '']]));
    $governmentalSceneRows = old('governmental_scenes', data_get($annex, 'governmental_scenes', [['site_name' => '', 'authority' => '', 'scene_description' => '', 'filming_date' => '']]));
    $rowHasData = static fn ($row): bool => collect((array) $row)->flatten()->contains(fn ($value) => filled($value));
    $rowsHaveData = static fn ($rows): bool => collect((array) $rows)->contains(fn ($row) => $rowHasData($row));
    $canUpdateApplicantAnnex = $application->canUpdateApplicantAnnex();
    $annexSubmissions = collect($annexSubmissions ?? $application->annexSubmissions ?? []);
    $pendingAnnexSubmission = $annexSubmissions->firstWhere('status', \App\Models\ApplicationAnnexSubmission::STATUS_SUBMITTED);
    $locationTypeOptionsForGovernorate = static function ($governorateCode) use ($locationTypeOptions, $locationTypesByGovernorate) {
        $governorateCode = filled($governorateCode) ? (string) $governorateCode : null;

        if (! $governorateCode || ! isset($locationTypesByGovernorate[$governorateCode])) {
            return $locationTypeOptions;
        }

        return $locationTypeOptions->filter(fn ($locationType): bool => in_array($locationType->code, (array) $locationTypesByGovernorate[$governorateCode], true))->values();
    };
    $annexRows = [
        [
            'label' => __('app.applications.annex_sections.work_content_summary'),
            'target' => 'WorkContentSummary',
            'filled' => filled(data_get($workContentSummary, 'synopsis')) && (bool) data_get($workContentSummary, 'confirmed'),
        ],
        [
            'label' => __('app.applications.annex_sections.cast_crew'),
            'target' => 'CastCrewList',
            'filled' => $rowsHaveData(data_get($annex, 'cast_crew', [])),
        ],
        [
            'label' => __('app.applications.annex_sections.filming_locations'),
            'target' => 'LocationList',
            'filled' => $rowsHaveData(data_get($annex, 'filming_locations', [])),
        ],
        [
            'label' => __('app.applications.annex_sections.safety_guidelines'),
            'target' => 'RFCGuidelines',
            'filled' => (bool) data_get($safetyGuidelines, 'acknowledged') || filled(data_get($safetyGuidelines, 'notes')),
        ],
        [
            'label' => __('app.applications.annex_sections.production_terms'),
            'target' => 'ProductionTerms',
            'filled' => (bool) data_get($productionTerms, 'accepted'),
        ],
        [
            'label' => __('app.applications.annex_sections.ministry_interior_personal_details'),
            'target' => 'MinistryInteriorPersonalDetails',
            'filled' => \App\Support\MinistryInteriorPersonalDetails::hasAnyConfirmed($ministryInteriorPersonalDetails),
        ],
        [
            'label' => __('app.applications.annex_sections.imported_equipment'),
            'target' => 'EquipmentList',
            'filled' => $rowsHaveData(data_get($annex, 'imported_equipment', [])) || $rowsHaveData(data_get($annex, 'equipment_travelers', [])),
        ],
        [
            'label' => __('app.applications.annex_sections.airport_filming'),
            'target' => 'FilmingAirports',
            'filled' => filled(data_get($airportFilming, 'airport_name')) || filled(data_get($airportFilming, 'area')) || filled(data_get($airportFilming, 'filming_date')) || $rowsHaveData(data_get($annex, 'airport_people', [])),
        ],
        [
            'label' => __('app.applications.annex_sections.governmental_scenes'),
            'target' => 'FilmingGovernmental',
            'filled' => $rowsHaveData(data_get($annex, 'governmental_scenes', [])) || (bool) data_get($annex, 'governmental_scenes_confirmed'),
        ],
    ];
@endphp

@once
    @push('styles')
        <style>
            .applicant-annex-table-wrap {
                overflow-x: auto;
            }

            .applicant-annex-table {
                min-width: 720px;
            }

            .applicant-annex-table td {
                vertical-align: middle;
            }

            .applicant-annex-list {
                margin-top: .75rem;
            }

            .applicant-annex-list .list-group-item {
                border: 0;
                border-radius: 4px;
                margin-bottom: .35rem;
                padding: .65rem .85rem;
            }

            .applicant-annex-list .list-group-item a {
                color: inherit;
                font-weight: 600;
            }
        </style>
    @endpush
@endonce

<form id="applicant-annex-form" method="POST" action="{{ route('applications.annex.update', $application) }}" enctype="multipart/form-data">
    @csrf
    <div class="card">
        <div class="card-body">
            <div class="form-card text-start pb-4">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.documents.annex_forms_heading') }}</span>
                </h2>

                @if ($pendingAnnexSubmission)
                    <div class="alert alert-warning border mt-4 mb-0">
                        <div class="fw-600">{{ __('app.annex_submissions.pending_applicant_title') }}</div>
                        <div>{{ __('app.annex_submissions.pending_applicant_body') }}</div>
                        <div class="border-top mt-3 pt-3">
                            @include('applications.partials.annex-summary', [
                                'application' => $application,
                                'annexPayload' => $pendingAnnexSubmission->payload ?? [],
                                'tableClass' => 'table table-striped mb-0',
                            ])
                        </div>
                    </div>
                @elseif ($annexSubmissions->isNotEmpty())
                    <div class="alert alert-light border mt-4 mb-0">
                        <div class="fw-600">{{ __('app.annex_submissions.latest_status_title') }}</div>
                        <div>{{ $annexSubmissions->first()?->localizedStatus() }}</div>
                        @if (filled($annexSubmissions->first()?->review_note))
                            <div class="mt-2">{{ $annexSubmissions->first()?->review_note }}</div>
                        @endif
                    </div>
                @endif

                <div class="row">
                    <div class="table-responsive mt-4 applicant-annex-table-wrap">
                        <table class="table table-striped mb-0 mx-auto applicant-annex-table" style="width: 88%" role="grid">
                            <tbody>
                                @foreach ($annexRows as $annexRow)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img class="rounded img-fluid avatar-40 me-3 bg-primary-subtle" src="{{ asset('images/clapboard.png') }}" alt="profile" loading="lazy">
                                                <h6 class="mb-0">{{ $annexRow['label'] }}</h6>
                                            </div>

                                            @if ($annexRow['filled'])
                                                <div class="list-group px-5 applicant-annex-list">
                                                    <div class="list-group-item d-flex justify-content-between align-items-center iq-bg-danger">
                                                        <a href="#" data-bs-toggle="offcanvas" data-bs-target="#{{ $annexRow['target'] }}" aria-controls="{{ $annexRow['target'] }}">
                                                            {{ __('app.documents.annex_item_label', ['number' => 1]) }}
                                                        </a>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-danger" type="button"
                                                data-annex-add-button
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#{{ $annexRow['target'] }}"
                                                aria-controls="{{ $annexRow['target'] }}"
                                                @disabled(! $canUpdateApplicantAnnex)>
                                                <i class="fa-solid fa-plus me-2"></i>{{ __('app.documents.add_annex_action') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('applications.partials.requirement-offcanvases', [
        'submitAnnexForms' => true,
        'requireSafetyGuidelines' => false,
        'annexSaveDisabled' => ! $canUpdateApplicantAnnex,
    ])
</form>

@include('applications.partials.annex-form-scripts')
