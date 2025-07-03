<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Для простоты примера: проверка, что текущий пользователь — админ
        // Здесь нужно сделать свою логику, например, если у юзера есть поле role === 'admin'
        if (auth()->check() && auth()->user()->email === 'admin@example.com') {
            return $next($request);
        }

        return response()->json(['message' => 'Недостаточно прав'], 403);
    }
}
