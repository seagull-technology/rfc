@php
    $currentAdmin = auth()->user();
    $layoutUnreadNotifications = $currentAdmin?->unreadNotifications ?? collect();
    $layoutNotificationCount = $layoutUnreadNotifications->count();
    $layoutNotificationItems = $notificationItems ?? ($currentAdmin?->notifications()->latest()->take(5)->get() ?? collect());
    $currentAdminEntity = $currentAdmin?->primaryEntity();
    $layoutProfileEntityName = $profileEntityName ?? $currentAdmin?->primaryEntity()?->displayName() ?? __('app.dashboard.no_entity');
    $layoutProfileEmail = $profileEmail ?? $currentAdmin?->email ?? '';
    $layoutSidebarCounters = $layoutSidebarCounters ?? ['applications' => 0, 'scouting_requests' => 0, 'contact_center' => 0];
    $adminProfileLinks = [
        ['label' => __('app.portal.profile_links.applicant'), 'url' => route('dashboard')],
        ['label' => __('app.portal.profile_links.foreign_producer'), 'url' => route('profile.show', ['variant' => 'foreign_producer'])],
        ['label' => __('app.portal.profile_links.rfc'), 'url' => $currentAdmin && $currentAdmin->canAccessAdminPanel($currentAdminEntity) ? route('admin.dashboard') : route('dashboard')],
        ['label' => __('app.portal.profile_links.authority'), 'url' => route('dashboard')],
    ];
@endphp

