<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FactoryPayment;
use App\Models\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\DB;

class FactoryPaymentController extends Controller
{
    protected function getEndpoint(): string
    {
        return config('ipay.use_sandbox')
            ? config('ipay.sandbox_endpoint')
            : config('ipay.endpoint');
    }

    protected function getMerchantId(): string
    {
        return config('ipay.merchant_id');
    }

    protected function getSignKey(): string
    {
        return config('ipay.sign_key');
    }

    private function factory(Request $request): ?Factory
{
    $userFromRequest = $request->user(); 
    $userFromGuard   = Auth::guard('factory')->user();

    $factory = $userFromRequest ?: $userFromGuard;

    if (! $factory) {
        Log::warning('Factory auth failed: no authenticated factory found', [
            'bearer_present' => $request->bearerToken() ? true : false,
        ]);
        return null; // вместо JsonResponse
    }

    return $factory;
}

        $factory = $userFromRequest ?: $userFromGuard;

        if (! $factory) {
            Log::warning('Factory auth failed: no authenticated factory found', [
                'bearer_present' => $bearer ? true : false,
            ]);
            return response()->json(['error' => 'Необхідна авторизація фабрики'], 401);
        }

        return $factory;
    }

    public function initiatePayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $factory = $this->factory($request);

        $amountGrn   = (float) $request->input('amount');
        $amountCents = (int) round($amountGrn * 100);

        $merchantId = $this->getMerchantId();
        $signKey    = $this->getSignKey();
        $systemKey  = config('ipay.system_key');
        $endpoint   = $this->getEndpoint();

        if (!$merchantId || !$signKey || !$systemKey) {
            return response()->json(['error' => 'Payment gateway not configured'], 500);
        }

        $salt = sha1(microtime(true));
        $sign = hash_hmac('sha512', $salt, $signKey);

        $callbackGood = url('/api/factory/payment/check');
        $callbackBad  = url('/api/factory/payment/check');

        $infoJson = json_encode(['factory_id' => $factory->id]);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<payment>
    <auth>
        <mch_id>{$merchantId}</mch_id>
        <salt>{$salt}</salt>
        <sign>{$sign}</sign>
    </auth>
    <urls>
        <good>{$callbackGood}</good>
        <bad>{$callbackBad}</bad>
        <auto_redirect_good>1</auto_redirect_good>
        <auto_redirect_bad>1</auto_redirect_bad>
    </urls>
    <transactions>
        <transaction>
            <amount>{$amountCents}</amount>
            <currency>UAH</currency>
            <desc>Поповнення балансу фабрики</desc>
            <info>{$infoJson}</info>
        </transaction>
    </transactions>
    <lifetime>0.5</lifetime>
    <lang>ua</lang>
</payment>
XML;

        try {
            $response = Http::asForm()->withHeaders([
                'Accept' => 'application/xml',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post($endpoint, ['data' => $xml]);

            if (!$response->successful()) {
                return response()->json(['error' => 'iPay error', 'details' => $response->body()], 400);
            }

            libxml_use_internal_errors(true);
            $bodyXml = simplexml_load_string($response->body());
            if (!$bodyXml) {
                return response()->json(['error' => 'Invalid response from payment gateway'], 500);
            }

            $respPid    = (string) ($bodyXml->pid ?? '');
            $respUrl    = (string) ($bodyXml->url ?? '');

            $payment = FactoryPayment::create([
                'factory_id'    => $factory->id,
                'order_id'      => uniqid('ipay_'),
                'pid'           => $respPid,
                'amount'        => $amountGrn,
                'amount_cents'  => $amountCents,
                'status'        => 'pending',
                'credited'      => false,
                'response_data' => (string)$response->body(),
            ]);

            return response()->json([
                'url'       => $respUrl,
                'payment_id'=> $payment->id,
                'order_id'  => $payment->order_id,
                'pid'       => $respPid
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    public function checkStatus(Request $request)
    {
        $orderId = $request->query('order_id');

        $query = FactoryPayment::query()->where('status', 'pending');
        if ($orderId) {
            $query->where('order_id', $orderId);
        } else {
            $query->orderByDesc('created_at');
        }

        $payment = $query->latest()->first();

        if (!$payment) {
            Log::warning('checkStatus: no pending factory payment found', ['order_id' => $orderId]);
            return redirect('/factoryPanel?error=no_payment');
        }

        $merchantId = config('ipay.merchant_id');
        $sign_key   = config('ipay.sign_key');
        $systemKey  = config('ipay.system_key');
        $salt = sha1(microtime(true));
        $sign = hash_hmac('sha512', $salt, $sign_key);

        $xml = <<<XML
<payment>
    <auth>
        <mch_id>{$merchantId}</mch_id>
        <salt>{$salt}</salt>
        <sign>{$sign}</sign>
    </auth>
    <action>status</action>
    <pid>{$payment->pid}</pid>
</payment>
XML;

        $endpoint = $this->getEndpoint();
        $response = Http::asForm()->withHeaders([
            'Accept' => 'application/xml',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post($endpoint, ['data' => $xml]);

        Log::info('checkStatus: iPay status request', [
            'payment_id' => $payment->id,
            'order_id'   => $payment->order_id,
            'pid'        => $payment->pid,
            'status'     => $response->status(),
            'body_snippet' => substr($response->body(), 0, 200),
        ]);

        if (!$response->successful()) {
            Log::error('checkStatus: iPay status fetch failed', [
                'status' => $response->status(),
                'body'   => $response->body()
            ]);
            return redirect('/factoryPanel?ipay=fail');
        }

        $body = $response->body();
        if (empty($body) || strpos(trim($body), '<') !== 0) {
            Log::error('checkStatus: invalid XML response', ['raw' => $body]);
            return redirect('/factoryPanel?ipay=fail');
        }

        libxml_use_internal_errors(true);
        $bodyXml = simplexml_load_string($body);
        if (!$bodyXml) {
            Log::error('checkStatus: XML parse failed', ['raw' => $body]);
            return redirect('/factoryPanel?ipay=fail');
        }

        $status = (string) ($bodyXml->status ?? '');

        Log::info('checkStatus: iPay returned', [
            'order_id' => $payment->order_id,
            'pid'      => $payment->pid,
            'status'   => $status
        ]);

        if ($status === '5') {
            if ($payment->status !== 'success') {
                DB::transaction(function () use ($payment) {
                    $factory = Factory::find($payment->factory_id);

                    if ($factory) {
                        $toAdd = $payment->amount ?? (($payment->amount_cents ?? 0) / 100);
                        $factory->balance = (float)$factory->balance + (float)$toAdd;
                        $factory->save();

                        $payment->status = 'success';
                        $payment->credited = true;
                        $payment->save();

                        Log::info('checkStatus: credited factory', [
                            'factory_id' => $factory->id,
                            'amount'     => $toAdd
                        ]);
                    } else {
                        Log::warning('checkStatus: factory not found', [
                            'payment_id' => $payment->id
                        ]);
                    }
                });
            }

            return redirect('/factoryPanel?ipay=success');
        } else {
            // если статус не 5 — считаем неуспешным
            $payment->status = $status === '4' ? 'failed' : 'pending';
            $payment->save();

            return redirect('/factoryPanel?ipay=fail');
        }
    }
}