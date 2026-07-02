@php
    $title = __('app.admin.dashboard.title');
    $breadcrumb = __('app.admin.navigation.dashboard');
    $dashboardIntro = __('app.admin.dashboard.operational_kpis_intro');
    $profileEntityName = $entity?->displayName() ?? __('app.dashboard.no_entity');
    $statusClass = static fn (?string $status): string => match ($status) {
        'approved' => 'success',
        'under_review', 'submitted', 'pending_review' => 'warning',
        'rejected' => 'danger',
        'draft' => 'secondary',
        default => 'info',
    };
    $translateOrFallback = static function (string $translationKey, string $fallback): string {
        $translated = __($translationKey);

        return $translated === $translationKey ? $fallback : $translated;
    };
    $formatFallback = static fn (?string $value): string => filled($value) ? str((string) $value)->replace('_', ' ')->title()->toString() : __('app.dashboard.not_available');
    $applicationsByCategoryChart = $chartData['applications_by_category']
        ->map(fn (int $count, string $key): array => ['label' => \App\Models\WorkCategory::labelFor($key), 'value' => $count])
        ->values();
    $applicationsByReleaseChart = $chartData['applications_by_release_method']
        ->map(fn (int $count, string $key): array => ['label' => \App\Models\ReleaseMethod::labelFor($key), 'value' => $count])
        ->values();
    $registrationBreakdownChart = $chartData['registrations_by_type']
        ->map(fn (int $count, string $key): array => ['label' => __('app.registration_types.'.$key), 'value' => $count])
        ->values();
    $approvalDurationChart = $chartData['approval_duration_by_authority']
        ->map(fn (array $row): array => ['label' => $translateOrFallback('app.applications.required_approval_options.'.$row['code'], $formatFallback($row['code'])), 'value' => $row['average_hours']])
        ->values();
    $monthlyApplicationComparison = $chartData['monthly_application_comparison'] ?? [
        'year' => now()->year,
        'previous_year' => now()->year - 1,
        'labels' => collect(),
        'current' => collect(),
        'previous' => collect(),
        'current_total' => 0,
        'previous_total' => 0,
        'delta' => 0,
        'delta_percent' => 0,
    ];
    $monthlyApplicationLabels = $chartData['monthly_applications']->pluck('label')->values();
    $monthlyApplicationCounts = $chartData['monthly_applications']->pluck('count')->values();
    $dashboardComparisonYears = collect($dashboardComparisonYears ?? [now()->year])->values();
    $operationalKpis = $productionOperationalReport['kpis'] ?? [];
    $operationalMap = collect($productionOperationalReport['map'] ?? []);
    $operationalCharts = $productionOperationalReport['charts'] ?? [];
    $dashboardFilters = $dashboardFilters ?? data_get($productionOperationalReport, 'filters', []);
    $dashboardFilterOptions = $dashboardFilterOptions ?? data_get($productionOperationalReport, 'options', []);
    $dashboardApplicationFacts = collect(data_get($productionOperationalReport, 'facts.applications', []));
    $dashboardLocationFacts = collect(data_get($productionOperationalReport, 'facts.locations', []))
        ->whereIn('timing', ['active', 'future'])
        ->values();
    $dashboardMapData = $operationalMap
        ->mapWithKeys(fn (array $row): array => [$row['code'] => $row])
        ->all();
    $dashboardMapStats = $operationalMap
        ->map(function (array $row): array {
            return [
                'code' => $row['code'],
                'label' => $row['label'],
                'work_types' => collect(data_get($row, 'work_types', []))->values()->all(),
                'release_methods' => collect(data_get($row, 'release_methods', []))->values()->all(),
                'production_scope' => collect(data_get($row, 'production_scope', []))->values()->all(),
                'active' => $row['active'] ?? 0,
                'future' => $row['future'] ?? 0,
                'completed' => $row['completed'] ?? 0,
                'projects' => $row['projects'] ?? 0,
                'locations' => $row['locations'] ?? 0,
                'spend' => $row['spend'] ?? 0,
            ];
        })
        ->values();
    $operationalProductionTypeChart = collect(data_get($operationalCharts, 'production_types', []))->values();
    $operationalScopeChart = collect(data_get($operationalCharts, 'production_scope', []))->values();
    $operationalSpendGovernorateChart = collect(data_get($operationalCharts, 'spend_by_governorate', []))->values();
    $operationalActivityGovernorateChart = collect(data_get($operationalCharts, 'activity_by_governorate', []))->values();
    $dashboardMapUrl = asset('json/jordan.json');
    $formatDashboardNumber = static fn ($value): string => number_format((float) $value);
    $formatDashboardMoney = static fn ($value): string => number_format((float) $value, 2).' JOD';
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-template-layout')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <style>
        .admin-template-layout {
            padding-top: 0;
        }

        .admin-template-layout > .row > [class*="col-"] {
            margin-bottom: 1.5rem;
        }

        .admin-template-layout .card {
            margin-bottom: 0;
        }

        .admin-template-layout .card-header {
            padding-bottom: 0;
        }

        .admin-template-layout .card-dashboard .card-header {
            padding-bottom: 0;
        }

        .admin-template-layout .card-dashboard .card-body > .table-responsive:first-child {
            margin-top: 1rem;
        }

        .admin-template-layout .admin-mini-card .card-body {
            min-height: 178px;
        }

        .admin-template-layout .admin-sparkline {
            min-height: 58px;
        }

        .admin-template-layout .admin-chart-card .card-body {
            min-height: 350px;
        }

        .admin-template-layout .admin-chart-surface {
            min-height: 280px;
        }

        .admin-template-layout .admin-wide-chart .card-body {
            min-height: 320px;
        }

        .admin-template-layout .admin-empty-chart {
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .admin-template-layout .table thead th,
        .admin-template-layout .table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .admin-template-layout .badge.bg-light.text-dark {
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .admin-template-layout .admin-dashboard-table-scroll {
            overflow-x: auto;
        }

        .admin-template-layout .admin-dashboard-table {
            table-layout: fixed;
            width: 100%;
        }

        .admin-template-layout .admin-dashboard-table thead th,
        .admin-template-layout .admin-dashboard-table tbody td {
            white-space: normal;
            vertical-align: top;
            word-break: break-word;
        }

        .admin-template-layout .admin-recent-requests-table {
            min-width: 1040px;
        }

        .admin-template-layout .response-flag {
            margin-top: .5rem;
        }

        .admin-template-layout .response-flag .small {
            display: block;
            margin-top: .35rem;
        }

        .admin-template-layout .dashboard-command-card {
            border: 1px solid #e2e7ef;
            background: #fff;
            min-height: 142px;
        }

        .admin-template-layout .dashboard-command-card .card-body {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 142px;
        }

        .admin-template-layout .dashboard-command-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(111, 31, 27, .08);
            color: #6f1f1b;
        }

        .admin-template-layout .dashboard-command-label {
            color: #667085;
            font-weight: 600;
        }

        .admin-template-layout .dashboard-section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .85rem;
        }

        .admin-template-layout .dashboard-section-kicker {
            color: #667085;
            font-size: .875rem;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .admin-template-layout .dashboard-operations-panel {
            border: 1px solid #e2e7ef;
            background: #fff;
            height: 100%;
        }

        .admin-template-layout .dashboard-operations-panel .card-body {
            min-height: 372px;
        }

        .admin-template-layout .dashboard-filter-card {
            border: 1px solid #e2e7ef;
            background: #fff;
        }

        .admin-template-layout .dashboard-filter-card .form-label {
            font-weight: 700;
            color: #394150;
        }

        .admin-template-layout .dashboard-kpi-card {
            border: 1px solid #e2e7ef;
            background: #fff;
            height: 100%;
            overflow: hidden;
        }

        .admin-template-layout .dashboard-kpi-card .card-body {
            min-height: 148px;
        }

        .admin-template-layout .dashboard-kpi-icon {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(111, 31, 27, .08);
            color: #6f1f1b;
        }

        .admin-template-layout .dashboard-kpi-card h2 {
            font-size: 2rem;
            font-weight: 800;
        }

        .admin-template-layout .dashboard-filtered-chip {
            border: 1px solid #e2e7ef;
            background: #f8fafc;
            color: #4b5563;
            font-weight: 700;
        }

        .admin-template-layout .operational-map-grid {
            display: grid;
            gap: .75rem;
        }

        .admin-template-layout .operational-governorate-row {
            display: grid;
            grid-template-columns: minmax(150px, 1fr) minmax(190px, 2fr) auto;
            gap: .85rem;
            align-items: center;
            padding: .85rem 0;
            border-bottom: 1px solid #eef1f5;
            background: #fff;
        }

        .admin-template-layout .operational-governorate-row:last-child {
            border-bottom: 0;
        }

        .admin-template-layout .operational-activity-stack {
            display: flex;
            height: 12px;
            overflow: hidden;
            background: #eef1f5;
        }

        .admin-template-layout .operational-activity-stack .active {
            background: #1f9d55;
        }

        .admin-template-layout .operational-activity-stack .future {
            background: #2f80ed;
        }

        .admin-template-layout .operational-activity-stack .completed {
            background: #98a2b3;
        }

        .admin-template-layout .dashboard-approval-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: .85rem 0;
            border-bottom: 1px solid #eef1f5;
        }

        .admin-template-layout .dashboard-approval-row:last-child {
            border-bottom: 0;
        }

        .admin-template-layout .dashboard-map-layout {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(280px, .85fr);
            gap: 1rem;
            align-items: start;
        }

        .admin-template-layout .dashboard-map-shell {
            position: relative;
            min-height: 520px;
            border: 1px solid #e2e7ef;
            background: #f8fafc;
            overflow: hidden;
        }

        .admin-template-layout .dashboard-map-shell svg {
            width: 100%;
            height: 520px;
            display: block;
        }

        .admin-template-layout .dashboard-map-shell path {
            stroke: #ffffff;
            stroke-width: 1.2;
            cursor: pointer;
            transition: fill .18s ease, opacity .18s ease, transform .18s ease;
        }

        .admin-template-layout .dashboard-map-shell path:hover,
        .admin-template-layout .dashboard-map-shell path.is-selected {
            opacity: .9;
            filter: drop-shadow(0 8px 12px rgba(15, 23, 42, .18));
        }

        .admin-template-layout .dashboard-map-bubble {
            cursor: pointer;
            filter: drop-shadow(0 8px 10px rgba(15, 23, 42, .22));
        }

        .admin-template-layout .dashboard-map-tooltip {
            position: absolute;
            z-index: 5;
            display: none;
            min-width: 190px;
            padding: .75rem;
            color: #111827;
            background: #fff;
            border: 1px solid #e2e7ef;
            box-shadow: 0 14px 30px rgba(15, 23, 42, .16);
            pointer-events: none;
        }

        .admin-template-layout .dashboard-map-legend {
            position: absolute;
            inset-inline-end: 1rem;
            bottom: 1rem;
            z-index: 4;
            padding: .75rem;
            background: rgba(255, 255, 255, .96);
            border: 1px solid #e2e7ef;
        }

        .admin-template-layout .dashboard-map-legend span {
            display: inline-flex;
            width: 14px;
            height: 14px;
            margin-inline-end: .4rem;
            vertical-align: middle;
        }

        .admin-template-layout .dashboard-map-reset {
            position: absolute;
            inset-inline-start: 1rem;
            top: 1rem;
            z-index: 4;
        }

        .admin-template-layout .dashboard-map-side {
            border: 1px solid #e2e7ef;
            background: #fff;
            min-height: 520px;
        }

        .admin-template-layout .dashboard-map-side .card-header {
            padding-bottom: 1rem;
        }

        .admin-template-layout .dashboard-map-side .tab-content {
            max-height: 326px;
            overflow: auto;
        }

        .admin-template-layout .dashboard-map-tabs .nav-link {
            color: #6f1f1b;
            border-radius: 0;
            font-weight: 800;
        }

        .admin-template-layout .dashboard-map-tabs .nav-link.active {
            color: #fff;
            background: #6f1f1b;
        }

        .admin-template-layout .dashboard-map-stat-list {
            max-height: 292px;
            overflow: auto;
        }

        .admin-template-layout .dashboard-map-stat-row {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            padding: .7rem 0;
            border-bottom: 1px solid #eef1f5;
        }

        .admin-template-layout .dashboard-map-stat-row.operational-governorate-row {
            display: flex;
            grid-template-columns: none;
            align-items: flex-start;
            background: transparent;
        }

        .admin-template-layout .dashboard-map-stat-row:last-child {
            border-bottom: 0;
        }

        .admin-template-layout .dashboard-chart-grid-card .card-body {
            min-height: 335px;
        }

        .admin-template-layout .dashboard-chart-surface {
            min-height: 260px;
        }

        .admin-template-layout .dashboard-map-empty {
            padding: 2rem 1rem;
            color: #667085;
            text-align: center;
        }

        .admin-template-layout .dashboard-demo2-shell {
            --dashboard-map-panel-height: 568px;
        }

        .admin-template-layout #map {
            height: var(--dashboard-map-panel-height);
            width: 100%;
        }

        .admin-template-layout .dashboard-map-card {
            height: var(--dashboard-map-panel-height);
            min-height: var(--dashboard-map-panel-height);
            overflow: hidden;
        }

        .admin-template-layout .dashboard-map-card .map-wrapper {
            height: 100%;
        }

        .admin-template-layout .dashboard-demo2-shell .card {
            margin-bottom: 0;
        }

        .admin-template-layout .dashboard-equal-column {
            display: flex;
            min-width: 0;
        }

        .admin-template-layout .dashboard-equal-column > .card,
        .admin-template-layout .dashboard-equal-column > .dashboard-operational-kpi-grid {
            flex: 1 1 auto;
            width: 100%;
            min-width: 0;
            height: 100% !important;
        }

        .admin-template-layout .dashboard-equal-column > .card,
        .admin-template-layout .dashboard-equal-row > [class*="col-"] > .card {
            display: flex;
            flex-direction: column;
        }

        .admin-template-layout .dashboard-equal-column > .card > .card-body,
        .admin-template-layout .dashboard-equal-row > [class*="col-"] > .card > .card-body {
            flex: 1 1 auto;
        }

        .admin-template-layout .dashboard-operational-kpi-grid {
            align-content: stretch;
        }

        .admin-template-layout .dashboard-operational-kpi-grid > [class*="col-"] {
            display: flex;
            min-width: 0;
        }

        .admin-template-layout .dashboard-operational-kpi-grid > [class*="col-"] > .card,
        .admin-template-layout .dashboard-operational-kpi-grid > [class*="col-"] > .dashboard-operational-stack {
            flex: 1 1 auto;
            width: 100%;
            min-width: 0;
        }

        .admin-template-layout .dashboard-operational-kpi-grid .card {
            height: 100%;
        }

        .admin-template-layout .dashboard-operational-stack {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .admin-template-layout .dashboard-operational-stack > .card {
            flex: 1 1 0;
        }

        .admin-template-layout .dashboard-equal-row {
            align-items: stretch;
        }

        .admin-template-layout .dashboard-equal-row > [class*="col-"] {
            display: flex;
            min-width: 0;
        }

        .admin-template-layout .dashboard-equal-row > [class*="col-"] > .card {
            flex: 1 1 auto;
            width: 100%;
            min-width: 0;
            height: 100% !important;
        }

        .admin-template-layout .dashboard-equal-row #dashboard-scope-chart {
            min-height: 520px;
        }

        .admin-template-layout .dashboard-demo2-filter {
            border: 1px solid #e2e7ef;
            background: #fff;
        }

        .admin-template-layout .dashboard-demo2-filter .form-label {
            font-weight: 700;
            color: #394150;
        }

        .admin-template-layout .dashboard-demo2-shell .tab-scroll {
            height: calc(var(--dashboard-map-panel-height) - 112px);
            max-height: calc(var(--dashboard-map-panel-height) - 112px);
            overflow-y: auto;
            padding-inline-end: 8px;
            padding-bottom: .35rem;
        }

        .admin-template-layout .dashboard-map-stats-card {
            height: var(--dashboard-map-panel-height);
            min-height: var(--dashboard-map-panel-height);
            overflow: hidden;
        }

        .admin-template-layout .dashboard-map-stats-card .tab-content {
            padding-bottom: 1rem;
        }

        .admin-template-layout .dashboard-map-stat-pane {
            padding-inline: 1.25rem;
        }

        .admin-template-layout .dashboard-map-summary-card.operational-governorate-row {
            display: block;
            padding: 1rem;
            margin-bottom: .85rem;
            border: 1px solid #e3e7ef;
            border-bottom: 1px solid #e3e7ef;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
        }

        .admin-template-layout .dashboard-map-summary-card:last-child {
            margin-bottom: 0;
        }

        .admin-template-layout .dashboard-map-summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .8rem;
        }

        .admin-template-layout .dashboard-map-summary-name {
            color: #374151;
            font-size: 1rem;
            font-weight: 800;
        }

        .admin-template-layout .dashboard-map-summary-total {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .28rem .55rem;
            color: #6f1f1b;
            background: rgba(111, 31, 27, .08);
            font-weight: 800;
            white-space: nowrap;
            font: inherit;
            cursor: pointer;
            transition: background .18s ease, box-shadow .18s ease;
        }

        .admin-template-layout .dashboard-map-summary-total:hover,
        .admin-template-layout .dashboard-map-summary-total:focus {
            background: rgba(111, 31, 27, .14);
            box-shadow: 0 8px 18px rgba(15, 23, 42, .08);
            outline: none;
        }

        .admin-template-layout .dashboard-map-metric-grid {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .admin-template-layout .dashboard-map-metric-pill {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: .55rem;
            min-width: 116px;
            padding: .5rem .65rem;
            border: 1px solid #eef1f5;
            background: #f8fafc;
            color: #4b5563;
            font: inherit;
            font-weight: 700;
            text-align: inherit;
            cursor: pointer;
            transition: border-color .18s ease, box-shadow .18s ease, color .18s ease, background .18s ease;
        }

        .admin-template-layout .dashboard-map-metric-pill:hover,
        .admin-template-layout .dashboard-map-metric-pill:focus {
            border-color: rgba(111, 31, 27, .35);
            background: #fff;
            color: #6f1f1b;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .08);
            outline: none;
        }

        .admin-template-layout .dashboard-map-metric-pill strong {
            color: #111827;
            font-weight: 900;
        }

        .admin-template-layout .dashboard-map-metric-empty {
            padding: .55rem .65rem;
            color: #667085;
            background: #f8fafc;
            border: 1px dashed #d7dde8;
            font-weight: 700;
        }

        .admin-template-layout .dashboard-project-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .95rem 0;
            border-bottom: 1px solid #edf1f6;
        }

        .admin-template-layout .dashboard-project-row:last-child {
            border-bottom: 0;
        }

        .admin-template-layout .dashboard-project-meta {
            color: #667085;
            font-size: .875rem;
            line-height: 1.8;
        }

        body.dashboard-metric-modal-open .modal-backdrop {
            z-index: 1050 !important;
        }

        body.dashboard-metric-modal-open #dashboardMetricProjectsModal {
            z-index: 1060 !important;
        }

        .admin-template-layout .dashboard-comparison-strip {
            display: flex;
            align-items: stretch;
            gap: .65rem;
            flex-wrap: wrap;
        }

        .admin-template-layout .dashboard-comparison-stat {
            min-width: 118px;
            padding: .65rem .75rem;
            border: 1px solid #eef1f5;
            background: #f8fafc;
        }

        .admin-template-layout .dashboard-comparison-stat span {
            display: block;
            color: #667085;
            font-size: .78rem;
            font-weight: 800;
        }

        .admin-template-layout .dashboard-comparison-stat strong {
            display: block;
            color: #111827;
            font-size: 1.15rem;
            font-weight: 900;
            line-height: 1.25;
        }

        .admin-template-layout .dashboard-year-form {
            min-width: 132px;
        }

        .admin-template-layout .dashboard-demo2-shell .nav-pills .nav-link {
            border-radius: 0;
            color: #6f1f1b;
            font-weight: 800;
        }

        .admin-template-layout .dashboard-demo2-shell #pills-tab-1 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .admin-template-layout .dashboard-demo2-shell #pills-tab-1 .nav-item {
            min-width: 0;
        }

        .admin-template-layout .dashboard-demo2-shell #pills-tab-1 .nav-link {
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: .7rem .35rem;
            line-height: 1.25;
            text-align: center;
            white-space: normal;
        }

        .admin-template-layout .dashboard-demo2-shell .nav-pills .nav-link.active {
            background: #6f1f1b;
            color: #fff;
        }

        .admin-template-layout .dashboard-demo2-shell .map-wrapper {
            position: relative;
        }

        .admin-template-layout .dashboard-demo2-shell .reset-btn {
            position: absolute;
            top: 10px;
            inset-inline-end: 10px;
            z-index: 9999;
            background: #fff;
            border: 1px solid #ddd;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .2);
        }

        .admin-template-layout .dashboard-demo2-shell .reset-btn:hover {
            background: #f2f2f2;
        }

        .admin-template-layout .dashboard-demo2-shell .info.legend.leaflet-control {
            background: #fff;
            padding: 8px;
        }

        .admin-template-layout .dashboard-demo2-shell .legend {
            background: #fff;
            padding: 10px;
            line-height: 18px;
            color: #333;
            box-shadow: 0 0 10px rgba(0, 0, 0, .2);
            border-radius: 6px;
        }

        .admin-template-layout .dashboard-demo2-shell .legend i {
            width: 18px;
            height: 18px;
            float: left;
            margin-right: 8px;
            opacity: 1;
            display: inline-block;
            border: 1px solid #999;
        }

        .admin-template-layout .dashboard-demo2-shell .leaflet-popup-content strong {
            display: block;
            margin-bottom: 5px;
        }

        .admin-template-layout .dashboard-demo2-shell .leaflet-popup-content {
            font-family: "DIN Next LT Arabic Regular", "Tajawal", sans-serif !important;
            direction: rtl;
            text-align: right;
            line-height: 1.8;
        }

        @media (max-width: 767.98px) {
            .admin-template-layout .operational-governorate-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1199.98px) {
            .admin-template-layout .dashboard-map-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4 px-0">
                <div>
                    <h2 class="episode-playlist-title wp-heading-inline mb-1">
                        <span class="position-relative">{{ $title }}</span>
                    </h2>
                    <div class="text-muted">{{ $dashboardIntro }}</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @can('reports.view.all')
                        <a class="btn btn-danger" href="{{ route('admin.reports.index') }}">{{ __('app.admin.dashboard.open_full_reports') }}</a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    @include('admin.partials.demo2-dashboard')
@endsection
