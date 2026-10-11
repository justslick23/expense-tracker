<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'opening_balance' => ['nullable', 'regex:/^-?\d{1,3}(,?\d{3})*(\.\d{1,2})?$/'],
        ];
    }

    public function messages(): array
    {
        return ['opening_balance.regex' => 'Enter an amount like 1234.50 (at most 2 decimals).'];
    }
}