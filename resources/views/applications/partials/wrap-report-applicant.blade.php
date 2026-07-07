@php
    $payload = $wrapReport?->payload ?? [];
    $producer = data_get($application->metadata, 'producer', []);
    $wrapEndDate = $application->wrapReportAvailableDate();
    $productionTypeOptions = collect($wrapReportOptions['production_types'] ?? [])
        ->mapWithKeys(fn (string $type): array => [$type => __('app.wrap_report.production_types.'.$type)]);
    $accommodationTypeOptions = collect($wrapReportOptions['accommodation_types'] ?? [])
        ->mapWithKeys(fn (string $type): array => [$type => __('app.wrap_report.accommodation_types.'.$type)]);
    $workTypeMap = [
        'commercial' => 'commercials',
        'documentary' => 'feature_documentary',
        'feature_film' => 'feature_film',
        'music_video' => 'music_video',
        'reality_program' => 'reality_show',
        'series' => 'series',
        'short_film' => 'short_film',
        'student_project' => 'student_film',
        'tv_program' => 'tv_program',
    ];
    $defaultProductionTypes = filled($application->work_category) && isset($workTypeMap[$application->work_category])
        ? [$workTypeMap[$application->work_category]]
        : [];
    $selectedProductionTypes = (array) old('production_types', data_get($payload, 'production_types', $defaultProductionTypes));
    $selectedAccommodationTypes = (array) old('accommodation_types', data_get($payload, 'accommodation_types', []));
    $fieldValue = static fn (string $field, mixed $default = null): mixed => old($field, data_get($payload, $field, $default));
@endphp

@if (! $wrapReportAvailable)
    <div class="card request-section-card">
        <div class="card-header">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.wrap_report.title') }}</span>
            </h2>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-0">
                {{ __('app.wrap_report.locked_body', ['date' => $wrapEndDate?->format('Y-m-d') ?: __('app.dashboard.not_available')]) }}
            </div>
        </div>
    </div>
@else
    <div class="card request-section-card">
        <div class="card-header">
            <div class="header-title">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.wrap_report.title') }}</span>
                </h2>
                <p class="text-muted mb-0 mt-2">{{ __('app.wrap_report.intro') }}</p>
            </div>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($wrapReport?->submitted_at)
                <div class="alert alert-success">
                    {{ __('app.wrap_report.last_saved_at', ['date' => $wrapReport->submitted_at->format('Y-m-d H:i')]) }}
                </div>
            @endif

            <form method="POST" action="{{ route('applications.wrap-report.update', $application) }}" class="text-start">
                @csrf

                <h3 class="h5 mb-3">{{ __('app.wrap_report.sections.project_information') }}</h3>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label">{{ __('app.wrap_report.fields.project_name') }} <span class="text-danger">*</span></label>
                        <input class="form-control" name="project_name" value="{{ $fieldValue('project_name', $application->project_name) }}" required>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">{{ __('app.wrap_report.fields.production_company') }} <span class="text-danger">*</span></label>
                        <input class="form-control" name="production_company" value="{{ $fieldValue('production_company', data_get($producer, 'production_company_name') ?: $entity->displayName()) }}" required>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">{{ __('app.wrap_report.fields.local_producer_services_company') }} <span class="text-danger">*</span></label>
                        <input class="form-control" name="local_producer_services_company" value="{{ $fieldValue('local_producer_services_company', data_get($producer, 'producer_name') ?: data_get($producer, 'production_company_name')) }}" required>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">{{ __('app.wrap_report.fields.production_year') }} <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" min="1900" max="2100" name="production_year" value="{{ $fieldValue('production_year', optional($application->planned_start_date)->format('Y') ?: now()->format('Y')) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('app.wrap_report.fields.type_of_production') }} <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            @foreach ($productionTypeOptions as $value => $label)
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input id="wrap-production-type-{{ $value }}" class="form-check-input" type="checkbox" name="production_types[]" value="{{ $value }}" @checked(in_array($value, $selectedProductionTypes, true))>
                                        <label class="form-check-label" for="wrap-production-type-{{ $value }}">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">{{ __('app.wrap_report.fields.production_type_other') }}</label>
                        <input class="form-control" name="production_type_other" value="{{ $fieldValue('production_type_other') }}">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">{{ __('app.wrap_report.fields.nationalities') }} <span class="text-danger">*</span></label>
                        <input class="form-control" name="nationalities" value="{{ $fieldValue('nationalities', $application->projectNationalityLabels()) }}" required>
                    </div>
                </div>

                <hr class="my-4">

                <h3 class="h5 mb-3">{{ __('app.wrap_report.sections.production_statistics') }}</h3>
                <div class="row g-3">
                    @foreach ([
                        'local_crew_count',
                        'foreign_crew_count',
                        'hotel_nights_count',
                        'national_carrier_ticket_count',
                        'rented_cars_count',
                        'rental_days_count',
                    ] as $field)
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">{{ __('app.wrap_report.fields.'.$field) }} <span class="text-danger">*</span></label>
                            <input class="form-control" type="number" min="0" name="{{ $field }}" value="{{ $fieldValue($field, 0) }}" required>
                        </div>
                    @endforeach

                    <div class="col-12">
                        <label class="form-label">{{ __('app.wrap_report.fields.accommodation_type') }} <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach ($accommodationTypeOptions as $value => $label)
                                <div class="form-check">
                                    <input id="wrap-accommodation-type-{{ $value }}" class="form-check-input" type="checkbox" name="accommodation_types[]" value="{{ $value }}" @checked(in_array($value, $selectedAccommodationTypes, true))>
                                    <label class="form-check-label" for="wrap-accommodation-type-{{ $value }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">{{ __('app.wrap_report.fields.hotel_stars') }}</label>
                        <input class="form-control" type="number" min="1" max="5" name="hotel_stars" value="{{ $fieldValue('hotel_stars') }}">
                    </div>

                    @foreach ([
                        'production_days_pre_production',
                        'production_days_production',
                        'production_days_post_production',
                    ] as $field)
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">{{ __('app.wrap_report.fields.'.$field) }} <span class="text-danger">*</span></label>
                            <input class="form-control" type="number" min="0" name="{{ $field }}" value="{{ $fieldValue($field, 0) }}" required>
                        </div>
                    @endforeach

                    <div class="col-lg-6">
                        <label class="form-label">{{ __('app.wrap_report.fields.total_local_spending_jod') }} <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" step="0.01" min="0" name="total_local_spending_jod" value="{{ $fieldValue('total_local_spending_jod', data_get($application->metadata, 'budget.local_spend_estimate', 0)) }}" required>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">{{ __('app.wrap_report.fields.additional_notes') }}</label>
                        <textarea class="form-control" name="additional_notes" rows="5">{{ $fieldValue('additional_notes') }}</textarea>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">{{ __('app.wrap_report.fields.submitted_by') }} <span class="text-danger">*</span></label>
                        <input class="form-control" name="submitted_by" value="{{ $fieldValue('submitted_by', $user->displayName()) }}" required>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">{{ __('app.wrap_report.fields.submitted_position') }} <span class="text-danger">*</span></label>
                        <input class="form-control" name="submitted_position" value="{{ $fieldValue('submitted_position', data_get($producer, 'liaison_position')) }}" required>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">{{ __('app.wrap_report.fields.submitted_date') }} <span class="text-danger">*</span></label>
                        <input class="form-control" type="date" name="submitted_date" value="{{ $fieldValue('submitted_date', now()->format('Y-m-d')) }}" required>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-danger">{{ __('app.wrap_report.save_action') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
