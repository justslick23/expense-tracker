@extends('layouts.shell')

@section('title', 'New Category')

@section('content')
<x-page-header title="New category" />
<div class="card p-4" style="max-width: 640px">@include('categories._form')</div>
@endsection