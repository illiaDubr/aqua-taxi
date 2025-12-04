<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DriverPayment;
use App\Models\Driver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\DB;

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

    /**
     * Универсальный метод получения водителя из запроса.
     * Логирует все варианты и возвращает JSON 401 вместо HTML abort — удобнее для API.
     */
    private function driver(Request $request)
    {
        $userFromRequest = $request->user(); // sanctum / default auth
        $userFromGuard   = Auth::guard('driver')->user(); // на случай кастомного guard

        // Логируем базовую информацию
        Log::debug('Driver auth check start', [
            'request_user_id' => $userFromRequest ? $userFromRequest->id : null,
            'request_user_model' => $userFromRequest ? get_class($userFromRequest) : null,
            'guard_user_id' => $userFromGuard ? $userFromGuard->id : null,
            'auth_default_guard' => config('auth.defaults.guard'),
            'authorization_header' => $request->header('Authorization'),
            'all_headers' => $request->headers->all(), // В проде можно убрать/сократить
        ]);

        // Если есть bearer token — проверим запись в personal_access_tokens
        $bearer = $request->bearerToken();
        if ($bearer) {
            try {
                $pat = PersonalAccessToken::findToken($bearer);
                Log::debug('PersonalAccessToken lookup', [
                    'token_exists' => (bool) $pat,
                    'tokenable_type' => $pat ? $pat->tokenable_type : null,
                    'tokenable_id' => $pat ? $pat->tokenable_id : null,
                    'abilities' => $pat ? $pat->abilities : null,
                    'last_used_at' => $pat ? $pat->last_used_at : null,
                ]);
            } catch (\Throwable $ex) {
                Log::error('PAT lookup failed', ['ex' => $ex->getMessage()]);
            }
        }

        $driver = $userFromRequest ?: $userFromGuard;

        if (! $driver) {
            Log::warning('Driver auth failed: no authenticated driver found', [
                'bearer_present' => $bearer ? true : false,
            ]);
            return response()->json(['error' => 'Необхідна авторизація водія'], 401);
        }

        return $driver;
    }


