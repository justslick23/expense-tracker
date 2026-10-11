<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\PeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $user = User::factory()->create();

        $period = $user->financialPeriods()->create([
            'name' => 'October 2026',
            'start_date' => '2026-09-27',
        ]);

        $category = $user->categories()->create([
            'name' => 'Groceries',
            'type' => 'expense',
        ]);

        return [$user, $period, $category];
    }

    private function payload(
        $period,
        Category $category,
        array $overrides = []
    ): array {
        return array_merge([
            'financial_period_id' => $period->id,
            'category_id' => $category->id,
            'amount' => '1,500.00',
        ], $overrides);
    }

    private function budget(
        User $user,
        $period,
        Category $category,
        int $amount = 150000
    ): Budget {
        return $user->budgets()->create([
            'financial_period_id' => $period->id,
            'category_id' => $category->id,
            'amount' => $amount,
        ]);
    }

    private function expense(
        User $user,
        $period,
        Category $category,
        int $amount
    ): void {
        $user->expenses()->create([
            'financial_period_id' => $period->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'expense_date' => '2026-10-08',
            'description' => 'Test expense',
        ]);
    }

    public function test_guests_cannot_access_budgets(): void
    {
        $this->get('/budgets')->assertRedirect('/login');
    }

    public function test_user_can_create_budget_in_minor_units(): void
    {
        [$user, $period, $category] = $this->world();

        $this->actingAs($user)
            ->post('/budgets', $this->payload(
                $period,
                $category
            ))
            ->assertSessionHasNoErrors();

        $budget = $user->budgets()->firstOrFail();

        $this->assertSame(150000, $budget->amount);
        $this->assertSame(
            $period->id,
            $budget->financial_period_id
        );
        $this->assertSame(
            $category->id,
            $budget->category_id
        );
    }

    public function test_duplicate_category_budget_is_rejected(): void
    {
        [$user, $period, $category] = $this->world();

        $this->budget($user, $period, $category);

        $this->actingAs($user)
            ->post('/budgets', $this->payload(
                $period,
                $category
            ))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(1, $user->budgets()->count());
    }

    public function test_same_category_can_have_budgets_in_different_periods(): void
    {
        [$user, $period, $category] = $this->world();

        $this->budget($user, $period, $category);

        app(PeriodService::class)->close(
            $period,
            Carbon::parse('2026-10-26')
        );

        $next = $user->financialPeriods()->create([
            'name' => 'November 2026',
            'start_date' => '2026-10-27',
        ]);

        $this->actingAs($user)
            ->post('/budgets', $this->payload(
                $next,
                $category
            ))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $user->budgets()->count());
    }

    public function test_only_own_financial_periods_are_allowed(): void
    {
        [$user, $period, $category] = $this->world();

        $foreign = User::factory()->create();

        $foreignPeriod = $foreign->financialPeriods()->create([
            'name' => 'Private Period',
            'start_date' => '2026-09-27',
        ]);

        $this->actingAs($user)
            ->post('/budgets', $this->payload(
                $foreignPeriod,
                $category
            ))
            ->assertSessionHasErrors('financial_period_id');

        $this->assertSame(0, $user->budgets()->count());
    }

    public function test_budget_category_must_be_an_own_expense_category(): void
    {
        [$user, $period, $category] = $this->world();

        $incomeCategory = $user->categories()->create([
            'name' => 'Salary',
            'type' => 'income',
        ]);

        $foreignCategory = User::factory()
            ->create()
            ->categories()
            ->create([
                'name' => 'Transport',
                'type' => 'expense',
            ]);

        $this->actingAs($user)
            ->post('/budgets', $this->payload(
                $period,
                $incomeCategory
            ))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($user)
            ->post('/budgets', $this->payload(
                $period,
                $foreignCategory
            ))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, $user->budgets()->count());
    }

    public function test_archived_category_cannot_receive_new_budget(): void
    {
        [$user, $period, $category] = $this->world();

        $category->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post('/budgets', $this->payload(
                $period,
                $category
            ))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, $user->budgets()->count());
    }

    public function test_user_can_update_existing_budget(): void
    {
        [$user, $period, $category] = $this->world();

        $budget = $this->budget(
            $user,
            $period,
            $category
        );

        $this->actingAs($user)
            ->put("/budgets/{$budget->id}", $this->payload(
                $period,
                $category,
                ['amount' => '2,000.50']
            ))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            200050,
            $budget->fresh()->amount
        );
    }

    public function test_closed_period_requires_confirmation(): void
    {
        [$user, $period, $category] = $this->world();

        app(PeriodService::class)->close(
            $period,
            Carbon::parse('2026-10-26')
        );

        $this->actingAs($user)
            ->post('/budgets', $this->payload(
                $period,
                $category
            ))
            ->assertSessionHasErrors('confirm_closed');

        $this->assertSame(0, $user->budgets()->count());

        $this->actingAs($user)
            ->post('/budgets', $this->payload(
                $period,
                $category,
                ['confirm_closed' => 1]
            ))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $user->budgets()->count());
    }

    public function test_user_cannot_modify_another_users_budget(): void
    {
        [$owner, $period, $category] = $this->world();

        $budget = $this->budget(
            $owner,
            $period,
            $category
        );

        $other = User::factory()->create();

        $this->actingAs($other)
            ->get("/budgets/{$budget->id}/edit")
            ->assertForbidden();

        $this->actingAs($other)
            ->put("/budgets/{$budget->id}", $this->payload(
                $period,
                $category
            ))
            ->assertForbidden();

        $this->actingAs($other)
            ->delete("/budgets/{$budget->id}")
            ->assertForbidden();

        $this->assertSame(
            150000,
            $budget->fresh()->amount
        );
    }

    public function test_invalid_budget_amounts_are_rejected(): void
    {
        [$user, $period, $category] = $this->world();

        foreach (['0', '-100', 'abc', '10.555'] as $amount) {
            $this->actingAs($user)
                ->post('/budgets', $this->payload(
                    $period,
                    $category,
                    ['amount' => $amount]
                ))
                ->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, $user->budgets()->count());
    }

    public function test_summary_calculates_spending_and_remaining(): void
    {
        [$user, $period, $category] = $this->world();

        $this->budget($user, $period, $category, 150000);
        $this->expense($user, $period, $category, 85000);

        $summary = app(BudgetService::class)
            ->summary($user, $period);

        $this->assertSame(150000, $summary['total_allocated']);
        $this->assertSame(85000, $summary['total_spent']);
        $this->assertSame(65000, $summary['remaining']);

        $row = $summary['categories']->first();

        $this->assertSame(85000, $row['spent']);
        $this->assertSame(65000, $row['remaining']);
        $this->assertEqualsWithDelta(
            56.7,
            $row['utilization'],
            0.1
        );
        $this->assertSame('healthy', $row['status']);
    }

    public function test_summary_includes_unbudgeted_spending(): void
    {
        [$user, $period, $category] = $this->world();

        $transport = $user->categories()->create([
            'name' => 'Transport',
            'type' => 'expense',
        ]);

        $this->budget($user, $period, $category, 150000);

        $this->expense($user, $period, $category, 50000);
        $this->expense($user, $period, $transport, 25000);

        $summary = app(BudgetService::class)
            ->summary($user, $period);

        $this->assertSame(75000, $summary['total_spent']);
        $this->assertSame(50000, $summary['budgeted_spending']);
        $this->assertSame(25000, $summary['unbudgeted_spending']);
        $this->assertSame(75000, $summary['remaining']);
    }

    public function test_summary_detects_overspending(): void
    {
        [$user, $period, $category] = $this->world();

        $this->budget($user, $period, $category, 100000);
        $this->expense($user, $period, $category, 125000);

        $summary = app(BudgetService::class)
            ->summary($user, $period);

        $row = $summary['categories']->first();

        $this->assertSame(-25000, $row['remaining']);
        $this->assertSame('over', $row['status']);
        $this->assertEqualsWithDelta(
            125.0,
            $row['utilization'],
            0.01
        );
    }

    public function test_deleting_budget_does_not_delete_expenses(): void
    {
        [$user, $period, $category] = $this->world();

        $budget = $this->budget(
            $user,
            $period,
            $category
        );

        $this->expense($user, $period, $category, 50000);

        $this->actingAs($user)
            ->delete("/budgets/{$budget->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('budgets', [
            'id' => $budget->id,
        ]);

        $this->assertSame(1, $user->expenses()->count());
    }
}
