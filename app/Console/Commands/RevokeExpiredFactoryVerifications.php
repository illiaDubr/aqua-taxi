<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RevokeExpiredFactoryVerifications extends Command
{
    protected $signature = 'factories:revoke-expired {--batch=500}';
    protected $description = 'Revoke factory verification when verified_until has passed';

    public function handle()
    {
        $batchSize = (int)$this->option('batch') ?: 500;
        $now = Carbon::now()->toDateString(); // сравниваем по дате (yyyy-mm-dd)

        $this->info("Looking for factories with verified_until <= {$now} ...");

        // Условие: is_verified = true AND verified_until IS NOT NULL AND verified_until < now
        // Берём пачками, чтобы не грузить память
        $query = DB::table('factories')
            ->where('is_verified', true)
            ->whereNotNull('verified_until')
            ->whereDate('verified_until', '<', $now);

        $total = $query->count();
        if ($total === 0) {
            $this->info('No expired verified factories found.');
            return 0;
        }

        $this->info("Found {$total} expired factories. Revoking in batches of {$batchSize}.");

        $progress = $this->output->createProgressBar(ceil($total / $batchSize));
        $progress->start();

        try {
            // Обновляем пачки по id
            $query->orderBy('id')->chunkById($batchSize, function ($rows) use (&$progress, $now) {
                $ids = collect($rows)->pluck('id')->toArray();

                DB::table('factories')->whereIn('id', $ids)->update([
                    'is_verified'    => false,
                    'verified_until' => null,
                    // при желании можно добавить поля revoked_at/revoked_reason если они есть
                ]);

                // логирование
                Log::info('Revoked factory verifications', ['ids' => $ids, 'revoked_at' => $now]);

                $progress->advance();
            });

            $progress->finish();
            $this->newLine();
            $this->info("Revocation finished for {$total} factories.");
        } catch (\Throwable $e) {
            $this->error('Error during revocation: ' . $e->getMessage());
            Log::error('Error revoking factory verifications', ['exception' => $e]);
            return 2;
        }

        return 0;
    }
}
