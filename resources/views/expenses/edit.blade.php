
@extends('layouts.shell')

@section('title', 'Edit Expense')

@section('content')
<x-page-header
    title="Edit expense"
    subtitle="Update the details of an existing expense."
/>

<div class="card p-4" style="max-width: 640px">
    @include('expenses._form')
</div>
@endsection
