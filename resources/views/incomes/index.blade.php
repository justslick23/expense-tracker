@extends('layouts.shell')
@use('App\Support\Money')

@section('title', 'Income')

@section('content')
<x-page-header title="Income" subtitle="Salary, commission, freelance work and other money in.">
    <a href="{{ route('incomes.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Add income</a>
</x-page-header>

@if ($unassigned && $sel !== 'none')
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $unassigned }} income record(s) outside any financial period.
        <a href="{{ route('incomes.index', ['period' => 'none']) }}" class="alert-link">Review</a>
    </div>
@endif

<form method="GET" class="d-flex flex-wrap gap-3 align-items-center mb-3">
    <select name="period" class="form-select w-auto" aria-label="Filter by period" onchange="this.form.submit()">
        <option value="all" @selected($sel === 'all')>All periods</option>
        <option value="none" @selected($sel === 'none')>No period</option>
        @foreach ($periods as $p)
            <option value="{{ $p->id }}" @selected($sel === (string) $p->id)>{{ $p->name }}{{ $p->isOpen() ? ' (open)' : '' }}</option>
        @endforeach
    </select>
    <x-chip tone="info" icon="fa-wallet">Total {{ Money::format($total) }}</x-chip>
</form>

<div class="card">
    @if ($incomes->isEmpty())
        <x-empty-state icon="fa-wallet" title="No income here" text="Income you record will appear here, grouped by financial period.">
            <a href="{{ route('incomes.create') }}" class="btn btn-primary">Add income</a>
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Period</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach ($incomes as $i)
                    @php $closed = $i->period && ! $i->period->isOpen(); @endphp
                    <tr>
                        <td class="text-nowrap">{{ $i->income_date->format('j M Y') }}</td>
                        <td><x-category-badge :category="$i->category" /></td>
                        <td class="text-secondary">{{ $i->description ?: '—' }}</td>
                        <td>
                            @if ($i->period)
                                <x-chip :tone="$closed ? 'neutral' : 'info'" :icon="$closed ? 'fa-lock' : 'fa-lock-open'">{{ $i->period->name }}</x-chip>
                            @else
                                <x-chip tone="warning" icon="fa-triangle-exclamation">No period</x-chip>
                            @endif
                        </td>
                        <td class="text-end num fw-semibold">{{ Money::format($i->amount) }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('incomes.edit', $i) }}" class="icon-btn" title="Edit" aria-label="Edit income"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="{{ route('incomes.destroy', $i) }}" data-confirm="Delete this income record?{{ $closed ? ' It belongs to a closed period; this change will be logged.' : '' }}">
                                    @csrf @method('DELETE')
                                    @if ($closed)<input type="hidden" name="confirm_closed" value="1">@endif
                                    <button class="icon-btn" title="Delete" aria-label="Delete income"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($incomes->hasPages())<div class="p-3 border-top">{{ $incomes->links() }}</div>@endif
    @endif
</div>
@endsection