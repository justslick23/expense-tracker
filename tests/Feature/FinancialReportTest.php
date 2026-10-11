<?php

namespace Tests\Feature;

use App\Models\FinancialPeriod;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->period = $this->user->financialPeriods()->create([
            'name' => 'October 2026',
            'start_date' => '2026-10-01',
        ]);
    }

    private function category(
        string $name,
        string $type = 'expense'
    ) {
        return $this->user->categories()->firstOrCreate([
            'name' => $name,
            'type' => $type,
        ]);
    }

    private function income(
        int $amount,
        ?FinancialPeriod $period = null
    ): void {
        $category = $this->category('Salary', 'income');

        $this->user->incomes()->create([
            'financial_period_id' => ($period ?? $this->period)->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'income_date' => '2026-10-05',
            'description' => 'Salary payment',
        ]);
    }

    private function expense(
        int $amount,
        string $merchant = 'Shoprite',
        ?FinancialPeriod $period = null,
        string $categoryName = 'Groceries'
    ): void {
        $category = $this->category($categoryName);

        $this->user->expenses()->create([
            'financial_period_id' => ($period ?? $this->period)->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'expense_date' => '2026-10-06',
            'merchant' => $merchant,
            'description' => 'Purchase',
        ]);
    }

    private function budget(
        int $amount,
        string $categoryName = 'Groceries'
    ): void {
        $category = $this->category($categoryName);

        $this->user->budgets()->create([
            'financial_period_id' => $this->period->id,
            'category_id' => $category->id,
            'amount' => $amount,
        ]);
    }

    private function report(): array
    {
        return app(FinancialReportService::class)
            ->periodReport($this->user, $this->period);
    }

    private function previousPeriod(): FinancialPeriod
    {
        return $this->user->financialPeriods()->create([
            'name' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'is_open' => false,
            'status' => 'closed',
        ]);
    }

    public function test_guests_cannot_access_reports(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));

        $this->get(route('reports.history'))
            ->assertRedirect(route('login'));
    }

    public function test_period_report_calculates_correct_totals(): void
    {
        $this->income(1200000);
        $this->expense(250000);
        $this->expense(150000);

        $overview = $this->report()['overview'];

        $this->assertSame(1200000, $overview['income']);
        $this->assertSame(400000, $overview['expenses']);
        $this->assertSame(800000, $overview['net_cash_flow']);
        $this->assertSame(1, $overview['income_count']);
        $this->assertSame(2, $overview['expense_count']);
    }

    public function test_expenses_are_grouped_by_category(): void
    {
        $this->expense(100000, 'Shoprite', null, 'Groceries');
        $this->expense(50000, 'Pick n Pay', null, 'Groceries');
        $this->expense(30000, 'Engen', null, 'Fuel');

        $categories = $this->report()['expenses_by_category'];

        $this->assertCount(2, $categories);
        $this->assertSame('Groceries', $categories[0]['category']);
        $this->assertSame(150000, $categories[0]['total']);
        $this->assertSame(2, $categories[0]['count']);
        $this->assertSame('Fuel', $categories[1]['category']);
        $this->assertSame(30000, $categories[1]['total']);
    }

    public function test_expenses_are_grouped_by_merchant(): void
    {
        $this->expense(70000, 'Shoprite');
        $this->expense(30000, 'Shoprite');
        $this->expense(20000, 'Pick n Pay');

        $merchants = $this->report()['expenses_by_merchant'];

        $this->assertCount(2, $merchants);
        $this->assertSame('Shoprite', $merchants[0]['merchant']);
        $this->assertSame(100000, $merchants[0]['total']);
        $this->assertSame(2, $merchants[0]['count']);
    }

    public function test_missing_merchant_is_labelled_unspecified(): void
    {
        $this->expense(15000, '');

        $merchants = $this->report()['expenses_by_merchant'];

        $this->assertSame('Unspecified', $merchants[0]['merchant']);
        $this->assertSame(15000, $merchants[0]['total']);
    }

    public function test_income_is_grouped_by_source(): void
    {
        $this->income(100000);
        $this->income(50000);

        $sources = $this->report()['income_by_category'];

        $this->assertCount(1, $sources);
        $this->assertSame('Salary', $sources[0]['category']);
        $this->assertSame(150000, $sources[0]['total']);
        $this->assertSame(2, $sources[0]['count']);
    }

    public function test_budget_variance_uses_actual_expenses(): void
    {
        $this->budget(150000);
        $this->expense(120000);

        $budget = $this->report()['budget'];

        $this->assertSame(150000, $budget['total_allocated']);
        $this->assertSame(120000, $budget['total_spent']);
        $this->assertSame(30000, $budget['remaining']);
        $this->assertSame(80.0, $budget['utilization']);
    }

    public function test_unbudgeted_spending_is_included(): void
    {
        $this->budget(100000, 'Groceries');

        $this->expense(40000, 'Shoprite', null, 'Groceries');
        $this->expense(20000, 'Engen', null, 'Fuel');

        $budget = $this->report()['budget'];

        $this->assertSame(60000, $budget['total_spent']);
        $this->assertSame(20000, $budget['unbudgeted_spending']);
        $this->assertSame(40000, $budget['remaining']);
    }

    public function test_historical_report_calculates_period_totals(): void
    {
        $previous = $this->previousPeriod();

        $this->income(800000, $previous);
        $this->expense(300000, 'Shoprite', $previous);

        $this->income(1200000);
        $this->expense(400000);

        $history = app(FinancialReportService::class)
            ->historicalReport(
                $this->user,
                collect([$this->period, $previous])
            );

        $this->assertCount(2, $history);

        $this->assertSame(
            $previous->id,
            $history[0]['period']->id
        );

        $this->assertSame(800000, $history[0]['income']);
        $this->assertSame(300000, $history[0]['expenses']);
        $this->assertSame(500000, $history[0]['net_cash_flow']);

        $this->assertSame(1200000, $history[1]['income']);
        $this->assertSame(400000, $history[1]['expenses']);
        $this->assertSame(800000, $history[1]['net_cash_flow']);
    }

    public function test_user_cannot_request_another_users_period(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('reports.index', [
                'period' => $this->period->id,
            ]))
            ->assertSessionHasErrors('period');
    }

    public function test_report_service_rejects_another_users_period(): void
    {
        $other = User::factory()->create();

        $this->expectException(
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        );

        app(FinancialReportService::class)
            ->periodReport($other, $this->period);
    }

    public function test_historical_report_rejects_foreign_periods(): void
    {
        $other = User::factory()->create();

        $this->expectException(
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        );

        app(FinancialReportService::class)
            ->historicalReport(
                $other,
                collect([$this->period])
            );
    }

    public function test_csv_export_downloads_with_correct_amounts(): void
    {
        $this->income(120050);
        $this->expense(25025);

        $response = $this->actingAs($this->user)
            ->get(route('reports.export', [
                'period' => $this->period->id,
            ]));

        $response->assertOk();
        $response->assertDownload(
            'financial-report-' . $this->period->id . '.csv'
        );

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            '1200.50',
            $csv
        );

        $this->assertStringContainsString(
            '250.25',
            $csv
        );

        $this->assertStringContainsString(
            'Financial Overview',
            $csv
        );
    }

    public function test_csv_export_rejects_foreign_period(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('reports.export', [
                'period' => $this->period->id,
            ]))
            ->assertSessionHasErrors('period');
    }

    public function test_historical_csv_export_contains_period_data(): void
    {
        $this->income(950050);
        $this->expense(150025);

        $response = $this->actingAs($this->user)
            ->get(route('reports.history.export', [
                'limit' => 3,
            ]));

        $response->assertOk();
        $response->assertDownload('financial-history.csv');

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            'October 2026',
            $csv
        );

        $this->assertStringContainsString(
            '9500.50',
            $csv
        );

        $this->assertStringContainsString(
            '1500.25',
            $csv
        );
    }

    public function test_csv_export_escapes_spreadsheet_formulas(): void
    {
        $this->expense(
            10000,
            '=HYPERLINK("https://example.com","Click")'
        );

        $response = $this->actingAs($this->user)
            ->get(route('reports.export', [
                'period' => $this->period->id,
            ]));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            "'=HYPERLINK",
            $csv
        );
    }

    public function test_negative_csv_amounts_remain_numeric(): void
    {
        $this->budget(10000);
        $this->expense(15000);

        $response = $this->actingAs($this->user)
            ->get(route('reports.export', [
                'period' => $this->period->id,
            ]));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            '-50.00',
            $csv
        );

        $this->assertStringNotContainsString(
            "'-50.00",
            $csv
        );
    }
}
