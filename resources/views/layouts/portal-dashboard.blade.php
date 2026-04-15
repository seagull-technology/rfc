@php
    $currentPortalUser = auth()->user();
    $currentPortalEntity = $currentPortalUser?->primaryEntity();
    $currentPortalGroupCode = $currentPortalEntity?->group?->code;
    $currentPortalRegistrationType = $currentPortalEntity?->registration_type ?? $currentPortalUser?->registration_type;
    $portalAvatar = asset('images/OIP.jpeg');
    $portalUnreadNotifications = $currentPortalUser?->unreadNotifications ?? collect();
    $portalInboxNotificationCount = $portalUnreadNotifications
        ->filter(fn ($notification) => \App\Support\NotificationPresenter::isInbox($notification))
        ->count();
    $portalBellNotificationCount = $portalUnreadNotifications->count();
    $portalNotificationItems = $notificationItems ?? ($currentPortalUser?->notifications()->latest()->take(5)->get() ?? collect());
    $portalProfileLinks = collect();
    $currentPortalProfileUrl = route('dashboard');

    if ($currentPortalUser && $currentPortalUser->canAccessAdminPanel($currentPortalEntity)) {
        $currentPortalProfileUrl = route('admin.dashboard');
        $portalProfileLinks->push([
            'label' => __('app.portal.profile_links.rfc'),
            'url' => route('admin.dashboard'),
        ]);
    } elseif ($currentPortalGroupCode === 'authorities') {
        $portalProfileLinks->push([
            'label' => __('app.portal.profile_links.authority'),
            'url' => route('dashboard'),
        ]);
    } elseif (in_array($currentPortalRegistrationType, ['company', 'ngo', 'school'], true) || $currentPortalGroupCode === 'organizations') {
        $currentPortalProfileUrl = request()->query('variant') === 'foreign_producer'
            ? route('profile.show', ['variant' => 'foreign_producer'])
            : route('profile.show');
        $portalProfileLinks->push([
            'label' => request()->query('variant') === 'foreign_producer'
                ? __('app.portal.profile_links.foreign_producer')
                : __('app.portal.profile_links.organization'),
            'url' => $currentPortalProfileUrl,
        ]);
    } else {
        $portalProfileLinks->push([
            'label' => __('app.portal.profile_links.applicant'),
            'url' => route('dashboard'),
        ]);
    }
@endphp

<!doctype html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="light" data-bs-theme-color="light" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
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
    <style>
        .portal-notification-item .notification-meta {
            margin-top: 0.5rem;
        }

        .portal-notification-item .notification-meta .badge {
            font-size: 0.72rem;
        }
    </style>
    @stack('styles')
