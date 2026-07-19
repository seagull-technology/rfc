<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.documents.print_all_forms_title') }} - {{ $application->code }}</title>
    <link rel="stylesheet" href="{{ asset('css/libs.min.css') }}">
    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('css/rtl.min.css') }}?v=5.4.0">
    @endif
    <style>
        :root { --rfc: #6f1d18; --ink: #1f2937; --muted: #667085; --line: #d9dee7; --soft: #f4f5f7; }
        * { box-sizing: border-box; }
        body { background: #eef0f3; color: var(--ink); font-family: Arial, Tahoma, sans-serif; margin: 0; }
        .print-toolbar { align-items: center; background: #fff; border-bottom: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; padding: 14px 24px; position: sticky; top: 0; z-index: 10; }
        .print-toolbar a, .print-toolbar button { border: 1px solid var(--rfc); border-radius: 4px; cursor: pointer; font: inherit; padding: 10px 18px; text-decoration: none; }
        .print-toolbar button { background: var(--rfc); color: #fff; }
        .print-toolbar a { background: #fff; color: var(--rfc); }
        .print-document { margin: 24px auto; max-width: 1400px; }
        .print-cover, .print-form-sheet { background: #fff; border: 1px solid var(--line); margin-bottom: 24px; padding: 28px; }
        .print-cover__header { align-items: center; border-bottom: 3px solid var(--rfc); display: flex; gap: 22px; justify-content: space-between; padding-bottom: 20px; }
        .print-cover__logo { height: 92px; object-fit: contain; width: 150px; }
        .print-cover h1 { color: var(--rfc); font-size: 28px; margin: 0 0 8px; }
        .print-cover__meta { color: var(--muted); font-size: 14px; }
        .print-summary { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 22px; }
        .print-summary__item { background: var(--soft); border-inline-start: 4px solid var(--rfc); min-height: 72px; padding: 12px 14px; }
        .print-summary__label { color: var(--muted); display: block; font-size: 12px; margin-bottom: 6px; }
        .print-summary__value { font-weight: 700; overflow-wrap: anywhere; }
        .print-form-sheet__header { align-items: center; border-bottom: 2px solid var(--rfc); display: flex; gap: 16px; justify-content: space-between; margin-bottom: 22px; padding-bottom: 12px; }
        .print-form-sheet__header h2 { font-size: 23px; margin: 0; }
        .print-result { border: 1px solid currentColor; border-radius: 4px; font-size: 13px; font-weight: 700; padding: 7px 10px; white-space: nowrap; }
        .print-result--filled { color: #18794e; }
        .print-result--empty { color: #7a8493; }
        .print-form-sheet .d-grid { display: block !important; }
        .print-form-sheet .d-grid > div { margin-bottom: 0 !important; }
        .print-form-sheet .table-responsive, .print-form-sheet .annex-summary-table-scroll { overflow: visible !important; padding-block: 0 !important; }
        .print-form-sheet table { border-collapse: collapse !important; font-size: 11px; min-width: 0 !important; table-layout: auto !important; width: 100% !important; }
        .print-form-sheet th, .print-form-sheet td { border: 1px solid var(--line) !important; padding: 7px !important; vertical-align: top; white-space: normal !important; word-break: break-word; }
        .print-form-sheet th { background: #e9eaed !important; color: #111827; }
        .print-form-sheet .form-control:disabled, .print-form-sheet .form-select:disabled, .print-form-sheet textarea:disabled { background: #fff !important; border: 1px solid var(--line); color: var(--ink) !important; opacity: 1; }
        .print-form-sheet .badge { border: 1px solid currentColor; color: inherit !important; }
        @media (max-width: 900px) { .print-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff; }
            .print-toolbar { display: none !important; }
            .print-document { margin: 0; max-width: none; }
            .print-cover, .print-form-sheet { border: 0; margin: 0; min-height: 180mm; padding: 0; }
            .print-cover { break-after: page; }
            .print-form-sheet { break-after: page; break-before: page; padding-top: 2mm; }
            .print-form-sheet:last-child { break-after: auto; }
            .print-form-sheet thead { display: table-header-group; }
            .print-form-sheet tfoot { display: table-footer-group; }
            .print-form-sheet tr, .print-form-sheet .ministry-personal-details-form__section { break-inside: avoid; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>
@php
    $annex = (array) data_get($application->metadata ?? [], 'annex', []);
    $allowedSections = collect($onlySections ?? [])->map(fn ($section): string => (string) $section)->filter()->unique();
    $requestedForm = is_string($requestedForm ?? null) ? trim($requestedForm) : null;
    $formIsFilled = static function (array $form) use ($annex): bool {
        return collect($form['sections'])
            ->contains(function (string $section) use ($annex): bool {
                $payload = data_get($annex, $section, []);

                return collect(\Illuminate\Support\Arr::dot((array) $payload))
                    ->contains(fn ($value): bool => filled($value));
            });
    };
    $forms = collect([
        ['key' => 'work_content_summary', 'label' => __('app.applications.annex_sections.work_content_summary'), 'sections' => ['work_content_summary']],
        ['key' => 'cast_crew', 'label' => __('app.applications.annex_sections.cast_crew'), 'sections' => ['cast_crew']],
        ['key' => 'filming_locations', 'label' => __('app.applications.annex_sections.filming_locations'), 'sections' => ['filming_locations', 'special_location_requirements', 'public_security_support', 'military_support']],
        ['key' => 'safety_guidelines', 'label' => __('app.applications.annex_sections.safety_guidelines'), 'sections' => ['safety_guidelines']],
        ['key' => 'production_terms', 'label' => __('app.applications.annex_sections.production_terms'), 'sections' => ['production_terms']],
        ['key' => 'ministry_interior_personal_details', 'label' => __('app.applications.annex_sections.ministry_interior_personal_details'), 'sections' => ['ministry_interior_personal_details']],
        ['key' => 'imported_equipment', 'label' => __('app.applications.annex_sections.imported_equipment'), 'sections' => ['equipment_travelers', 'imported_equipment']],
        ['key' => 'airport_filming', 'label' => __('app.applications.annex_sections.airport_filming'), 'sections' => ['airport_filming', 'airport_people']],
        ['key' => 'governmental_scenes', 'label' => __('app.applications.annex_sections.governmental_scenes'), 'sections' => ['governmental_scenes']],
    ])
        ->filter(fn (array $form): bool => $allowedSections->isEmpty() || collect($form['sections'])->intersect($allowedSections)->isNotEmpty())
        ->filter(fn (array $form): bool => $formIsFilled($form))
        ->when(filled($requestedForm), fn ($forms) => $forms->where('key', $requestedForm))
        ->values();
    $singleForm = filled($requestedForm) ? $forms->first() : null;
@endphp

<div class="print-toolbar">
    <a href="{{ $backUrl }}">{{ __('app.official_letters.back_to_request') }}</a>
    <button type="button" onclick="window.print()">{{ __('app.documents.print_now') }}</button>
</div>

<main class="print-document">
    <section class="print-cover">
        <div class="print-cover__header">
            <div>
                <h1>{{ $singleForm['label'] ?? __('app.documents.print_all_forms_title') }}</h1>
                <div class="print-cover__meta">{{ __('app.documents.generated_at') }}: {{ now()->format('Y-m-d H:i') }}</div>
            </div>
            <img class="print-cover__logo" src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}">
        </div>
        <h2 class="mt-4 mb-0">{{ __('app.documents.application_summary') }}</h2>
        <div class="print-summary">
            <div class="print-summary__item"><span class="print-summary__label">{{ __('app.documents.request_number') }}</span><span class="print-summary__value">{{ $application->code }}</span></div>
            <div class="print-summary__item"><span class="print-summary__label">{{ __('app.applications.project_name') }}</span><span class="print-summary__value">{{ $application->project_name }}</span></div>
            <div class="print-summary__item"><span class="print-summary__label">{{ __('app.admin.applications.entity') }}</span><span class="print-summary__value">{{ $application->entity?->displayName() ?: __('app.dashboard.not_available') }}</span></div>
            <div class="print-summary__item"><span class="print-summary__label">{{ __('app.applications.status') }}</span><span class="print-summary__value">{{ $application->localizedStatus() }}</span></div>
        </div>
    </section>

    @foreach ($forms as $form)
        <section class="print-form-sheet" data-print-form="{{ $form['key'] }}">
            <div class="print-form-sheet__header">
                <h2>{{ $form['label'] }}</h2>
                <span class="print-result print-result--filled">
                    {{ __('app.documents.form_result') }}: {{ __('app.documents.form_filled') }}
                </span>
            </div>
            @include('applications.partials.annex-summary', [
                'application' => $application,
                'annexPayload' => $annex,
                'onlySections' => $form['sections'],
                'hideEmptySections' => false,
                'tableClass' => 'table annex-summary-table',
            ])
        </section>
    @endforeach
</main>
@stack('styles')
</body>
</html>
