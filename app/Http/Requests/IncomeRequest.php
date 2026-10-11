<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $income = $this->route('income');

        return ! $income || $this->user()->can('update', $income);
    }

    public function rules(): array
    {
        $income = $this->route('income');

        return [
            'category_id' => ['required', Rule::exists('categories', 'id')->where(fn ($q) => $q
                ->where('user_id', $this->user()->id)->where('type', 'income')
                ->where(fn ($w) => $w->whereNull('archived_at')->orWhere('id', $income?->category_id)))],
            'amount' => ['bail', 'required', 'max:15', 'regex:/^\d{1,3}(,?\d{3})*(\.\d{1,2})?$/',
                fn ($a, $v, $fail) => Money::parse((string) $v) > 0 || $fail('The amount must be greater than zero.')],
            'income_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'confirm_closed' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Choose an income category.',
            'category_id.exists' => 'Choose one of your active income categories.',
            'amount.regex' => 'Enter an amount like 1500.50 (at most 2 decimals).',
        ];
    }
}