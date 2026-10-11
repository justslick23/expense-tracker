<?php

namespace App\Http\Requests;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return ! $budget
            || $this->user()->can('update', $budget);
    }

    public function rules(): array
    {
        $user = $this->user();
        $budget = $this->route('budget');

        return [
            'financial_period_id' => [
                'required',
                'integer',
                Rule::exists('financial_periods', 'id')
                    ->where('user_id', $user->id),
            ],

            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(function ($query) use ($user, $budget) {
                        $query->where('user_id', $user->id)
                            ->where('type', 'expense')
                            ->where(function ($q) use ($budget) {
                                $q->whereNull('archived_at');

                                if ($budget) {
                                    $q->orWhere(
                                        'id',
                                        $budget->category_id
                                    );
                                }
                            });
                    }),
            ],

            'amount' => [
                'bail',
                'required',
                'string',
                'max:15',
                'regex:/^\d{1,3}(,?\d{3})*(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    try {
                        if (\App\Support\Money::parse((string) $value) <= 0) {
                            $fail('The budget amount must be greater than zero.');
                        }
                    } catch (\InvalidArgumentException $e) {
                        $fail('Enter a valid monetary amount.');
                    }
                },
            ],

            'confirm_closed' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $budget = $this->route('budget');

            $periodId = $this->input('financial_period_id');
            $categoryId = $this->input('category_id');

            // Prevent duplicate budget allocations.
            $exists = Budget::query()
                ->where('user_id', $user->id)
                ->where('financial_period_id', $periodId)
                ->where('category_id', $categoryId)
                ->when(
                    $budget,
                    fn ($query) => $query->whereKeyNot($budget->id)
                )
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'category_id',
                    'This category already has a budget allocation for the selected financial period.'
                );
            }

            // Changing a closed period requires confirmation.
            $period = $user->financialPeriods()
                ->find($periodId);

            $affectedPeriods = collect([
                $period,
                $budget?->period,
            ])->filter();

            $closedPeriod = $affectedPeriods->first(
                fn ($p) => ! $p->isOpen()
            );

            if (
                $closedPeriod
                && ! $this->boolean('confirm_closed')
            ) {
                $validator->errors()->add(
                    'confirm_closed',
                    "This change affects the closed period \"{$closedPeriod->name}\". Confirm before proceeding."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'financial_period_id.required' =>
                'Select a financial period.',

            'financial_period_id.exists' =>
                'Select one of your financial periods.',

            'category_id.required' =>
                'Select an expense category.',

            'category_id.exists' =>
                'Select one of your active expense categories.',

            'amount.regex' =>
                'Enter an amount like 1500.50 with at most two decimal places.',
        ];
    }
}
