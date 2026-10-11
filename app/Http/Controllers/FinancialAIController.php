<?php

namespace App\Http\Controllers;

use App\Services\DashboardAnalyticsService;
use App\Services\DashboardService;
use App\Services\FinancialInsightsService;
use App\Services\GeminiFinancialAdvisorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class FinancialAIController extends Controller
{
    public function __construct(
        private DashboardService $dashboard,
        private DashboardAnalyticsService $analytics,
        private FinancialInsightsService $insights,
        private GeminiFinancialAdvisorService $advisor
    ) {}

    public function generate(
        Request $request,
        int $period
    ): RedirectResponse {
        $user = $request->user();

        // Only analyse periods belonging to this user.
        $financialPeriod = $user->financialPeriods()
            ->findOrFail($period);

        $cacheKey = "financial_ai:user:{$user->id}:period:{$financialPeriod->id}";

        try {
            /*
            |--------------------------------------------------------------------------
            | Retrieve Actual Financial Calculations
            |--------------------------------------------------------------------------
            */

            $summary = $this->dashboard->summary(
                $user,
                $financialPeriod
            );

            $charts = $this->analytics->charts(
                $user,
                $financialPeriod
            );

            $insights = $this->insights->analyze(
                $user,
                $financialPeriod
            );

            /*
            |--------------------------------------------------------------------------
            | Prepare Aggregated Financial Information
            |--------------------------------------------------------------------------
            |
            | Amounts remain in integer minor units (cents).
            | Gemini receives no account numbers or individual
            | transaction descriptions.
            |
            */

            $categorySpending = [];

            $labels = $charts['category_spending']['labels'] ?? [];
            $amounts = $charts['category_spending']['amounts'] ?? [];

            foreach ($labels as $index => $label) {
                $categorySpending[] = [
                    'category' => (string) $label,
                    'spent_minor_units' => (int) (
                        $amounts[$index] ?? 0
                    ),
                ];
            }

            usort(
                $categorySpending,
                fn ($a, $b) =>
                    $b['spent_minor_units']
                    <=> $a['spent_minor_units']
            );

            $comparison = $insights['comparison'] ?? [];
            $velocity = $insights['velocity'] ?? [];

            $budgetAlerts = collect(
                $insights['alerts'] ?? []
            )->map(fn ($alert) => [
                'category' => $alert['category'] ?? null,
                'utilization_percent' =>
                    $alert['utilization'] ?? null,
                'spent_minor_units' =>
                    $alert['spent'] ?? null,
                'allocated_minor_units' =>
                    $alert['allocated'] ?? null,
            ])->values()->all();

            $financialData = [
                'currency' => 'LSL',
                'amount_unit' => 'minor_units',
                'minor_units_per_maloti' => 100,

                'period' => [
                    'name' => $financialPeriod->name,
                    'start_date' =>
                        $financialPeriod->start_date->toDateString(),
                    'end_date' =>
                        $financialPeriod->end_date?->toDateString(),
                    'status' => $financialPeriod->isOpen()
                        ? 'open'
                        : 'closed',
                    'analysis_date' => now()->toDateString(),
                ],

                'financial_summary' => [
                    'total_income' =>
                        $summary['total_income'] ?? 0,
                    'total_expenses' =>
                        $summary['total_expenses'] ?? 0,
                    'net_cash_flow' =>
                        $summary['net_cash_flow'] ?? 0,
                    'total_budget' =>
                        $summary['total_budget'] ?? 0,
                    'remaining_budget' =>
                        $summary['remaining_budget'] ?? 0,
                    'unbudgeted_spending' =>
                        $summary['unbudgeted_spending'] ?? 0,
                    'budget_utilization_percent' =>
                        $summary['budget_utilization'] ?? null,
                    'savings_rate_percent' =>
                        $summary['savings_rate'] ?? null,
                    'income_count' =>
                        $summary['income_count'] ?? 0,
                    'expense_count' =>
                        $summary['expense_count'] ?? 0,
                ],

                'category_spending' => $categorySpending,

                'historical_comparison' => [
                    'available' =>
                        $comparison['available'] ?? false,
                    'previous_period' =>
                        $comparison['period']->name ?? null,
                    'income_change_percent' =>
                        $comparison['income_change'] ?? null,
                    'expense_change_percent' =>
                        $comparison['expense_change'] ?? null,
                    'cash_flow_change_percent' =>
                        $comparison['cash_flow_change'] ?? null,
                ],

                'spending_velocity' => [
                    'daily_average_minor_units' =>
                        $velocity['daily_average'] ?? null,
                    'days_elapsed' =>
                        $velocity['days_elapsed'] ?? null,
                    'days_remaining' =>
                        $velocity['days_remaining'] ?? null,
                    'projected_spending_minor_units' =>
                        ($velocity['projection_available'] ?? false)
                            ? ($velocity['projected_spending'] ?? null)
                            : null,
                ],

                'budget_alerts' => $budgetAlerts,
            ];

            /*
            |--------------------------------------------------------------------------
            | Generate AI Analysis
            |--------------------------------------------------------------------------
            */

            $analysis = $this->advisor->analyze(
                $financialData
            );

            /*
            |--------------------------------------------------------------------------
            | Cache Result for Six Hours
            |--------------------------------------------------------------------------
            */

            Cache::put(
                $cacheKey,
                [
                    'generated_at' => now()->toIso8601String(),
                    'period_id' => $financialPeriod->id,
                    'model' => config(
                        'services.gemini.model',
                        'gemini-2.5-flash'
                    ),
                    'analysis' => $analysis,
                ],
                now()->addHours(6)
            );

            return redirect()
                ->route('home', [
                    'period' => $financialPeriod->id,
                ])
                ->with(
                    'success',
                    'AI financial analysis generated successfully.'
                );

        } catch (Throwable $exception) {
            Log::error('AI financial analysis failed', [
                'user_id' => $user->id,
                'period_id' => $financialPeriod->id,
                'exception' => $exception,
            ]);

            return redirect()
                ->route('home', [
                    'period' => $financialPeriod->id,
                ])
                ->withErrors([
                    'financial_ai' =>
                        'Unable to generate financial analysis. Please try again.',
                ]);
        }
    }
}
