<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return ! $category || $this->user()->can('update', $category);
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $type = $category?->type ?? $this->input('type');

        return [
            'type' => [$category ? 'prohibited' : 'required', Rule::in(Category::TYPES)],
            'name' => ['required', 'string', 'max:60',
                Rule::unique('categories')
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id)->where('type', $type))
                    ->ignore($category?->id)],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', Rule::in(Category::ICONS)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a category with this name and type.',
            'type.prohibited' => 'A category type cannot be changed after it is created.',
        ];
    }
}