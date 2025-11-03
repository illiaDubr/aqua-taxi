<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DriverPayment;
use App\Models\Driver;

class DriverPaymentController extends Controller
{
    public function initiatePayment(Request $request)
    {
        $amount = floatval($request->input('amount'));
        $driver = auth()->user(); // предполагается, что водитель аутентифицирован

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

        $params = [
            'MERCHANT_ID' => $merchantId,
            'ORDER_ID' => $orderId,
            'AMOUNT' => number_format($amount, 2, '.', ''),
            'CURRENCY' => 'UAH',
            'DESCRIPTION' => 'Поповнення балансу водія',
            'SUCCESS_URL' => $callbackUrl,
            'FAIL_URL' => $callbackUrl,
        ];

        $signature = hash('sha256', implode('', $params) . $merchantKey);

        return response()->json([
            'url' => 'https://secure.ipay.ua/order/',
            'params' => array_merge($params, ['SIGNATURE' => $signature]),
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

        if ($data['STATUS'] === 'SUCCESS') {
            $payment->status = 'success';
            $payment->response_data = json_encode($data);
            $payment->save();

            $driver = Driver::find($payment->driver_id);
            if ($driver) {
                $driver->balance += floatval($data['AMOUNT']);
                $driver->save();
            }

            return response('OK', 200);
        }

        $payment->status = 'failed';
        $payment->response_data = json_encode($data);
        $payment->save();

        return response('Payment failed', 400);
    }
}