</head>
<body class="  ">
    <div id="loading">
        <div class="loader simple-loader">
            <div class="loader-body ">
                <img src="{{ asset('images/Clapper.gif') }}" alt="loader" class="image-loader img-fluid" />
            </div>
        </div>
    </div>

    <main class="main-content">
        <div class="position-relative ">
            <nav class="nav navbar navbar-expand-xl header-hover-menu navbar-light iq-navbar">
                <div class="container-fluid navbar-inner">
                    <a href="{{ route('dashboard') }}" class="navbar-brand">
                        <img class="logo-normal" src="{{ asset('images/logo.svg') }}" alt="#">
                        <img class="logo-normal logo-white" src="{{ asset('images/logo.svg') }}" alt="#">
                        <img class="logo-full" src="{{ asset('images/logo.svg') }}" alt="#">
                        <img class="logo-full logo-full-white" src="{{ asset('images/logo.svg') }}" alt="#">
                    </a>

                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="mb-2 navbar-nav ms-auto align-items-center navbar-list mb-lg-0 ">
                            <li class="nav-item">
                                <a href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL(app()->getLocale() === 'ar' ? 'en' : 'ar', null, [], true) }}" class="nav-link" id="langues-drop">
                                    {{ app()->getLocale() === 'ar' ? __('app.meta.english') : __('app.meta.arabic') }}
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a href="{{ route('contact-center.index') }}" class="nav-link position-relative">
                                    <i class="ph-fill ph-chat-centered-text fs-4 align-middle"></i>
                                    @if ($portalInboxNotificationCount > 0)
                                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" style="font-size:.65rem;">{{ $portalInboxNotificationCount > 99 ? '99+' : $portalInboxNotificationCount }}</span>
                                    @endif
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a href="#" class="nav-link" id="notification-drop" data-bs-toggle="dropdown">
                                    <i class="ph-fill ph-bell fs-4 align-middle"></i>
                                    @if ($portalBellNotificationCount > 0)
                                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" style="font-size:.65rem;">{{ $portalBellNotificationCount > 99 ? '99+' : $portalBellNotificationCount }}</span>
                                    @endif
                                </a>
                                <ul class="p-0 sub-drop dropdown-menu dropdown-menu-end" aria-labelledby="notification-drop">
                                    <li class="p-0">
                                        <div class="p-3 card-header d-flex justify-content-between bg-danger rounded-top">
                                            <div class="header-title">
                                                <h5 class="mb-0 text-white">{{ __('app.portal.notifications_title') }}</h5>
                                            </div>
                                        </div>
                                        <div class="p-0 card-body all-notification">
                                            @forelse ($portalNotificationItems as $notification)
                                                @php($notificationView = \App\Support\NotificationPresenter::present($notification))
                                                <a href="{{ $notificationView['url'] }}" class="iq-sub-card text-start portal-notification-item">
                                                    <div>
                                                        <h6 class="mb-0">{{ $notificationView['title'] }}</h6>
                                                        @if ($notificationView['highlight_active'])
                                                            <div class="notification-meta">
                                                                <span class="badge bg-{{ $notificationView['highlight_class'] }}">{{ $notificationView['highlight_title'] }}</span>
                                                                @if ($notificationView['highlight_summary'])
                                                                    <div class="small text-muted mt-1">{{ $notificationView['highlight_summary'] }}</div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <p class="mb-0">{{ $notificationView['body'] }}</p>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <small class="float-end font-size-12">{{ $notification->created_at?->diffForHumans() }}</small>
                                                    </div>
                                                </a>
                                            @empty
                                                <div class="p-3 text-muted">{{ __('app.portal.notifications_empty') }}</div>
                                            @endforelse
                                        </div>
                                    </li>
                                </ul>
                            </li>
                            <li class="nav-item iq-full-screen d-none d-xl-block border-end" id="fullscreen-item">
                                <a href="#" class="nav-link pe-3" id="btnFullscreen" data-bs-toggle="dropdown">
                                    <span class="btn-inner">
                                        <i class="normal-screen ph ph-arrows-out-simple fs-4 align-middle"></i>
                                        <i class="full-normal-screen ph ph-arrows-in-simple d-none align-middle fs-4"></i>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="py-0 nav-link d-flex align-items-center ps-3" href="#" id="profile-setting" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="{{ $portalAvatar }}" alt="User-Profile" class="theme-color-default-img img-fluid avatar avatar-50 avatar-rounded" loading="lazy">
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile-setting">
                                    <li class="px-3 mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-3">
                                                <img class="avatar-img rounded-circle shadow avatar-70 rounded" src="{{ $portalAvatar }}" alt="avatar">
                                            </div>
                                            <div class="text-start">
                                                <a class="h6" href="{{ $currentPortalProfileUrl }}">{{ $currentPortalEntity?->displayName() ?? __('app.dashboard.no_entity') }}</a>
                                                <p class="small m-0">{{ $currentPortalUser?->email }}</p>
                                            </div>
                                        </div>
                                    </li>
                                    @foreach ($portalProfileLinks as $portalProfileLink)
                                        <li><a class="dropdown-item" href="{{ $portalProfileLink['url'] }}">{{ $portalProfileLink['label'] }}</a></li>
                                    @endforeach
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">{{ __('app.dashboard.logout') }}</button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>

        @hasSection('hero')
            @yield('hero')
        @endif

        <div class="content-inner container-fluid pb-0 @yield('page_layout_class')" id="page_layout">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            @yield('content')
        </div>
    </main>

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
