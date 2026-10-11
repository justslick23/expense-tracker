<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClosePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['end_date' => ['required', 'date']];
    }
}