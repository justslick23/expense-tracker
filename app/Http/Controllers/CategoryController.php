<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $type = in_array($request->query('type'), Category::TYPES) ? $request->query('type') : null;
        $archived = $request->boolean('archived');

        $categories = $request->user()->categories()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when(! $archived, fn ($q) => $q->active())
            ->orderBy('type')->orderBy('name')->get();

        return view('categories.index', compact('categories', 'type', 'archived'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(CategoryRequest $request)
    {
        $request->user()->categories()->create($request->validated());

        return redirect()->route('categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        Gate::authorize('update', $category);

        return view('categories.edit', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function archive(Category $category)
    {
        Gate::authorize('update', $category);
        $category->update(['archived_at' => now()]);

        return back()->with('success', 'Category archived. Its history is kept.');
    }

    public function restore(Category $category)
    {
        Gate::authorize('update', $category);
        $category->update(['archived_at' => null]);

        return back()->with('success', 'Category restored.');
    }
}