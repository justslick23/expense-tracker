@extends('layouts.shell')

@section('title', 'Close Period')

@section('content')
<x-page-header :title="'Close '.$period->name" :subtitle="'Started '.$period->start_date->format('j M Y').'. Pick the last day of this period.'" />

<div class="card p-4" style="max-width: 640px">
    <div class="alert alert-secondary">
        The closing summary (income, expenses, savings and budget utilization) will appear here once those modules are built. For now, closing only sets the end date.
    </div>
    @error('status')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <form method="POST" action="{{ route('periods.close.store', $period) }}">@csrf
        <div class="mb-4">
            <label class="form-label" for="end_date">End date</label>
            <input id="end_date" type="date" name="end_date" min="{{ $period->start_date->toDateString() }}" value="{{ old('end_date', today()->toDateString()) }}" class="form-control @error('end_date') is-invalid @enderror" required>
            @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Closing early (for example when salary arrives sooner) is fine. A new period can start the day after.</div>
        </div>
        <button class="btn btn-primary">Close period</button>
        <a href="{{ route('periods.index') }}" class="btn btn-link">Cancel</a>
    </form>
</div>
@endsection