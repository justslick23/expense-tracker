<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/periods')->assertRedirect('/login');
    }

    public function test_opening_balance_is_stored_in_minor_units(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/periods', ['name' => 'October 2026', 'start_date' => '2026-09-27', 'opening_balance' => '1,234.50'])
            ->assertRedirect(route('periods.index'));

        $this->assertSame(123450, $user->financialPeriods()->first()->opening_balance);
    }

    public function test_owner_can_close_their_period(): void
    {
        $user = User::factory()->create();
        $period = $user->financialPeriods()->create(['name' => 'A', 'start_date' => '2026-09-27']);

        $this->actingAs($user)->post("/periods/{$period->id}/close", ['end_date' => '2026-10-26'])
            ->assertRedirect(route('periods.index'));

        $this->assertFalse($period->fresh()->isOpen());
    }

    public function test_a_user_cannot_close_someone_elses_period(): void
    {
        $period = User::factory()->create()->financialPeriods()->create(['name' => 'A', 'start_date' => '2026-09-27']);

        $this->actingAs(User::factory()->create())
            ->post("/periods/{$period->id}/close", ['end_date' => '2026-10-26'])
            ->assertForbidden();

        $this->assertTrue($period->fresh()->isOpen());
    }

    public function test_index_lists_only_own_periods(): void
    {
        User::factory()->create()->financialPeriods()->create(['name' => 'Secret period', 'start_date' => '2026-09-27']);

        $this->actingAs(User::factory()->create())->get('/periods')->assertOk()->assertDontSee('Secret period');
    }
}