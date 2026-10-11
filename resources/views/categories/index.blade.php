@extends('layouts.shell')

@section('title', 'Categories')

@section('content')
<x-page-header title="Categories" subtitle="Group your income and spending. Archiving keeps history intact.">
    <a href="{{ route('categories.create', ['type' => $type]) }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>New category</a>
</x-page-header>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="btn-group">
        <a href="{{ route('categories.index', ['archived' => $archived ?: null]) }}" class="btn btn-outline-secondary {{ ! $type ? 'active' : '' }}">All</a>
        <a href="{{ route('categories.index', ['type' => 'expense', 'archived' => $archived ?: null]) }}" class="btn btn-outline-secondary {{ $type === 'expense' ? 'active' : '' }}">Expense</a>
        <a href="{{ route('categories.index', ['type' => 'income', 'archived' => $archived ?: null]) }}" class="btn btn-outline-secondary {{ $type === 'income' ? 'active' : '' }}">Income</a>
    </div>
    <a href="{{ route('categories.index', ['type' => $type, 'archived' => $archived ? null : 1]) }}" class="btn btn-link ms-auto">
        <i class="fa-solid {{ $archived ? 'fa-eye-slash' : 'fa-box-archive' }} me-1"></i>{{ $archived ? 'Hide archived' : 'Show archived' }}
    </a>
</div>

<div class="card">
    @if ($categories->isEmpty())
        <x-empty-state icon="fa-tags" title="No categories here" text="Create categories such as Groceries or Salary to organize your records.">
            <a href="{{ route('categories.create', ['type' => $type]) }}" class="btn btn-primary">New category</a>
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Category</th><th>Type</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach ($categories as $c)
                    <tr class="{{ $c->isArchived() ? 'opacity-50' : '' }}">
                        <td><x-category-badge :category="$c" /></td>
                        <td>
                            @if ($c->type === 'income')<x-chip tone="info" icon="fa-arrow-down">Income</x-chip>
                            @else<x-chip icon="fa-arrow-up">Expense</x-chip>@endif
                        </td>
                        <td>@if ($c->isArchived())<x-chip icon="fa-box-archive">Archived</x-chip>@else<x-chip tone="success" icon="fa-circle-check">Active</x-chip>@endif</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('categories.edit', $c) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $c->name }}"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="{{ route($c->isArchived() ? 'categories.restore' : 'categories.archive', $c) }}">@csrf @method('PATCH')
                                    <button class="icon-btn" title="{{ $c->isArchived() ? 'Restore' : 'Archive' }}" aria-label="{{ $c->isArchived() ? 'Restore' : 'Archive' }} {{ $c->name }}">
                                        <i class="fa-solid {{ $c->isArchived() ? 'fa-rotate-left' : 'fa-box-archive' }}"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection