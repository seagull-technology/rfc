<!doctype html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="dark" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('app.meta.app_name') }}</title>
    <meta name="description" content="{{ __('app.meta.app_name') }}">
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('fonts/phosphor.css') }}">
    <link rel="stylesheet" href="{{ asset('css/libs.min.css') }}">
    @if ($usesFlatpickr ?? false)
        <link rel="stylesheet" href="{{ asset('css/flatpickr.min.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/streamit.min.css') }}?v=5.4.0">
    <link rel="stylesheet" href="{{ asset('css/custom.min.css') }}?v=5.4.0">
    <link rel="stylesheet" href="{{ asset('css/dashboard-custom.min.css') }}?v=5.4.0">
    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('css/rtl.min.css') }}?v=5.4.0">
    @endif
    <link rel="stylesheet" href="{{ asset('css/customizer.min.css') }}?v=5.4.0">
    @include('auth.partials.auth-visual-styles')
    @stack('styles')
</head>
<body class=" ">
    <div id="loading">
        <div class="loader simple-loader">
            <div class="loader-body ">
                <picture>
                    <source srcset="{{ asset('images/Clapper.webp') }}" type="image/webp">
                    <img src="{{ asset('images/Clapper.gif') }}" alt="loader" class="image-loader img-fluid" />
                </picture>
            </div>
        </div>
    </div>

    @yield('content')

    <script nonce="{{ $cspNonce ?? '' }}">
        (function () {
            const releaseLoader = function () {
                const loadingWrapper = document.getElementById('loading');
                const loader = document.querySelector('.loader.simple-loader');

                if (loader) {
                    loader.classList.add('animate__animated', 'animate__fadeOut', 'd-none');
                    loader.style.pointerEvents = 'none';
                }

                if (loadingWrapper) {
                    loadingWrapper.style.pointerEvents = 'none';
                    loadingWrapper.style.display = 'none';
                }
            };

            document.addEventListener('DOMContentLoaded', function () {
                window.setTimeout(releaseLoader, 50);
            });

            window.addEventListener('load', function () {
                window.setTimeout(releaseLoader, 50);
            });

            window.setTimeout(releaseLoader, 1800);
        })();
    </script>
    @if ($usesFlatpickr ?? false)
        <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('js/flatpickr.min.js') }}?v=5.4.0"></script>
    @endif
    @include('layouts.partials.form-submit-state')
    @stack('scripts')
</body>
</html>
