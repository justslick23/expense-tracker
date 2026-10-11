
@extends('layouts.shell')

@section('title', 'Edit Savings Goal')

@section('content')
<div class="savings-page">

    <div class="mb-4">
        <a href="{{ route('savings.show', $goal) }}"
           class="text-decoration-none small d-inline-block mb-2">
            <i class="fa-solid fa-arrow-left me-2"></i>
            Back to goal
        </a>

        <h1 class="h3 fw-bold mb-1">Edit savings goal</h1>
        <p class="text-secondary mb-0">
            Update the target and details for {{ $goal->name }}.
        </p>
    </div>

    <div class="card savings-form-card p-4">
        @include('savings._form')
    </div>

</div>
@endsection
