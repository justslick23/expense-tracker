
@extends('layouts.shell')
@use('App\Support\Money')

@section('title', 'Expenses')

@section('content')
<x-page-header
    title="Expenses"
    subtitle="Track your spending across financial periods."
>
    <a href="{{ route('expenses.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i>Add expense
    </a>
</x-page-header>

@if ($unassigned && $selectedPeriod !== 'none')
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        {{ $unassigned }} expense(s) are outside any financial period.
        <a href="{{ route('expenses.index', ['period' => 'none']) }}"
           class="alert-link">Review</a>
    </div>
@endif

<div class="card p-3 mb-3">
    <form method="GET" action="{{ route('expenses.index') }}">
        <div class="row g-3 align-items-end">

            <div class="col-md-3">
                <label for="period" class="form-label">Financial period</label>
                <select id="period" name="period" class="form-select">
                    <option value="all" @selected($selectedPeriod === 'all')>
                        All periods
                    </option>
                    <option value="none" @selected($selectedPeriod === 'none')>
                        No period
                    </option>

                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}"
                            @selected($selectedPeriod === (string) $period->id)>
                            {{ $period->name }}
                            {{ $period->isOpen() ? '(open)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <select id="category" name="category" class="form-select">
                    <option value="">All categories</option>

                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}"
                            @selected((string) $selectedCategory === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label for="search" class="form-label">Search expenses</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    value="{{ $search }}"
                    class="form-control"
                    placeholder="Description or merchant"
                >
            </div>

            <div class="col-md-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>

                    <a href="{{ route('expenses.index') }}"
                       class="btn btn-outline-secondary"
                       title="Reset filters"
                       aria-label="Reset filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </div>

        </div>
    </form>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <div class="text-secondary small">Total spending</div>
        <h2 class="h4 fw-bold num mb-0">{{ Money::format($total) }}</h2>
        <div class="text-secondary small">Based on the selected filters</div>
    </div>

    <x-chip tone="info" icon="fa-receipt">
        {{ $expenses->total() }} transactions
    </x-chip>
</div>

<div class="card">
    @if ($expenses->isEmpty())

        <x-empty-state
            icon="fa-receipt"
            title="No expenses found"
            text="Expenses you record will appear here. Try adjusting your filters or adding a new expense."
        >
            <a href="{{ route('expenses.create') }}" class="btn btn-primary">
                Add expense
            </a>
        </x-empty-state>

    @else

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Expense</th>
                        <th>Category</th>
                        <th>Financial period</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($expenses as $expense)
                        @php
                            $closed = $expense->period
                                && ! $expense->period->isOpen();
                        @endphp

                        <tr>
                            <td class="text-nowrap">
                                {{ $expense->expense_date->format('j M Y') }}
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    {{ $expense->description ?: 'Expense' }}
                                </div>

                                @if ($expense->merchant)
                                    <div class="text-secondary small">
                                        <i class="fa-solid fa-store me-1"></i>
                                        {{ $expense->merchant }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <x-category-badge :category="$expense->category" />
                            </td>

                            <td>
                                @if ($expense->period)
                                    <x-chip
                                        :tone="$closed ? 'neutral' : 'info'"
                                        :icon="$closed ? 'fa-lock' : 'fa-lock-open'"
                                    >
                                        {{ $expense->period->name }}
                                    </x-chip>
                                @else
                                    <x-chip
                                        tone="warning"
                                        icon="fa-triangle-exclamation"
                                    >
                                        No period
                                    </x-chip>
                                @endif
                            </td>

                            <td class="text-end num fw-semibold">
                                {{ Money::format($expense->amount) }}
                            </td>

                            <td class="text-end">
                                <div class="d-inline-flex gap-1">

                                    <a
                                        href="{{ route('expenses.edit', $expense) }}"
                                        class="icon-btn"
                                        title="Edit expense"
                                        aria-label="Edit expense"
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('expenses.destroy', $expense) }}"
                                        data-confirm="Delete this expense?{{ $closed ? ' It belongs to a closed period; this change will be logged.' : '' }}"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        @if ($closed)
                                            <input
                                                type="hidden"
                                                name="confirm_closed"
                                                value="1"
                                            >
                                        @endif

                                        <button
                                            type="submit"
                                            class="icon-btn"
                                            title="Delete expense"
                                            aria-label="Delete expense"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($expenses->hasPages())
            <div class="p-3 border-top">
                {{ $expenses->links() }}
            </div>
        @endif

    @endif
</div>
@endsection
