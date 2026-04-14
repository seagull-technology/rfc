@php($title = __('app.permits.verification_title'))
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="{{ asset('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/streamit.min.css') }}?v=5.4.0">
    <link rel="stylesheet" href="{{ asset('css/custom.min.css') }}?v=5.4.0">
    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('css/rtl.min.css') }}?v=5.4.0">
    @endif
</head>
<body>
    <div class="content-inner container-fluid py-5">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header"><div class="iq-header-title"><h2 class="card-title mb-0">{{ __('app.permits.verification_title') }}</h2></div></div>
                    <div class="card-body">
                        <p class="text-muted">{{ __('app.permits.verification_intro') }}</p>
                        <form method="GET" action="{{ route('permits.verify') }}" class="row g-3 mb-4">
                            <div class="col-md-9">
                                <label class="form-label" for="permit_number">{{ __('app.permits.permit_number') }}</label>
                                <input id="permit_number" name="permit_number" type="text" class="form-control" value="{{ $filters['permit_number'] }}" placeholder="{{ __('app.permits.verification_placeholder') }}">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button class="btn btn-primary w-100" type="submit">{{ __('app.permits.verify_action') }}</button>
                            </div>
                        </form>

                        @if ($permit)
                            <div class="alert alert-success">{{ __('app.permits.verification_valid') }}</div>
                            <div class="row g-3">
                                <div class="col-md-6"><small class="text-muted d-block">{{ __('app.permits.permit_number') }}</small><div>{{ $permit->permit_number }}</div></div>
                                <div class="col-md-6"><small class="text-muted d-block">{{ __('app.permits.status') }}</small><div>{{ $permit->localizedStatus() }}</div></div>
                                <div class="col-md-6"><small class="text-muted d-block">{{ __('app.applications.project_name') }}</small><div>{{ $permit->application?->project_name ?? __('app.dashboard.not_available') }}</div></div>
                                <div class="col-md-6"><small class="text-muted d-block">{{ __('app.admin.applications.entity') }}</small><div>{{ $permit->entity?->displayName() ?? __('app.dashboard.not_available') }}</div></div>
                                <div class="col-md-6"><small class="text-muted d-block">{{ __('app.permits.issued_at') }}</small><div>{{ $permit->issued_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div></div>
                                <div class="col-md-6"><small class="text-muted d-block">{{ __('app.final_decision.issued_by') }}</small><div>{{ $permit->issuedBy?->displayName() ?? __('app.dashboard.not_available') }}</div></div>
                            </div>
                        @elseif (filled($filters['permit_number']))
                            <div class="alert alert-danger mb-0">{{ __('app.permits.verification_invalid') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
