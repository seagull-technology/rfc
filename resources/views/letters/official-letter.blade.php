<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.official_letters.print_title') }} - {{ $application->code }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f4; color: #151515; }
        .print-actions { max-width: 900px; margin: 24px auto 0; display: flex; justify-content: flex-end; gap: 12px; }
        .print-actions a, .print-actions button { background: #b52b1e; color: #fff; border: 0; padding: 12px 18px; text-decoration: none; cursor: pointer; }
        .print-actions a.secondary { background: #222; }
        .page { max-width: 900px; margin: 24px auto; background: #fff; padding: 40px; box-shadow: 0 6px 24px rgba(0,0,0,.08); }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 24px; border-bottom: 2px solid #7f1d1d; padding-bottom: 20px; margin-bottom: 28px; }
        .logo { width: 110px; }
        .title { font-size: 26px; font-weight: 700; margin: 0 0 8px; }
        .subtitle { color: #666; margin: 0; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; margin-bottom: 28px; }
        .label { color: #666; font-size: 13px; margin-bottom: 4px; }
        .value { font-size: 16px; font-weight: 600; word-break: break-word; }
        .subject { border: 1px solid #ddd; background: #fafafa; padding: 14px 16px; margin-bottom: 24px; }
        .body { font-size: 17px; line-height: 2; white-space: pre-line; word-break: break-word; }
        .attachments { margin-top: 28px; }
        .attachments h2 { color: #7f1d1d; font-size: 18px; margin: 0 0 12px; }
        .attachments li { margin-bottom: 8px; }
        .closing { margin-top: 36px; text-align: center; }
        @media print {
            body { background: #fff; }
            .print-actions { display: none; }
            .page { margin: 0; box-shadow: none; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" onclick="window.print()">{{ __('app.official_letters.print_action') }}</button>
        <a class="secondary" href="{{ route('admin.applications.show', $application) }}">{{ __('app.official_letters.back_to_request') }}</a>
    </div>

    <div class="page">
        <div class="header">
            <div>
                <h1 class="title">{{ __('app.official_letters.print_title') }}</h1>
                <p class="subtitle">{{ __('app.meta.app_name') }} - {{ $application->code }}</p>
            </div>
            <img class="logo" src="{{ asset('images/logo.svg') }}" alt="RFC">
        </div>

        <div class="meta">
            <div>
                <div class="label">{{ __('app.official_letters.letter_date') }}</div>
                <div class="value">{{ $letter->letter_date?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</div>
            </div>
            <div>
                <div class="label">{{ __('app.official_letters.serial_number') }}</div>
                <div class="value">{{ $letter->serial_number ?: __('app.dashboard.not_available') }}</div>
            </div>
            <div>
                <div class="label">{{ __('app.official_letters.target_entity') }}</div>
                <div class="value">{{ $letter->recipientDisplayName() }}</div>
            </div>
            <div>
                <div class="label">{{ $letter->recipient_prefix ?: __('app.official_letters.recipient_prefix') }}</div>
                <div class="value">{{ $letter->recipient_name }}</div>
            </div>
        </div>

        <div class="subject">
            <div class="label">{{ __('app.official_letters.subject') }}</div>
            <div class="value">{{ $letter->subject }}</div>
        </div>

        <div class="body">{{ $letter->body }}</div>

        @if (filled($letter->attachments))
            <div class="attachments">
                <h2>{{ __('app.official_letters.attachments') }}</h2>
                <ul>
                    @foreach ($letter->attachments as $attachment)
                        <li>{{ $attachment }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="closing">
            <p>{{ __('app.official_letters.formal_thanks') }}</p>
            <p>{{ __('app.official_letters.formal_respect') }}</p>
        </div>
    </div>
</body>
</html>
