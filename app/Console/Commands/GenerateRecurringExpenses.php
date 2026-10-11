<?php

namespace App\Console\Commands;

use App\Models\RecurringExpense;
use App\Services\RecurringExpenseScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'recurring:generate
                            {--days=30 : Number of days to generate ahead}';

    protected $description =
        'Generate pending recurring expense occurrences';

    public function handle(
        RecurringExpenseScheduleService $scheduler
    ): int {
        $days = filter_var(
            $this->option('days'),
            FILTER_VALIDATE_INT
        );

        if ($days === false || $days < 0 || $days > 365) {
            $this->error(
                'The --days option must be between 0 and 365.'
            );

            return self::FAILURE;
        }

        $today = CarbonImmutable::today();
        $throughDate = $today->addDays($days);

        $generated = 0;
        $processed = 0;
        $failed = 0;

        $this->info(
            'Generating recurring payments through '
            . $throughDate->toDateString()
        );

        RecurringExpense::query()
            ->active()
            ->with('user')
            ->chunkById(100, function ($templates) use (
                $scheduler,
                $throughDate,
                &$generated,
                &$processed,
                &$failed
            ) {
                foreach ($templates as $template) {
                    $processed++;

                    try {
                        $user = $template->user;

                        if (! $user) {
                            throw new \RuntimeException(
                                'Recurring template has no user.'
                            );
                        }

                        $created = $scheduler->generate(
                            $user,
                            $template,
                            $throughDate
                        );

                        $generated += $created;
                    } catch (Throwable $exception) {
                        $failed++;

                        Log::error(
                            'Recurring expense generation failed',
                            [
                                'recurring_expense_id' =>
                                    $template->id,
                                'user_id' => $template->user_id,
                                'error' => $exception->getMessage(),
                            ]
                        );

                        $this->warn(
                            'Failed to generate schedule #'
                            . $template->id
                        );
                    }
                }
            });

        Log::info('Recurring expense generation completed', [
            'processed' => $processed,
            'generated' => $generated,
            'failed' => $failed,
            'through_date' => $throughDate->toDateString(),
        ]);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Schedules processed', $processed],
                ['Occurrences generated', $generated],
                ['Failed schedules', $failed],
            ]
        );

        if ($failed > 0) {
            $this->error(
                'Generation completed with failures. Check the logs.'
            );

            return self::FAILURE;
        }

        $this->info(
            'Recurring payments generated successfully.'
        );

        return self::SUCCESS;
    }
}
