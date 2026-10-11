<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FinancialPeriod;
use App\Models\User;
use App\Services\PeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IncomeTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $u = User::factory()->create();

        return [
            $u,
            $u->financialPeriods()->create(['name' => 'October', 'start_date' => '2026-09-27']),
            $u->categories()->create(['name' => 'Salary', 'type' => 'income']),
        ];
    }

    private function payload(Category $cat, array $o = []): array
    {
        return array_merge(['category_id' => $cat->id, 'amount' => '1,500.50', 'income_date' => '2026-10-01', 'description' => 'Pay'], $o);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/incomes')->assertRedirect('/login');
    }

    public function test_income_is_stored_in_minor_units_and_assigned_to_the_open_period(): void
    {
        [$u, $period, $cat] = $this->world();

        $this->actingAs($u)->post('/incomes', $this->payload($cat))->assertRedirect();

        $i = $u->incomes()->first();
        $this->assertSame(150050, $i->amount);
        $this->assertSame($period->id, $i->financial_period_id);
    }

    public function test_date_outside_every_period_is_left_unassigned(): void
    {
        [$u, , $cat] = $this->world();

        $this->actingAs($u)->post('/incomes', $this->payload($cat, ['income_date' => '2026-08-01']));

        $this->assertNull($u->incomes()->first()->financial_period_id);
    }

    public function test_closed_period_requires_confirmation(): void
    {
        [$u, $period, $cat] = $this->world();
        app(PeriodService::class)->close($period, Carbon::parse('2026-10-26'));

        $this->actingAs($u)->post('/incomes', $this->payload($cat))->assertSessionHasErrors('confirm_closed');
        $this->assertSame(0, $u->incomes()->count());

        $this->actingAs($u)->post('/incomes', $this->payload($cat, ['confirm_closed' => 1]))->assertSessionHasNoErrors();
        $this->assertSame($period->id, $u->incomes()->first()->financial_period_id);
    }

    public function test_editing_the_date_reassigns_the_period(): void
    {
        [$u, , $cat] = $this->world();
        $closed = $u->financialPeriods()->create(['name' => 'September', 'start_date' => '2026-08-27', 'end_date' => '2026-09-26', 'status' => 'closed']);

        $this->actingAs($u)->post('/incomes', $this->payload($cat, ['income_date' => '2026-09-01', 'confirm_closed' => 1]));
        $income = $u->incomes()->first();
        $this->assertSame($closed->id, $income->financial_period_id);

        $this->actingAs($u)->put("/incomes/{$income->id}", $this->payload($cat, ['income_date' => '2026-10-01', 'confirm_closed' => 1]));
        $this->assertSame('October', $income->fresh()->period->name);
    }

    public function test_category_must_be_an_own_income_category(): void
    {
        [$u, , $cat] = $this->world();
        $expense = $u->categories()->create(['name' => 'Rent', 'type' => 'expense']);
        $foreign = User::factory()->create()->categories()->create(['name' => 'Salary', 'type' => 'income']);

        $this->actingAs($u)->post('/incomes', $this->payload($cat, ['category_id' => $expense->id]))->assertSessionHasErrors('category_id');
        $this->actingAs($u)->post('/incomes', $this->payload($cat, ['category_id' => $foreign->id]))->assertSessionHasErrors('category_id');
        $this->assertSame(0, $u->incomes()->count());
    }

    public function test_cannot_touch_someone_elses_income(): void
    {
        [$owner, $period, $cat] = $this->world();
        $income = $owner->incomes()->create(['category_id' => $cat->id, 'amount' => 100, 'income_date' => '2026-10-01', 'financial_period_id' => $period->id]);
        $other = User::factory()->create();

        $this->actingAs($other)->get("/incomes/{$income->id}/edit")->assertForbidden();
        $this->actingAs($other)->put("/incomes/{$income->id}", $this->payload($cat))->assertForbidden();
        $this->actingAs($other)->delete("/incomes/{$income->id}")->assertForbidden();
        $this->assertNotSoftDeleted($income);
    }

    public function test_delete_is_soft_and_hides_the_record(): void
    {
        [$u, , $cat] = $this->world();

        $this->actingAs($u)->post('/incomes', $this->payload($cat, ['description' => 'Zzyzx bonus']));
        $income = $u->incomes()->first();
        $this->actingAs($u)->get('/incomes')->assertSee('Zzyzx bonus');

        $this->actingAs($u)->delete("/incomes/{$income->id}");

        $this->assertSoftDeleted($income);
        $this->actingAs($u)->get('/incomes')->assertDontSee('Zzyzx bonus');
    }

    public function test_amount_must_be_a_positive_number(): void
    {
        [$u, , $cat] = $this->world();

        $this->actingAs($u)->post('/incomes', $this->payload($cat, ['amount' => '0.00']))->assertSessionHasErrors('amount');
        $this->actingAs($u)->post('/incomes', $this->payload($cat, ['amount' => 'abc']))->assertSessionHasErrors('amount');
        $this->actingAs($u)->post('/incomes', $this->payload($cat, ['amount' => '10.555']))->assertSessionHasErrors('amount');
        $this->assertSame(0, $u->incomes()->count());
    }

    public function test_index_totals_follow_the_period_filter(): void
    {
        [$u, $period, $cat] = $this->world();
        $u->incomes()->create(['category_id' => $cat->id, 'amount' => 10000, 'income_date' => '2026-10-01', 'financial_period_id' => $period->id]);
        $u->incomes()->create(['category_id' => $cat->id, 'amount' => 5000, 'income_date' => '2026-08-01']);

        $this->actingAs($u)->get('/incomes')->assertSee('Total M 100.00')->assertDontSee('Total M 150.00');
        $this->actingAs($u)->get('/incomes?period=all')->assertSee('Total M 150.00');
        $this->actingAs($u)->get('/incomes?period=none')->assertSee('Total M 50.00');
    }
}