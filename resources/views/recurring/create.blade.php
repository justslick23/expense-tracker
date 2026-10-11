
@extends('layouts.shell')

@section('title', 'New Recurring Expense')

@section('content')
<div class="recurring-page">

    <div class="mb-4">
        <a href="{{ route('recurring.index') }}"
           class="text-decoration-none small d-inline-block mb-2">
            <i class="fa-solid fa-arrow-left me-2"></i>
            Recurring expenses
        </a>

        <h1 class="h3 fw-bold mb-1">New recurring expense</h1>
        <p class="text-secondary mb-0">
            Create a schedule for a regular payment.
        </p>
    </div>

    @if ($categories->isEmpty())
        <div class="card p-4">
            <h2 class="h5">An expense category is required</h2>
            <p class="text-secondary">
                Create an expense category before adding a recurring schedule.
            </p>
            <div>
                <a href="{{ route('categories.create', ['type' => 'expense']) }}"
                   class="btn btn-primary">
                    Create category
                </a>
            </div>
        </div>
    @else
        <div class="card p-4 recurring-form-card">
            @include('recurring._form')
        </div>
    @endif

</div>
@endsection
