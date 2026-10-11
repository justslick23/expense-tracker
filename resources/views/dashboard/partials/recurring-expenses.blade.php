
@use('App\Support\Money')

<section class="dashboard-recurring mb-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">
                Recurring Expenses
            </h2>
            <p class="text-secondary small mb-0">
                Upcoming bills, subscriptions and outstanding payments.
            </p>
        </div>

        <a href="{{ route('recurring.index') }}"
           class="btn btn-sm btn-outline-secondary">
            Manage recurring expenses
            <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>

    {{-- Financial summary --}}
    <div class="row g-3 mb-3">

        <div class="col-md-4">
            <div class="card recurring-dashboard-card h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <span class="small text-secondary">
                        Due in 30 days
                    </span>
                    <i class="fa-solid fa-calendar-days text-primary"></i>
                </div>

                <div class="h4 fw-bold mb-1 recurring-number">
                    {{ Money::format($recurringSummary['upcoming_amount']) }}
                </div>

                <div class="small text-secondary">
                    {{ $recurringSummary['upcoming_count'] }}
                    upcoming
                    {{ \Illuminate\Support\Str::plural(
                        'payment',
                        $recurringSummary['upcoming_count']
                    ) }}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card recurring-dashboard-card h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <span class="small text-secondary">
                        Overdue
                    </span>
                    <i class="fa-solid fa-triangle-exclamation text-danger"></i>
                </div>

                <div class="h4 fw-bold mb-1 recurring-number">
                    {{ Money::format($recurringSummary['overdue_amount']) }}
                </div>

                <div class="small text-secondary">
                    {{ $recurringSummary['overdue_count'] }}
                    overdue
                    {{ \Illuminate\Support\Str::plural(
                        'payment',
                        $recurringSummary['overdue_count']
                    ) }}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card recurring-dashboard-card h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <span class="small text-secondary">
                        Total outstanding
                    </span>
                    <i class="fa-solid fa-wallet text-primary"></i>
                </div>

                <div class="h4 fw-bold mb-1 recurring-number">
                    {{ Money::format($recurringSummary['total_outstanding']) }}
                </div>

                <div class="small text-secondary">
                    All pending scheduled payments
                </div>
            </div>
        </div>

    </div>

    {{-- Upcoming payment list --}}
    <div class="card recurring-dashboard-panel">

        <div class="recurring-dashboard-heading">
            <div>
                <h3 class="h6 fw-bold mb-1">Next payments</h3>
                <p class="small text-secondary mb-0">
                    Your next five pending payments.
                </p>
            </div>

            <a href="{{ route('recurring.index', ['status' => 'upcoming']) }}"
               class="small text-decoration-none">
                View all
                <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        @forelse ($recurringSummary['next_payments'] as $payment)

            <div class="recurring-dashboard-payment">

                <div class="recurring-payment-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">
                        {{ $payment->recurringExpense?->name ?? 'Recurring payment' }}
                    </div>

                    <div class="small text-secondary">
                        {{ $payment->due_date->format('d M Y') }}

                        @if ($payment->recurringExpense?->category)
                            &middot;
                            {{ $payment->recurringExpense->category->name }}
                        @endif

                        @if ($payment->recurringExpense?->trashed())
                            &middot; Archived schedule
                        @endif
                    </div>
                </div>

                <div class="text-end">
                    <div class="fw-semibold recurring-number">
                        {{ Money::format($payment->planned_amount) }}
                    </div>

                    <a href="{{ route('recurring.index', ['status' => 'upcoming']) }}"
                       class="small text-decoration-none">
                        Manage
                    </a>
                </div>

            </div>

        @empty

            <div class="recurring-dashboard-empty">
                <i class="fa-solid fa-circle-check mb-2"></i>
                <p class="fw-semibold mb-1">No upcoming payments</p>
                <p class="small text-secondary mb-0">
                    You have no scheduled payments coming up.
                </p>
            </div>

        @endforelse

    </div>

    <div class="small text-secondary mt-2">
        <i class="fa-solid fa-circle-info me-1"></i>
        Scheduled payments are not included in actual spending until confirmed.
    </div>

</section>
