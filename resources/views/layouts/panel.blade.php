<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? __('app.meta.app_name') }}</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6f4;
            color: #123126;
            direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }};
        }
        a {
            color: inherit;
        }
        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
        }
        .sidebar {
            background: #0d3f2e;
            color: #f4faf7;
            padding: 28px 22px;
        }
        .brand {
            margin-bottom: 28px;
        }
        .brand strong {
            display: block;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .brand span {
            font-size: 13px;
            color: #b8d0c6;
        }
        .nav {
            display: grid;
            gap: 10px;
        }
        .nav a {
            padding: 12px 14px;
            border-radius: 12px;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.04);
        }
        .nav a.active {
            background: #1a6c4e;
            font-weight: 700;
        }
        .locale-switcher {
            margin-top: 24px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .locale-switcher a,
        .logout button,
        .btn {
            display: inline-block;
            border: 0;
            border-radius: 12px;
            padding: 11px 16px;
            background: #1a6c4e;
            color: white;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
        }
        .locale-switcher a.alt,
        .btn-secondary,
        .logout button {
            background: #e8f0eb;
            color: #123126;
        }
        .logout {
            margin-top: 12px;
        }
        .content {
            padding: 28px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }
        p {
            margin: 0;
            color: #5a6e65;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 18px;
            background: #e6f6ec;
            color: #0f5c33;
        }
        .grid {
            display: grid;
            gap: 18px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 16px 40px rgba(12, 48, 37, 0.08);
        }
        .card h2 {
            margin: 0 0 14px;
            font-size: 20px;
        }
        .metric strong {
            display: block;
            font-size: 32px;
            margin-bottom: 8px;
        }
        .metric span {
            color: #5a6e65;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th,
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e4ece7;
            text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }};
            vertical-align: top;
            font-size: 14px;
        }
        th {
            color: #567065;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #edf4f1;
            margin: 0 6px 6px 0;
            font-size: 12px;
        }
        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
        }
        input,
        select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #c8d5ce;
            border-radius: 12px;
            box-sizing: border-box;
            font-size: 14px;
            background: #fff;
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }
        .muted {
            color: #6b8077;
            font-size: 13px;
        }
        @media (max-width: 980px) {
            .shell {
                grid-template-columns: 1fr;
            }
            .grid-2,
            .grid-4,
            .field-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <strong>{{ __('app.meta.app_name') }}</strong>
                <span>{{ $sidebarSubtitle ?? __('app.admin.panel_subtitle') }}</span>
            </div>

            <nav class="nav">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">{{ __('app.admin.navigation.dashboard') }}</a>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">{{ __('app.admin.navigation.users') }}</a>
                <a href="{{ route('admin.entities.index') }}" class="{{ request()->routeIs('admin.entities.*') ? 'active' : '' }}">{{ __('app.admin.navigation.entities') }}</a>
                <a href="{{ route('admin.groups.index') }}" class="{{ request()->routeIs('admin.groups.*') ? 'active' : '' }}">{{ __('app.admin.navigation.groups') }}</a>
                <a href="{{ route('admin.integrations.index') }}" class="{{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}">{{ __('app.admin.navigation.integrations') }}</a>
            </nav>

            <div class="locale-switcher">
                <a class="{{ app()->getLocale() === 'en' ? '' : 'alt' }}" href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL('en', null, [], true) }}">{{ __('app.meta.english') }}</a>
                <a class="{{ app()->getLocale() === 'ar' ? '' : 'alt' }}" href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL('ar', null, [], true) }}">{{ __('app.meta.arabic') }}</a>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="logout">
                @csrf
                <button type="submit">{{ __('app.dashboard.logout') }}</button>
            </form>
        </aside>

        <main class="content">
            <div class="topbar">
                <div>
                    <h1>{{ $pageTitle ?? __('app.dashboard.title') }}</h1>
                    <p>{{ $pageIntro ?? '' }}</p>
                </div>

                @isset($topbarMeta)
                    <div class="muted">{{ $topbarMeta }}</div>
                @endisset
            </div>

            @if (session('status'))
                <div class="alert">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert" style="background: #fdecec; color: #8d1f1f;">{{ $errors->first() }}</div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
