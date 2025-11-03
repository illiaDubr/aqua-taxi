<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DriverPayment;
use App\Models\Driver;

class DriverPaymentController extends Controller
{
    public function initiatePayment(Request $request)
{
    \Log::info('📥 Incoming /api/driver/pay', [
        'raw' => $request->getContent(),
        'parsed' => $request->all(),
        'amount' => $request->input('amount'),
        'driver' => auth()->user(),
        'headers' => $request->headers->all(),
    ]);

    $amount = floatval($request->input('amount'));
    $driver = auth()->user();

    if (!$driver || $amount <= 0) {
        return response()->json(['error' => 'Invalid driver or amount'], 400);
    }

        $merchantId = '5521';
        $merchantKey = '40fc2925da51789c7b7914f6e6e2bafa0ffc0bd3';

        $orderId = uniqid('ipay_');
        $callbackUrl = url('/map?ipay=success');

        // Сохраняем платёж
        DriverPayment::create([
            'driver_id' => $driver->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => 'pending',
        ]);

        // Формируем подпись строго по документации
        $signatureString = 
            $merchantId .
            $orderId .
            number_format($amount, 2, '.', '') .
            'UAH' .
            'Поповнення балансу водія' .
            $callbackUrl .
            $callbackUrl;

        $signature = hash('sha256', $signatureString . $merchantKey);

        $params = [
            'MERCHANT_ID' => $merchantId,
            'ORDER_ID' => $orderId,
            'AMOUNT' => number_format($amount, 2, '.', ''),
            'CURRENCY' => 'UAH',
            'DESCRIPTION' => 'Поповнення балансу водія',
            'SUCCESS_URL' => $callbackUrl,
            'FAIL_URL' => $callbackUrl,
            'SIGNATURE' => $signature,
        ];

        \Log::info('🔍 iPay init', [
            'driver_id' => $driver->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'params' => $params,
        ]);

        return response()->json([
            'url' => 'https://secure.ipay.ua/order/',
            'params' => $params,
        ]);
    }

    public function handleCallback(Request $request)
    {
        $merchantKey = '40fc2925da51789c7b7914f6e6e2bafa0ffc0bd3';
        $data = $request->all();

        $expectedSignature = hash('sha256',
            $data['MERCHANT_ID'] .
            $data['ORDER_ID'] .
            $data['AMOUNT'] .
            $data['CURRENCY'] .
            $data['DESCRIPTION'] .
            $data['STATUS'] .
            $merchantKey
        );

        if ($expectedSignature !== $data['SIGNATURE']) {
            \Log::warning('❌ Невірна підпис у callback', [
                'received' => $data['SIGNATURE'],
                'expected' => $expectedSignature
            ]);
            return response('Invalid signature', 400);
        }

        $payment = DriverPayment::where('order_id', $data['ORDER_ID'])->first();

        if (!$payment || $payment->status === 'success') {
            return response('Already processed or not found', 200);
        }

        $payment->status = $data['STATUS'] === 'SUCCESS' ? 'success' : 'failed';
        $payment->response_data = json_encode($data);
        $payment->save();

        if ($payment->status === 'success') {
            $driver = Driver::find($payment->driver_id);
            if ($driver) {
                $driver->balance += floatval($data['AMOUNT']);
                $driver->save();
            }
            return response('OK', 200);
        }

        return response('Payment failed', 400);
    }
}
