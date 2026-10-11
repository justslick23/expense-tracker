<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Services\PeriodService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    public function __construct(
        private ExpenseService $expenses,
        private PeriodService $periods
    ) {}

    /**
     * Display expenses with filtering.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $selectedPeriod = $request->query(
            'period',
            $this->periods->current($user)?->id ?? 'all'
        );

        $selectedCategory = $request->query('category');
        $search = trim((string) $request->query('search', ''));

        $query = $user->expenses()
            ->when(
                $selectedPeriod === 'none',
                fn ($q) => $q->whereNull('financial_period_id')
            )
            ->when(
                is_numeric($selectedPeriod),
                fn ($q) => $q->where(
                    'financial_period_id',
                    $selectedPeriod
                )
            )
            ->when(
                $selectedCategory,
                fn ($q) => $q->where(
                    'category_id',
                    $selectedCategory
                )
            )
            ->when(
                $search !== '',
                fn ($q) => $q->where(function ($sub) use ($search) {
                    $sub->where('description', 'like', "%{$search}%")
                        ->orWhere('merchant', 'like', "%{$search}%");
                })
            );

        return view('expenses.index', [
            'expenses' => (clone $query)
                ->with(['category', 'period'])
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),

            'total' => (int) (clone $query)->sum('amount'),

            'periods' => $user->financialPeriods()
                ->latest('start_date')
                ->get(),

            'categories' => $user->categories()
                ->where('type', 'expense')
                ->orderBy('name')
                ->get(),

            'unassigned' => $user->expenses()
                ->whereNull('financial_period_id')
                ->count(),

            'selectedPeriod' => (string) $selectedPeriod,
            'selectedCategory' => $selectedCategory,
            'search' => $search,
        ]);
    }

    /**
     * Show the expense creation form.
     */
    public function create(Request $request)
    {
        return view('expenses.create', [
            'categories' => $this->categories($request),

            'pastDescriptions' => $request->user()
                ->expenses()
                ->whereNotNull('description')
                ->where('description', '!=', '')
                ->select('description')
                ->distinct()
                ->orderBy('description')
                ->limit(100)
                ->pluck('description'),

            'pastMerchants' => $request->user()
                ->expenses()
                ->whereNotNull('merchant')
                ->where('merchant', '!=', '')
                ->select('merchant')
                ->distinct()
                ->orderBy('merchant')
                ->limit(100)
                ->pluck('merchant'),
        ]);
    }

    /**
     * Store a new expense.
     */
    public function store(ExpenseRequest $request)
    {
        $expense = $this->expenses->save(
            $request->user(),
            $this->data($request),
            $request->boolean('confirm_closed')
        );

        return $this->back($expense, 'Expense recorded.');
    }

    /**
     * Show the expense editing form.
     */
    public function edit(Request $request, Expense $expense)
    {
        Gate::authorize('update', $expense);

        return view('expenses.edit', [
            'expense' => $expense->load('period'),
            'categories' => $this->categories($request, $expense),

            'pastDescriptions' => collect(),
            'pastMerchants' => collect(),
        ]);
    }

    /**
     * Update an expense.
     */
    public function update(
        ExpenseRequest $request,
        Expense $expense
    ) {
        $this->expenses->save(
            $request->user(),
            $this->data($request),
            $request->boolean('confirm_closed'),
            $expense
        );

        return $this->back(
            $expense->fresh(),
            'Expense updated.'
        );
    }

    /**
     * Soft-delete an expense.
     */
    public function destroy(
        Request $request,
        Expense $expense
    ) {
        Gate::authorize('delete', $expense);

        $this->expenses->delete(
            $request->user(),
            $expense,
            $request->boolean('confirm_closed')
        );

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense deleted.');
    }

    /**
     * Convert validated form input into service data.
     */
    private function data(ExpenseRequest $request): array
    {
        return [
            ...$request->validated(),
            'amount' => Money::parse(
                $request->validated('amount')
            ),
        ];
    }

    /**
     * Get the user's valid expense categories.
     */
    private function categories(
        Request $request,
        ?Expense $expense = null
    ) {
        return $request->user()
            ->categories()
            ->where('type', 'expense')
            ->where(function ($query) use ($expense) {
                $query->whereNull('archived_at');

                if ($expense) {
                    $query->orWhere(
                        'id',
                        $expense->category_id
                    );
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Redirect to the expense's assigned period.
     */
    private function back(
        Expense $expense,
        string $message
    ) {
        return redirect()
            ->route('expenses.index', [
                'period' => $expense->financial_period_id
                    ?? 'none',
            ])
            ->with('success', $message);
    }
}
