<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\FinancialPeriod;
use App\Models\LegacyImportRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportLegacyFinanceData extends Command
{
    protected $signature = 'legacy:import
        {--user=1 : Legacy user ID}
        {--commit : Write imported data}
        {--confirm-period-convention : Confirm the legacy 26th-to-25th period convention}';

    protected $description =
        'Preview or import legacy categories, income and expenses';

    private const SOURCE = 'old_finance_tracker';

    public function handle(): int
    {
        $legacyUserId = filter_var(
            $this->option('user'),
            FILTER_VALIDATE_INT
        );

        if ($legacyUserId === false || $legacyUserId < 1) {
            $this->error('Invalid legacy user ID.');
            return self::FAILURE;
        }

        if (User::query()->count() !== 1) {
            $this->error(
                'The destination must contain exactly one user.'
            );
            return self::FAILURE;
        }

        $user = User::query()->firstOrFail();

        $this->info('Legacy Finance Data Importer');
        $this->line(
            'Mode: ' . (
                $this->option('commit')
                    ? 'COMMIT'
                    : 'DRY RUN'
            )
        );
        $this->line('Legacy user: ' . $legacyUserId);
        $this->line('Destination user: ' . $user->id);

        try {
            $legacy = DB::connection(
                config('legacy.connection', 'legacy')
            );

            $legacy->getPdo();

            $encrypter = $this->makeEncrypter();

            $this->validateDestinationSchema();

            $categories = $legacy->table('categories')
                ->where('user_id', $legacyUserId)
                ->orderBy('id')
                ->get();

            $incomes = $legacy->table('incomes')
                ->where('user_id', $legacyUserId)
                ->orderBy('id')
                ->get();

            $expenses = $legacy->table('expenses')
                ->where('user_id', $legacyUserId)
                ->orderBy('id')
                ->get();

            $prepared = $this->prepare(
                $user,
                $categories,
                $incomes,
                $expenses,
                $encrypter
            );

            $this->displayPreview($prepared);

            if (! $this->option('commit')) {
                $this->info(
                    'Dry run complete. No records were changed.'
                );

                return self::SUCCESS;
            }

            if (! $this->option('confirm-period-convention')) {
                $this->error(
                    'Commit requires --confirm-period-convention.'
                );

                $this->warn(
                    'Verify that legacy dates follow the 26th-to-25th cycle first.'
                );

                return self::FAILURE;
            }

            $result = DB::transaction(function () use (
                $user,
                $legacyUserId,
                $prepared
            ) {
                return $this->import(
                    $user,
                    $legacyUserId,
                    $prepared
                );
            }, 3);

            $this->newLine();
            $this->info('Import committed successfully.');

            $this->table(
                ['Record type', 'Imported'],
                collect($result)
                    ->map(fn ($count, $type) => [$type, $count])
                    ->values()
                    ->all()
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->newLine();
            $this->error(
                'Import stopped: ' . $exception->getMessage()
            );

            $this->warn(
                'Any changes made in this import transaction were rolled back.'
            );

            return self::FAILURE;
        }
    }

    /**
     * Check destination columns before any writes.
     */
    private function validateDestinationSchema(): void
    {
        $requirements = [
            'legacy_import_records' => [
                'source', 'record_type', 'legacy_id',
                'legacy_user_id', 'new_user_id',
                'new_record_id',
            ],
            'categories' => [
                'id', 'user_id', 'name', 'type',
            ],
            'financial_periods' => [
                'id', 'user_id', 'name',
                'start_date', 'end_date',
                'status', 'is_open', 'source',
            ],
            'incomes' => [
                'id', 'user_id', 'category_id',
                'financial_period_id', 'amount',
                'income_date',
            ],
            'expenses' => [
                'id', 'user_id', 'category_id',
                'financial_period_id', 'amount',
                'expense_date', 'description',
                'merchant',
            ],
        ];

        foreach ($requirements as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Missing destination table: {$table}"
                );
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException(
                        "Missing destination column: {$table}.{$column}"
                    );
                }
            }
        }
    }

    /**
     * Create an encrypter using the old application's key.
     */
    private function makeEncrypter(): Encrypter
    {
        $configuredKey = config('legacy.key');

        if (! is_string($configuredKey) || $configuredKey === '') {
            throw new RuntimeException(
                'LEGACY_APP_KEY is missing.'
            );
        }

        $key = str_starts_with($configuredKey, 'base64:')
            ? base64_decode(substr($configuredKey, 7), true)
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
     * Decrypt and convert to exact integer minor units.
     * No floating-point currency conversion.
     */
    private function amount(
        Encrypter $encrypter,
        mixed $encrypted,
        string $context
    ): int {
        try {
            $plain = trim(
                $encrypter->decryptString((string) $encrypted)
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "{$context}: unable to decrypt amount."
            );
        }

        if (! preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $plain
        )) {
            throw new RuntimeException(
                "{$context}: invalid decimal amount."
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
                "{$context}: amount exceeds integer range."
            );
        }

        return (int) $minor;
    }

    private function date(string $value, string $context): string
    {
        try {
            $date = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $value
            );

            if (
                ! $date
                || $date->toDateString() !== $value
            ) {
                throw new RuntimeException('Invalid date.');
            }

            return $date->toDateString();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "{$context}: invalid date."
            );
        }
    }

    /**
     * Determine the candidate 26th-to-25th period.
     *
     * Example:
     * 2025-05-26 through 2025-06-25.
     */
   

     private function periodForDate(string $date): array
     {
         $transactionDate = CarbonImmutable::parse($date);
     
         if ($transactionDate->day >= 26) {
             $start = $transactionDate
                 ->startOfMonth()
                 ->addDays(25);
         } else {
             $start = $transactionDate
                 ->startOfMonth()
                 ->subMonthNoOverflow()
                 ->addDays(25);
         }
     
         $end = $start
             ->addMonthNoOverflow()
             ->subDay();
     
         // Respect the start date of the existing open period.
         // Historical periods must not overlap it.
         $currentOpenPeriod = FinancialPeriod::query()
             ->where('is_open', true)
             ->first();
     
         if ($currentOpenPeriod) {
             $openStart = CarbonImmutable::parse(
                 $currentOpenPeriod->start_date
             );
     
             if (
                 $start->lt($openStart)
                 && $end->gte($openStart)
             ) {
                 $end = $openStart->subDay();
             }
         }
     
         return [
             'name' => 'Legacy ' . $end->format('F Y'),
             'start_date' => $start->toDateString(),
             'end_date' => $end->toDateString(),
         ];
     }
     


    private function imported(string $type, int $legacyId): ?int
    {
        $record = LegacyImportRecord::query()
            ->where('source', self::SOURCE)
            ->where('record_type', $type)
            ->where('legacy_id', $legacyId)
            ->first();

        if (! $record) {
            return null;
        }

        return (int) $record->new_record_id;
    }

    /**
     * Validate all source records in memory.
     * Dry run and commit use the same prepared data.
     */
    private function prepare(
        User $user,
        $categories,
        $incomes,
        $expenses,
        Encrypter $encrypter
    ): array {
        $prepared = [
            'categories' => [],
            'income_categories' => [],
            'incomes' => [],
            'expenses' => [],
            'periods' => [],
            'existing' => [
                'categories' => 0,
                'incomes' => 0,
                'expenses' => 0,
            ],
        ];

        $legacyCategories = [];

        foreach ($categories as $category) {
            $name = trim((string) $category->name);
            $type = strtolower(trim((string) $category->type));

            if ($name === '' || ! in_array(
                $type,
                ['income', 'expense'],
                true
            )) {
                throw new RuntimeException(
                    "Invalid legacy category #{$category->id}."
                );
            }

            $legacyCategories[(int) $category->id] = [
                'name' => $name,
                'type' => $type,
            ];

            if ($this->imported('category', (int) $category->id)) {
                $prepared['existing']['categories']++;
            }

            $prepared['categories'][] = [
                'id' => (int) $category->id,
                'name' => $name,
                'type' => $type,
            ];
        }

        foreach ($incomes as $income) {
            $id = (int) $income->id;

            $date = $this->date(
                (string) $income->date,
                "Income #{$id}"
            );

            $amount = $this->amount(
                $encrypter,
                $income->amount,
                "Income #{$id}"
            );

            $source = trim((string) ($income->source ?? ''));

            if ($source === '') {
                $source = 'Other Income';
            }

            if (mb_strlen($source) > 150) {
                throw new RuntimeException(
                    "Income #{$id}: source name exceeds 150 characters."
                );
            }

            if ($this->imported('income', $id)) {
                $prepared['existing']['incomes']++;
            }

            $prepared['income_categories'][$source] = true;

            $prepared['incomes'][] = [
                'id' => $id,
                'date' => $date,
                'amount' => $amount,
                'source' => $source,
            ];

            $this->addPeriod($prepared, $date);
        }

        foreach ($expenses as $expense) {
            $id = (int) $expense->id;
            $categoryId = (int) $expense->category_id;

            if (! isset($legacyCategories[$categoryId])) {
                throw new RuntimeException(
                    "Expense #{$id}: legacy category not found."
                );
            }

            if ($legacyCategories[$categoryId]['type'] !== 'expense') {
                throw new RuntimeException(
                    "Expense #{$id}: category is not an expense category."
                );
            }

            $date = $this->date(
                (string) $expense->date,
                "Expense #{$id}"
            );

            $amount = $this->amount(
                $encrypter,
                $expense->amount,
                "Expense #{$id}"
            );

            if ($this->imported('expense', $id)) {
                $prepared['existing']['expenses']++;
            }

            $prepared['expenses'][] = [
                'id' => $id,
                'category_id' => $categoryId,
                'date' => $date,
                'amount' => $amount,
                'description' => $expense->description,
                'merchant' => $expense->merchant,
            ];

            $this->addPeriod($prepared, $date);
        }

        $this->validatePeriods(
            $user,
            $prepared['periods']
        );

        return $prepared;
    }

    private function addPeriod(
        array &$prepared,
        string $date
    ): void {
        $period = $this->periodForDate($date);

        $prepared['periods'][$period['start_date']] = $period;
    }

    /**
     * Reject overlapping destination periods before writing.
     * Existing matching periods can be reused.
     */
    private function validatePeriods(
        User $user,
        array $periods
    ): void {
        $existing = $user->financialPeriods()
            ->orderBy('start_date')
            ->get();

        foreach ($periods as $candidate) {
            $matching = $existing->first(
                fn ($period) =>
                    $period->start_date->toDateString()
                        === $candidate['start_date']
                    && $period->end_date?->toDateString()
                        === $candidate['end_date']
            );

            if ($matching) {
                continue;
            }

            foreach ($existing as $period) {
                $start = $period->start_date->toDateString();
                $end = $period->end_date?->toDateString()
                    ?? '9999-12-31';

                $overlap =
                    $candidate['start_date'] <= $end
                    && $candidate['end_date'] >= $start;

                if ($overlap) {
                    throw new RuntimeException(
                        'Historical period '
                        . $candidate['start_date']
                        . ' to '
                        . $candidate['end_date']
                        . ' overlaps existing period #'
                        . $period->id
                        . '. Resolve this before importing.'
                    );
                }
            }
        }
    }

    private function displayPreview(array $prepared): void
    {
        $this->newLine();
        $this->info('Import preview');

        $rows = [
            [
                'Categories',
                count($prepared['categories']),
                $prepared['existing']['categories'],
            ],
            [
                'Income',
                count($prepared['incomes']),
                $prepared['existing']['incomes'],
            ],
            [
                'Expenses',
                count($prepared['expenses']),
                $prepared['existing']['expenses'],
            ],
        ];

        $this->table(
            ['Type', 'Source records', 'Already imported'],
            $rows
        );

        $this->line(
            'Historical periods: '
            . count($prepared['periods'])
        );

        $this->line(
            'Distinct income sources: '
            . count($prepared['income_categories'])
        );

        $this->warn(
            'Budgets are excluded from this import.'
        );
    }

    private function import(
        User $user,
        int $legacyUserId,
        array $prepared
    ): array {
        $result = [
            'categories' => 0,
            'periods' => 0,
            'incomes' => 0,
            'expenses' => 0,
        ];

        $categoryMap = [];

        // Step A: Map source categories.
        foreach ($prepared['categories'] as $legacyCategory) {
            $oldId = $legacyCategory['id'];

            $mappedId = $this->imported('category', $oldId);

            if ($mappedId !== null) {
                $existing = $user->categories()
                    ->find($mappedId);

                if (! $existing) {
                    throw new RuntimeException(
                        "Missing mapped category for legacy #{$oldId}."
                    );
                }

                if ($existing->type !== $legacyCategory['type']) {
                    throw new RuntimeException(
                        "Mapped category #{$mappedId} has an unexpected type."
                    );
                }

                $categoryMap[$oldId] = $existing->id;
                continue;
            }

            $category = $user->categories()
                ->firstOrCreate([
                    'name' => $legacyCategory['name'],
                    'type' => $legacyCategory['type'],
                ]);

            $categoryMap[$oldId] = $category->id;

            $this->recordImport(
                'category',
                $oldId,
                $legacyUserId,
                $user->id,
                $category->id
            );

            $result['categories']++;
        }

        // Step B: Map income sources to income categories.
        $incomeCategories = [];

        foreach (
            array_keys($prepared['income_categories']) as $source
        ) {
            $category = $user->categories()
                ->firstOrCreate([
                    'name' => $source,
                    'type' => 'income',
                ]);

            $incomeCategories[$source] = $category->id;
        }

        // Step C: Create or reuse historical periods.
        $periodMap = [];

        foreach ($prepared['periods'] as $periodData) {
            $existing = $user->financialPeriods()
                ->whereDate(
                    'start_date',
                    $periodData['start_date']
                )
                ->whereDate(
                    'end_date',
                    $periodData['end_date']
                )
                ->first();

            if ($existing) {
                $periodMap[$periodData['start_date']] =
                    $existing->id;

                continue;
            }

            $period = $user->financialPeriods()->create([
                'name' => $periodData['name'],
                'start_date' => $periodData['start_date'],
                'end_date' => $periodData['end_date'],
                'status' => 'closed',
                'is_open' => false,
                'opening_balance' => 0,
                'closed_at' => null,
                'source' => 'legacy_import',
            ]);

            $periodMap[$periodData['start_date']] =
                $period->id;

            $result['periods']++;
        }

        // Step D: Import income records.
        foreach ($prepared['incomes'] as $income) {
            if ($this->imported('income', $income['id'])) {
                continue;
            }

            $period = $this->periodForDate($income['date']);

            $newIncome = $user->incomes()->create([
                'category_id' =>
                    $incomeCategories[$income['source']],
                'financial_period_id' =>
                    $periodMap[$period['start_date']],
                'amount' => $income['amount'],
                'income_date' => $income['date'],
                'description' => $income['source'],
            ]);

            $this->recordImport(
                'income',
                $income['id'],
                $legacyUserId,
                $user->id,
                $newIncome->id
            );

            $result['incomes']++;
        }

        // Step E: Import expenses.
        foreach ($prepared['expenses'] as $expense) {
            if ($this->imported('expense', $expense['id'])) {
                continue;
            }

            $period = $this->periodForDate($expense['date']);

            $newExpense = $user->expenses()->create([
                'category_id' =>
                    $categoryMap[$expense['category_id']],
                'financial_period_id' =>
                    $periodMap[$period['start_date']],
                'amount' => $expense['amount'],
                'expense_date' => $expense['date'],
                'description' => $expense['description'],
                'merchant' => $expense['merchant'],
            ]);

            $this->recordImport(
                'expense',
                $expense['id'],
                $legacyUserId,
                $user->id,
                $newExpense->id
            );

            $result['expenses']++;
        }

        return $result;
    }

    private function recordImport(
        string $type,
        int $oldId,
        int $oldUserId,
        int $newUserId,
        int $newRecordId
    ): void {
        LegacyImportRecord::query()->create([
            'source' => self::SOURCE,
            'record_type' => $type,
            'legacy_id' => $oldId,
            'legacy_user_id' => $oldUserId,
            'new_user_id' => $newUserId,
            'new_record_id' => $newRecordId,
            'imported_at' => now(),
        ]);
    }
}