<!doctype html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="light" data-bs-theme-color="default" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
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
        .admin-notification-item .notification-meta {
            margin-top: .35rem;
        }

        .admin-notification-item .notification-meta .small {
            display: block;
            margin-top: .3rem;
            white-space: normal;
        }

        .admin-sidebar-counter {
            margin-inline-start: auto;
            min-width: 1.5rem;
            text-align: center;
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

    <aside class="sidebar sidebar-base sidebar-default navs-rounded-all sidebar-color" data-toggle="main-sidebar" data-sidebar="responsive">
        <div class="sidebar-header d-flex align-items-center justify-content-center">
            <a href="{{ route('admin.dashboard') }}" class="navbar-brand">
                <img class="logo-normal" src="{{ asset('images/logo.svg') }}" alt="#">
                <img class="logo-normal logo-white" src="{{ asset('images/logo.svg') }}" alt="#">
                <img class="logo-full" src="{{ asset('images/logo.svg') }}" alt="#">
                <img class="logo-full logo-full-white" src="{{ asset('images/logo.svg') }}" alt="#">
            </a>
            <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
                <i class="chevron-right">
                    <svg xmlns="http://www.w3.org/2000/svg" height="1.2rem" viewBox="0 0 512 512" fill="white">
                        <path d="M470.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L402.7 256 265.4 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160zm-352 160l160-160c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L210.7 256 73.4 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0z" />
                    </svg>
                </i>
                <i class="chevron-left">
                    <svg xmlns="http://www.w3.org/2000/svg" height="1.2rem" viewBox="0 0 512 512" fill="white" transform="rotate(180)">
                        <path d="M470.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L402.7 256 265.4 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160zm-352 160l160-160c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L210.7 256 73.4 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0z" />
                    </svg>
                </i>
            </div>
        </div>

        <div class="sidebar-body pt-0 data-scrollbar">
            <div class="sidebar-list">
                <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="icon"><i class="ph ph-squares-four fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.dashboard') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.producers.*') ? 'active' : '' }}" href="{{ route('admin.producers.index') }}">
                            <i class="icon"><i class="ph ph-users-three fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.producers') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.entities.*') ? 'active' : '' }}" href="{{ route('admin.entities.index') }}">
                            <i class="icon"><i class="ph ph-users-three fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.entities') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                            <i class="icon"><i class="ph ph-user-circle fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.users') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.applications.*') ? 'active' : '' }}" href="{{ route('admin.applications.index') }}">
                            <i class="icon"><i class="ph ph-film-strip fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.applications') }}</span>
                            @if ($layoutSidebarCounters['applications'] > 0)
                                <span class="badge bg-primary rounded-pill admin-sidebar-counter" data-sidebar-counter="applications">{{ $layoutSidebarCounters['applications'] }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.scouting-requests.*') ? 'active' : '' }}" href="{{ route('admin.scouting-requests.index') }}">
                            <i class="icon"><i class="ph ph-map-pin-area fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.scouting_requests') }}</span>
                            @if ($layoutSidebarCounters['scouting_requests'] > 0)
                                <span class="badge bg-primary rounded-pill admin-sidebar-counter" data-sidebar-counter="scouting_requests">{{ $layoutSidebarCounters['scouting_requests'] }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.contact-center.*') ? 'active' : '' }}" href="{{ route('admin.contact-center.index') }}">
                            <i class="icon"><i class="ph ph-headset fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.contact_center') }}</span>
                            @if ($layoutSidebarCounters['contact_center'] > 0)
                                <span class="badge bg-danger rounded-pill admin-sidebar-counter" data-sidebar-counter="contact_center">{{ $layoutSidebarCounters['contact_center'] }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.permits.*') ? 'active' : '' }}" href="{{ route('admin.permits.index') }}">
                            <i class="icon"><i class="ph ph-seal-check fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.permits') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.groups.*') ? 'active' : '' }}" href="{{ route('admin.groups.index') }}">
                            <i class="icon"><i class="ph ph-stack fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.groups') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}" href="{{ route('admin.integrations.index') }}">
                            <i class="icon"><i class="ph ph-plugs-connected fs-4"></i></i>
                            <span class="item-name">{{ __('app.admin.navigation.integrations') }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="sidebar-footer"></div>
    </aside>

    <main class="main-content">
        <div class="position-relative ">
            <nav class="nav navbar navbar-expand-xl header-hover-menu navbar-light iq-navbar">
                <div class="container-fluid navbar-inner">
                    <a href="{{ route('admin.dashboard') }}" class="navbar-brand">
                        <img class="logo-normal" src="{{ asset('images/logo.svg') }}" alt="#">
                        <img class="logo-normal logo-white" src="{{ asset('images/logo.svg') }}" alt="#">
                        <img class="logo-full" src="{{ asset('images/logo.svg') }}" alt="#">
                        <img class="logo-full logo-full-white" src="{{ asset('images/logo.svg') }}" alt="#">
                    </a>
                    <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
                        <i class="icon d-flex">
                            <svg class="icon-20" width="20" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z" />
                            </svg>
                        </i>
                    </div>
                    <div class="d-flex align-items-center justify-content-between product-offcanvas">
                        <div class="breadcrumb-title pe-3 d-none d-xl-block">
                            <small class="mb-0 text-capitalize">{{ $breadcrumb ?? __('app.admin.navigation.dashboard') }}</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <button id="navbar-toggle" class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                            <span class="navbar-toggler-icon">
                                <span class="navbar-toggler-bar bar1 mt-1"></span>
                                <span class="navbar-toggler-bar bar2"></span>
                                <span class="navbar-toggler-bar bar3"></span>
                            </span>
                        </button>
                    </div>
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="mb-2 navbar-nav ms-auto align-items-center navbar-list mb-lg-0 ">
                            <li class="nav-item">
                                <a href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL(app()->getLocale() === 'ar' ? 'en' : 'ar', null, [], true) }}" class="nav-link" id="langues-drop">
                                    {{ app()->getLocale() === 'ar' ? __('app.meta.english') : __('app.meta.arabic') }}
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a href="#" class="nav-link position-relative" id="notification-drop" data-bs-toggle="dropdown">
                                    <i class="ph-fill ph-bell fs-4 align-middle"></i>
                                    @if ($layoutNotificationCount > 0)
                                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" style="font-size:.65rem;">{{ $layoutNotificationCount > 99 ? '99+' : $layoutNotificationCount }}</span>
                                    @endif
                                </a>
                                <ul class="p-0 sub-drop dropdown-menu dropdown-menu-end" aria-labelledby="notification-drop">
                                    <li class="p-0">
                                        <div class="p-3 card-header d-flex justify-content-between bg-primary rounded-top">
                                            <div class="header-title">
                                                <h5 class="mb-0 text-white">{{ __('app.admin.dashboard.notifications_title') }}</h5>
                                            </div>
                                        </div>
                                        <div class="p-0 card-body all-notification">
                                            @forelse ($layoutNotificationItems as $notification)
                                                @php($notificationView = \App\Support\NotificationPresenter::present($notification))
                                                <a href="{{ $notificationView['url'] }}" class="iq-sub-card text-start admin-notification-item">
                                                    <div class="d-flex align-items-center">
                                                        <img class="p-1 avatar-40 rounded-pill bg-primary-subtle" src="{{ asset('images/logo.svg') }}" alt="img" loading="lazy">
                                                        <div class="w-100 ms-3">
                                                            <h6 class="mb-0">{{ $notificationView['title'] }}</h6>
                                                            @if ($notificationView['highlight_active'])
                                                                <div class="notification-meta">
                                                                    <span class="badge bg-{{ $notificationView['highlight_class'] }}">{{ $notificationView['highlight_title'] }}</span>
                                                                    @if ($notificationView['highlight_summary'])
                                                                        <span class="small text-muted">{{ $notificationView['highlight_summary'] }}</span>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <p class="mb-0">{{ $notificationView['body'] }}</p>
                                                            </div>
                                                            <div><small class="float-end font-size-12">{{ $notification->created_at?->diffForHumans() }}</small></div>
                                                        </div>
                                                    </div>
                                                </a>
                                            @empty
                                                <div class="p-3 text-muted">{{ __('app.admin.dashboard.notifications_empty') }}</div>
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
                                    <img src="{{ asset('images/logo.svg') }}" alt="User-Profile" class="theme-color-default-img img-fluid avatar avatar-50 avatar-rounded" loading="lazy">
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile-setting">
                                    <li class="px-3 mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-3">
                                                <img class="avatar-img rounded-circle shadow avatar-70 rounded" src="{{ asset('images/logo.svg') }}" alt="avatar">
                                            </div>
                                            <div class="text-start">
                                                <a class="h6" href="{{ route('admin.dashboard') }}">{{ $layoutProfileEntityName }}</a>
                                                <p class="small m-0">{{ $layoutProfileEmail }}</p>
                                            </div>
                                        </div>
                                    </li>
                                    @foreach ($adminProfileLinks as $adminProfileLink)
                                        <li><a class="dropdown-item" href="{{ $adminProfileLink['url'] }}">{{ $adminProfileLink['label'] }}</a></li>
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
        <footer class="footer">
            <div class="footer-body">
                <ul class="left-panel list-inline mb-0 p-0">
                    <li class="list-inline-item"><a href="{{ route('admin.dashboard') }}">{{ __('app.meta.app_name') }}</a></li>
                </ul>
                <div class="right-panel">
                    <span class="text-gray">
                        <svg class="icon-16" width="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M15.85 2.50065C16.481 2.50065 17.111 2.58965 17.71 2.79065C21.401 3.99065 22.731 8.04065 21.62 11.5806C20.99 13.3896 19.96 15.0406 18.611 16.3896C16.68 18.2596 14.561 19.9196 12.28 21.3496L12.03 21.5006L11.77 21.3396C9.48102 19.9196 7.35002 18.2596 5.40102 16.3796C4.06102 15.0306 3.03002 13.3896 2.39002 11.5806C1.26002 8.04065 2.59002 3.99065 6.32102 2.76965C6.61102 2.66965 6.91002 2.59965 7.21002 2.56065H7.33002C7.61102 2.51965 7.89002 2.50065 8.17002 2.50065H8.28002C8.91002 2.51965 9.52002 2.62965 10.111 2.83065H10.17C10.21 2.84965 10.24 2.87065 10.26 2.88965C10.481 2.96065 10.69 3.04065 10.89 3.15065L11.27 3.32065C11.3618 3.36962 11.4649 3.44445 11.554 3.50912C11.6104 3.55009 11.6612 3.58699 11.7 3.61065C11.7163 3.62028 11.7329 3.62996 11.7496 3.63972C11.8354 3.68977 11.9247 3.74191 12 3.79965C13.111 2.95065 14.46 2.49065 15.85 2.50065ZM18.51 9.70065C18.92 9.68965 19.27 9.36065 19.3 8.93965V8.82065C19.33 7.41965 18.481 6.15065 17.19 5.66065C16.78 5.51965 16.33 5.74065 16.18 6.16065C16.04 6.58065 16.26 7.04065 16.68 7.18965C17.321 7.42965 17.75 8.06065 17.75 8.75965V8.79065C17.731 9.01965 17.8 9.24065 17.94 9.41065C18.08 9.58065 18.29 9.67965 18.51 9.70065Z" fill="currentColor"></path>
                        </svg>
                    </span>
                    by <a href="https://seagull-technology.com/ar" target="_blank" rel="noreferrer">seagull-technology</a>.
                </div>
            </div>
        </footer>
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
