<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        private FinancialReportService $reports
    ) {}

    /**
     * Export a detailed financial period report.
     */
    public function period(
        User $user,
        FinancialPeriod $period
    ): StreamedResponse {
        $report = $this->reports->periodReport($user, $period);

        return $this->stream(
            'financial-report-' . $period->id . '.csv',
            function ($handle) use ($report, $period) {
                $overview = $report['overview'];
                $budget = $report['budget'];

                $this->row($handle, ['Financial Report']);
                $this->row($handle, ['Period', $period->name]);
                $this->row($handle, [
                    'Start date',
                    $period->start_date->format('Y-m-d'),
                ]);
                $this->row($handle, [
                    'End date',
                    $period->end_date?->format('Y-m-d') ?? 'Ongoing',
                ]);

                $this->blank($handle);

                $this->row($handle, ['Financial Overview']);
                $this->row($handle, ['Metric', 'Amount (M)']);

                $this->row($handle, [
                    'Total income',
                    $this->amount($overview['income']),
                ]);
                $this->row($handle, [
                    'Total expenses',
                    $this->amount($overview['expenses']),
                ]);
                $this->row($handle, [
                    'Net cash flow',
                    $this->amount($overview['net_cash_flow']),
                ]);
                $this->row($handle, [
                    'Total budget',
                    $this->amount($budget['total_allocated']),
                ]);
                $this->row($handle, [
                    'Remaining budget',
                    $this->amount($budget['remaining']),
                ]);
                $this->row($handle, [
                    'Unbudgeted spending',
                    $this->amount($budget['unbudgeted_spending']),
                ]);
                $this->row($handle, [
                    'Budget utilization (%)',
                    $budget['utilization'],
                ]);

                $this->blank($handle);

                $this->row($handle, ['Expenses by Category']);
                $this->row($handle, [
                    'Category',
                    'Transactions',
                    'Amount (M)',
                ]);

                foreach ($report['expenses_by_category'] as $item) {
                    $this->row($handle, [
                        $item['category'],
                        $item['count'],
                        $this->amount($item['total']),
                    ]);
                }

                $this->blank($handle);

                $this->row($handle, ['Income by Source']);
                $this->row($handle, [
                    'Source',
                    'Transactions',
                    'Amount (M)',
                ]);

                foreach ($report['income_by_category'] as $item) {
                    $this->row($handle, [
                        $item['category'],
                        $item['count'],
                        $this->amount($item['total']),
                    ]);
                }

                $this->blank($handle);

                $this->row($handle, ['Spending by Merchant']);
                $this->row($handle, [
                    'Merchant',
                    'Transactions',
                    'Amount (M)',
                ]);

                foreach ($report['expenses_by_merchant'] as $item) {
                    $this->row($handle, [
                        $item['merchant'],
                        $item['count'],
                        $this->amount($item['total']),
                    ]);
                }

                $this->blank($handle);

                $this->row($handle, ['Budget Variance']);
                $this->row($handle, [
                    'Category',
                    'Allocated (M)',
                    'Spent (M)',
                    'Remaining (M)',
                    'Utilization (%)',
                ]);

                foreach ($budget['categories'] as $item) {
                    $this->row($handle, [
                        $item['category']?->name ?? 'Uncategorized',
                        $this->amount($item['allocated']),
                        $this->amount($item['spent']),
                        $this->amount($item['remaining']),
                        $item['utilization'],
                    ]);
                }
            }
        );
    }

    /**
     * Export a comparison of historical periods.
     */
    public function history(
        User $user,
        Collection $periods
    ): StreamedResponse {
        $history = $this->reports->historicalReport(
            $user,
            $periods
        );

        return $this->stream(
            'financial-history.csv',
            function ($handle) use ($history) {
                $this->row($handle, [
                    'Financial Period',
                    'Start Date',
                    'End Date',
                    'Income (M)',
                    'Expenses (M)',
                    'Net Cash Flow (M)',
                    'Budget (M)',
                    'Remaining Budget (M)',
                    'Budget Utilization (%)',
                ]);

                foreach ($history as $item) {
                    $period = $item['period'];

                    $this->row($handle, [
                        $period->name,
                        $period->start_date->format('Y-m-d'),
                        $period->end_date?->format('Y-m-d') ?? '',
                        $this->amount($item['income']),
                        $this->amount($item['expenses']),
                        $this->amount($item['net_cash_flow']),
                        $this->amount($item['budget']),
                        $this->amount($item['remaining_budget']),
                        $item['budget_utilization'],
                    ]);
                }
            }
        );
    }

    /**
     * Create a streamed CSV download.
     */
    private function stream(
        string $filename,
        callable $writer
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($writer) {
                $handle = fopen('php://output', 'w');

                try {
                    // UTF-8 BOM helps Excel recognize Unicode text.
                    fwrite($handle, "\xEF\xBB\xBF");

                    $writer($handle);
                } finally {
                    fclose($handle);
                }
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate',
                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    /**
     * Write a CSV row.
     *
     * Prefix spreadsheet formulas to prevent CSV injection
     * when files are opened in Excel or similar software.
     */
    private function row($handle, array $values): void
    {
        $safe = array_map(function ($value) {
            if (is_int($value) || is_float($value)) {
                return $value;
            }
    
            $value = (string) ($value ?? '');
    
            // Allow signed decimal values generated by our
            // trusted financial formatter.
            if (preg_match('/^-?\d+\.\d{2}$/', $value)) {
                return $value;
            }
    
            // Protect cells against spreadsheet formula injection.
            if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value)) {
                return "'" . $value;
            }
    
            return $value;
        }, $values);
    
        fputcsv($handle, $safe, ',', '"', '');
    }

    private function blank($handle): void
    {
        fputcsv($handle, [], ',', '"', '');
    }

    /**
     * Convert minor units to a plain decimal value.
     *
     * Example: 150050 becomes 1500.50.
     */

/**
 * Convert integer minor units to a plain decimal amount.
 *
 * Examples:
 * 150050  => 1500.50
 * -5000   => -50.00
 * 100     => 1.00
 */
private function amount(int $minorUnits): string
{
    $negative = $minorUnits < 0 ? '-' : '';

    $absolute = abs($minorUnits);

    $whole = intdiv($absolute, 100);
    $cents = $absolute % 100;

    return $negative
        . $whole
        . '.'
        . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
}

}
