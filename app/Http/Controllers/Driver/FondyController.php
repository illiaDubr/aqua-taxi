<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FondyController extends Controller
{
    public function initialize(Request $request)
    {
        $order_id = uniqid('order_');
        $amount = (int) $request->amount * 100;

        $data = [
            'order_id' => $order_id,
            'merchant_id' => env('FONDY_MERCHANT_ID'),
            'order_desc' => 'Поповнення балансу Aqua Taxi',
            'currency' => 'UAH',
            'amount' => $amount,
            'response_url' => route('fondy.callback'),
            'server_callback_url' => route('fondy.callback'),
        ];

        $data['signature'] = $this->generateSignature($data);

        return response()->json([
            'url' => 'https://pay.fondy.eu/api/checkout/redirect/',
            'params' => $data,
        ]);
    }

    public function callback(Request $request)
    {
        // тут можно логировать или обновлять статус
        return response('OK');
    }

    private function generateSignature(array $data): string
    {
        $secret = env('FONDY_SECRET_KEY');
        ksort($data);
        array_unshift($data, $secret);
        return sha1(implode('|', $data));
    }
}

