<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Обязательно для Reverb
        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
