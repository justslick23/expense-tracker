<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PreviewLegacyFinanceImport extends Command
{
    protected $signature = 'legacy:preview
        {--user=1 : Legacy user ID}';

    protected $description =
        'Validate legacy finance data before importing it';

    private array $errors = [];

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
                'The destination database must contain exactly one user.'
            );
            return self::FAILURE;
        }

        $user = User::query()->firstOrFail();

        $this->info('Legacy Finance Migration Preview');
        $this->line('Mode: READ ONLY');
        $this->line('Legacy user: ' . $legacyUserId);
        $this->line('Destination user: ' . $user->id);

        $connectionName = config('legacy.connection', 'legacy');

        try {
            $legacy = DB::connection($connectionName);
            $legacy->getPdo();

            $encrypter = $this->makeEncrypter();
        } catch (Throwable $exception) {
            $this->error('Could not initialize legacy connection or encryption.');
            return self::FAILURE;
        }

        $requiredTables = [
            'categories',
            'incomes',
            'expenses',
            'budgets',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::connection($connectionName)->hasTable($table)) {
                $this->error("Missing legacy table: {$table}");
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Legacy record validation');

        $categories = $legacy->table('categories')
            ->where('user_id', $legacyUserId)
            ->get();

        $categoryIds = $categories
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])
            ->all();

        $records = [
            'categories' => $categories->count(),
            'incomes' => 0,
            'expenses' => 0,
            'budgets' => 0,
        ];

        $dates = [];
        $budgetMonths = [];

        foreach (['incomes', 'expenses', 'budgets'] as $table) {
            $legacy->table($table)
                ->where('user_id', $legacyUserId)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use (
                    $table,
                    $encrypter,
                    $categoryIds,
                    &$records,
                    &$dates,
                    &$budgetMonths
                ) {
                    foreach ($rows as $row) {
                        $records[$table]++;

                        $fields = ['amount'];

                        foreach ($fields as $field) {
                            $this->validateAmount(
                                $encrypter,
                                $table,
                                $row,
                                $field
                            );
                        }

                        if (in_array($table, ['expenses', 'budgets'], true)) {
                            if (
                                $row->category_id === null
                                || ! isset($categoryIds[(string) $row->category_id])
                            ) {
                                $this->errors[] =
                                    "{$table} #{$row->id}: category belongs to another user or is missing.";
                            }
                        }

                        if ($table === 'incomes' || $table === 'expenses') {
                            $date = $this->validateDate(
                                $row->date,
                                "{$table} #{$row->id}"
                            );

                            if ($date) {
                                $dates[] = $date;

                                if (count($dates) <= 5) {
                                    $this->line(
                                        "{$table} #{$row->id} period candidate: "
                                        . $this->periodLabel($date)
                                    );
                                }
                            }
                        }

                        if ($table === 'budgets') {
                            $month = (int) $row->month;
                            $year = (int) $row->year;

                            if (
                                $month < 1 || $month > 12
                                || $year < 2000 || $year > 2100
                            ) {
                                $this->errors[] =
                                    "budgets #{$row->id}: invalid month/year.";
                            } else {
                                $budgetMonths[
                                    sprintf('%04d-%02d', $year, $month)
                                ] = true;
                            }
                        }
                    }
                });
        }

        $this->table(
            ['Table', 'Records'],
            collect($records)
                ->map(fn ($count, $name) => [$name, $count])
                ->values()
                ->all()
        );

        $this->newLine();
        $this->info('Category mapping');

        $duplicateGroups = $categories
            ->groupBy(fn ($c) =>
                strtolower(trim($c->type . ':' . $c->name))
            )
            ->filter(fn ($group) => $group->count() > 1);

        $this->line(
            'Legacy categories: ' . $categories->count()
        );

        $this->line(
            'Duplicate category name/type groups: '
            . $duplicateGroups->count()
        );

        foreach ($duplicateGroups as $key => $group) {
            $this->errors[] =
                'Conflicting legacy category identities: ' . $key;
        }

        $this->newLine();
        $this->info('Historical financial periods');

        $uniquePeriods = [];

        foreach ($dates as $date) {
            $uniquePeriods[$this->periodLabel($date)] = true;
        }

        $this->line(
            'Transaction period candidates: '
            . count($uniquePeriods)
        );

        if ($dates !== []) {
            sort($dates);

            $this->line('Earliest transaction: ' . $dates[0]);
            $this->line('Latest transaction: ' . end($dates));
        }

        $this->line(
            'Distinct legacy budget months: '
            . count($budgetMonths)
        );

        $this->warn(
            'Budget month/year interpretation is not yet confirmed.'
        );

        $this->newLine();
        $this->info('Destination financial period structure');

        if (Schema::hasTable('financial_periods')) {
            $this->line(
                'Existing periods: '
                . $user->financialPeriods()->count()
            );

            $this->line(
                'Columns: '
                . implode(
                    ', ',
                    Schema::getColumnListing('financial_periods')
                )
            );
            foreach (Schema::getIndexes('financial_periods') as $index) {
                $this->line(
                    'Index: '
                    . $index['name']
                    . ' | Unique: '
                    . (! empty($index['unique']) ? 'Yes' : 'No')
                    . ' | Columns: '
                    . implode(', ', $index['columns'])
                );
            } {
                $this->line(
                    'SQLite index: ' . ($index->name ?? 'unknown')
                );
            }
        }

        $this->newLine();

        if ($this->errors !== []) {
            $this->error(
                count($this->errors) . ' validation issue(s) found.'
            );

            foreach (array_slice($this->errors, 0, 20) as $error) {
                $this->warn($error);
            }

            if (count($this->errors) > 20) {
                $this->warn('Additional issues not displayed.');
            }

            return self::FAILURE;
        }

        $this->info('All inspected monetary values decrypted successfully.');
        $this->info('Legacy data validation completed.');
        $this->warn('No records have been imported.');

        return self::SUCCESS;
    }

    private function makeEncrypter(): Encrypter
    {
        $configuredKey = config('legacy.key');

        if (! is_string($configuredKey) || $configuredKey === '') {
            throw new \RuntimeException('Missing legacy key.');
        }

        $key = str_starts_with($configuredKey, 'base64:')
            ? base64_decode(substr($configuredKey, 7), true)
            : $configuredKey;

        if ($key === false) {
            throw new \RuntimeException('Invalid legacy base64 key.');
        }

        return new Encrypter(
            $key,
            config('legacy.cipher', 'AES-256-CBC')
        );
    }

    private function validateAmount(
        Encrypter $encrypter,
        string $table,
        object $row,
        string $field
    ): void {
        try {
            $plain = $encrypter->decryptString(
                (string) $row->{$field}
            );

            $plain = trim($plain);

            if (! preg_match(
                '/^\d+(?:\.\d{1,2})?$/',
                $plain
            )) {
                throw new \RuntimeException(
                    'Invalid monetary format.'
                );
            }

            // Exact decimal conversion without floating-point arithmetic.
            [$whole, $fraction] = array_pad(
                explode('.', $plain, 2),
                2,
                ''
            );

            $cents = str_pad($fraction, 2, '0');

            $minor = ltrim($whole . $cents, '0');
            $minor = $minor === '' ? '0' : $minor;

            $max = (string) PHP_INT_MAX;

            if (
                strlen($minor) > strlen($max)
                || (
                    strlen($minor) === strlen($max)
                    && strcmp($minor, $max) > 0
                )
            ) {
                throw new \RuntimeException('Amount exceeds integer range.');
            }
        } catch (Throwable $exception) {
            $this->errors[] =
                "{$table} #{$row->id}.{$field}: invalid encrypted amount or decimal format.";
        }
    }

    private function validateDate(
        ?string $value,
        string $record
    ): ?string {
        try {
            $date = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                (string) $value
            );

            if (
                ! $date
                || $date->toDateString() !== $value
            ) {
                throw new \RuntimeException('Invalid date.');
            }

            return $date->toDateString();
        } catch (Throwable $exception) {
            $this->errors[] =
                "{$record}: invalid date.";
            return null;
        }
    }

    /**
     * Candidate period labels:
     * 2026-09-26 through 2026-10-25 => 2026-10.
     *
     * This must be confirmed against the old budgeting rules.
     */
    private function periodLabel(string $date): string
    {
        $day = (int) substr($date, 8, 2);

        $periodMonth = CarbonImmutable::parse($date)
            ->startOfMonth();

        if ($day >= 26) {
            $periodMonth = $periodMonth->addMonth();
        }

        return $periodMonth->format('Y-m');
    }
}
