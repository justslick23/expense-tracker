<?php

namespace App\Http\Controllers;

use App\Models\RecurringExpense;
use App\Models\RecurringExpenseOccurrence;
use App\Services\RecurringExpensePaymentService;
use App\Services\RecurringExpenseScheduleService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecurringExpenseController extends Controller
{
    public function __construct(
        private RecurringExpenseScheduleService $scheduler,
        private RecurringExpensePaymentService $payments
    ) {}

    /**
     * List recurring templates and scheduled payments.
     */
   
/**
 * Recurring expenses overview with payment filters,
 * schedule search and financial summaries.
 */
public function index(Request $request)
{
    $user = $request->user();

    $today = CarbonImmutable::today();
    $todayString = $today->toDateString();
    $next30Days = $today->addDays(30)->toDateString();

    $monthStart = $today->startOfMonth()->toDateString();
    $monthEnd = $today->endOfMonth()->toDateString();

    $status = $request->query('status', 'all');

    $allowedStatuses = [
        'all',
        'pending',
        'overdue',
        'upcoming',
        'paid',
        'skipped',
    ];

    if (! in_array($status, $allowedStatuses, true)) {
        $status = 'all';
    }

    $search = trim((string) $request->query('search', ''));
    $search = mb_substr($search, 0, 100);

    // Recurring templates
    $templatesQuery = $user->recurringExpenses()
        ->with('category');

    if ($search !== '') {
        $templatesQuery->where('name', 'like', '%' . $search . '%');
    }

    $templates = $templatesQuery
        ->orderByDesc('is_active')
        ->orderBy('name')
        ->paginate(10, ['*'], 'templates_page')
        ->withQueryString();

    // Payment statistics
    $occurrencesQuery = $user->recurringExpenseOccurrences();

    $pendingQuery = (clone $occurrencesQuery)
        ->where('status', 'pending');

    $overdueQuery = (clone $occurrencesQuery)
        ->where('status', 'pending')
        ->whereDate('due_date', '<', $todayString);

    $upcomingQuery = (clone $occurrencesQuery)
        ->where('status', 'pending')
        ->whereDate('due_date', '>=', $todayString);

    $stats = [
        'active' => $user->recurringExpenses()
            ->active()
            ->count(),

        'pending' => (clone $pendingQuery)->count(),

        'overdue' => (clone $overdueQuery)->count(),

        'upcoming' => (clone $upcomingQuery)->count(),
    ];

    // Financial summaries use integer minor units.
    $financials = [
        'monthly_commitments' => (int) $user
            ->recurringExpenseOccurrences()
            ->whereBetween('due_date', [$monthStart, $monthEnd])
            ->where('status', '!=', 'skipped')
            ->sum('planned_amount'),

        'next_30_days' => (int) $user
            ->recurringExpenseOccurrences()
            ->where('status', 'pending')
            ->whereDate('due_date', '>=', $todayString)
            ->whereDate('due_date', '<=', $next30Days)
            ->sum('planned_amount'),

        'overdue_total' => (int) (clone $overdueQuery)
            ->sum('planned_amount'),
    ];

    // Filter scheduled payments
    $paymentsQuery = $user->recurringExpenseOccurrences()
        ->with([
            'recurringExpense.category',
            'expense',
        ]);

    switch ($status) {
        case 'pending':
            $paymentsQuery->where('status', 'pending');
            break;

        case 'overdue':
            $paymentsQuery
                ->where('status', 'pending')
                ->whereDate('due_date', '<', $todayString);
            break;

        case 'upcoming':
            $paymentsQuery
                ->where('status', 'pending')
                ->whereDate('due_date', '>=', $todayString);
            break;

        case 'paid':
            $paymentsQuery->where('status', 'paid');
            break;

        case 'skipped':
            $paymentsQuery->where('status', 'skipped');
            break;
    }

    // Show pending payments first, followed by
    // paid/skipped history. Earliest due date first.
    $occurrences = $paymentsQuery
        ->orderByRaw(
            "CASE
                WHEN status = 'pending' THEN 0
                WHEN status = 'paid' THEN 1
                ELSE 2
            END"
        )
        ->orderBy('due_date')
        ->orderBy('id')
        ->paginate(15, ['*'], 'payments_page')
        ->withQueryString();

    return view('recurring.index', compact(
        'templates',
        'occurrences',
        'stats',
        'financials',
        'status',
        'search'
    ));
}


    /**
     * New recurring expense form.
     */
    public function create(Request $request)
    {
        $categories = $request->user()
            ->categories()
            ->where('type', 'expense')
            ->active()
            ->orderBy('name')
            ->get();

        return view('recurring.create', compact('categories'));
    }

    /**
     * Create a recurring expense template.
     */
    public function store(Request $request)
    {
        $data = $this->validateTemplate($request);

        if (Money::parse($data['amount']) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The amount must be greater than zero.',
            ]);
        }

        $startDate = CarbonImmutable::parse(
            $data['start_date']
        );

        $endDate = ! empty($data['end_date'])
            ? CarbonImmutable::parse($data['end_date'])
            : null;

        $recurring = $request->user()
            ->recurringExpenses()
            ->create([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'amount' => Money::parse($data['amount']),
                'frequency' => $data['frequency'],
                'start_date' => $startDate->toDateString(),
                'next_due_date' => $startDate->toDateString(),
                'end_date' => $endDate?->toDateString(),
                'merchant' => $data['merchant'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

        return redirect()
            ->route('recurring.index')
            ->with(
                'success',
                'Recurring expense created successfully.'
            );
    }

    /**
     * Edit a recurring template.
     */
    public function edit(
        Request $request,
        RecurringExpense $recurring
    ) {
        $this->authorizeOwner($request, $recurring);

        $categories = $request->user()
            ->categories()
            ->where('type', 'expense')
            ->where(function ($query) use ($recurring) {
                $query->whereNull('archived_at')
                    ->orWhere('id', $recurring->category_id);
            })
            ->orderBy('name')
            ->get();

        return view('recurring.edit', compact(
            'recurring',
            'categories'
        ));
    }

    /**
     * Update recurring template details.
     *
     * Existing schedules retain their frequency and
     * original start date once occurrences exist.
     */
    public function update(
        Request $request,
        RecurringExpense $recurring
    ) {
        $this->authorizeOwner($request, $recurring);

        $data = $this->validateTemplate(
            $request,
            $recurring
        );

        DB::transaction(function () use (
            $request,
            $recurring,
            $data
        ) {
            $template = $request->user()
                ->recurringExpenses()
                ->lockForUpdate()
                ->findOrFail($recurring->id);

            $hasHistory = $template->occurrences()
                ->exists();

            if ($hasHistory) {
                if (
                    $data['frequency'] !== $template->frequency
                    || $data['start_date']
                        !== $template->start_date->toDateString()
                ) {
                    throw ValidationException::withMessages([
                        'frequency' =>
                            'The frequency and start date cannot change after scheduled payments have been generated.',
                    ]);
                }
            } else {
                $template->next_due_date = $data['start_date'];
            }

            $template->fill([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'amount' => Money::parse($data['amount']),
                'frequency' => $data['frequency'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'merchant' => $data['merchant'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]);

            // Never move the template's end date before
            // an occurrence already recorded.
            if ($hasHistory && $template->end_date) {
                $latest = $template->occurrences()
                    ->orderByDesc('due_date')
                    ->first();

                if (
                    $latest
                    && $latest->due_date->gt(
                        $template->end_date
                    )
                ) {
                    throw ValidationException::withMessages([
                        'end_date' =>
                            'The end date cannot precede an existing scheduled payment.',
                    ]);
                }
            }

            $template->save();
        });

        return redirect()
            ->route('recurring.index')
            ->with('success', 'Recurring expense updated.');
    }

    /**
     * Archive a recurring template.
     *
     * Already generated occurrences are preserved.
     */
    public function destroy(
        Request $request,
        RecurringExpense $recurring
    ) {
        $this->authorizeOwner($request, $recurring);

        DB::transaction(function () use ($recurring) {
            $recurring->update(['is_active' => false]);
            $recurring->delete();
        });

        return redirect()
            ->route('recurring.index')
            ->with('success', 'Recurring expense archived.');
    }

    /**
     * Generate pending occurrences up to the horizon.
     *
     * Default: today plus 30 days.
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'through_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ]);

        $horizon = isset($data['through_date'])
            ? CarbonImmutable::parse($data['through_date'])
            : CarbonImmutable::today()->addDays(30);

        $user = $request->user();
        $generated = 0;

        $user->recurringExpenses()
            ->active()
            ->orderBy('id')
            ->chunkById(100, function ($templates) use (
                $user,
                $horizon,
                &$generated
            ) {
                foreach ($templates as $template) {
                    $generated += $this->scheduler->generate(
                        $user,
                        $template,
                        $horizon
                    );
                }
            });

        return redirect()
            ->route('recurring.index')
            ->with(
                'success',
                "{$generated} scheduled payment(s) generated."
            );
    }

    /**
     * Confirm an actual payment.
     */
    public function pay(
        Request $request,
        RecurringExpenseOccurrence $occurrence
    ) {
        $this->authorizeOccurrence($request, $occurrence);

        $data = $request->validate([
            'amount' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d{1,3}(,?\d{3})*(\.\d{1,2})?$/',
            ],
            'paid_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'confirm_closed' => [
                'nullable',
                'boolean',
            ],
        ]);

        $amount = Money::parse($data['amount']);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The amount must be greater than zero.',
            ]);
        }

        $this->payments->pay(
            $request->user(),
            $occurrence,
            $amount,
            $data['paid_date'],
            $request->boolean('confirm_closed')
        );

        return redirect()
            ->route('recurring.index')
            ->with('success', 'Payment recorded successfully.');
    }

    /**
     * Skip an unpaid occurrence.
     */
    public function skip(
        Request $request,
        RecurringExpenseOccurrence $occurrence
    ) {
        $this->authorizeOccurrence($request, $occurrence);

        $this->payments->skip(
            $request->user(),
            $occurrence
        );

        return redirect()
            ->route('recurring.index')
            ->with('success', 'Scheduled payment skipped.');
    }

    /**
     * Reopen a skipped occurrence.
     */
    public function reopen(
        Request $request,
        RecurringExpenseOccurrence $occurrence
    ) {
        $this->authorizeOccurrence($request, $occurrence);

        $this->payments->reopen(
            $request->user(),
            $occurrence
        );

        return redirect()
            ->route('recurring.index')
            ->with('success', 'Scheduled payment reopened.');
    }

    /**
     * Validate recurring template fields.
     */
    private function validateTemplate(
        Request $request,
        ?RecurringExpense $recurring = null
    ): array {
        $user = $request->user();

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(function ($query) use (
                        $user,
                        $recurring
                    ) {
                        $query->where('user_id', $user->id)
                            ->where('type', 'expense')
                            ->where(function ($q) use ($recurring) {
                                $q->whereNull('archived_at');

                                if ($recurring) {
                                    $q->orWhere(
                                        'id',
                                        $recurring->category_id
                                    );
                                }
                            });
                    }),
            ],
            'amount' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d{1,3}(,?\d{3})*(\.\d{1,2})?$/',
            ],
            'frequency' => [
                'required',
                Rule::in([
                    'daily',
                    'weekly',
                    'monthly',
                    'quarterly',
                    'yearly',
                ]),
            ],
            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'end_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            'merchant' => [
                'nullable',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);
    }

    private function authorizeOwner(
        Request $request,
        RecurringExpense $recurring
    ): void {
        abort_unless(
            $recurring->user_id === $request->user()->id,
            403
        );
    }

    private function authorizeOccurrence(
        Request $request,
        RecurringExpenseOccurrence $occurrence
    ): void {
        abort_unless(
            $occurrence->user_id === $request->user()->id,
            403
        );
    }
}
