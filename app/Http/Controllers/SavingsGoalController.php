<?php

namespace App\Http\Controllers;

use App\Models\SavingsGoal;
use App\Services\SavingsGoalService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SavingsGoalController extends Controller
{
    public function __construct(
        private SavingsGoalService $savings
    ) {}

    /**
     * Display savings goals and an overview.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $status = $request->query('status', 'active');

        if (! in_array($status, [
            'all',
            'active',
            'completed',
            'archived',
        ], true)) {
            $status = 'active';
        }

        $goalsQuery = $user->savingsGoals();

        if ($status !== 'all') {
            $goalsQuery->where('status', $status);
        }

        $goals = $goalsQuery
            ->withSum([
                'transactions as contributions_total' => fn ($query) =>
                    $query->where('type', 'contribution'),
            ], 'amount')
            ->withSum([
                'transactions as withdrawals_total' => fn ($query) =>
                    $query->where('type', 'withdrawal'),
            ], 'amount')
            ->orderByRaw(
                "CASE status
                    WHEN 'active' THEN 0
                    WHEN 'completed' THEN 1
                    ELSE 2
                END"
            )
            ->orderBy('target_date')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $allGoals = $user->savingsGoals()
            ->withSum([
                'transactions as contributions_total' => fn ($query) =>
                    $query->where('type', 'contribution'),
            ], 'amount')
            ->withSum([
                'transactions as withdrawals_total' => fn ($query) =>
                    $query->where('type', 'withdrawal'),
            ], 'amount')
            ->get();

        $summary = [
            'active_count' => $allGoals
                ->where('status', 'active')
                ->count(),

            'completed_count' => $allGoals
                ->where('status', 'completed')
                ->count(),

            'total_target' => $allGoals
                ->where('status', 'active')
                ->sum('target_amount'),

            'total_saved' => $allGoals
                ->where('status', 'active')
                ->sum(function ($goal) {
                    return (int) $goal->starting_amount
                        + (int) ($goal->contributions_total ?? 0)
                        - (int) ($goal->withdrawals_total ?? 0);
                }),
        ];

        return view('savings.index', compact(
            'goals',
            'summary',
            'status'
        ));
    }

    /**
     * Show the new goal form.
     */
    public function create()
    {
        return view('savings.create');
    }

    /**
     * Store a savings goal.
     */
    public function store(Request $request)
    {
        $data = $request->validate(
            $this->goalRules()
        );

        $goal = $this->savings->create(
            $request->user(),
            [
                'name' => $data['name'],
                'target_amount' => $this->parsePositiveAmount(
                    $data['target_amount'],
                    'target_amount'
                ),
                'starting_amount' => $this->parseNonNegativeAmount(
                    $data['starting_amount'] ?? '0',
                    'starting_amount'
                ),
                'target_date' => $data['target_date'] ?? null,
                'description' => $data['description'] ?? null,
            ]
        );

        return redirect()
            ->route('savings.show', $goal)
            ->with('success', 'Savings goal created successfully.');
    }

    /**
     * Display goal details and transaction history.
     */
    public function show(Request $request, SavingsGoal $goal)
    {
        $this->authorizeGoal($request, $goal);

        $goal->loadSum([
            'transactions as contributions_total' => fn ($query) =>
                $query->where('type', 'contribution'),
        ], 'amount');

        $goal->loadSum([
            'transactions as withdrawals_total' => fn ($query) =>
                $query->where('type', 'withdrawal'),
        ], 'amount');

        $transactions = $goal->transactions()
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('savings.show', compact(
            'goal',
            'transactions'
        ));
    }

    /**
     * Show the goal editing form.
     */
    public function edit(Request $request, SavingsGoal $goal)
    {
        $this->authorizeGoal($request, $goal);

        abort_if(
            $goal->isArchived(),
            403,
            'Archived goals cannot be edited.'
        );

        return view('savings.edit', compact('goal'));
    }

    /**
     * Update goal details.
     */
    public function update(Request $request, SavingsGoal $goal)
    {
        $this->authorizeGoal($request, $goal);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'target_amount' => [
    'required',
    ...$this->amountRules(),
],


            'target_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $this->savings->update(
            $request->user(),
            $goal,
            [
                'name' => $data['name'],
                'target_amount' => $this->parsePositiveAmount(
                    $data['target_amount'],
                    'target_amount'
                ),
                'target_date' => $data['target_date'] ?? null,
                'description' => $data['description'] ?? null,
            ]
        );

        return redirect()
            ->route('savings.show', $goal)
            ->with('success', 'Savings goal updated successfully.');
    }

    /**
     * Record a contribution.
     */
    public function contribute(Request $request, SavingsGoal $goal)
    {
        $this->authorizeGoal($request, $goal);

        $data = $this->validateTransaction($request);

        $this->savings->contribute(
            $request->user(),
            $goal,
            $this->parsePositiveAmount(
                $data['amount'],
                'amount'
            ),
            $data['transaction_date'],
            $data['note'] ?? null
        );

        return redirect()
            ->route('savings.show', $goal)
            ->with('success', 'Savings contribution recorded.');
    }

    /**
     * Record a withdrawal.
     */
    public function withdraw(Request $request, SavingsGoal $goal)
    {
        $this->authorizeGoal($request, $goal);

        $data = $this->validateTransaction($request);

        $this->savings->withdraw(
            $request->user(),
            $goal,
            $this->parsePositiveAmount(
                $data['amount'],
                'amount'
            ),
            $data['transaction_date'],
            $data['note'] ?? null
        );

        return redirect()
            ->route('savings.show', $goal)
            ->with('success', 'Savings withdrawal recorded.');
    }

    /**
     * Mark a goal as completed.
     */
    public function complete(Request $request, SavingsGoal $goal)
    {
        $this->authorizeGoal($request, $goal);

        $this->savings->complete(
            $request->user(),
            $goal
        );

        return redirect()
            ->route('savings.show', $goal)
            ->with('success', 'Savings goal completed.');
    }

    /**
     * Reopen a completed goal.
     */
    public function reopen(Request $request, SavingsGoal $goal)
    {
        $this->authorizeGoal($request, $goal);

        $this->savings->reopen(
            $request->user(),
            $goal
        );

        return redirect()
            ->route('savings.show', $goal)
            ->with('success', 'Savings goal reopened.');
    }

    /**
     * Archive a goal without deleting its history.
     */
    public function archive(Request $request, SavingsGoal $goal)
    {
        $this->authorizeGoal($request, $goal);

        $this->savings->archive(
            $request->user(),
            $goal
        );

        return redirect()
            ->route('savings.index')
            ->with('success', 'Savings goal archived.');
    }

    /**
     * Validate savings goal fields.
     */
    private function goalRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],
