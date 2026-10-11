@extends('layouts.shell')

@section('title', 'Edit Income')

@section('content')
<x-page-header title="Edit income" />
<div class="card p-4" style="max-width: 640px">@include('incomes._form')</div>
@endsection