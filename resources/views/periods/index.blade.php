@extends('layouts.shell')

@section('title', 'Financial Periods')

@section('content')
<x-page-header title="Financial Periods" subtitle="Your payroll-based tracking windows, independent of calendar months.">
    @if ($current)
        <a href="{{ route('periods.close', $current) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-lock me-1"></i>Close current period</a>
    @else
        <a href="{{ route('periods.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Start period</a>
    @endif
</x-page-header>

<div class="card">
    @if ($periods->isEmpty())
        <x-empty-state icon="fa-calendar-days" title="No financial periods yet" text="Start your first period to begin tracking income, expenses and budgets.">
            <a href="{{ route('periods.create') }}" class="btn btn-primary">Start period</a>
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Dates</th><th>Length</th><th>Status</th><th class="text-end">Opening balance</th></tr></thead>
                <tbody>
                @foreach ($periods as $p)
                    <tr>
                        <td class="fw-semibold">{{ $p->name }}</td>
                        <td>{{ $p->start_date->format('j M Y') }} &ndash; {{ $p->end_date?->format('j M Y') ?? 'Ongoing' }}</td>
                        <td class="num">{{ $p->end_date ? ((int) $p->start_date->diffInDays($p->end_date) + 1).' days' : '—' }}</td>
                        <td>
                            @if ($p->isOpen())<x-chip tone="info" icon="fa-lock-open">Open</x-chip>
                            @else<x-chip icon="fa-lock">Closed</x-chip>@endif
                        </td>
                        <td class="text-end num">{{ \App\Support\Money::format($p->opening_balance) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection