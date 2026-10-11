@extends('layouts.shell')

@section('title', 'Start Period')

@section('content')
<x-page-header title="Start a financial period" subtitle="Choose your own dates. Starting a period does not record any income." />

<div class="card p-4" style="max-width: 640px">
    <form method="POST" action="{{ route('periods.store') }}">@csrf
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. October 2026" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label class="form-label" for="start_date">Start date</label>
                <input id="start_date" type="date" name="start_date" value="{{ old('start_date', today()->toDateString()) }}" class="form-control @error('start_date') is-invalid @enderror" required>
                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="end_date">End date <span class="text-secondary">(optional)</span></label>
                <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}" class="form-control @error('end_date') is-invalid @enderror">
                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label" for="opening_balance">Opening balance</label>
            <div class="input-group">
                <span class="input-group-text">M</span>
                <input id="opening_balance" name="opening_balance" inputmode="decimal" value="{{ old('opening_balance', '0.00') }}" class="form-control num @error('opening_balance') is-invalid @enderror">
                @error('opening_balance')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-text">Unspent funds carried into this period. This is not income. Leave at 0.00 if none.</div>
        </div>
        <button class="btn btn-primary">Start period</button>
        <a href="{{ route('periods.index') }}" class="btn btn-link">Cancel</a>
    </form>
</div>
@endsection