<!doctype html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="dark" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('app.meta.app_name') }}</title>
    <meta name="description" content="{{ __('app.meta.app_name') }}">
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset('fonts/phosphor.css') }}">
    <link rel="stylesheet" href="{{ asset('fonts/Phosphor-Bold.css') }}">
    <link rel="stylesheet" href="{{ asset('fonts/Phosphor-Fill.css') }}">
    <link rel="stylesheet" href="{{ asset('fonts/Phosphor-Duotone.css') }}">
    <link rel="stylesheet" href="{{ asset('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/streamit.min.css') }}?v=5.4.0">
    <link rel="stylesheet" href="{{ asset('css/custom.min.css') }}?v=5.4.0">
    <link rel="stylesheet" href="{{ asset('css/dashboard-custom.min.css') }}?v=5.4.0">
    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('css/rtl.min.css') }}?v=5.4.0">
    @endif
    <link rel="stylesheet" href="{{ asset('css/customizer.min.css') }}?v=5.4.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;1,100;1,300&display=swap" rel="stylesheet">
    @stack('styles')
</head>
<body class=" ">
    <div id="loading">
        <div class="loader simple-loader">
            <div class="loader-body ">
                <img src="{{ asset('images/Clapper.gif') }}" alt="loader" class="image-loader img-fluid" />
            </div>
        </div>
    </div>

    @yield('content')

    <script>
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
    <script src="{{ asset('js/libs.min.js') }}"></script>
    <script src="{{ asset('js/slider-tabs.js') }}"></script>
    <script src="{{ asset('js/lodash.min.js') }}"></script>
    <script src="{{ asset('js/utility.min.js') }}"></script>
    <script src="{{ asset('js/setting.min.js') }}"></script>
    <script src="{{ asset('js/setting-init.js') }}"></script>
    <script src="{{ asset('js/external.min.js') }}"></script>
    <script src="{{ asset('js/widgetcharts.js') }}?v=5.4.0" defer></script>
    <script src="{{ asset('js/dashboard.js') }}?v=5.4.0" defer></script>
    <script src="{{ asset('js/streamit.js') }}?v=5.4.1" defer></script>
    <script src="{{ asset('js/sidebar.js') }}?v=5.4.0" defer></script>
    <script src="{{ asset('js/chart-custom.js') }}?v=5.4.0" defer></script>
    <script src="{{ asset('js/select2.js') }}?v=5.4.0" defer></script>
    <script src="{{ asset('js/flatpickr.js') }}?v=5.4.0" defer></script>
    <script src="{{ asset('js/countdown.js') }}?v=5.4.0" defer></script>
    @stack('scripts')
</body>
</html>
