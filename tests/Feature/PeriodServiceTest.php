<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    private PeriodService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new PeriodService;
    }

    private function d(string $date): Carbon
    {
        return Carbon::parse($date);
    }

    public function test_starts_an_open_period(): void
    {
        $p = $this->svc->start(User::factory()->create(), 'October 2026', $this->d('2026-09-27'));

        $this->assertTrue($p->isOpen());
        $this->assertSame('2026-09-27', $p->fresh()->start_date->toDateString());
        $this->assertNull($p->fresh()->end_date);
    }

    public function test_cannot_start_a_second_period_while_one_is_open(): void
    {
        $user = User::factory()->create();
        $this->svc->start($user, 'A', $this->d('2026-09-27'));

        $this->expectException(ValidationException::class);
        $this->svc->start($user, 'B', $this->d('2026-10-27'));
    }

    public function test_early_close_then_next_period_the_day_after(): void
    {
        $user = User::factory()->create();
        $a = $this->svc->start($user, 'A', $this->d('2026-09-27'), $this->d('2026-10-26'));
        $closed = $this->svc->close($a, $this->d('2026-10-20'));
        $b = $this->svc->start($user, 'B', $this->d('2026-10-21'));

        $this->assertFalse($closed->fresh()->isOpen());
        $this->assertSame('2026-10-20', $closed->fresh()->end_date->toDateString());
        $this->assertNotNull($closed->fresh()->closed_at);
        $this->assertTrue($b->isOpen());
    }

    public function test_new_period_cannot_start_on_the_day_the_previous_one_ended(): void
    {
        $user = User::factory()->create();
        $a = $this->svc->start($user, 'A', $this->d('2026-09-27'));
        $this->svc->close($a, $this->d('2026-10-26'));

        $this->expectException(ValidationException::class);
        $this->svc->start($user, 'B', $this->d('2026-10-26'));
    }

    public function test_cannot_close_before_the_start_date(): void
    {
        $a = $this->svc->start(User::factory()->create(), 'A', $this->d('2026-09-27'));

        $this->expectException(ValidationException::class);
        $this->svc->close($a, $this->d('2026-09-26'));
    }

    public function test_cannot_close_an_already_closed_period(): void
    {
        $a = $this->svc->start(User::factory()->create(), 'A', $this->d('2026-09-27'));
        $this->svc->close($a, $this->d('2026-10-26'));

        $this->expectException(ValidationException::class);
        $this->svc->close($a, $this->d('2026-10-27'));
    }

    public function test_other_users_periods_do_not_interfere(): void
    {
        $this->svc->start(User::factory()->create(), 'A', $this->d('2026-09-27'));
        $b = $this->svc->start(User::factory()->create(), 'B', $this->d('2026-09-27'));

        $this->assertTrue($b->isOpen());
    }

    public function test_database_rejects_two_open_periods_even_without_the_service(): void
    {
        $user = User::factory()->create();
        $user->financialPeriods()->create(['name' => 'A', 'start_date' => '2026-09-27']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $user->financialPeriods()->create(['name' => 'B', 'start_date' => '2026-10-27']);
    }
}