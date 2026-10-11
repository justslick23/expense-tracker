<?php

namespace Tests\Feature;

use App\Models\RecurringExpense;
use App\Models\RecurringExpenseOccurrence;
use App\Models\User;
use App\Services\RecurringExpenseScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RecurringExpenseScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RecurringExpenseScheduleService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->scheduler = app(
            RecurringExpenseScheduleService::class
        );
    }

    private function template(array $overrides = []): RecurringExpense
    {
        $category = $this->user->categories()->firstOrCreate([
            'name' => 'Subscriptions',
            'type' => 'expense',
        ]);

        return $this->user->recurringExpenses()->create(
            array_merge([
                'category_id' => $category->id,
                'name' => 'Internet Subscription',
                'amount' => 65000,
                'frequency' => 'monthly',
                'start_date' => '2026-01-15',
                'next_due_date' => '2026-01-15',
                'end_date' => null,
                'merchant' => 'Internet Provider',
                'description' => 'Monthly internet',
                'is_active' => true,
            ], $overrides)
        );
    }

    private function generate(
        RecurringExpense $template,
        string $throughDate,
        int $limit = 500
    ): int {
        return $this->scheduler->generate(
            $this->user,
            $template,
            CarbonImmutable::parse($throughDate),
            $limit
        );
    }

    private function dueDates(RecurringExpense $template): array
    {
        return $template->occurrences()
            ->orderBy('due_date')
            ->get()
            ->map(fn ($item) => $item->due_date->toDateString())
            ->all();
    }

    public function test_monthly_schedule_handles_the_31st(): void
    {
        $template = $this->template([
            'start_date' => '2026-01-31',
            'next_due_date' => '2026-01-31',
        ]);

        $created = $this->generate($template, '2026-05-31');

        $this->assertSame(5, $created);

        $this->assertSame([
            '2026-01-31',
            '2026-02-28',
            '2026-03-31',
            '2026-04-30',
            '2026-05-31',
        ], $this->dueDates($template));

        $this->assertSame(
            '2026-06-30',
            $template->fresh()->next_due_date->toDateString()
        );
    }

    public function test_monthly_schedule_handles_the_30th(): void
    {
        $template = $this->template([
            'start_date' => '2026-01-30',
            'next_due_date' => '2026-01-30',
        ]);

        $this->generate($template, '2026-04-30');

        $this->assertSame([
            '2026-01-30',
            '2026-02-28',
            '2026-03-30',
            '2026-04-30',
        ], $this->dueDates($template));
    }

    public function test_monthly_schedule_handles_the_29th(): void
    {
        $template = $this->template([
            'start_date' => '2026-01-29',
            'next_due_date' => '2026-01-29',
        ]);

        $this->generate($template, '2026-03-29');

        $this->assertSame([
            '2026-01-29',
            '2026-02-28',
            '2026-03-29',
        ], $this->dueDates($template));
    }

    public function test_leap_year_february_is_handled(): void
    {
        $template = $this->template([
            'start_date' => '2028-01-31',
            'next_due_date' => '2028-01-31',
        ]);

        $this->generate($template, '2028-03-31');

        $this->assertSame([
            '2028-01-31',
            '2028-02-29',
            '2028-03-31',
        ], $this->dueDates($template));
    }

    public function test_daily_schedule_generates_every_day(): void
    {
        $template = $this->template([
            'frequency' => 'daily',
            'start_date' => '2026-10-01',
            'next_due_date' => '2026-10-01',
        ]);

        $this->assertSame(
            4,
            $this->generate($template, '2026-10-04')
        );

        $this->assertSame([
            '2026-10-01',
            '2026-10-02',
            '2026-10-03',
            '2026-10-04',
        ], $this->dueDates($template));
    }

    public function test_weekly_schedule_generates_every_seven_days(): void
    {
        $template = $this->template([
            'frequency' => 'weekly',
            'start_date' => '2026-10-01',
            'next_due_date' => '2026-10-01',
        ]);

        $this->generate($template, '2026-10-22');

        $this->assertSame([
            '2026-10-01',
            '2026-10-08',
            '2026-10-15',
            '2026-10-22',
        ], $this->dueDates($template));
    }

    public function test_quarterly_schedule_preserves_original_anchor(): void
    {
        $template = $this->template([
            'frequency' => 'quarterly',
            'start_date' => '2026-01-31',
            'next_due_date' => '2026-01-31',
        ]);

        $this->generate($template, '2026-10-31');

        $this->assertSame([
            '2026-01-31',
            '2026-04-30',
            '2026-07-31',
            '2026-10-31',
        ], $this->dueDates($template));
    }

    public function test_yearly_schedule_handles_february_29(): void
    {
        $template = $this->template([
            'frequency' => 'yearly',
            'start_date' => '2024-02-29',
            'next_due_date' => '2024-02-29',
        ]);

        $this->generate($template, '2028-02-29');

        $this->assertSame([
            '2024-02-29',
            '2025-02-28',
            '2026-02-28',
            '2027-02-28',
            '2028-02-29',
        ], $this->dueDates($template));
    }

    public function test_repeated_generation_does_not_duplicate_payments(): void
    {
        $template = $this->template();

        $first = $this->generate($template, '2026-04-15');
        $second = $this->generate($template, '2026-04-15');

        $this->assertSame(4, $first);
        $this->assertSame(0, $second);

        $this->assertSame(
            4,
            $template->occurrences()->count()
        );
    }

    public function test_missed_payments_are_generated_in_order(): void
    {
        $template = $this->template([
            'start_date' => '2026-01-15',
            'next_due_date' => '2026-01-15',
        ]);

        $this->generate($template, '2026-06-15');

        $this->assertSame([
            '2026-01-15',
            '2026-02-15',
            '2026-03-15',
            '2026-04-15',
            '2026-05-15',
            '2026-06-15',
        ], $this->dueDates($template));
    }

    public function test_generation_limit_preserves_remaining_schedule(): void
    {
        $template = $this->template();

        $this->assertSame(
            2,
            $this->generate($template, '2026-06-15', 2)
        );

        $this->assertSame(
            '2026-03-15',
            $template->fresh()->next_due_date->toDateString()
        );

        $this->assertSame(
            4,
            $this->generate($template, '2026-06-15')
        );

        $this->assertSame(
            6,
            $template->occurrences()->count()
        );
    }

    public function test_inactive_templates_generate_nothing(): void
    {
        $template = $this->template([
            'is_active' => false,
        ]);

        $this->assertSame(
            0,
            $this->generate($template, '2026-06-15')
        );

        $this->assertSame(
            0,
            $template->occurrences()->count()
        );
    }

    public function test_end_date_stops_future_occurrences(): void
    {
        $template = $this->template([
            'end_date' => '2026-03-15',
        ]);

        $this->assertSame(
            3,
            $this->generate($template, '2026-12-31')
        );

        $this->assertSame([
            '2026-01-15',
            '2026-02-15',
            '2026-03-15',
        ], $this->dueDates($template));

        $this->assertSame(
            '2026-04-15',
            $template->fresh()->next_due_date->toDateString()
        );
    }

    public function test_invalid_schedule_cursor_is_rejected(): void
    {
        $template = $this->template([
            'start_date' => '2026-01-15',
            'next_due_date' => '2026-02-20',
        ]);

        $this->expectException(ValidationException::class);

        $this->generate($template, '2026-03-31');
    }

    public function test_user_cannot_generate_another_users_schedule(): void
    {
        $template = $this->template();

        $otherUser = User::factory()->create();

        $this->expectException(HttpException::class);

        $this->scheduler->generate(
            $otherUser,
            $template,
            CarbonImmutable::parse('2026-06-15')
        );
    }

    public function test_existing_occurrences_are_not_overwritten(): void
    {
        $template = $this->template();

        $existing = $template->occurrences()->create([
            'user_id' => $this->user->id,
            'due_date' => '2026-01-15',
            'planned_amount' => 50000,
            'status' => 'skipped',
        ]);

        $created = $this->generate(
            $template,
            '2026-03-15'
        );

        $this->assertSame(2, $created);
        $this->assertSame(3, $template->occurrences()->count());

        $existing->refresh();

        $this->assertSame(50000, $existing->planned_amount);
        $this->assertSame('skipped', $existing->status);

        $this->assertSame(
            65000,
            $template->occurrences()
                ->whereDate('due_date', '2026-02-15')
                ->firstOrFail()
                ->planned_amount
        );
    }

    public function test_generation_does_not_create_actual_expenses(): void
    {
        $template = $this->template();

        $this->generate($template, '2026-04-15');

        $this->assertSame(
            4,
            RecurringExpenseOccurrence::query()->count()
        );

        $this->assertSame(
            0,
            $this->user->expenses()->count()
        );

        $this->assertTrue(
            $template->occurrences()
                ->get()
                ->every(fn ($item) =>
                    $item->status === 'pending'
                    && $item->expense_id === null
                )
        );
    }
}
