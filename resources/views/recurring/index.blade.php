
@extends('layouts.shell')

@use('App\Support\Money')

@section('title', 'Recurring Expenses')

@section('content')
<div class="recurring-page">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Recurring Expenses</h1>
            <p class="text-secondary mb-0">
                Manage subscriptions, scheduled payments and recurring commitments.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <form action="{{ route('recurring.generate') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrows-rotate me-2"></i>
                    Generate payments
                </button>
            </form>

            <a href="{{ route('recurring.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus me-2"></i>
                New recurring expense
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Unable to complete the request.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Payment statistics --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Active schedules', 'value' => $stats['active'],
                'icon' => 'fa-rotate', 'tone' => 'primary'],
            ['label' => 'Pending payments', 'value' => $stats['pending'],
                'icon' => 'fa-clock', 'tone' => 'info'],
            ['label' => 'Overdue payments', 'value' => $stats['overdue'],
                'icon' => 'fa-triangle-exclamation', 'tone' => 'danger'],
            ['label' => 'Upcoming payments', 'value' => $stats['upcoming'],
                'icon' => 'fa-calendar-check', 'tone' => 'success'],
        ] as $stat)
            <div class="col-sm-6 col-xl-3">
                <div class="card recurring-stat h-100">
                    <div class="d-flex justify-content-between gap-2 mb-3">
                        <span class="small text-secondary">{{ $stat['label'] }}</span>
                        <i class="fa-solid {{ $stat['icon'] }} text-{{ $stat['tone'] }}"></i>
                    </div>
                    <div class="h3 fw-bold num mb-0">{{ $stat['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    
{{-- Financial overview --}}
<div class="row g-3 mb-4">

    <div class="col-md-4">
        <div class="card recurring-summary-card h-100">
            <div class="recurring-summary-icon">
                <i class="fa-solid fa-calendar-days"></i>
            </div>

            <div class="small text-secondary mb-1">
                This month's commitments
            </div>

            <div class="h4 fw-bold num mb-1">
                {{ Money::format($financials['monthly_commitments']) }}
            </div>

            <div class="small text-secondary">
                Planned payments due this month
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card recurring-summary-card h-100">
            <div class="recurring-summary-icon">
                <i class="fa-solid fa-calendar-clock"></i>
            </div>

            <div class="small text-secondary mb-1">
                Due in the next 30 days
            </div>

            <div class="h4 fw-bold num mb-1">
                {{ Money::format($financials['next_30_days']) }}
            </div>

            <div class="small text-secondary">
                Pending payments from today onwards
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card recurring-summary-card recurring-summary-alert h-100">
            <div class="recurring-summary-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="small text-secondary mb-1">
                Total overdue
            </div>

            <div class="h4 fw-bold num mb-1">
                {{ Money::format($financials['overdue_total']) }}
            </div>

            <div class="small text-secondary">
                {{ $stats['overdue'] }}
                outstanding {{ Str::plural('payment', $stats['overdue']) }}
            </div>
        </div>
    </div>

</div>


    {{-- Recurring templates --}}
  
<div class="recurring-panel-heading">
    <div>
        <h2 class="h5 fw-bold mb-1">Recurring schedules</h2>
        <p class="small text-secondary mb-0">
            Your recurring payment rules and planned amounts.
        </p>
    </div>

    <form method="GET"
          action="{{ route('recurring.index') }}"
          class="recurring-search-form">

        @if ($status !== 'all')
            <input type="hidden"
                   name="status"
                   value="{{ $status }}">
        @endif

        <div class="input-group input-group-sm">
            <span class="input-group-text">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>

            <input type="search"
                   name="search"
                   value="{{ $search }}"
                   class="form-control"
                   placeholder="Search schedules..."
                   aria-label="Search recurring schedules">

            <button type="submit"
                    class="btn btn-outline-secondary">
                Search
            </button>
        </div>
    </form>
</div>


    {{-- Scheduled payments --}}
    <div class="card recurring-panel mb-4">
        <div class="recurring-panel-heading">
            <div>
                <h2 class="h5 fw-bold mb-1">Scheduled payments</h2>
                <p class="small text-secondary mb-0">
                    Confirm payments to record them as actual expenses.
                </p>
            </div>
        </div>

        
{{-- Scheduled payment filters --}}
<div class="recurring-filter-bar">

    @php
        $paymentFilters = [
            'all' => 'All',
            'pending' => 'Pending',
            'overdue' => 'Overdue',
            'upcoming' => 'Upcoming',
            'paid' => 'Paid',
            'skipped' => 'Skipped',
        ];
    @endphp

    @foreach ($paymentFilters as $value => $label)
        <a href="{{ route('recurring.index', array_filter([
                'status' => $value === 'all' ? null : $value,
                'search' => $search ?: null,
            ])) }}"
           class="recurring-filter {{ $status === $value ? 'active' : '' }}"
           @if ($status === $value) aria-current="page" @endif>
            {{ $label }}

            @if ($value === 'overdue' && $stats['overdue'] > 0)
                <span class="recurring-filter-count">
                    {{ $stats['overdue'] }}
                </span>
            @endif
        </a>
    @endforeach

