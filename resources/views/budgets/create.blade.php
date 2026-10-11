
@extends('layouts.shell')

@section('title', 'New Budget Allocation')

@section('content')

<x-page-header
    title="New allocation"
    subtitle="Assign a spending budget to an expense category."
/>

@if ($periods->isEmpty())
    <div class="card">
        <x-empty-state
            icon="fa-calendar-days"
            title="Create a financial period first"
            text="Budget allocations must belong to a financial period."
        >
            <a href="{{ route('periods.create') }}" class="btn btn-primary">
                Create financial period
            </a>
        </x-empty-state>
    </div>

@elseif ($categories->isEmpty())
    <div class="card">
        <x-empty-state
            icon="fa-tags"
            title="Create an expense category first"
            text="You need an expense category before assigning a budget."
        >
            <a href="{{ route('categories.create', ['type' => 'expense']) }}"
               class="btn btn-primary">
                New expense category
            </a>
        </x-empty-state>
    </div>

@else
    <div class="card p-4" style="max-width: 640px;">
        @include('budgets._form')
    </div>
@endif

@endsection
