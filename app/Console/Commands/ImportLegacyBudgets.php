<?php

namespace App\Console\Commands;

use App\Models\LegacyBudgetArchive;
use App\Models\LegacyImportRecord;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ImportLegacyBudgets extends Command
{
    private const SOURCE = 'old_finance_tracker';

    protected $signature = 'legacy:import-budgets
        {--user=1 : Legacy user ID}
        {--commit : Write the historical budget archives}';

    protected $description =
        'Preview or import legacy calendar-month budget allocations';

    public function handle(): int
    {
        $legacyUserId = filter_var(
            $this->option('user'),
            FILTER_VALIDATE_INT
        );

        if ($legacyUserId === false || $legacyUserId < 1) {
            $this->error('The legacy user ID must be a positive integer.');
            return self::FAILURE;
        }

        if (User::query()->count() !== 1) {
            $this->error(
                'The destination database must contain exactly one user.'
            );

            return self::FAILURE;
        }

        $user = User::query()->firstOrFail();

        $this->info('Historical Budget Archive Import');
        $this->line(
            'Mode: ' . ($this->option('commit') ? 'COMMIT' : 'DRY RUN')
        );
        $this->line("Legacy user: {$legacyUserId}");
        $this->line("Destination user: {$user->id}");

        try {
            $connectionName = config('legacy.connection', 'legacy');

            if (
                ! Schema::connection($connectionName)
                    ->hasTable('budgets')
            ) {
                throw new RuntimeException(
                    'The legacy budgets table was not found.'
                );
            }

            foreach ([
                'legacy_budget_archives',
                'legacy_import_records',
                'categories',
            ] as $table) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException(
                        "Missing destination table: {$table}"
                    );
                }
            }

            $legacy = DB::connection($connectionName);
            $legacy->getPdo();

            $encrypter = $this->makeEncrypter();

            $budgets = $legacy->table('budgets')
                ->where('user_id', $legacyUserId)
                ->orderBy('id')
                ->get();

            $prepared = [];
            $alreadyImported = 0;
            $ready = 0;
            $monthlyTotals = [];

            foreach ($budgets as $budget) {
                $id = (int) $budget->id;
                $categoryId = (int) $budget->category_id;

                $month = filter_var(
                    $budget->month,
                    FILTER_VALIDATE_INT
                );

                $year = filter_var(
                    $budget->year,
                    FILTER_VALIDATE_INT
                );

                if (
                    $month === false
                    || $month < 1
                    || $month > 12
                    || $year === false
                    || $year < 2000
                    || $year > 2100
                ) {
                    throw new RuntimeException(
                        "Legacy budget #{$id} has an invalid month or year."
                    );
                }

                $amount = $this->decryptAmount(
                    $encrypter,
                    $budget->amount,
                    $id
                );

                $mappedCategoryId = $this->mappedCategory(
                    $legacyUserId,
                    $user,
                    $categoryId
                );

                $existing = $this->importRecord($id);

                if ($existing) {
                    if (
                        $existing->legacy_user_id !== $legacyUserId
                        || $existing->new_user_id !== $user->id
                    ) {
                        throw new RuntimeException(
                            "Budget #{$id} has a conflicting import mapping."
                        );
                    }

                    $archive = $user->legacyBudgetArchives()
                        ->find($existing->new_record_id);

                    if (! $archive) {
                        throw new RuntimeException(
                            "Imported budget #{$id} is missing its archive record."
                        );
                    }

                    if (
                        $archive->legacy_budget_id !== $id
                        || $archive->category_id !== $mappedCategoryId
                        || $archive->year !== $year
                        || $archive->month !== $month
                        || $archive->allocated_amount !== $amount
                    ) {
                        throw new RuntimeException(
                            "Imported budget #{$id} does not match its source."
                        );
                    }

                    $alreadyImported++;
                    continue;
                }

                // Refuse to create a duplicate archive if a
                // previous operation created it without a ledger entry.
                if (
                    $user->legacyBudgetArchives()
                        ->where('legacy_budget_id', $id)
                        ->exists()
                ) {
                    throw new RuntimeException(
                        "Budget #{$id} already has an archive but no import mapping."
                    );
                }

                $prepared[] = [
                    'legacy_id' => $id,
                    'legacy_user_id' => $legacyUserId,
                    'new_user_id' => $user->id,
                    'category_id' => $mappedCategoryId,
                    'year' => $year,
                    'month' => $month,
                    'amount' => $amount,
                ];

                $ready++;

                $monthKey = sprintf('%04d-%02d', $year, $month);

                $monthlyTotals[$monthKey] ??= [
                    'count' => 0,
                    'allocated' => 0,
                ];

                $monthlyTotals[$monthKey]['count']++;
                $monthlyTotals[$monthKey]['allocated'] += $amount;
            }

            $this->newLine();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Legacy budget records', $budgets->count()],
                    ['Ready to import', $ready],
                    ['Already imported', $alreadyImported],
                ]
            );

            $this->newLine();
            $this->info('Monthly allocations ready for import');

            ksort($monthlyTotals);

            $this->table(
                ['Month', 'Budgets', 'Allocated (M)'],
                collect($monthlyTotals)
                    ->map(fn ($row, $month) => [
                        $month,
                        $row['count'],
                        $this->formatAmount($row['allocated']),
                    ])
                    ->values()
                    ->all()
            );

            if (! $this->option('commit')) {
                $this->info(
                    'Dry run completed. No records were modified.'
                );

                return self::SUCCESS;
            }

            $imported = DB::transaction(function () use (
                $user,
                $prepared
            ) {
                $count = 0;

                foreach ($prepared as $row) {
                    // Recheck the ledger inside the transaction.
                    $existing = $this->importRecord(
                        $row['legacy_id']
                    );

                    if ($existing) {
                        throw new RuntimeException(
                            "Budget #{$row['legacy_id']} was imported concurrently. Retry the command."
                        );
                    }

                    $archive = $user->legacyBudgetArchives()
                        ->create([
                            'legacy_budget_id' => $row['legacy_id'],
                            'category_id' => $row['category_id'],
                            'year' => $row['year'],
                            'month' => $row['month'],
                            'allocated_amount' => $row['amount'],
                        ]);

                    LegacyImportRecord::query()->create([
                        'source' => self::SOURCE,
                        'record_type' => 'budget',
                        'legacy_id' => $row['legacy_id'],
                        'legacy_user_id' => $row['legacy_user_id'],
                        'new_user_id' => $row['new_user_id'],
                        'new_record_id' => $archive->id,
                        'imported_at' => now(),
                    ]);

                    $count++;
                }

                return $count;
            }, 3);

            $this->newLine();
            $this->info(
                "{$imported} historical budget(s) imported successfully."
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(
                'Budget import stopped: ' . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    private function makeEncrypter(): Encrypter
    {
        $configuredKey = config('legacy.key');

        if (! is_string($configuredKey) || $configuredKey === '') {
            throw new RuntimeException(
                'LEGACY_APP_KEY is missing.'
            );
        }

        $key = str_starts_with($configuredKey, 'base64:')
            ? base64_decode(
                substr($configuredKey, 7),
                true
            )
            : $configuredKey;

        if ($key === false) {
            throw new RuntimeException(
                'Invalid legacy encryption key encoding.'
            );
        }

        return new Encrypter(
            $key,
            config('legacy.cipher', 'AES-256-CBC')
        );
    }

    /**
     * Convert legacy decimal currency into minor units
     * without using floating-point calculations.
     */
    private function decryptAmount(
        Encrypter $encrypter,
        mixed $encrypted,
        int $budgetId
    ): int {
        try {
            $plain = trim(
                $encrypter->decryptString(
                    (string) $encrypted
                )
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Budget #{$budgetId}: unable to decrypt amount."
            );
        }

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $plain)) {
            throw new RuntimeException(
                "Budget #{$budgetId}: unsupported decimal amount."
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $plain, 2),
            2,
            ''
        );

        $minor = ltrim(
            $whole . str_pad($fraction, 2, '0'),
            '0'
        );

        $minor = $minor === '' ? '0' : $minor;
        $maximum = (string) PHP_INT_MAX;

        if (
            strlen($minor) > strlen($maximum)
            || (
                strlen($minor) === strlen($maximum)
                && strcmp($minor, $maximum) > 0
            )
        ) {
            throw new RuntimeException(
                "Budget #{$budgetId}: amount exceeds integer range."
            );
        }

        return (int) $minor;
    }

    /**
     * Resolve a category using the original category's
     * import ledger entry.
     */
    private function mappedCategory(
        int $legacyUserId,
        User $user,
        int $legacyCategoryId
    ): int {
        $mapping = LegacyImportRecord::query()
            ->where('source', self::SOURCE)
            ->where('record_type', 'category')
            ->where('legacy_id', $legacyCategoryId)
            ->where('legacy_user_id', $legacyUserId)
            ->where('new_user_id', $user->id)
            ->first();

        if (! $mapping) {
            throw new RuntimeException(
                "Legacy category #{$legacyCategoryId} has no import mapping."
            );
        }

        $category = $user->categories()
            ->find($mapping->new_record_id);

        if (! $category || $category->type !== 'expense') {
            throw new RuntimeException(
                "Legacy category #{$legacyCategoryId} is missing or is not an expense category."
            );
        }

        return (int) $category->id;
    }

    private function importRecord(int $legacyBudgetId): ?LegacyImportRecord
    {
        return LegacyImportRecord::query()
            ->where('source', self::SOURCE)
            ->where('record_type', 'budget')
            ->where('legacy_id', $legacyBudgetId)
            ->first();
    }

    private function formatAmount(int $minorUnits): string
    {
        $whole = intdiv($minorUnits, 100);
        $cents = $minorUnits % 100;

        return number_format($whole)
            . '.'
            . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }
}
