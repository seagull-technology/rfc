@php
    $payload = $wrapReport?->payload ?? [];
    $productionTypeLabels = collect((array) data_get($payload, 'production_types', []))
        ->map(fn (string $type): string => __('app.wrap_report.production_types.'.$type))
        ->filter()
        ->values();
    if (in_array('other', (array) data_get($payload, 'production_types', []), true) && filled(data_get($payload, 'production_type_other'))) {
        $productionTypeLabels->push(data_get($payload, 'production_type_other'));
    }
    $accommodationTypeLabels = collect((array) data_get($payload, 'accommodation_types', []))
        ->map(fn (string $type): string => __('app.wrap_report.accommodation_types.'.$type))
        ->filter()
        ->values();
    $displayValue = static fn (string $field): mixed => filled(data_get($payload, $field)) ? data_get($payload, $field) : __('app.dashboard.not_available');
@endphp

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0">{{ __('app.wrap_report.title') }}</h3>
    </div>
    <div class="card-body">
        @if (! $wrapReport)
            <div class="alert alert-info mb-0">
                {{ $wrapReportAvailable ? __('app.wrap_report.admin_empty_available') : __('app.wrap_report.admin_empty_locked') }}
            </div>
        @else
            <div class="mb-3 text-muted">
                {{ __('app.wrap_report.last_saved_at', ['date' => $wrapReport->submitted_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available')]) }}
            </div>

            <div class="table-responsive">
                <table class="table table-striped mb-0 admin-detail-table">
                    <tbody>
                        @foreach ([
                            'project_name',
                            'production_company',
                            'local_producer_services_company',
                            'nationalities',
                            'production_year',
                            'local_crew_count',
                            'foreign_crew_count',
                            'hotel_nights_count',
                            'hotel_stars',
                            'national_carrier_ticket_count',
                            'rented_cars_count',
                            'rental_days_count',
                            'rented_cars_total_days',
                            'production_days_pre_production',
                            'production_days_production',
                            'production_days_post_production',
                            'total_production_days',
                            'total_local_spending_jod',
                            'submitted_by',
                            'submitted_position',
                            'submitted_date',
                        ] as $field)
                            <tr>
                                <th>{{ __('app.wrap_report.fields.'.$field) }}</th>
                                <td>{{ $displayValue($field) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th>{{ __('app.wrap_report.fields.type_of_production') }}</th>
                            <td>{{ $productionTypeLabels->join('، ') ?: __('app.dashboard.not_available') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('app.wrap_report.fields.accommodation_type') }}</th>
                            <td>{{ $accommodationTypeLabels->join('، ') ?: __('app.dashboard.not_available') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('app.wrap_report.fields.additional_notes') }}</th>
                            <td style="white-space: pre-wrap">{{ $displayValue('additional_notes') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
