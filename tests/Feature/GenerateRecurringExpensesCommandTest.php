<?php

namespace Tests\Feature;

use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenerateRecurringExpensesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function template(
        ?User $user = null,
        array $overrides = []
    ): RecurringExpense {
        $user ??= User::factory()->create();

        $category = $user->categories()
            ->firstOrCreate([
                'name' => 'Subscriptions',
                'type' => 'expense',
            ]);

        return $user->recurringExpenses()->create(
            array_merge([
                'category_id' => $category->id,
                'name' => 'Internet Subscription',
                'amount' => 65000,
                'frequency' => 'monthly',
                'start_date' => '2026-10-01',
                'next_due_date' => '2026-10-01',
                'end_date' => null,
                'is_active' => true,
            ], $overrides)
        );
    }

    public function test_command_generates_upcoming_payments(): void
    {
        $template = $this->template();

        $result = Artisan::call(
            'recurring:generate',
            ['--days' => 30]
        );

        $this->assertSame(0, $result);

        $this->assertSame(
            2,
            $template->occurrences()->count()
        );

        $this->assertDatabaseHas(
            'recurring_expense_occurrences',
            [
                'recurring_expense_id' => $template->id,
                'status' => 'pending',
                'planned_amount' => 65000,
            ]
        );
    }

    public function test_running_command_twice_does_not_duplicate_payments(): void
    {
        $template = $this->template();

        Artisan::call('recurring:generate', [
            '--days' => 30,
        ]);

        Artisan::call('recurring:generate', [
            '--days' => 30,
        ]);

        $this->assertSame(
            2,
            $template->occurrences()->count()
        );
    }

    public function test_inactive_and_archived_templates_are_ignored(): void
    {
        $active = $this->template();

        $inactive = $this->template(
            null,
            [
                'name' => 'Inactive Subscription',
                'is_active' => false,
            ]
        );

        $archived = $this->template(
            null,
            ['name' => 'Archived Subscription']
        );

        $archived->delete();

        Artisan::call('recurring:generate');

        $this->assertSame(
            2,
            $active->occurrences()->count()
        );

        $this->assertSame(
            0,
            $inactive->occurrences()->count()
        );

        $this->assertSame(
            0,
            $archived->occurrences()->count()
        );
    }

    public function test_command_processes_different_users(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $first = $this->template($firstUser);
        $second = $this->template($secondUser);

        Artisan::call('recurring:generate');

        $this->assertSame(
            2,
            $first->occurrences()->count()
        );

        $this->assertSame(
            2,
            $second->occurrences()->count()
        );

        $this->assertSame(
            2,
            $firstUser->recurringExpenseOccurrences()->count()
        );

        $this->assertSame(
            2,
            $secondUser->recurringExpenseOccurrences()->count()
        );
    }

    public function test_invalid_generation_horizon_is_rejected(): void
    {
        $template = $this->template();

        $result = Artisan::call(
            'recurring:generate',
            ['--days' => -1]
        );

        $this->assertSame(1, $result);

        $this->assertSame(
            0,
            $template->occurrences()->count()
        );
    }
}
