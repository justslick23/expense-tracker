@extends('layouts.shell')

@section('title', 'Add Income')

@section('content')
<x-page-header title="Add income" subtitle="Record money you received." />

@if ($categories->isEmpty())
    <div class="card">
        <x-empty-state icon="fa-tags" title="Create an income category first" text="Income needs a category such as Salary or Freelance.">
            <a href="{{ route('categories.create', ['type' => 'income']) }}" class="btn btn-primary">New income category</a>
        </x-empty-state>
    </div>
@else
    <div class="card p-4" style="max-width: 640px">@include('incomes._form')</div>
@endif
@endsection