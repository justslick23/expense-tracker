
@extends('layouts.shell')

@section('title', 'New Savings Goal')

@section('content')
<div class="savings-page">

    <div class="mb-4">
        <a href="{{ route('savings.index') }}"
           class="text-decoration-none small d-inline-block mb-2">
            <i class="fa-solid fa-arrow-left me-2"></i>
            Savings Goals
        </a>

        <h1 class="h3 fw-bold mb-1">Create savings goal</h1>
        <p class="text-secondary mb-0">
            Choose a target and start building towards it.
        </p>
    </div>

    <div class="card savings-form-card p-4">
        @include('savings._form')
    </div>

</div>
@endsection
