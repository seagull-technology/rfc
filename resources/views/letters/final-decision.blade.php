<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.final_decision.letter_title') }} - {{ $application->code }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f4; color: #151515; }
        .page { max-width: 900px; margin: 24px auto; background: #fff; padding: 40px; box-shadow: 0 6px 24px rgba(0,0,0,.08); }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 24px; border-bottom: 2px solid #b52b1e; padding-bottom: 20px; margin-bottom: 28px; }
        .logo { width: 110px; }
        .title { font-size: 28px; font-weight: 700; margin: 0 0 8px; }
        .subtitle { color: #666; margin: 0; }
        .section { margin-top: 28px; }
        .section-title { font-size: 18px; font-weight: 700; margin: 0 0 12px; color: #b52b1e; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 24px; }
        .field-label { font-size: 13px; color: #666; margin-bottom: 4px; }
        .field-value { font-size: 16px; font-weight: 600; word-break: break-word; }
        .decision-box { margin-top: 24px; padding: 18px; border: 1px solid #d5d5d5; background: #faf7f6; }
        .decision-status { font-size: 22px; font-weight: 700; color: {{ $application->final_decision_status === 'approved' ? '#198754' : '#b52b1e' }}; margin-bottom: 8px; }
        .print-actions { max-width: 900px; margin: 24px auto 0; display: flex; justify-content: flex-end; gap: 12px; }
        .print-actions a, .print-actions button { background: #b52b1e; color: #fff; border: 0; padding: 12px 18px; text-decoration: none; cursor: pointer; }
        .print-actions a.secondary { background: #222; }
        @media print {
            body { background: #fff; }
            .print-actions { display: none; }
            .page { margin: 0; box-shadow: none; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" onclick="window.print()">{{ __('app.final_decision.print_letter') }}</button>
        <a class="secondary" href="{{ $isAdminView ? route('admin.applications.show', $application) : route('applications.show', $application) }}">{{ __('app.final_decision.back_to_request') }}</a>
    </div>

    <div class="page">
        <div class="header">
            <div>
                <h1 class="title">{{ __('app.final_decision.letter_title') }}</h1>
                <p class="subtitle">{{ __('app.meta.app_name') }}</p>
            </div>
            <img class="logo" src="{{ asset('images/logo.svg') }}" alt="RFC">
        </div>

        <div class="grid">
            <div>
                <div class="field-label">{{ __('app.applications.project_name') }}</div>
                <div class="field-value">{{ $application->project_name }}</div>
            </div>
            <div>
                <div class="field-label">{{ __('app.admin.applications.application') }}</div>
                <div class="field-value">{{ $application->code }}</div>
            </div>
            <div>
                <div class="field-label">{{ __('app.admin.applications.entity') }}</div>
                <div class="field-value">{{ $entity?->displayName() ?? __('app.dashboard.not_available') }}</div>
            </div>
            <div>
                <div class="field-label">{{ __('app.final_decision.permit_number') }}</div>
                <div class="field-value">{{ $application->final_permit_number ?: __('app.dashboard.not_available') }}</div>
            </div>
            <div>
                <div class="field-label">{{ __('app.final_decision.issued_at') }}</div>
                <div class="field-value">{{ $application->final_decision_issued_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
            </div>
            <div>
                <div class="field-label">{{ __('app.final_decision.issued_by') }}</div>
                <div class="field-value">{{ $issuedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">{{ __('app.applications.project_information') }}</h2>
            <div class="grid">
                <div>
                    <div class="field-label">{{ __('app.applications.project_nationality') }}</div>
                    <div class="field-value">{{ __('app.applications.project_nationalities.'.$application->project_nationality) }}</div>
                </div>
                <div>
                    <div class="field-label">{{ __('app.applications.work_category') }}</div>
                    <div class="field-value">{{ __('app.applications.work_categories.'.$application->work_category) }}</div>
                </div>
                <div>
                    <div class="field-label">{{ __('app.applications.planned_start_date') }}</div>
                    <div class="field-value">{{ optional($application->planned_start_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</div>
                </div>
                <div>
                    <div class="field-label">{{ __('app.applications.planned_end_date') }}</div>
                    <div class="field-value">{{ optional($application->planned_end_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</div>
                </div>
            </div>
        </div>

        <div class="decision-box">
            <div class="decision-status">{{ __('app.statuses.'.$application->final_decision_status) }}</div>
            <div>{{ $application->final_decision_note ?: __('app.final_decision.default_letter_note') }}</div>
        </div>

        <div class="section">
            <h2 class="section-title">{{ __('app.final_decision.registry_reference') }}</h2>
            <div class="grid">
                <div>
                    <div class="field-label">{{ __('app.permits.registry_title') }}</div>
                    <div class="field-value">{{ $permit?->permit_number ?: __('app.dashboard.not_available') }}</div>
                </div>
                <div>
                    <div class="field-label">{{ __('app.permits.status') }}</div>
                    <div class="field-value">{{ $permit?->localizedStatus() ?? __('app.dashboard.not_available') }}</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <div class="field-label">{{ __('app.permits.verification_link') }}</div>
                    <div class="field-value">{{ $permit?->verificationUrl() ?? __('app.dashboard.not_available') }}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
