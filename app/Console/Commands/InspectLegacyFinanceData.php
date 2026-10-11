<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InspectLegacyFinanceData extends Command
{
    protected $signature = 'legacy:inspect
        {--user= : Inspect a specific legacy user ID}';

    protected $description =
        'Inspect legacy finance records without modifying data';

    public function handle(): int
    {
        $connectionName = config(
            'legacy.connection',
            'legacy'
        );

        $connection = DB::connection($connectionName);

        $this->info('Legacy Finance Tracker Inspection');
        $this->line('Mode: READ ONLY');

        try {
            $connection->getPdo();
        } catch (Throwable $e) {
            $this->error(
                'Could not connect to the legacy database.'
            );

            $this->warn(
                'Check the LEGACY_DB_* settings.'
            );

            return self::FAILURE;
        }

        $tables = [
            'users',
            'categories',
            'incomes',
            'expenses',
            'budgets',
        ];

        $this->newLine();
        $this->info('Database tables');

        $rows = [];

        foreach ($tables as $table) {
            $exists = Schema::connection(
                $connectionName
            )->hasTable($table);

            $rows[] = [
                $table,
                $exists ? 'Found' : 'Missing',
                $exists
                    ? $connection->table($table)->count()
                    : '-',
            ];
        }

        $this->table(
            ['Table', 'Status', 'Total records'],
            $rows
        );

        $this->newLine();
        $this->info('Table columns');

        foreach ($tables as $table) {
            if (! Schema::connection(
                $connectionName
            )->hasTable($table)) {
                continue;
            }

            $columns = Schema::connection(
                $connectionName
            )->getColumnListing($table);

            $this->line(
                $table . ': ' . implode(', ', $columns)
            );
        }

        $this->inspectUsers($connectionName);
        $this->inspectDates($connectionName);
        $this->inspectEncryption($connectionName);

        $this->newLine();

        $this->info(
            'Inspection complete. No data was modified.'
        );

        return self::SUCCESS;
    }

    private function inspectUsers(
        string $connectionName
    ): void {
        $this->newLine();
        $this->info('Records by legacy user');

        $selectedUser = $this->option('user');

        if (
            $selectedUser !== null
            && (
                ! ctype_digit((string) $selectedUser)
                || (int) $selectedUser < 1
            )
        ) {
            $this->error(
                '--user must be a positive integer.'
            );

            return;
        }

        foreach ([
            'categories',
            'incomes',
            'expenses',
            'budgets',
        ] as $table) {
            if (! Schema::connection(
                $connectionName
            )->hasTable($table)) {
                continue;
            }

            if (! Schema::connection(
                $connectionName
            )->hasColumn($table, 'user_id')) {
                continue;
            }

            $query = DB::connection(
                $connectionName
            )->table($table);

            if ($selectedUser !== null) {
                $query->where(
                    'user_id',
                    (int) $selectedUser
                );
            }

            $results = $query
                ->select('user_id')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('user_id')
                ->orderBy('user_id')
                ->get();

            $this->line($table);

            $this->table(
                ['Legacy user ID', 'Records'],
                $results->map(fn ($row) => [
                    $row->user_id,
                    $row->total,
                ])->all()
            );
        }
    }

    private function inspectDates(
        string $connectionName
    ): void {
        $this->newLine();
        $this->info('Historical transaction dates');

        foreach (['incomes', 'expenses'] as $table) {
            if (! Schema::connection(
                $connectionName
            )->hasTable($table)) {
                continue;
            }

            if (! Schema::connection(
                $connectionName
            )->hasColumn($table, 'date')) {
                continue;
            }

            $query = DB::connection(
                $connectionName
            )->table($table);

            if ($this->option('user') !== null) {
                $query->where(
                    'user_id',
                    (int) $this->option('user')
                );
            }

            $dates = $query
                ->selectRaw(
                    'MIN(date) as earliest, MAX(date) as latest'
                )
                ->first();

            $this->line(
                "{$table}: "
                . ($dates->earliest ?? 'No records')
                . ' to '
                . ($dates->latest ?? 'No records')
            );
        }
    }

    private function inspectEncryption(
        string $connectionName
    ): void {
        $this->newLine();
        $this->info('Encryption check');

        $configuredKey = config('legacy.key');

        if (! $configuredKey) {
            $this->warn(
                'LEGACY_APP_KEY is not configured.'
            );

            return;
        }

        $key = str_starts_with(
            $configuredKey,
            'base64:'
        )
            ? base64_decode(
                substr($configuredKey, 7),
                true
            )
            : $configuredKey;

        $cipher = config(
            'legacy.cipher',
            'AES-256-CBC'
        );

        try {
            $encrypter = new Encrypter($key, $cipher);
        } catch (Throwable $e) {
            $this->error(
                'Invalid legacy encryption configuration.'
            );

            return;
        }

        foreach ([
            'incomes' => ['amount'],
            'expenses' => ['amount'],
            'budgets' => ['amount', 'spent'],
        ] as $table => $fields) {
            if (! Schema::connection(
                $connectionName
            )->hasTable($table)) {
                continue;
            }

            foreach ($fields as $field) {
                if (! Schema::connection(
                    $connectionName
                )->hasColumn($table, $field)) {
                    continue;
                }

                $query = DB::connection(
                    $connectionName
                )->table($table)
                    ->whereNotNull($field);

                if ($this->option('user') !== null) {
                    $query->where(
                        'user_id',
                        (int) $this->option('user')
                    );
                }

                // We inspect only one sample.
                // Never display the encrypted or decrypted value.
                $value = $query->value($field);

                if ($value === null) {
                    $result = 'No sample available';
                } else {
                    try {
                        $plain = $encrypter->decryptString(
                            (string) $value
                        );

                        $result = is_numeric($plain)
                            ? 'Decrypted numeric value successfully'
                            : 'Decrypted, but value is not numeric';
                    } catch (Throwable $e) {
                        $result = 'Could not decrypt sample';
                    }
                }

                $this->line(
                    "{$table}.{$field}: {$result}"
                );
            }
        }
    }
}