public function pollUntilSuccess(DriverPayment $payment)
{
    $merchantId = config('ipay.merchant_id');
    $systemKey  = config('ipay.system_key');
    $endpoint   = config('ipay.sandbox_endpoint'); // или боевой

    if (! $merchantId || ! $systemKey || ! $endpoint) {
        Log::error('iPay polling: missing config', [
            'merchant_id' => $merchantId,
            'system_key'  => $systemKey,
            'endpoint'    => $endpoint,
        ]);
        return;
    }

    sleep(2); // дать iPay время зарегистрировать платёж

    for ($i = 0; $i < 6; $i++) {
        $salt = sha1(microtime(true));
        $sign = hash_hmac('sha512', $salt, $systemKey);

        $xml = <<<XML
<payment>
    <auth>
        <mch_id>{$merchantId}</mch_id>
        <salt>{$salt}</salt>
        <sign>{$sign}</sign>
    </auth>
    <action>status</action>
    <pid>{$payment->order_id}</pid>
</payment>
XML;

        $response = Http::asForm()->withHeaders([
            'Accept' => 'application/xml',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post($endpoint, ['data' => $xml]);

        if (! $response->successful()) {
            Log::warning('iPay polling failed', [
                'payment_id' => $payment->id,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);
            sleep(5);
            continue;
        }

        Log::debug('iPay polling raw XML', [
            'payment_id' => $payment->id,
            'attempt'    => $i + 1,
            'body'       => $response->body(),
        ]);

        $bodyXml = simplexml_load_string($response->body());
        if (! $bodyXml) {
            Log::warning('iPay polling: invalid XML', [
                'payment_id' => $payment->id,
                'raw'        => $response->body(),
            ]);
            sleep(5);
            continue;
        }

        $status = (string) ($bodyXml->status ?? '');

        Log::info('iPay polling attempt', [
            'payment_id' => $payment->id,
            'attempt'    => $i + 1,
            'status'     => $status,
        ]);

        if ($status === '5' && $payment->status !== 'success' && ! $payment->credited) {
            $driver = Driver::find($payment->driver_id);
            if ($driver) {
                $driver->balance += $payment->amount;
                $driver->save();

                $payment->status = 'success';
                $payment->credited = true;
                $payment->save();

                Log::info('Driver balance credited via polling', [
                    'driver_id' => $driver->id,
                    'amount'    => $payment->amount,
                ]);
            }
            return;
        }

        sleep(5);
    }

    Log::info('iPay polling ended without success', ['payment_id' => $payment->id]);
}



    public function initiatePayment(Request $request)
{
    Log::debug('initiatePayment request headers', [
        'authorization' => $request->header('Authorization'),
        'all_headers' => $request->headers->all(),
    ]);

    $validated = $request->validate([
        'amount' => 'required|numeric|min:0.01',
    ]);

    $driverOrResponse = $this->driver($request);
    if ($driverOrResponse instanceof \Illuminate\Http\JsonResponse) {
        return $driverOrResponse;
    }
    $driver = $driverOrResponse;

    $amountGrn = (float) $request->input('amount');
    $amount = (int) round($amountGrn * 100);

    $merchantId = $this->getMerchantId();
    $signKey    = $this->getSignKey();
    $systemKey  = config('ipay.system_key');
    $endpoint   = $this->getEndpoint();

    Log::debug('iPay config loaded', [
        'merchant_id' => $merchantId ? 'present' : null,
        'sign_key'    => $signKey ? 'present' : null,
        'system_key'  => $systemKey ? 'present' : null,
        'endpoint'    => $endpoint,
        'sandbox'     => config('ipay.use_sandbox'),
        'driver_id'   => $driver->id ?? null,
    ]);

    if (!$merchantId || !$signKey || !$systemKey) {
        Log::error('iPay: missing config (merchant, sign key, or system key)');
        return response()->json(['error' => 'Payment gateway not configured'], 500);
    }

    $salt = sha1(microtime(true));
    $sign = hash_hmac('sha512', $salt, $signKey);

    $callbackGood = url('/api/driver/pay/good');
    $callbackBad  = url('/ordersDrive?ipay=fail');
    $callbackSrv  = url('/api/driver/pay/callback');

    Log::info('iPay callback URL set', ['callback' => $callbackSrv]);

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
        <callback>{$callbackSrv}</callback>
        <auto_redirect_good>1</auto_redirect_good>
        <auto_redirect_bad>1</auto_redirect_bad>
    </urls>
    <transactions>
        <transaction>
            <amount>{$amount}</amount>
            <currency>UAH</currency>
            <desc>Поповнення балансу водія</desc>
            <info>{"dogovor":37973023}</info>
           
        </transaction>
    </transactions>
    <lifetime>0.5</lifetime>
    <lang>ua</lang>
</payment>
XML;

    Log::debug('iPay full XML payload', ['xml' => $xml]);

    try {
        $response = Http::asForm()->withHeaders([
            'Accept' => 'application/xml',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post($endpoint, ['data' => $xml]);

        Log::info('iPay XML sent', ['endpoint' => $endpoint]);

        Log::debug('iPay HTTP response received', [
            'status' => $response->status(),
            'body_snippet' => substr($response->body(), 0, 500),
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

        $respSalt   = (string) ($bodyXml->salt ?? $bodyXml->auth->salt ?? '');
        $respSign   = (string) ($bodyXml->sign ?? '');
        $respPid    = (string) ($bodyXml->pid ?? '');
        $respUrl    = (string) ($bodyXml->url ?? '');
        $respStatus = (string) ($bodyXml->status ?? '');

        Log::debug('iPay parsed response', [
            'respSalt'   => $respSalt,
            'respSign'   => $respSign,
            'respPid'    => $respPid,
            'respUrl'    => $respUrl,
            'respStatus' => $respStatus,
        ]);

        if (!$respPid) {
            Log::warning('iPay response missing pid', ['body' => $response->body()]);
        }

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
            'driver_id'     => $driver->id,
            'order_id'      => $respPid ?: uniqid('ipay_'),
            'amount'        => $amountGrn,
            'amount_cents'  => $amount,
            'status'        => $respStatus ?: 'created',
            'response_data' => $response->body(),
        ]);

        Log::info('iPay payment created', [
            'payment_id' => $payment->id,
            'driver_id'  => $driver->id,
            'amount'     => $amountGrn,
            'url'        => $respUrl,
        ]);

        // ===============================
        //  FIX: добавлено определение $statusSalt
        // ===============================
        $statusSalt = sha1(microtime(true));

        $statusSign = hash_hmac('sha512', $statusSalt, $systemKey);

        $statusXml = <<<XML
<payment>
    <auth>
        <mch_id>{$merchantId}</mch_id>
        <salt>{$statusSalt}</salt>
        <sign>{$statusSign}</sign>
    </auth>
    <action>status</action>
    <pid>{$payment->order_id}</pid>
</payment>
XML;

        $statusResponse = Http::asForm()->withHeaders([
            'Accept' => 'application/xml',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post($endpoint, ['data' => $statusXml]);

        if ($statusResponse->successful()) {
            $statusXmlBody = simplexml_load_string($statusResponse->body());
            $statusCode = (string) ($statusXmlBody->status ?? '');

            Log::info('iPay status check after creation', [
                'payment_id' => $payment->id,
                'status' => $statusCode,
            ]);

            if ($statusCode === '5' && $payment->status !== 'success') {
                $driver = Driver::find($payment->driver_id);
                if ($driver) {
                    $driver->balance += $payment->amount;
                    $driver->save();
                    $payment->status = 'success';
                    $payment->credited = true;
                    $payment->save();
                    Log::info('Driver balance credited via status check', [
                        'driver_id' => $driver->id,
                        'amount' => $payment->amount,
                    ]);
                }
            }
        }

        return response()->json(['url' => $respUrl, 'payment_id' => $payment->id]);
    } catch (\Exception $e) {
        Log::error('iPay exception', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Internal error'], 500);
    }
}


public function handleGoodRedirect(Request $request)
{
    // 1. Получаем ID пользователя
    $driver = auth('driver')->user();

    if (!$driver) {
        return redirect('https://example.com/login?error=no_driver');
    }

    // 2. Получаем последний неоплаченный платёж
    $payment = DriverPayment::where('driver_id', $driver->id)
        ->where('status', 'pending')
        ->latest()
        ->first();

    if (!$payment) {
        return redirect('https://example.com/ordersDrive?error=no_payment');
    }

    // 3. Начисляем деньги
    $driver->balance += $payment->amount;
    $driver->save();

    // 4. Закрываем платёж
    $payment->status = 'success';
    $payment->save();

    // 5. Редиректим на фронт
    return redirect("https://example.com/ordersDrive?ipay=success");
}


    // ------------------- Универсальная обработка callback'ов (оставлена твоя логика) -------------------

 

    public function checkStatus($paymentId)
{
    $payment = DriverPayment::find($paymentId);
    if (! $payment) {
        return response()->json(['error' => 'Payment not found'], 404);
    }

    $merchantId = config('ipay.merchant_id');
    $systemKey  = config('ipay.system_key');
    $salt       = sha1(microtime(true));
    $sign       = hash_hmac('sha512', $salt, $systemKey);

    $xml = <<<XML
<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<payment>
    <auth>
        <mch_id>{$merchantId}</mch_id>
        <salt>{$salt}</salt>
        <sign>{$sign}</sign>
    </auth>
    <action>status</action>
    <pid>{$payment->order_id}</pid>
</payment>
XML;

    $endpoint = config('ipay.sandbox_endpoint'); // или боевой endpoint
    $response = Http::asForm()->withHeaders([
        'Accept' => 'application/xml',
        'Content-Type' => 'application/x-www-form-urlencoded',
    ])->post($endpoint, ['data' => $xml]);

    if (! $response->successful()) {
        Log::error('iPay status request failed', ['status' => $response->status(), 'body' => $response->body()]);
        return response()->json(['error' => 'iPay error'], 500);
    }

    $bodyXml = simplexml_load_string($response->body());
    $status = (string) ($bodyXml->status ?? '');

    Log::info('iPay status response', ['payment_id' => $payment->id, 'status' => $status]);

    if ($status === '5' && $payment->status !== 'success') {
        $driver = Driver::find($payment->driver_id);
        if ($driver) {
            $driver->balance += $payment->amount;
            $driver->save();
            $payment->status = 'success';
            $payment->credited = true;
            $payment->save();
            Log::info('iPay status: credited driver balance', ['driver_id' => $driver->id, 'amount' => $payment->amount]);
        }
    }

    return response()->json(['status' => $status]);
}

public function handleCallback(Request $request)
{
    Log::info("iPay CALLBACK HIT", [
        'ip'       => $request->ip(),
        'method'   => $request->method(),
        'url'      => $request->fullUrl(),
        'query'    => $request->query(),
        'headers'  => $request->headers->all(),
        'raw_len'  => strlen($request->getContent()),
        'raw_body_first_5k' => substr($request->getContent(), 0, 5000),
    ]);

    $raw = $request->getContent();
    $systemKey = config('ipay.system_key');

    if (empty($raw)) {
        Log::warning("iPay callback: EMPTY BODY");
        return response('EMPTY', 200); // 200 чтобы iPay не слал заново
    }

    // --- XML DETECT ---
    if ($this->looksLikeXml($raw)) {
        Log::info("iPay callback detected: XML payload");

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);

        if (!$xml) {
            Log::error("iPay callback: invalid XML", [
                'errors' => libxml_get_errors(),
                'body'   => substr($raw, 0, 2000),
            ]);
            return response('INVALID XML', 200);
        }

        Log::info("iPay callback: XML parsed ok", [
            'pid'    => (string)($xml->pid ?? ''),
            'status' => (string)($xml->status ?? ''),
            'action' => (string)($xml->action ?? ''),
        ]);

        // Sign check
        $respSalt = (string) ($xml->salt ?? $xml->auth->salt ?? '');
        $respSign = (string) ($xml->sign ?? '');

        if ($respSalt && $respSign && $systemKey) {
            $expected = hash_hmac('sha512', $respSalt, $systemKey);
            if (!hash_equals($expected, $respSign)) {
                Log::error("iPay callback: signature mismatch", [
                    'expected_start' => substr($expected, 0, 24),
                    'received_start' => substr($respSign, 0, 24),
                ]);
                return response('BAD SIGNATURE', 200);
            }
            Log::info("iPay callback: signature OK");
        }

        // Pid
        $pid = (string) ($xml->pid ?? '');
        if (!$pid) {
            Log::error("iPay callback: missing PID");
            return response('NO PID', 200);
        }

        $payment = DriverPayment::where('order_id', $pid)->first();
        if (!$payment) {
            Log::error("iPay callback: payment not found", ['pid' => $pid]);
            return response('NOT FOUND', 200);
        }

        Log::info("iPay callback: payment found", [
            'payment_id' => $payment->id,
            'driver_id'  => $payment->driver_id
        ]);

        // Status mapping
        $xmlStatus = (string)($xml->status ?? '');
        $mapped = [
            '1' => 'registered',
            '3' => 'authorized',
            '4' => 'failed',
            '5' => 'success',
        ][$xmlStatus] ?? $xmlStatus;

        Log::info("iPay callback: mapped status", ['mapped' => $mapped]);

        DB::transaction(function () use ($payment, $mapped, $xml) {

            if ($payment->status === 'success') {
                Log::info("iPay callback: already success – skipping");
                return;
            }

            $payment->status = $mapped;
            $payment->response_data = $xml->asXML();
            $payment->save();

            if ($mapped === 'success') {
                $amount = (float)$payment->amount;
                $driver = Driver::find($payment->driver_id);

                if ($driver) {
                    $driver->balance += $amount;
                    $driver->save();

                    Log::info("iPay callback: BALANCE CREDITED", [
                        'driver_id' => $driver->id,
                        'amount'    => $amount,
                        'new_balance' => $driver->balance
                    ]);
                } else {
                    Log::error("iPay callback: driver not found");
                }
            }
        });

        return response("OK", 200);
    }

    // Not XML → log
    Log::warning("iPay callback: NON-XML PAYLOAD", [
        'json' => $request->all(),
    ]);

    return response("OK", 200);
}


    protected function looksLikeXml(string $s): bool
    {
        return strpos(trim($s), '<') === 0;
    }
}
