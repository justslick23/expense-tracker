<?php

namespace Tests\Feature;

use App\Models\RecurringExpense;
use App\Models\RecurringExpenseOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-08 12:00:00');

        $this->user = User::factory()->create();

        $this->categoryId = $this->user->categories()
            ->create([
                'name' => 'Subscriptions',
                'type' => 'expense',
            ])->id;

        $this->user->financialPeriods()->create([
            'name' => 'October 2026',
            'start_date' => '2026-10-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Internet Subscription',
            'category_id' => $this->categoryId,
            'amount' => '650.00',
            'frequency' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => null,
            'merchant' => 'Internet Provider',
            'description' => 'Monthly internet',
            'is_active' => '1',
        ], $overrides);
    }

    private function template(array $overrides = []): RecurringExpense
    {
        return $this->user->recurringExpenses()->create(
            array_merge([
                'name' => 'Internet Subscription',
                'category_id' => $this->categoryId,
                'amount' => 65000,
                'frequency' => 'monthly',
                'start_date' => '2026-10-01',
                'next_due_date' => '2026-10-01',
                'end_date' => null,
                'merchant' => 'Internet Provider',
                'description' => 'Monthly internet',
                'is_active' => true,
            ], $overrides)
        );
    }

    private function occurrence(
        RecurringExpense $template
    ): RecurringExpenseOccurrence {
        return $template->occurrences()->create([
            'user_id' => $this->user->id,
            'due_date' => '2026-10-01',
            'planned_amount' => 65000,
            'status' => 'pending',
        ]);
    }

    public function test_guests_cannot_access_recurring_expenses(): void
    {
        $this->get(route('recurring.index'))
            ->assertRedirect(route('login'));

        $this->get(route('recurring.create'))
            ->assertRedirect(route('login'));

        $this->post(route('recurring.store'), $this->payload())
            ->assertRedirect(route('login'));
    }

    public function test_user_can_view_recurring_expense_pages(): void
    {
        $this->actingAs($this->user)
            ->get(route('recurring.index'))
            ->assertOk();

        $this->actingAs($this->user)
            ->get(route('recurring.create'))
            ->assertOk();
    }

    public function test_user_can_create_recurring_template(): void
    {
        $this->actingAs($this->user)
            ->post(route('recurring.store'), $this->payload())
            ->assertRedirect(route('recurring.index'))
            ->assertSessionHasNoErrors();

        $template = $this->user->recurringExpenses()
            ->firstOrFail();

        $this->assertSame(65000, $template->amount);
        $this->assertSame('monthly', $template->frequency);
        $this->assertSame(
            '2026-10-01',
            $template->next_due_date->toDateString()
        );

        // Creating a template must not record spending.
        $this->assertSame(0, $this->user->expenses()->count());
        $this->assertSame(0, $template->occurrences()->count());
    }

    public function test_zero_amount_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('recurring.store'), $this->payload([
                'amount' => '0.00',
            ]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(
            0,
            $this->user->recurringExpenses()->count()
        );
    }

    public function test_other_users_category_is_rejected(): void
    {
        $other = User::factory()->create();

        $category = $other->categories()->create([
            'name' => 'Private Category',
            'type' => 'expense',
        ]);

        $this->actingAs($this->user)
            ->post(route('recurring.store'), $this->payload([
                'category_id' => $category->id,
            ]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_user_can_update_template_details(): void
    {
        $template = $this->template();

        $this->actingAs($this->user)
            ->put(
                route('recurring.update', $template),
                $this->payload([
                    'name' => 'Home Internet',
                    'amount' => '700.50',
                ])
            )
            ->assertRedirect(route('recurring.index'))
            ->assertSessionHasNoErrors();

        $template->refresh();

        $this->assertSame('Home Internet', $template->name);
        $this->assertSame(70050, $template->amount);
    }

    public function test_frequency_cannot_change_after_history_exists(): void
    {
        $template = $this->template();
        $this->occurrence($template);

        $this->actingAs($this->user)
            ->put(
                route('recurring.update', $template),
                $this->payload([
                    'frequency' => 'weekly',
                ])
            )
            ->assertSessionHasErrors('frequency');

        $this->assertSame(
            'monthly',
            $template->fresh()->frequency
        );
    }

    public function test_generating_payments_does_not_create_expenses(): void
    {
        $template = $this->template();

        $this->actingAs($this->user)
            ->post(route('recurring.generate'), [
                'through_date' => '2026-11-01',
            ])
            ->assertRedirect(route('recurring.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $template->occurrences()->count());
        $this->assertSame(0, $this->user->expenses()->count());

        $this->actingAs($this->user)
            ->post(route('recurring.generate'), [
                'through_date' => '2026-11-01',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $template->occurrences()->count());
    }

    public function test_user_can_confirm_scheduled_payment(): void
    {
        $template = $this->template();
        $payment = $this->occurrence($template);

        $this->actingAs($this->user)
            ->post(route('recurring.pay', $payment), [
                'amount' => '700.50',
                'paid_date' => '2026-10-05',
            ])
            ->assertRedirect(route('recurring.index'))
            ->assertSessionHasNoErrors();

        $payment->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertSame(65000, $payment->planned_amount);
        $this->assertNotNull($payment->expense_id);
        $this->assertSame(70050, $payment->expense->amount);
        $this->assertSame(1, $this->user->expenses()->count());
    }

    public function test_confirming_payment_twice_is_rejected(): void
    {
        $template = $this->template();
        $payment = $this->occurrence($template);

        $payload = [
            'amount' => '650.00',
            'paid_date' => '2026-10-05',
        ];

        $this->actingAs($this->user)
            ->post(route('recurring.pay', $payment), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->post(route('recurring.pay', $payment), $payload)
            ->assertSessionHasErrors('status');

        $this->assertSame(1, $this->user->expenses()->count());
    }

    public function test_user_can_skip_and_reopen_payment(): void
    {
        $template = $this->template();
        $payment = $this->occurrence($template);

        $this->actingAs($this->user)
            ->post(route('recurring.skip', $payment))
            ->assertRedirect(route('recurring.index'));

        $this->assertSame('skipped', $payment->fresh()->status);

        $this->actingAs($this->user)
            ->post(route('recurring.reopen', $payment))
            ->assertRedirect(route('recurring.index'));

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, $this->user->expenses()->count());
    }

    public function test_user_cannot_modify_another_users_template(): void
    {
        $template = $this->template();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('recurring.edit', $template))
            ->assertForbidden();

        $this->actingAs($other)
            ->put(
                route('recurring.update', $template),
                $this->payload()
            )
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('recurring.destroy', $template))
            ->assertForbidden();

        $this->assertNull($template->fresh()->deleted_at);
    }

    public function test_user_cannot_modify_another_users_occurrence(): void
    {
        $template = $this->template();
        $payment = $this->occurrence($template);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post(route('recurring.pay', $payment), [
                'amount' => '650.00',
                'paid_date' => '2026-10-05',
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('recurring.skip', $payment))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('recurring.reopen', $payment))
            ->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, $this->user->expenses()->count());
    }

    public function test_archiving_template_preserves_occurrences(): void
    {
        $template = $this->template();
        $payment = $this->occurrence($template);

        $this->actingAs($this->user)
            ->delete(route('recurring.destroy', $template))
            ->assertRedirect(route('recurring.index'));

        $this->assertSoftDeleted('recurring_expenses', [
            'id' => $template->id,
        ]);

        $this->assertDatabaseHas(
            'recurring_expense_occurrences',
            [
                'id' => $payment->id,
                'recurring_expense_id' => $template->id,
                'status' => 'pending',
            ]
        );

        $this->assertSame(0, $this->user->expenses()->count());
    }

    public function test_archived_template_is_not_scheduled_again(): void
    {
        $template = $this->template();

        $this->actingAs($this->user)
            ->delete(route('recurring.destroy', $template))
            ->assertRedirect(route('recurring.index'));

        $this->actingAs($this->user)
            ->post(route('recurring.generate'))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $template->occurrences()->count());
    }
}
