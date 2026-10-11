
@extends('layouts.shell')

@section('title', 'Add Expense')

@section('content')
<x-page-header
    title="Add expense"
    subtitle="Record a new expense and keep your finances up to date."
/>

@if ($categories->isEmpty())
    <div class="card">
        <x-empty-state
            icon="fa-tags"
            title="Create an expense category first"
            text="Every expense needs a category to keep your spending organized."
        >
            <a
                href="{{ route('categories.create', ['type' => 'expense']) }}"
                class="btn btn-primary"
            >
                New expense category
            </a>
        </x-empty-state>
    </div>
@else
    <div class="card p-4" style="max-width: 640px">
        @include('expenses._form')
    </div>
@endif
@endsection
