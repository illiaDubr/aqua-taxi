<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders', function ($user = null) {
    return true; // в проде стоит делать auth-проверку
});
