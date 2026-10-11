<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Services\PeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpenseTest extends TestCase
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
        Category $category,
        array $overrides = []
    ): array {
        return array_merge([
            'category_id' => $category->id,
            'amount' => '1,250.50',
            'expense_date' => '2026-10-08',
            'description' => 'Weekly groceries',
            'merchant' => 'Pick n Pay',
        ], $overrides);
    }

    public function test_guests_cannot_access_expenses(): void
    {
        $this->get('/expenses')
            ->assertRedirect('/login');
    }

    public function test_expense_is_stored_in_minor_units(): void
    {
        [$user, $period, $category] = $this->world();

        $this->actingAs($user)
            ->post('/expenses', $this->payload($category))
            ->assertSessionHasNoErrors();

        $expense = $user->expenses()->firstOrFail();

        $this->assertSame(125050, $expense->amount);
        $this->assertSame($period->id, $expense->financial_period_id);
        $this->assertSame('Pick n Pay', $expense->merchant);
    }

    public function test_expense_outside_period_is_unassigned(): void
    {
        [$user, , $category] = $this->world();

        $this->actingAs($user)
            ->post('/expenses', $this->payload($category, [
                'expense_date' => '2026-08-01',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(
            $user->expenses()->firstOrFail()->financial_period_id
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
            ->post('/expenses', $this->payload($category))
            ->assertSessionHasErrors('confirm_closed');

        $this->assertSame(0, $user->expenses()->count());

        $this->actingAs($user)
            ->post('/expenses', $this->payload($category, [
                'confirm_closed' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $user->expenses()->count());
    }

    public function test_editing_date_reassigns_financial_period(): void
    {
        [$user, $period, $category] = $this->world();

        $previous = $user->financialPeriods()->create([
            'name' => 'September 2026',
            'start_date' => '2026-08-27',
            'end_date' => '2026-09-26',
            'status' => 'closed',
        ]);

        $this->actingAs($user)
            ->post('/expenses', $this->payload($category, [
                'expense_date' => '2026-09-01',
                'confirm_closed' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $expense = $user->expenses()->firstOrFail();

        $this->assertSame(
            $previous->id,
            $expense->financial_period_id
        );

        $this->actingAs($user)
            ->put(
                "/expenses/{$expense->id}",
                $this->payload($category, [
                    'expense_date' => '2026-10-08',
                    'confirm_closed' => 1,
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $period->id,
            $expense->fresh()->financial_period_id
        );
    }

    public function test_category_must_belong_to_user_and_be_expense_type(): void
    {
        [$user, , $category] = $this->world();

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
            ->post('/expenses', $this->payload($category, [
                'category_id' => $incomeCategory->id,
            ]))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($user)
            ->post('/expenses', $this->payload($category, [
                'category_id' => $foreignCategory->id,
            ]))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_user_cannot_modify_another_users_expense(): void
    {
        [$owner, $period, $category] = $this->world();

        $expense = $owner->expenses()->create([
            'financial_period_id' => $period->id,
            'category_id' => $category->id,
            'amount' => 10000,
            'expense_date' => '2026-10-08',
            'description' => 'Private expense',
        ]);

        $other = User::factory()->create();

        $this->actingAs($other)
            ->get("/expenses/{$expense->id}/edit")
            ->assertForbidden();

        $this->actingAs($other)
            ->put(
                "/expenses/{$expense->id}",
                $this->payload($category)
            )
            ->assertForbidden();

        $this->actingAs($other)
            ->delete("/expenses/{$expense->id}")
            ->assertForbidden();

        $this->assertSame(10000, $expense->fresh()->amount);
    }

    public function test_deleting_expense_uses_soft_deletes(): void
    {
        [$user, $period, $category] = $this->world();

        $expense = $user->expenses()->create([
            'financial_period_id' => $period->id,
            'category_id' => $category->id,
            'amount' => 5000,
            'expense_date' => '2026-10-08',
            'description' => 'Test expense',
        ]);

        $this->actingAs($user)
            ->delete("/expenses/{$expense->id}")
            ->assertRedirect();

        $this->assertSoftDeleted($expense);

        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        [$user, , $category] = $this->world();

        foreach (['0', '-10', 'abc', '10.555'] as $amount) {
            $this->actingAs($user)
                ->post('/expenses', $this->payload($category, [
                    'amount' => $amount,
                ]))
                ->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_filtering_by_category_and_period(): void
    {
        [$user, $period, $category] = $this->world();

        $transport = $user->categories()->create([
            'name' => 'Transport',
            'type' => 'expense',
        ]);

        $user->expenses()->create([
            'financial_period_id' => $period->id,
            'category_id' => $category->id,
            'amount' => 10000,
            'expense_date' => '2026-10-08',
            'description' => 'Zzyzx groceries',
        ]);

        $user->expenses()->create([
            'financial_period_id' => $period->id,
            'category_id' => $transport->id,
            'amount' => 5000,
            'expense_date' => '2026-10-09',
            'description' => 'Zzyzx fuel',
        ]);

        $this->actingAs($user)
            ->get("/expenses?period={$period->id}&category={$category->id}")
            ->assertOk()
            ->assertSee('Zzyzx groceries')
            ->assertDontSee('Zzyzx fuel');
    }

    public function test_search_by_merchant(): void
    {
        [$user, $period, $category] = $this->world();

        $user->expenses()->create([
            'financial_period_id' => $period->id,
            'category_id' => $category->id,
            'amount' => 15000,
            'expense_date' => '2026-10-08',
            'description' => 'Food shopping',
            'merchant' => 'Zzyzx Supermarket',
        ]);

        $this->actingAs($user)
            ->get('/expenses?search=Zzyzx')
            ->assertOk()
            ->assertSee('Zzyzx Supermarket');
    }
}
