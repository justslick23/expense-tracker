<?php

namespace Tests\Feature;

use App\Models\FinancialPeriod;
use App\Models\RecurringExpenseOccurrence;
use App\Models\User;
use App\Services\RecurringExpensePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RecurringExpensePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private FinancialPeriod $period;
    private RecurringExpenseOccurrence $occurrence;
    private RecurringExpensePaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-08 12:00:00');

        $this->payments = app(
            RecurringExpensePaymentService::class
        );

        $this->user = User::factory()->create();

        $this->period = $this->user->financialPeriods()->create([
            'name' => 'October 2026',
            'start_date' => '2026-10-01',
        ]);

        $category = $this->user->categories()->create([
            'name' => 'Subscriptions',
            'type' => 'expense',
        ]);

        $template = $this->user->recurringExpenses()->create([
            'category_id' => $category->id,
            'name' => 'Internet Subscription',
            'amount' => 65000,
            'frequency' => 'monthly',
            'start_date' => '2026-10-01',
            'next_due_date' => '2026-11-01',
            'merchant' => 'Internet Provider',
            'description' => 'Monthly internet',
            'is_active' => true,
        ]);

        $this->occurrence = $template->occurrences()->create([
            'user_id' => $this->user->id,
            'due_date' => '2026-10-01',
            'planned_amount' => 65000,
            'status' => 'pending',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function pay(
        int $amount = 65000,
        string $date = '2026-10-05',
        bool $confirmClosed = false
    ): RecurringExpenseOccurrence {
        return $this->payments->pay(
            $this->user,
            $this->occurrence,
            $amount,
            $date,
            $confirmClosed
        );
    }

    private function closeCurrentPeriod(): void
    {
        $this->period->update([
            'end_date' => '2026-10-07',
            'is_open' => false,
            'status' => 'closed',
        ]);

        $this->period->refresh();
    }

    public function test_confirming_payment_creates_an_expense(): void
    {
        $payment = $this->pay();

        $this->assertSame('paid', $payment->status);

        $this->assertNotNull($payment->expense_id);

        $expense = $this->user->expenses()->firstOrFail();

        $this->assertSame(65000, $expense->amount);

        $this->assertSame(
            $this->period->id,
            $expense->financial_period_id
        );

        $this->assertSame(
            '2026-10-05',
            $expense->expense_date->toDateString()
        );

        $this->assertSame(
            $expense->id,
            $payment->expense_id
        );

        $this->assertSame(
            '2026-10-05',
            $payment->paid_date->toDateString()
        );
    }

    public function test_actual_amount_can_differ_from_planned_amount(): void
    {
        $payment = $this->pay(70000);

        $this->assertSame(
            65000,
            $payment->planned_amount
        );

        $this->assertSame(
            70000,
            $payment->expense->amount
        );

        $this->assertSame(
            5000,
            $payment->expense->amount - $payment->planned_amount
        );
    }

    public function test_confirming_payment_preserves_template_details(): void
    {
        $payment = $this->pay();

        $expense = $payment->expense;

        $this->assertSame(
            'Internet Provider',
            $expense->merchant
        );

        $this->assertSame(
            'Monthly internet',
            $expense->description
        );

        $this->assertSame(
            $payment->recurringExpense->category_id,
            $expense->category_id
        );
    }

    public function test_payment_date_determines_financial_period(): void
    {
        $this->period->update([
            'start_date' => '2026-10-06',
        ]);

        $payment = $this->pay(65000, '2026-10-07');

        $this->assertSame(
            $this->period->id,
            $payment->expense->financial_period_id
        );

        $this->assertSame(
            '2026-10-07',
            $payment->expense->expense_date->toDateString()
        );
    }

    public function test_duplicate_payment_is_rejected(): void
    {
        $this->pay();

        $this->expectException(ValidationException::class);

        try {
            $this->pay();
        } finally {
            $this->assertSame(
                1,
                $this->user->expenses()->count()
            );
        }
    }

    public function test_invalid_amount_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->pay(0);
    }

    public function test_future_payment_date_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->pay(65000, '2026-10-20');
    }

    public function test_payment_without_matching_period_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->pay(65000, '2026-09-15');
        } finally {
            $this->assertSame(
                0,
                $this->user->expenses()->count()
            );
        }
    }

    public function test_closed_period_requires_confirmation(): void
    {
        $this->closeCurrentPeriod();

        try {
            $this->pay();

            $this->fail(
                'A closed period should require confirmation.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'confirm_closed',
                $exception->errors()
            );
        }

        $this->assertSame(
            0,
            $this->user->expenses()->count()
        );

        $payment = $this->pay(
            65000,
            '2026-10-05',
            true
        );

        $this->assertSame('paid', $payment->status);

        $this->assertSame(
            $this->period->id,
            $payment->expense->financial_period_id
        );
    }

    public function test_skipping_payment_does_not_create_expense(): void
    {
        $payment = $this->payments->skip(
            $this->user,
            $this->occurrence
        );

        $this->assertSame(
            'skipped',
            $payment->status
        );

        $this->assertNull($payment->expense_id);

        $this->assertSame(
            0,
            $this->user->expenses()->count()
        );
    }

    public function test_skipped_payment_can_be_reopened(): void
    {
        $this->payments->skip(
            $this->user,
            $this->occurrence
        );

        $payment = $this->payments->reopen(
            $this->user,
            $this->occurrence
        );

        $this->assertSame(
            'pending',
            $payment->status
        );

        $this->assertNull($payment->expense_id);
        $this->assertNull($payment->paid_date);

        $this->assertSame(
            0,
            $this->user->expenses()->count()
        );
    }

    public function test_skipped_payment_cannot_be_paid_until_reopened(): void
    {
        $this->payments->skip(
            $this->user,
            $this->occurrence
        );

        $this->expectException(ValidationException::class);

        $this->pay();
    }

    public function test_paid_payment_cannot_be_skipped(): void
    {
        $this->pay();

        $this->expectException(ValidationException::class);

        $this->payments->skip(
            $this->user,
            $this->occurrence
        );
    }

    public function test_paid_payment_cannot_be_reopened(): void
    {
        $this->pay();

        $this->expectException(ValidationException::class);

        $this->payments->reopen(
            $this->user,
            $this->occurrence
        );
    }

    public function test_other_user_cannot_confirm_payment(): void
    {
        $other = User::factory()->create();

        $this->expectException(HttpException::class);

        try {
            $this->payments->pay(
                $other,
                $this->occurrence,
                65000,
                '2026-10-05'
            );
        } finally {
            $this->assertSame(
                0,
                $this->user->expenses()->count()
            );
        }
    }

    public function test_other_user_cannot_skip_payment(): void
    {
        $other = User::factory()->create();

        $this->expectException(HttpException::class);

        $this->payments->skip(
            $other,
            $this->occurrence
        );
    }

    public function test_other_user_cannot_reopen_payment(): void
    {
        $this->payments->skip(
            $this->user,
            $this->occurrence
        );

        $other = User::factory()->create();

        $this->expectException(HttpException::class);

        $this->payments->reopen(
            $other,
            $this->occurrence
        );
    }

    public function test_payment_does_not_change_planned_amount(): void
    {
        $this->pay(80000);

        $this->assertSame(
            65000,
            $this->occurrence->fresh()->planned_amount
        );

        $this->assertSame(
            65000,
            $this->occurrence
                ->recurringExpense
                ->amount
        );
    }
}
