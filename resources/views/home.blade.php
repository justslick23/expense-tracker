@extends('layouts.shell')

@section('title', 'Dashboard')

@section('content')
<x-page-header :title="'Hello, '.Auth::user()->name" subtitle="Component preview. Every figure below is a sample placeholder, not a real record.">
    <button class="btn btn-outline-secondary" onclick="toast('Sample notification')"><i class="fa-solid fa-bell me-1"></i>Test toast</button>
    <button class="btn btn-outline-secondary" onclick="toast('Sample error', 'danger')">Test error</button>
</x-page-header>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><x-stat-card label="Total Income" icon="fa-wallet" value="M 0.00" trend="+3.2%" trend-tone="info" trend-icon="fa-arrow-up" sample /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total Expenses" icon="fa-receipt" value="M 0.00" tone="danger" trend="+5.1%" trend-tone="danger" trend-icon="fa-arrow-up" sample /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Net Cash Flow" icon="fa-arrow-trend-up" value="M 0.00" sample /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Remaining Budget" icon="fa-chart-pie" value="M 0.00" tone="success" trend="Within budget" trend-tone="success" trend-icon="fa-circle-check" sample /></div>
</div>

<div class="card p-3 mb-4">
    <div class="small text-secondary mb-2">Status chips</div>
    <div class="d-flex flex-wrap gap-2">
        <x-chip tone="success" icon="fa-circle-check">Within budget</x-chip>
        <x-chip tone="warning" icon="fa-triangle-exclamation">Nearing limit</x-chip>
        <x-chip tone="danger" icon="fa-circle-exclamation">Over budget</x-chip>
        <x-chip tone="info" icon="fa-lock-open">Open period</x-chip>
        <x-chip icon="fa-lock">Closed</x-chip>
    </div>
</div>

<div class="card">
    <x-empty-state icon="fa-receipt" title="No transactions yet" text="Sample empty state. Expenses and income you record will appear here.">
        <button class="btn btn-primary" disabled>Add expense</button>
    </x-empty-state>
</div>
@endsection