<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DriverPayment;
use App\Models\Driver;
use Illuminate\Support\Facades\Http;

class DriverPaymentController extends Controller
{
    public function initiatePayment(Request $request)
{
    $request->validate([
        'amount' => 'required|numeric|min:1',
    ]);

    $driver = auth()->user();
    if (!$driver) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $amount = intval($request->input('amount') * 100); // в копійках
    $merchantId = '2023';
    $salt = bin2hex(random_bytes(16));
    $callbackUrl = url('/map?ipay=success');

    $signatureString = $merchantId . $salt . $amount . 'UAH' . 'Поповнення балансу водія';
    $sign = hash('sha512', $signatureString . 'секретний_ключ'); // замени на реальний ключ

    $xml = <<<XML
<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<payment>
    <auth>
        <mch_id>{$merchantId}</mch_id>
        <salt>{$salt}</salt>
        <sign>{$sign}</sign>
    </auth>
    <urls>
        <good>{$callbackUrl}</good>
        <bad>{$callbackUrl}</bad>
        <auto_redirect_good>1</auto_redirect_good>
        <auto_redirect_bad>1</auto_redirect_bad>
    </urls>
    <transactions>
        <transaction>
            <amount>{$amount}</amount>
            <currency>UAH</currency>
            <desc>Поповнення балансу водія</desc>
            <info>{"driver_id":{$driver->id}}</info>
            <smch_id>4301</smch_id>
        </transaction>
    </transactions>
    <lifetime>0.1</lifetime>
    <lang>ua</lang>
</payment>
XML;

    $response = Http::withHeaders([
        'Content-Type' => 'application/xml',
    ])->post('https://checkout.ipay.ua/api/checkout', $xml);

    if (!$response->ok()) {
        \Log::error('❌ iPay error', ['body' => $response->body()]);
        return response()->json(['error' => 'iPay error', 'details' => $response->body()], 400);
    }

    $body = simplexml_load_string($response->body());
    $url = (string) $body->url ?? null;

    if (!$url) {
        return response()->json(['error' => 'No payment URL returned'], 400);
    }

    // Сохраняем платёж
    DriverPayment::create([
        'driver_id' => $driver->id,
        'order_id' => uniqid('ipay_'),
        'amount' => $request->input('amount'),
        'status' => 'pending',
        'response_data' => $response->body(),
    ]);

    return
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
