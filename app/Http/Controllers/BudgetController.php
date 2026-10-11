<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Models\FinancialPeriod;
use App\Services\BudgetService;
use App\Services\PeriodService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetService $budgets,
        private PeriodService $periods
    ) {}

    /**
     * Display budget allocations and spending summary.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $periods = $user->financialPeriods()
            ->orderByDesc('start_date')
            ->get();

        $selectedId = $request->query('period');

        if ($selectedId !== null && ! ctype_digit((string) $selectedId)) {
            abort(404);
        }

        $period = $selectedId !== null
            ? $periods->firstWhere('id', (int) $selectedId)
            : $this->periods->current($user);

        // If no period is currently open, show the latest one.
        if (! $period && $selectedId === null) {
            $period = $periods->first();
        }

        if ($selectedId !== null && ! $period) {
            abort(404);
        }

        $summary = $period
            ? $this->budgets->summary($user, $period)
            : null;

        return view('budgets.index', compact(
            'periods',
            'period',
            'summary'
        ));
    }

    /**
     * Show the budget creation form.
     */
    public function create(Request $request)
    {
        $user = $request->user();

        $periods = $user->financialPeriods()
            ->orderByDesc('start_date')
            ->get();

        $categories = $user->categories()
            ->where('type', 'expense')
            ->active()
            ->orderBy('name')
            ->get();

        $selectedPeriodId = $request->query(
            'period',
            $this->periods->current($user)?->id
        );

        return view('budgets.create', compact(
            'periods',
            'categories',
            'selectedPeriodId'
        ));
    }

    /**
     * Save a new category budget.
     */
    public function store(BudgetRequest $request)
    {
        $budget = $this->budgets->save(
            $request->user(),
            $this->data($request),
            $request->boolean('confirm_closed')
        );

        return redirect()
            ->route('budgets.index', [
                'period' => $budget->financial_period_id,
            ])
            ->with('success', 'Budget allocation created.');
    }

    /**
     * Show the budget editing form.
     */
    public function edit(Request $request, Budget $budget)
    {
        Gate::authorize('update', $budget);

        $user = $request->user();

        $periods = $user->financialPeriods()
            ->orderByDesc('start_date')
            ->get();

        $categories = $user->categories()
            ->where('type', 'expense')
            ->where(function ($query) use ($budget) {
                $query->whereNull('archived_at')
                    ->orWhere('id', $budget->category_id);
            })
            ->orderBy('name')
            ->get();

        return view('budgets.edit', compact(
            'budget',
            'periods',
            'categories'
        ));
    }

    /**
     * Update an existing category budget.
     */
    public function update(
        BudgetRequest $request,
        Budget $budget
    ) {
        $updated = $this->budgets->save(
            $request->user(),
            $this->data($request),
            $request->boolean('confirm_closed'),
            $budget
        );

        return redirect()
            ->route('budgets.index', [
                'period' => $updated->financial_period_id,
            ])
            ->with('success', 'Budget allocation updated.');
    }

    /**
     * Delete a budget allocation.
     */
    public function destroy(Request $request, Budget $budget)
    {
        Gate::authorize('delete', $budget);

        $periodId = $budget->financial_period_id;

        $this->budgets->delete(
            $request->user(),
            $budget,
            $request->boolean('confirm_closed')
        );

        return redirect()
            ->route('budgets.index', [
                'period' => $periodId,
            ])
            ->with('success', 'Budget allocation deleted.');
    }

    /**
     * Convert the validated amount into integer minor units.
     */
    private function data(BudgetRequest $request): array
    {
        $validated = $request->validated();

        return [
            'financial_period_id' => $validated['financial_period_id'],
            'category_id' => $validated['category_id'],
            'amount' => Money::parse((string) $validated['amount']),
        ];
    }
}
