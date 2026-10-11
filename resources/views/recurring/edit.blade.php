
@extends('layouts.shell')

@section('title', 'Edit Recurring Expense')

@section('content')
<div class="recurring-page">

    <div class="mb-4">
        <a href="{{ route('recurring.index') }}"
           class="text-decoration-none small d-inline-block mb-2">
            <i class="fa-solid fa-arrow-left me-2"></i>
            Recurring expenses
        </a>

        <h1 class="h3 fw-bold mb-1">Edit recurring expense</h1>
        <p class="text-secondary mb-0">
            Update {{ $recurring->name }}.
        </p>
    </div>

    <div class="card p-4 recurring-form-card">
        @include('recurring._form')
    </div>

</div>
@endsection
