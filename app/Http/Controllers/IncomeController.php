<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeRequest;
use App\Models\Income;
use App\Services\IncomeService;
use App\Services\PeriodService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IncomeController extends Controller
{
    public function __construct(private IncomeService $incomes, private PeriodService $periods) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $sel = $request->query('period', $this->periods->current($user)?->id ?? 'all');

        $query = $user->incomes()
            ->when($sel === 'none', fn ($q) => $q->whereNull('financial_period_id'))
            ->when(is_numeric($sel), fn ($q) => $q->where('financial_period_id', $sel));

        return view('incomes.index', [
            'sel' => (string) $sel,
            'incomes' => (clone $query)->with(['category', 'period'])->orderByDesc('income_date')->orderByDesc('id')->paginate(15)->withQueryString(),
            'total' => (int) (clone $query)->sum('amount'),
            'periods' => $user->financialPeriods()->latest('start_date')->get(),
            'unassigned' => $user->incomes()->whereNull('financial_period_id')->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('incomes.create', ['categories' => $this->categories($request)]);
    }

    public function store(IncomeRequest $request)
    {
        $income = $this->incomes->save($request->user(), $this->data($request), $request->boolean('confirm_closed'));

        return $this->back($income, 'Income recorded.');
    }

    public function edit(Request $request, Income $income)
    {
        Gate::authorize('update', $income);

        return view('incomes.edit', ['income' => $income->load('period'), 'categories' => $this->categories($request, $income)]);
    }

    public function update(IncomeRequest $request, Income $income)
    {
        $this->incomes->save($request->user(), $this->data($request), $request->boolean('confirm_closed'), $income);

        return $this->back($income->fresh(), 'Income updated.');
    }

    public function destroy(Request $request, Income $income)
    {
        Gate::authorize('delete', $income);
        $this->incomes->delete($request->user(), $income, $request->boolean('confirm_closed'));

        return back()->with('success', 'Income deleted.');
    }

    private function data(IncomeRequest $request): array
    {
        return [...$request->validated(), 'amount' => Money::parse($request->validated('amount'))];
    }

    private function categories(Request $request, ?Income $income = null)
    {
        return $request->user()->categories()->where('type', 'income')
            ->where(fn ($q) => $q->whereNull('archived_at')->orWhere('id', $income?->category_id))
            ->orderBy('name')->get();
    }

    private function back(Income $income, string $message)
    {
        return redirect()->route('incomes.index', ['period' => $income->financial_period_id ?? 'none'])->with('success', $message);
    }
}