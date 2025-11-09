<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DriverPayment;
use App\Models\Driver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DriverPaymentController extends Controller
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
private function driver(Request $request)
{
    $driver = $request->user() ?: Auth::guard('driver')->user();
    abort_if(!$driver, 401, 'Необхідна авторизація водія');
    return $driver;
}


public function initiatePayment(Request $request)
{
    $request->validate([
        'amount' => 'required|numeric|min:0.01',
    ]);

    $driver = $this->driver($request); 

    $amountGrn = (float) $request->input('amount');
    $amount = (int) round($amountGrn * 100);

    $merchantId = $this->getMerchantId();
    $signKey = $this->getSignKey();
    $systemKey = config('ipay.system_key');
    $endpoint = $this->getEndpoint();

    Log::debug('iPay config loaded', [
        'merchant_id' => $merchantId,
        'sign_key' => $signKey,
        'system_key' => $systemKey,
        'endpoint' => $endpoint,
        'sandbox' => config('ipay.use_sandbox'),
        'driver_id' => $driver->id,
    ]);

    if (!$merchantId || !$signKey || !$systemKey) {
        Log::error('iPay: missing config (merchant, sign key, or system key)');
        return response()->json(['error' => 'Payment gateway not configured'], 500);
    }

    $salt = sha1(microtime(true));
    $sign = hash_hmac('sha512', $salt, $signKey);

    $callbackGood = url('/map?ipay=success');
    $callbackBad  = url('/map?ipay=fail');
    $infoJson = json_encode(['driver_id' => $driver->id]);

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
            <amount>{$amount}</amount>
            <currency>UAH</currency>
            <desc>Поповнення балансу водія</desc>
            <info>{$infoJson}</info>
            <smch_id>{$merchantId}</smch_id>
        </transaction>
    </transactions>
    <lifetime>0.1</lifetime>
    <lang>ua</lang>
</payment>
XML;

    Log::debug('iPay XML payload constructed', [
        'salt' => $salt,
        'sign' => $sign,
        'xml' => $xml,
    ]);

    try {
        $response = Http::asForm()->withHeaders([
            'Accept' => 'application/xml',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post($endpoint, [
            'data' => $xml,
        ]);

        Log::debug('iPay HTTP response received', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (!$response->successful()) {
            Log::error('iPay request failed', [
                'url' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return response()->json(['error' => 'iPay error', 'details' => $response->body()], 400);
        }

        libxml_use_internal_errors(true);
        $bodyXml = simplexml_load_string($response->body());
        if (!$bodyXml) {
            Log::error('iPay: invalid XML response', ['raw' => $response->body()]);
            return response()->json(['error' => 'Invalid response from payment gateway'], 500);
        }

        $respSalt = (string) ($bodyXml->salt ?? $bodyXml->auth->salt ?? '');
        $respSign = (string) ($bodyXml->sign ?? '');
        $respPid  = (string) ($bodyXml->pid ?? '');
        $respUrl  = (string) ($bodyXml->url ?? '');
        $respStatus = (string) ($bodyXml->status ?? '');

        Log::debug('iPay parsed response', [
            'respSalt' => $respSalt,
            'respSign' => $respSign,
            'respPid' => $respPid,
            'respUrl' => $respUrl,
            'respStatus' => $respStatus,
        ]);

        $expectedRespSign = $respSalt && $systemKey ? hash_hmac('sha512', $respSalt, $systemKey) : null;

        if ($expectedRespSign && $respSign !== $expectedRespSign) {
            Log::warning('iPay: response signature mismatch', [
                'expected' => $expectedRespSign,
                'received' => $respSign,
                'body' => $response->body(),
            ]);
            return response()->json(['error' => 'Invalid response signature'], 400);
        }

        $payment = DriverPayment::create([
            'driver_id' => $driver->id, // 👈 теперь не null
            'order_id'  => $respPid ?: uniqid('ipay_'),
            'amount'    => $amountGrn,
            'amount_cents' => $amount,
            'status'    => $respStatus ?: 'created',
            'response_data' => $response->body(),
        ]);

        Log::info('iPay payment created', [
            'payment_id' => $payment->id,
            'url' => $respUrl,
        ]);

        if (!$respUrl) {
            return response()->json(['error' => 'No payment URL returned', 'response' => $response->body()], 400);
        }

        return response()->json(['url' => $respUrl, 'payment_id' => $payment->id]);
    } catch (\Exception $e) {
        Log::error('iPay exception', ['exception' => $e]);
        return response()->json(['error' => 'Internal error'], 500);
    }
}


    /**
     * Универсальная обработка callback'ов / нотификаций от iPay.
     * iPay может присылать XML или form-data — обрабатываем оба варианта.
     */
    public function handleCallback(Request $request)
    {
        $raw = $request->getContent();
        $signKey = $this->getSignKey();

        // Сначала попробуем разобрать как XML (новый стиль)
        if (!empty($raw) && $this->looksLikeXml($raw)) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($raw);
            if (!$xml) {
                Log::warning('iPay callback: invalid XML', ['raw' => $raw]);
                return response('Invalid XML', 400);
            }

            // ожидаем pid, status, salt, sign в ответе (как в примере)
            $pid = (string) ($xml->pid ?? '');
            $status = (string) ($xml->status ?? '');
            $respSalt = (string) ($xml->salt ?? $xml->auth->salt ?? '');
            $respSign = (string) ($xml->sign ?? '');

            // проверка подписи
            $expectedSign = $respSalt && $signKey ? hash_hmac('sha512', $respSalt, $signKey) : null;
            if ($expectedSign && $respSign !== $expectedSign) {
                Log::warning('iPay callback: signature mismatch (xml)', [
                    'pid' => $pid, 'expected' => $expectedSign, 'received' => $respSign
                ]);
                return response('Invalid signature', 400);
            }

            $payment = DriverPayment::where('order_id', $pid)->first();
            if (!$payment) {
                Log::warning('iPay callback: payment not found', ['pid' => $pid]);
                return response('Not found', 200);
            }

            // статус: документация даёт коды: 1,3,4,5 — приведём к понятным статусам
            $statusMapping = [
                '1' => 'registered',
                '3' => 'authorized',
                '4' => 'failed',
                '5' => 'success'
            ];
            $mapped = $statusMapping[$status] ?? $status;

            if ($payment->status === 'success') {
                return response('Already processed', 200);
            }

            $payment->status = $mapped;
            $payment->response_data = $raw;
            $payment->save();

            if ($mapped === 'success') {
                // увеличить баланс водителя (если не начисляли ранее)
                $driver = Driver::find($payment->driver_id);
                if ($driver) {
                    // если в payment хранится amount (в гривнах) — используем его
                    $amountToAdd = $payment->amount ?? 0;
                    $driver->balance += floatval($amountToAdd);
                    $driver->save();
                }
            }

            return response('OK', 200);
        }

        // Если не XML — возможно form/callback старого типа (пример в старом коде)
        $data = $request->all();
        if (empty($data)) {
            Log::warning('iPay callback: empty payload', ['content' => $raw]);
            return response('Empty', 400);
        }

        // Примерная проверка подписи для form-varianta (как в изначальном коде)
        // ВНИМАНИЕ: если в вашей интеграции другой алгоритм подписи у callback'а — замените логику ниже
        $merchantKey = $this->getSignKey();
        $expectedSignature = hash('sha256',
            ($data['MERCHANT_ID'] ?? '') .
            ($data['ORDER_ID'] ?? '') .
            ($data['AMOUNT'] ?? '') .
            ($data['CURRENCY'] ?? '') .
            ($data['DESCRIPTION'] ?? '') .
            ($data['STATUS'] ?? '') .
            $merchantKey
        );

        if (isset($data['SIGNATURE']) && $expectedSignature !== ($data['SIGNATURE'] ?? '')) {
            Log::warning('iPay callback: signature mismatch (form)', [
                'expected' => $expectedSignature,
                'received' => $data['SIGNATURE'] ?? null,
                'payload' => $data
            ]);
            return response('Invalid signature', 400);
        }

        // Ищем платёж по ORDER_ID или по custom id
        $orderId = $data['ORDER_ID'] ?? ($data['ORDERID'] ?? null);
        $payment = DriverPayment::where('order_id', $orderId)->first();

        if (!$payment) {
            Log::warning('iPay callback: payment not found (form)', ['order_id' => $orderId, 'payload' => $data]);
            return response('Not found', 200);
        }

        if ($payment->status === 'success') {
            return response('Already processed', 200);
        }

        $status = strtoupper($data['STATUS'] ?? '');
        $mapped = $status === 'SUCCESS' ? 'success' : ($status === 'FAIL' ? 'failed' : $status);

        $payment->status = $mapped;
        $payment->response_data = json_encode($data);
        $payment->save();

        if ($mapped === 'success') {
            $driver = Driver::find($payment->driver_id);
            if ($driver) {
                // AMOUNT в callback может быть в копейках или в гривнах — проверьте реальный формат и скорректируйте
                $amountFromCallback = $data['AMOUNT'] ?? $payment->amount;
                // Попробуем распознать: если больше 1000 => видимо в копейках
                if (is_numeric($amountFromCallback) && (int)$amountFromCallback > 1000) {
                    $amountToAdd = (int)$amountFromCallback / 100;
                } else {
                    $amountToAdd = (float)$amountFromCallback;
                }
                $driver->balance += floatval($amountToAdd);
                $driver->save();
            }
        }

        return response('OK', 200);
    }

    protected function looksLikeXml(string $s): bool
    {
        return strpos(trim($s), '<') === 0;
    }
}