</div>


        @if ($occurrences->isEmpty())
            <div class="recurring-empty">
                <i class="fa-solid fa-calendar-days"></i>
                <h3 class="h6 mb-1">No scheduled payments</h3>
                <p class="text-secondary small mb-0">
                    Click Generate payments to create upcoming occurrences.
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Payment</th>
                            <th>Due date</th>
                            <th class="text-end">Planned</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($occurrences as $payment)
                            @php
                                $template = $payment->recurringExpense;
                                $overdue = $payment->isOverdue();
                                $canManage = $template !== null;
                            @endphp

                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $template?->name ?? 'Unknown schedule' }}

                                        @if ($template?->trashed())
                                            <span class="badge text-bg-secondary ms-1">
                                                Archived
                                            </span>
                                        @endif
                                    </div>
                                    <div class="small text-secondary">
                                        {{ $template?->category?->name ?? 'Recurring payment' }}
                                    </div>
                                </td>

                                <td class="text-nowrap">
                                    {{ $payment->due_date->format('d M Y') }}
                                </td>

                                <td class="text-end fw-semibold num">
                                    {{ Money::format($payment->planned_amount) }}
                                </td>

                                <td>
                                    @if ($payment->isPaid())
                                        <span class="badge text-bg-success">Paid</span>
                                    @elseif ($payment->isSkipped())
                                        <span class="badge text-bg-secondary">Skipped</span>
                                    @elseif ($overdue)
                                        <span class="badge text-bg-danger">Overdue</span>
                                    @else
                                        <span class="badge text-bg-warning">Pending</span>
                                    @endif
                                </td>

                                <td class="text-end text-nowrap">
                                    @if ($payment->isPending() && $canManage)
                                        <button class="btn btn-primary btn-sm"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#payRecurring{{ $payment->id }}">
                                            <i class="fa-solid fa-check me-1"></i>
                                            Pay
                                        </button>

                                        <form class="d-inline"
                                              method="POST"
                                              action="{{ route('recurring.skip', $payment) }}"
                                              onsubmit="return confirm('Skip this scheduled payment?')">
                                            @csrf
                                            <button class="btn btn-outline-secondary btn-sm"
                                                    type="submit">
                                                Skip
                                            </button>
                                        </form>

                                    @elseif ($payment->isSkipped() && $canManage)
                                        <form method="POST"
                                              action="{{ route('recurring.reopen', $payment) }}">
                                            @csrf
                                            <button class="btn btn-outline-secondary btn-sm"
                                                    type="submit">
                                                <i class="fa-solid fa-rotate-left me-1"></i>
                                                Reopen
                                            </button>
                                        </form>

                                    @elseif ($payment->isPaid() && $payment->expense)
                                        <a href="{{ route('expenses.edit', $payment->expense) }}"
                                           class="btn btn-outline-secondary btn-sm">
                                            View expense
                                        </a>
                                    @else
                                        <span class="text-secondary small">No actions</span>
                                    @endif
                                </td>
                            </tr>

                            @if ($payment->isPending() && $canManage)
                                <tr class="d-none">
                                    <td colspan="5">
                                        {{-- Modal markup is included below, outside table cells. --}}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($occurrences->hasPages())
                <div class="p-3 border-top">
                    {{ $occurrences->withQueryString()->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>

    {{-- Payment confirmation dialogs --}}
    @foreach ($occurrences as $payment)
        @if ($payment->isPending() && $payment->recurringExpense)
            <div class="modal fade"
                 id="payRecurring{{ $payment->id }}"
                 tabindex="-1"
                 aria-labelledby="payTitle{{ $payment->id }}"
                 aria-hidden="true">

                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST"
                              action="{{ route('recurring.pay', $payment) }}">
                            @csrf

                            <div class="modal-header">
                                <h3 class="modal-title fs-5"
                                    id="payTitle{{ $payment->id }}">
                                    Confirm payment
                                </h3>
                                <button type="button" class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <div class="mb-3">
                                    <div class="fw-semibold">
                                        {{ $payment->recurringExpense->name }}
                                    </div>
                                    <div class="small text-secondary">
                                        Due {{ $payment->due_date->format('d M Y') }}
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="paymentAmount{{ $payment->id }}"
                                           class="form-label">
                                        Actual amount paid
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">M</span>
                                        <input type="text"
                                               class="form-control"
                                               id="paymentAmount{{ $payment->id }}"
                                               name="amount"
                                               inputmode="decimal"
                                               value="{{ number_format($payment->planned_amount / 100, 2, '.', '') }}"
                                               required>
                                    </div>
                                    <div class="form-text">
                                        Change this if the actual amount differs.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="paymentDate{{ $payment->id }}"
                                           class="form-label">
                                        Payment date
                                    </label>
                                    <input type="date"
                                           class="form-control"
                                           id="paymentDate{{ $payment->id }}"
                                           name="paid_date"
                                           value="{{ today()->toDateString() }}"
                                           max="{{ today()->toDateString() }}"
                                           required>
                                </div>

                                <div class="form-check">
                                    <input type="checkbox"
                                           name="confirm_closed"
                                           id="confirmClosed{{ $payment->id }}"
                                           class="form-check-input"
                                           value="1">
                                    <label class="form-check-label small"
                                           for="confirmClosed{{ $payment->id }}">
                                        I confirm changes to a closed financial period,
                                        if the payment date falls within one.
                                    </label>
                                </div>

                                <div class="alert alert-info small mt-3 mb-0">
                                    <i class="fa-solid fa-circle-info me-1"></i>
                                    An actual expense will be created using
                                    the payment date and amount entered above.
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        data-bs-dismiss="modal">
                                    Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-check me-1"></i>
                                    Confirm payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

</div>
@endsection
