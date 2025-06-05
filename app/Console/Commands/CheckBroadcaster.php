<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Broadcast;

class CheckBroadcaster extends Command
{
    protected $signature = 'debug:broadcaster';

    protected $description = 'Показать текущий broadcaster';

    public function handle()
    {
        $driver = get_class(Broadcast::connection());
        $this->info("Broadcast driver: {$driver}");
    }
}
