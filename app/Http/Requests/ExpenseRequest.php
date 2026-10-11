<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return ! $expense || $this->user()->can('update', $expense);
    }

    public function rules(): array
    {
        $expense = $this->route('expense');

        return [
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->where('type', 'expense')
                        ->where(
                            fn ($q) => $q
                                ->whereNull('archived_at')
                                ->orWhere('id', $expense?->category_id)
                        )
                ),
            ],

            'amount' => [
                'bail',
                'required',
                'string',
                'max:15',
                'regex:/^\d{1,3}(,?\d{3})*(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    try {
                        if (Money::parse((string) $value) <= 0) {
                            $fail('The amount must be greater than zero.');
                        }
                    } catch (\InvalidArgumentException $e) {
                        $fail('Enter a valid monetary amount.');
                    }
                },
            ],

            'expense_date' => [
                'required',
                'date',
            ],

            'description' => [
                'nullable',
                'string',
                'max:255',
            ],

            'merchant' => [
                'nullable',
                'string',
                'max:255',
            ],

            'confirm_closed' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Choose an expense category.',
            'category_id.exists' => 'Choose one of your active expense categories.',
            'amount.regex' => 'Enter an amount like 1500.50 (at most 2 decimal places).',
            'expense_date.required' => 'Enter the date of the expense.',
        ];
    }
}
