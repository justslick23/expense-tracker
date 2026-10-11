
@extends('layouts.app')

@section('shell')
@php
    $nav = [
        ['Dashboard', 'fa-gauge-high', 'home'],
        ['Income', 'fa-wallet', 'incomes.index'],
        ['Expenses', 'fa-money-bill-transfer', 'expenses.index'],
        ['Receipts', 'fa-camera', 'receipts.index'],
        ['Recurring Expenses', 'fa-rotate', 'recurring.index'],
        ['Budgets', 'fa-chart-pie', 'budgets.index'],
        ['Periods', 'fa-calendar-days', 'periods.index'],
        ['Categories', 'fa-tags', 'categories.index'],
        ['Savings Goals', 'fa-bullseye', 'savings.index'],
        ['Reports', 'fa-chart-column', 'reports.index'],
    ];

    $initial = mb_strtoupper(
        mb_substr(Auth::user()->name, 0, 1)
    );

    $activePeriod = \App\Models\FinancialPeriod::query()
        ->where('user_id', Auth::id())
        ->where('is_open', true)
        ->first();

    $hasReceiptRoutes = Route::has('receipts.create');
@endphp

<aside class="app-sidebar">

    <a href="{{ route('home') }}" class="brand">
        <span class="brand-mark">
            <i class="fa-solid fa-wallet"></i>
        </span>

        <span class="brand-text">
            {{ config('app.name') }}
        </span>
    </a>

    <nav class="side-nav" aria-label="Main navigation">

        @foreach ($nav as [$label, $icon, $route])

            @php
                $prefix = \Illuminate\Support\Str::before($route, '.');

                $active = request()->routeIs(
                    $route,
                    $prefix . '.*'
                );
            @endphp

            <a
                href="{{ route($route) }}"
                class="side-link {{ $active ? 'active' : '' }}"
                title="{{ $label }}"
                @if($active) aria-current="page" @endif
            >
                <i class="fa-solid {{ $icon }}"></i>

                <span class="side-label">
                    {{ $label }}
                </span>
            </a>

        @endforeach

    </nav>

    <div class="side-bottom">

        {{-- Settings is not implemented yet --}}
        <span
            class="side-link disabled"
            title="Settings will be available in a future update"
        >
            <i class="fa-solid fa-gear"></i>
            <span class="side-label">Settings</span>
            <span class="soon">Soon</span>
        </span>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                type="submit"
                class="side-link"
                title="Log out"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="side-label">Log out</span>
            </button>
        </form>

    </div>

</aside>

<div class="app-main">

    <header class="app-topbar">

        {{-- Sidebar toggle --}}
        <button
            type="button"
            class="icon-btn"
            data-sidebar-toggle
            aria-label="Toggle sidebar"
        >
            <i class="fa-solid fa-bars"></i>
        </button>

        <span class="topbar-title">
            @yield('title', config('app.name'))
        </span>

        <div class="ms-auto d-flex align-items-center gap-2">

            {{-- Open financial period --}}
            <a
                href="{{ route('periods.index') }}"
                class="period-pill d-none d-md-inline-flex
                       align-items-center text-decoration-none"
                title="Manage financial periods"
            >
                <i class="fa-solid fa-calendar-days me-2"></i>

                @if($activePeriod)
                    {{ $activePeriod->name }}
                @else
                    No open period
                @endif
            </a>

            {{-- Quick Add Dropdown --}}
            <div class="dropdown d-none d-lg-block">

                <button
                    type="button"
                    class="btn btn-primary d-inline-flex align-items-center"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <i class="fa-solid fa-plus me-2"></i>
                    Add
                    <i class="fa-solid fa-chevron-down ms-2 small"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow">

                    <li>
                        <a
                            class="dropdown-item py-2"
                            href="{{ route('incomes.create') }}"
                        >
                            <i class="fa-solid fa-wallet me-2"></i>
                            Add Income
                        </a>
                    </li>

                    <li>
                        <a
                            class="dropdown-item py-2"
                            href="{{ route('expenses.create') }}"
                        >
                            <i class="fa-solid fa-money-bill-transfer me-2"></i>
                            Add Expense
                        </a>
                    </li>

                    @if($hasReceiptRoutes)
                        <li><hr class="dropdown-divider"></li>

                        <li>
                            <a
                                class="dropdown-item py-2"
                                href="{{ route('receipts.create') }}"
                            >
                                <i class="fa-solid fa-camera me-2"></i>
                                Scan Receipt
                            </a>
                        </li>
                    @endif

                </ul>
            </div>

            {{-- Recurring expenses shortcut --}}
            <a
                href="{{ route('recurring.index') }}"
                class="icon-btn"
                title="View recurring expenses"
                aria-label="View recurring expenses"
            >
                <i class="fa-solid fa-bell"></i>
            </a>

            {{-- Theme toggle --}}
            <button
                type="button"
                class="icon-btn"
                data-theme-toggle
                aria-label="Toggle theme"
                title="Toggle light and dark mode"
            >
                <i class="fa-solid fa-moon theme-moon"></i>
                <i class="fa-solid fa-sun theme-sun"></i>
            </button>

            {{-- Account --}}
            <div class="user-chip">
                <span class="avatar">
                    {{ $initial }}
                </span>

                <span class="d-none d-md-inline small fw-semibold">
                    {{ Auth::user()->name }}
                </span>
            </div>

        </div>

    </header>

    <div class="app-content">
        @yield('content')
    </div>

</div>

{{-- Mobile bottom navigation --}}
<nav class="tabbar d-lg-none" aria-label="Mobile navigation">

    <a
        href="{{ route('home') }}"
        class="tab {{ request()->routeIs('home') ? 'active' : '' }}"
    >
        <i class="fa-solid fa-gauge-high"></i>
        <span>Home</span>
    </a>

    <a
        href="{{ route('expenses.index') }}"
        class="tab {{ request()->routeIs('expenses.*') ? 'active' : '' }}"
    >
        <i class="fa-solid fa-receipt"></i>
        <span>Expenses</span>
    </a>

    {{-- Mobile add dropdown --}}
    <div class="dropdown dropup">

        <button
            type="button"
            class="tab-fab"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-label="Add transaction or scan receipt"
        >
            <i class="fa-solid fa-plus"></i>
        </button>

        <ul class="dropdown-menu shadow mb-2">

            <li>
                <a
                    href="{{ route('incomes.create') }}"
                    class="dropdown-item py-2"
                >
                    <i class="fa-solid fa-wallet me-2"></i>
                    Add Income
                </a>
            </li>

            <li>
                <a
                    href="{{ route('expenses.create') }}"
                    class="dropdown-item py-2"
                >
                    <i class="fa-solid fa-money-bill-transfer me-2"></i>
                    Add Expense
                </a>
            </li>

            @if($hasReceiptRoutes)
                <li><hr class="dropdown-divider"></li>

                <li>
                    <a
                        href="{{ route('receipts.create') }}"
                        class="dropdown-item py-2"
                    >
                        <i class="fa-solid fa-camera me-2"></i>
                        Scan Receipt
                    </a>
                </li>
            @endif

        </ul>

    </div>

    <a
        href="{{ route('budgets.index') }}"
        class="tab {{ request()->routeIs('budgets.*') ? 'active' : '' }}"
    >
        <i class="fa-solid fa-chart-pie"></i>
        <span>Budgets</span>
    </a>

    <button
        type="button"
        class="tab"
        data-sidebar-toggle
        aria-label="More navigation options"
    >
        <i class="fa-solid fa-ellipsis"></i>
        <span>More</span>
    </button>

</nav>

<div class="sidebar-backdrop"></div>

@endsection
