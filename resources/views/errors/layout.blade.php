@php
    $supportedLocales = ['ar', 'en'];
    $requestedLocale = request()->segment(1);
    $locale = in_array($requestedLocale, $supportedLocales, true)
        ? $requestedLocale
        : (in_array(app()->getLocale(), $supportedLocales, true) ? app()->getLocale() : 'ar');

    app()->setLocale($locale);

    $isRtl = $locale === 'ar';
    $statusCode = (string) ($statusCode ?? 500);
    $translationKey = (string) ($translationKey ?? $statusCode);
    $icon = $icon ?? 'ph-warning-circle';
    $pageTitle = __("app.errors.pages.{$translationKey}.title");
    $heading = __("app.errors.pages.{$translationKey}.heading");
    $message = __("app.errors.pages.{$translationKey}.message");
    $homeUrl = url("/{$locale}/dashboard");

    $pathSegments = request()->segments();
    if (in_array($pathSegments[0] ?? null, $supportedLocales, true)) {
        $pathSegments[0] = $isRtl ? 'en' : 'ar';
    } else {
        array_unshift($pathSegments, $isRtl ? 'en' : 'ar');
    }
    $languageUrl = url('/'.implode('/', $pathSegments));
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }} | {{ __('app.meta.app_name') }}</title>
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('fonts/phosphor.css') }}">
    <link rel="stylesheet" href="{{ asset('fonts/Phosphor-Bold.css') }}">
    <style>
        @font-face {
            font-family: 'DIN Next LT Arabic Regular';
            src: url('{{ asset('arabicFont/DINNextLTArabic/DINNextLTArabic-Regular.woff') }}') format('woff');
            font-display: swap;
            font-style: normal;
            font-weight: 400;
        }

        @font-face {
            font-family: 'DIN Next LT Arabic Bold';
            src: url('{{ asset('arabicFont/DINNextLTArabic/DINNextLTArabic-Bold.woff') }}') format('woff');
            font-display: swap;
            font-style: normal;
            font-weight: 700;
        }

        :root {
            color-scheme: light;
            --rfc-primary: #5e1d19;
            --rfc-primary-hover: #461411;
            --rfc-text: #4a4e58;
            --rfc-heading: #151515;
            --rfc-muted: #8a92a6;
            --rfc-border: #d9dce3;
            --rfc-panel: #e9eaed;
            --rfc-panel-soft: #f5f5f6;
            --rfc-white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
        }

        body {
            min-height: 100vh;
            color: var(--rfc-text);
            background: #f9f9f9;
            font-family: Arial, sans-serif;
            font-size: 16px;
            letter-spacing: 0;
        }

        html[dir='rtl'] body {
            font-family: 'DIN Next LT Arabic Regular', 'Tajawal', sans-serif;
        }

        button,
        a {
            font: inherit;
        }

        .system-header {
            min-height: 98px;
            border-bottom: 1px solid var(--rfc-border);
            background: var(--rfc-panel);
        }

        .system-header__inner,
        .error-heading__inner,
        .error-main,
        .system-footer__inner {
            width: min(calc(100% - 64px), 1500px);
            margin-inline: auto;
        }

        .system-header__inner {
            display: flex;
            min-height: 98px;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
        }

        .system-brand {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: 18px;
            color: var(--rfc-heading);
            text-decoration: none;
        }

        .system-brand__logo {
            width: 116px;
            height: 70px;
            flex: 0 0 116px;
            object-fit: contain;
        }

        .system-brand__name {
            max-width: 430px;
            font-size: 19px;
            font-weight: 700;
            line-height: 1.55;
        }

        .system-navigation {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .system-navigation__link {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border: 1px solid transparent;
            color: var(--rfc-text);
            text-decoration: none;
        }

        .system-navigation__link:hover,
        .system-navigation__link:focus-visible {
            border-color: var(--rfc-border);
            color: var(--rfc-primary);
            background: var(--rfc-white);
            outline: none;
        }

        .system-navigation__link i {
            color: var(--rfc-primary);
            font-size: 21px;
        }

        .error-heading {
            border-bottom: 1px solid var(--rfc-border);
            background: var(--rfc-white);
        }

        .error-heading__inner {
            display: flex;
            min-height: 126px;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
            padding-block: 24px;
        }

        .error-heading__title {
            display: inline-block;
            margin: 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #2d1715;
            color: var(--rfc-heading);
            font-size: clamp(24px, 3vw, 30px);
            font-weight: 700;
            line-height: 1.35;
        }

        .error-heading__code {
            display: inline-flex;
            min-height: 36px;
            align-items: center;
            padding: 6px 13px;
            color: var(--rfc-primary);
            background: #f0e7e6;
            font-weight: 700;
        }

        .error-main {
            min-height: calc(100vh - 303px);
            padding-block: 32px 48px;
        }

        .error-shell {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 92px;
            gap: clamp(28px, 5vw, 72px);
            align-items: center;
            min-height: 310px;
            padding: clamp(34px, 5vw, 68px);
            border: 1px solid var(--rfc-border);
            border-radius: 4px;
            background: var(--rfc-panel);
        }

        .error-shell::before {
            position: absolute;
            inset-block: 0;
            inset-inline-start: 0;
            width: 5px;
            content: '';
            background: var(--rfc-primary);
        }

        .error-symbol {
            display: grid;
            width: 92px;
            height: 92px;
            place-items: center;
            border: 1px solid #ddc9c7;
            border-radius: 4px;
            color: var(--rfc-primary);
            background: #f3e9e8;
        }

        .error-symbol i {
            font-size: 42px;
            line-height: 1;
        }

        .error-content {
            min-width: 0;
        }

        .error-reference {
            margin: 0 0 8px;
            color: var(--rfc-primary);
            font-size: 15px;
            font-weight: 700;
        }

        .error-title {
            margin: 0;
            color: var(--rfc-heading);
            font-size: clamp(25px, 3.5vw, 32px);
            font-weight: 700;
            line-height: 1.45;
        }

        .error-message {
            max-width: 760px;
            margin: 12px 0 0;
            color: var(--rfc-text);
            font-size: 18px;
            line-height: 1.9;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .error-action {
            display: inline-flex;
            min-width: 142px;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 10px 20px;
            border: 1px solid var(--rfc-primary);
            border-radius: 3px;
            color: var(--rfc-primary);
            background: transparent;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .error-action:hover,
        .error-action:focus-visible {
            border-color: var(--rfc-primary-hover);
            color: var(--rfc-white);
            background: var(--rfc-primary-hover);
            outline: none;
        }

        .error-action--primary {
            color: var(--rfc-white);
            background: var(--rfc-primary);
        }

        .error-action i {
            font-size: 20px;
        }

        .error-support {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 18px 0 0;
            padding: 16px 18px;
            border: 1px solid var(--rfc-border);
            color: var(--rfc-muted);
            background: var(--rfc-panel-soft);
            font-size: 14px;
            line-height: 1.8;
        }

        .error-support i {
            flex: 0 0 auto;
            margin-top: 3px;
            color: var(--rfc-primary);
            font-size: 19px;
        }

        .system-footer {
            border-top: 1px solid var(--rfc-border);
            background: var(--rfc-panel);
        }

        .system-footer__inner {
            display: flex;
            min-height: 78px;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            color: var(--rfc-text);
            font-size: 14px;
        }

        html[dir='rtl'] .system-brand__name,
        html[dir='rtl'] .error-heading__title,
        html[dir='rtl'] .error-title {
            font-family: 'DIN Next LT Arabic Bold', 'DIN Next LT Arabic Regular', 'Tajawal', sans-serif;
        }

        html[dir='ltr'] .error-action--back i {
            transform: rotate(180deg);
        }

        @media (max-width: 760px) {
            .system-header__inner,
            .error-heading__inner,
            .error-main,
            .system-footer__inner {
                width: min(calc(100% - 32px), 1500px);
            }

            .system-header,
            .system-header__inner {
                min-height: 82px;
            }

            .system-brand__logo {
                width: 84px;
                height: 56px;
                flex-basis: 84px;
            }

            .system-brand__name,
            .system-navigation__link span {
                display: none;
            }

            .system-navigation__link {
                width: 42px;
                min-height: 42px;
                justify-content: center;
                padding: 8px;
            }

            .error-heading__inner {
                min-height: 104px;
            }

            .error-heading__code {
                font-size: 13px;
            }

            .error-main {
                min-height: calc(100vh - 264px);
                padding-block: 20px 32px;
            }

            .error-shell {
                grid-template-columns: 1fr;
                gap: 22px;
                min-height: 0;
                padding: 28px 24px 32px;
            }

            .error-symbol {
                width: 70px;
                height: 70px;
                grid-row: 1;
            }

            .error-symbol i {
                font-size: 34px;
            }

            .error-content {
                grid-row: 2;
            }

            .error-message {
                font-size: 16px;
            }

            .error-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .error-action {
                width: 100%;
            }

            .system-footer__inner {
                min-height: 68px;
                justify-content: center;
                text-align: center;
            }

            .system-footer__code {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header class="system-header">
        <div class="system-header__inner">
            <a class="system-brand" href="{{ $homeUrl }}">
                <img class="system-brand__logo" src="{{ asset('images/logo.svg') }}" alt="">
                <span class="system-brand__name">{{ __('app.errors.portal_name') }}</span>
            </a>

            <nav class="system-navigation" aria-label="{{ __('app.meta.language') }}">
                <a class="system-navigation__link" href="{{ $languageUrl }}">
                    <i class="ph ph-globe" aria-hidden="true"></i>
                    <span>{{ $isRtl ? __('app.meta.english') : __('app.meta.arabic') }}</span>
                </a>
                <a class="system-navigation__link" href="{{ $homeUrl }}">
                    <i class="ph ph-house" aria-hidden="true"></i>
                    <span>{{ __('app.errors.actions.home') }}</span>
                </a>
            </nav>
        </div>
    </header>

    <section class="error-heading">
        <div class="error-heading__inner">
            <h1 class="error-heading__title">{{ $pageTitle }}</h1>
            <span class="error-heading__code">{{ __('app.errors.reference', ['code' => $statusCode]) }}</span>
        </div>
    </section>

    <main class="error-main">
        <section class="error-shell" aria-labelledby="error-title">
            <div class="error-content">
                <p class="error-reference">{{ __('app.errors.reference', ['code' => $statusCode]) }}</p>
                <h2 class="error-title" id="error-title">{{ $heading }}</h2>
                <p class="error-message">{{ $message }}</p>

                <div class="error-actions">
                    <a class="error-action error-action--primary" href="{{ $homeUrl }}">
                        <i class="ph ph-house" aria-hidden="true"></i>
                        <span>{{ __('app.errors.actions.home') }}</span>
                    </a>
                    <button
                        class="error-action error-action--back"
                        type="button"
                        onclick="window.history.length > 1 ? window.history.back() : window.location.assign(@js($homeUrl))"
                    >
                        <i class="ph ph-arrow-right" aria-hidden="true"></i>
                        <span>{{ __('app.errors.actions.back') }}</span>
                    </button>
                </div>
            </div>

            <div class="error-symbol" aria-hidden="true">
                <i class="ph {{ $icon }}"></i>
            </div>
        </section>

        <p class="error-support">
            <i class="ph ph-info" aria-hidden="true"></i>
            <span>{{ __('app.errors.support_hint', ['code' => $statusCode]) }}</span>
        </p>
    </main>

    <footer class="system-footer">
        <div class="system-footer__inner">
            <span>{{ __('app.errors.portal_name') }}</span>
            <span class="system-footer__code">{{ __('app.errors.reference', ['code' => $statusCode]) }}</span>
        </div>
    </footer>
</body>
</html>
