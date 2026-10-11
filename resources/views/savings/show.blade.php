
@extends('layouts.shell')

@use('App\Support\Money')

@section('title', $goal->name)

@section('content')
@php
    $saved = $goal->saved_amount;
    $remaining = $goal->remaining_amount;
    $progress = $goal->progress_percentage;
@endphp

<div class="savings-page">

    <div class="mb-4">
        <a href="{{ route('savings.index') }}"
           class="text-decoration-none small d-inline-block mb-2">
            <i class="fa-solid fa-arrow-left me-2"></i>
            Savings Goals
        </a>

        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h1 class="h3 fw-bold mb-0">{{ $goal->name }}</h1>

                    <span class="badge text-bg-{{
                        $goal->isCompleted() ? 'success' :
                        ($goal->isArchived() ? 'secondary' : 'primary')
                    }}">
                        {{ ucfirst($goal->status) }}
                    </span>
                </div>

                @if ($goal->description)
                    <p class="text-secondary mb-0">
                        {{ $goal->description }}
                    </p>
                @endif
            </div>

            @if ($goal->isActive())
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('savings.edit', $goal) }}"
                       class="btn btn-outline-secondary">
                        <i class="fa-solid fa-pen me-2"></i>
                        Edit
                    </a>

                    <button class="btn btn-outline-primary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#withdrawModal">
                        <i class="fa-solid fa-arrow-up me-2"></i>
                        Withdraw
                    </button>

                    <button class="btn btn-primary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#contributeModal">
                        <i class="fa-solid fa-plus me-2"></i>
                        Add savings
                    </button>
                </div>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fa-solid fa-circle-check me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Unable to complete the request.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Main progress --}}
    <div class="card savings-detail-card mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <div class="small text-secondary mb-2">Total saved</div>

                <div class="savings-big-number">
                    {{ Money::format($saved) }}
                </div>

                <div class="text-secondary small mt-2">
                    Target: {{ Money::format($goal->target_amount) }}
                </div>
            </div>

            <div class="text-end">
                <div class="h3 fw-bold mb-1">
                    {{ number_format($progress, 1) }}%
                </div>
                <div class="small text-secondary">Completed</div>
            </div>
        </div>

        <div class="progress savings-progress savings-progress-lg mb-3"
             role="progressbar"
             aria-label="Savings goal progress"
             aria-valuenow="{{ $progress }}"
             aria-valuemin="0"
             aria-valuemax="100">
            <div class="progress-bar"
                 style="width: {{ $progress }}%"></div>
        </div>

        <div class="d-flex justify-content-between flex-wrap gap-3">
            <div>
                <div class="small text-secondary">Remaining</div>
                <div class="fw-bold savings-number">
                    {{ Money::format($remaining) }}
                </div>
            </div>

            <div class="text-md-end">
                <div class="small text-secondary">Target date</div>
                <div class="fw-semibold">
                    {{ $goal->target_date
                        ? $goal->target_date->format('d M Y')
                        : 'No deadline' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Breakdown --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card savings-stat h-100">
                <div class="small text-secondary mb-2">Starting savings</div>
                <div class="h5 fw-bold savings-number mb-0">
                    {{ Money::format($goal->starting_amount) }}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card savings-stat h-100">
                <div class="small text-secondary mb-2">Contributions</div>
                <div class="h5 fw-bold savings-number mb-0">
                    {{ Money::format($goal->contributed_amount) }}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card savings-stat h-100">
                <div class="small text-secondary mb-2">Withdrawals</div>
                <div class="h5 fw-bold savings-number mb-0">
                    {{ Money::format($goal->withdrawn_amount) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Transactions --}}
    <div class="card savings-panel mb-4">
        <div class="savings-panel-heading">
            <div>
                <h2 class="h5 fw-bold mb-1">Savings history</h2>
                <p class="small text-secondary mb-0">
                    Contributions and withdrawals recorded for this goal.
                </p>
            </div>
        </div>

        @if ($transactions->isEmpty())
            <div class="savings-empty savings-empty-inside">
                <i class="fa-solid fa-clock-rotate-left mb-2"></i>
                <h3 class="h6 fw-semibold">No transactions yet</h3>
                <p class="text-secondary small mb-0">
                    Add savings or record a withdrawal to see it here.
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transaction</th>
                            <th>Note</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="text-nowrap">
                                    {{ $transaction->transaction_date->format('d M Y') }}
                                </td>

                                <td>
                                    @if ($transaction->isContribution())
                                        <span class="badge text-bg-success">
                                            <i class="fa-solid fa-arrow-down me-1"></i>
                                            Contribution
                                        </span>
                                    @else
                                        <span class="badge text-bg-warning">
                                            <i class="fa-solid fa-arrow-up me-1"></i>
                                            Withdrawal
                                        </span>
                                    @endif
                                </td>

                                <td class="text-secondary">
                                    {{ $transaction->note ?: '—' }}
                                </td>

                                <td class="text-end fw-semibold savings-number
                                    {{ $transaction->isContribution() ? 'text-success' : 'text-danger' }}">
                                    {{ $transaction->isContribution() ? '+' : '-' }}
                                    {{ Money::format($transaction->amount) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($transactions->hasPages())
                <div class="p-3 border-top">
                    {{ $transactions->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>

    {{-- Goal management --}}
    @if (! $goal->isArchived())
        <div class="card savings-panel mb-4">
            <div class="savings-panel-heading">
                <div>
                    <h2 class="h5 fw-bold mb-1">Manage goal</h2>
                    <p class="small text-secondary mb-0">
                        Update this goal's lifecycle.
                    </p>
                </div>
            </div>

            <div class="p-4 d-flex flex-wrap gap-2">
                @if ($goal->isActive() && $goal->target_reached)
                    <form method="POST"
                          action="{{ route('savings.complete', $goal) }}">
                        @csrf
                        <button class="btn btn-success" type="submit">
                            <i class="fa-solid fa-circle-check me-2"></i>
                            Mark as completed
                        </button>
                    </form>
                @endif

                @if ($goal->isCompleted())
                    <form method="POST"
                          action="{{ route('savings.reopen', $goal) }}">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fa-solid fa-rotate-left me-2"></i>
                            Reopen goal
                        </button>
                    </form>
                @endif

                <form method="POST"
                      action="{{ route('savings.archive', $goal) }}"
                      onsubmit="return confirm('Archive this savings goal? Your savings history will be preserved.')">
                    @csrf
                    <button class="btn btn-outline-danger" type="submit">
                        <i class="fa-solid fa-box-archive me-2"></i>
                        Archive goal
                    </button>
                </form>
            </div>
        </div>
    @endif

</div>

{{-- Transaction modals --}}
@if ($goal->isActive())
    @foreach ([
        [
            'id' => 'contributeModal',
            'title' => 'Add savings',
            'action' => route('savings.contribute', $goal),
            'button' => 'Record contribution',
            'icon' => 'fa-plus'
        ],
        [
            'id' => 'withdrawModal',
            'title' => 'Withdraw savings',
            'action' => route('savings.withdraw', $goal),
            'button' => 'Record withdrawal',
            'icon' => 'fa-arrow-up'
        ],
    ] as $modal)
        <div class="modal fade"
             id="{{ $modal['id'] }}"
             tabindex="-1"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ $modal['action'] }}">
                        @csrf

                        <div class="modal-header">
                            <h2 class="modal-title fs-5">{{ $modal['title'] }}</h2>
                            <button type="button" class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="amount-{{ $modal['id'] }}"
                                       class="form-label">
                                    Amount
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">M</span>
                                    <input type="text"
                                           id="amount-{{ $modal['id'] }}"
                                           name="amount"
                                           inputmode="decimal"
                                           class="form-control"
                                           placeholder="500.00"
                                           required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="date-{{ $modal['id'] }}"
                                       class="form-label">
                                    Transaction date
                                </label>

                                <input type="date"
                                       id="date-{{ $modal['id'] }}"
                                       name="transaction_date"
                                       class="form-control"
                                       value="{{ today()->toDateString() }}"
                                       max="{{ today()->toDateString() }}"
                                       required>
                            </div>

                            <div>
                                <label for="note-{{ $modal['id'] }}"
                                       class="form-label">
                                    Note (optional)
                                </label>

                                <textarea id="note-{{ $modal['id'] }}"
                                          name="note"
                                          rows="3"
                                          maxlength="500"
                                          class="form-control"
                                          placeholder="Additional details"></textarea>
                            </div>

                            @if ($modal['id'] === 'withdrawModal')
                                <div class="alert alert-info small mt-3 mb-0">
                                    Available savings:
                                    <strong>{{ Money::format($saved) }}</strong>.
                                    Withdrawals cannot exceed this balance.
                                </div>
                            @endif
                        </div>

                        <div class="modal-footer">
                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button class="btn btn-primary" type="submit">
                                <i class="fa-solid {{ $modal['icon'] }} me-2"></i>
                                {{ $modal['button'] }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif

@endsection
