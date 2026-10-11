<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RecurringExpenseOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecurringExpenseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function records(): array
    {
        $user = User::factory()->create();

        $period = $user->financialPeriods()->create([
            'name' => 'October 2026',
            'start_date' => '2026-10-01',
        ]);

        $category = $user->categories()->create([
            'name' => 'Subscriptions',
            'type' => 'expense',
        ]);

        $template = $user->recurringExpenses()->create([
            'name' => 'Internet Subscription',
            'category_id' => $category->id,
            'amount' => 65000,
            'frequency' => 'monthly',
            'start_date' => '2026-10-01',
            'next_due_date' => '2026-11-01',
            'is_active' => true,
        ]);

        $expense = $user->expenses()->create([
            'financial_period_id' => $period->id,
            'category_id' => $category->id,
            'amount' => 70000,
            'expense_date' => '2026-10-05',
            'description' => 'Internet payment',
        ]);

        $occurrence = $template->occurrences()->create([
            'user_id' => $user->id,
            'due_date' => '2026-10-01',
            'planned_amount' => 65000,
            'status' => 'paid',
            'expense_id' => $expense->id,
            'paid_date' => '2026-10-05',
        ]);

        return compact(
            'user',
            'period',
            'category',
            'template',
            'expense',
            'occurrence'
        );
    }

    public function test_archived_template_remains_accessible_from_payment(): void
    {
        $data = $this->records();

        $data['template']->delete();

        $occurrence = $data['occurrence']->fresh();

        $this->assertNotNull($occurrence->recurringExpense);

        $this->assertTrue(
            $occurrence->recurringExpense->trashed()
        );

        $this->assertSame(
            'Internet Subscription',
            $occurrence->recurringExpense->name
        );
    }

    public function test_changing_expense_date_updates_paid_date(): void
    {
        $data = $this->records();

        $data['expense']->update([
            'expense_date' => '2026-10-07',
        ]);

        $occurrence = $data['occurrence']->fresh();

        $this->assertSame(
            '2026-10-07',
            $occurrence->paid_date->toDateString()
        );
    }

    public function test_changing_expense_amount_preserves_planned_amount(): void
    {
        $data = $this->records();

        $data['expense']->update([
            'amount' => 80000,
        ]);

        $occurrence = $data['occurrence']->fresh();

        $this->assertSame(
            80000,
            $occurrence->expense->amount
        );

        $this->assertSame(
            65000,
            $occurrence->planned_amount
        );
    }

    public function test_linked_expense_cannot_be_deleted(): void
    {
        $data = $this->records();

        try {
            $data['expense']->delete();

            $this->fail(
                'A linked recurring expense should not be deleted.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'expense',
                $exception->errors()
            );
        }

        $this->assertNotNull(
            $data['expense']->fresh()
        );

        $this->assertSame(
            'paid',
            $data['occurrence']->fresh()->status
        );
    }

    public function test_normal_expenses_can_still_be_deleted(): void
    {
        $data = $this->records();

        $normal = $data['user']->expenses()->create([
            'financial_period_id' => $data['period']->id,
            'category_id' => $data['category']->id,
            'amount' => 25000,
            'expense_date' => '2026-10-06',
            'description' => 'Unrelated purchase',
        ]);

        $normal->delete();

        $this->assertSoftDeleted('expenses', [
            'id' => $normal->id,
        ]);

        $this->assertSame(
            'paid',
            $data['occurrence']->fresh()->status
        );
    }
}