'target_amount' => [
    'required',
    ...$this->amountRules(),
],

'starting_amount' => [
                'nullable',
                ...$this->amountRules(),
            ],
            'target_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * Accept ordinary currency amounts such as:
     * 500, 500.50 and 1,500.50.
     */
    private function amountRules(): array
    {
        return [
            'string',
            'max:20',
            'regex:/^(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d{1,2})?$/',
        ];
    }

    /**
     * Validate contribution and withdrawal fields.
     */
    private function validateTransaction(Request $request): array
    {
        return $request->validate([
            'amount' => [
                'required',
                ...$this->amountRules(),
            ],
            'transaction_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'note' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);
    }

    private function parsePositiveAmount(
        string $value,
        string $field
    ): int {
        $amount = $this->parseNonNegativeAmount(
            $value,
            $field
        );

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                $field => 'The amount must be greater than zero.',
            ]);
        }

        return $amount;
    }

    private function parseNonNegativeAmount(
        string $value,
        string $field
    ): int {
        // Prevent overflow before converting to integer minor units.
        $digits = str_replace(',', '', $value);
        $parts = explode('.', $digits, 2);

        $whole = ltrim($parts[0], '0');
        $whole = $whole === '' ? '0' : $whole;

        $cents = str_pad($parts[1] ?? '', 2, '0');

        $minorUnits = $whole . $cents;
        $minorUnits = ltrim($minorUnits, '0');
        $minorUnits = $minorUnits === '' ? '0' : $minorUnits;

        $maximum = (string) PHP_INT_MAX;

        if (
            strlen($minorUnits) > strlen($maximum)
            || (
                strlen($minorUnits) === strlen($maximum)
                && strcmp($minorUnits, $maximum) > 0
            )
        ) {
            throw ValidationException::withMessages([
                $field => 'The amount is too large.',
            ]);
        }

        return Money::parse($value);
    }

    /**
     * Block access to another user's savings goal.
     */
    private function authorizeGoal(
        Request $request,
        SavingsGoal $goal
    ): void {
        abort_unless(
            $goal->user_id === $request->user()->id,
            403
        );
    }
}
