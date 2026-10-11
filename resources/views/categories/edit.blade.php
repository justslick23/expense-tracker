@extends('layouts.shell')

@section('title', 'Edit Category')

@section('content')
<x-page-header :title="'Edit '.$category->name" />
<div class="card p-4" style="max-width: 640px">@include('categories._form')</div>
@endsection