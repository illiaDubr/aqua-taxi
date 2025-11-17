<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use App\Console\Commands\RevokeExpiredFactoryVerifications;

class Kernel extends HttpKernel
{
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];
    protected $routeMiddleware = [
        // ...
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
    ];
    protected $commands = [
    \App\Console\Commands\IpayStatusCommand::class,
];
protected function schedule(Schedule $schedule)
{
    // запускать каждую минуту/каждые 5 минут — выбери по нагрузке
    $schedule->command('factories:revoke-expired')->everyMinute()->withoutOverlapping()->runInBackground();

    // либо реже:
    // $schedule->command('factories:revoke-expired')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
}
}

