
@extends('layouts.shell')

@section('title', 'Edit Budget Allocation')

@section('content')

<x-page-header
    title="Edit allocation"
    subtitle="Update an existing category budget."
/>

<div class="card p-4" style="max-width: 640px;">
    @include('budgets._form')
</div>

@endsection
