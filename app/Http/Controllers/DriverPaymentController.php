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

    $callbackGood = url('/ordersDrive?ipay=success');
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
    <lifetime>0.1</lifetime>
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
    $raw = $request->getContent();
    $systemKey = config('ipay.system_key'); 
    Log::info('iPay callback received', ['content_snippet' => substr($raw, 0, 1000), 'content_length' => strlen($raw)]);

    // Если это XML
    if (!empty($raw) && $this->looksLikeXml($raw)) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        if (!$xml) {
            Log::warning('iPay callback: invalid XML', ['raw_snippet' => substr($raw, 0, 1000)]);
            return response('Invalid XML', 400);
        }

        // Если это ответ на создание платежа или нотификация по оплате
        $pid = (string) ($xml->pid ?? '');
        $status = (string) ($xml->status ?? '');
        $action = (string) ($xml->action ?? '');
        $respSalt = (string) ($xml->salt ?? $xml->auth->salt ?? '');
        $respSign = (string) ($xml->sign ?? '');

        // Проверка подписи iPay — подпись должна проверяться системным ключом
        if ($respSalt && $respSign && $systemKey) {
            $expected = hash_hmac('sha512', $respSalt, $systemKey);
            if (!hash_equals($expected, $respSign)) {
                Log::warning('iPay callback: signature mismatch (xml)', [
                    'pid' => $pid, 'expected' => substr($expected, 0, 16), 'received' => substr($respSign, 0, 16)
                ]);
                return response('Invalid signature', 400);
            }
        } else {
            Log::warning('iPay callback: missing signature fields', ['salt' => $respSalt ? true : false, 'sign' => $respSign ? true : false]);
            // Не всегда требуется подпись? если требуется — вернуть 400
        }

        // Найдём платёж по pid
        if (empty($pid)) {
            Log::warning('iPay callback: missing pid', ['xml' => substr($raw, 0, 500)]);
            return response('Missing pid', 400);
        }

        $payment = DriverPayment::where('order_id', $pid)->first();
        if (! $payment) {
            Log::warning('iPay callback: payment not found', ['pid' => $pid]);
            // Возвращаем 200, чтобы iPay не повторял (или 200/404 в зависимости от соглашения)
            return response('Not found', 200);
        }

        // Обработка completion (action) или обычного статуса
        // Внутри используем транзакцию для идемпотентного обновления и начисления баланса
        DB::transaction(function () use ($xml, $payment, $status) {
            // Если уже success — ничего не делаем (идемпотентность)
            if ($payment->status === 'success') {
                Log::info('iPay callback: payment already success, skipping', ['payment_id' => $payment->id]);
                return;
            }

            // Определим новый статус
            $xmlStatus = (string) ($xml->status ?? '');
            $statusMap = [
                '1' => 'registered',
                '3' => 'authorized',
                '4' => 'failed',
                '5' => 'success',
            ];
            $mapped = $statusMap[$xmlStatus] ?? $xmlStatus;

            // Если это completion с транзакциями — аккумулируем сумму из транзакций
            $totalReceivedCents = null;
            if (!empty($xml->transactions) && !empty($xml->transactions->transaction)) {
                $sum = 0;
                foreach ($xml->transactions->transaction as $t) {
                    // сначала смотрим invoice (пример ответа completion), иначе amount
                    $invoice = (string) ($t->invoice ?? '');
                    $amountField = (string) ($t->amount ?? '');
                    $value = 0;
                    if (is_numeric($invoice) && $invoice != '') {
                        $value = (int)$invoice;
                    } elseif (is_numeric($amountField) && $amountField != '') {
                        $value = (int)$amountField;
                    }
                    $sum += $value;
                }
                $totalReceivedCents = $sum > 0 ? $sum : null;
            }

            // Сохраняем response_data (чтобы можно было отладить)
            $payment->response_data = (string) $xml->asXML();
            $payment->status = $mapped;
            // если у нас есть сумма из транзакций — её следует записать как amount_cents (может быть частичное списание)
            if ($totalReceivedCents !== null) {
                $payment->amount_cents = $totalReceivedCents;
                $payment->amount = round($totalReceivedCents / 100, 2); // если есть поле amount (в гривнах)
            }
            $payment->save();

            // Если успешный платёж — начисляем баланс драйверу (если ещё не начислено)
            if ($mapped === 'success') {
                $driver = Driver::find($payment->driver_id);
                if ($driver) {
                    // Определи сумму для начисления: либо payment.amount (грн) либо amount_cents
                    $toAdd = 0.0;
                    if (!empty($payment->amount_cents)) {
                        $toAdd = (float)($payment->amount_cents) / 100.0;
                    } elseif (!empty($payment->amount)) {
                        $toAdd = (float)$payment->amount;
                    } else {
                        // last ditch: попытаемся взять из xml first transaction amount
                        $firstTr = $xml->transactions->transaction[0] ?? null;
                        if ($firstTr) {
                            $amt = (string)($firstTr->amount ?? $firstTr->invoice ?? 0);
                            if (is_numeric($amt)) {
                                $toAdd = ((int)$amt) / 100.0;
                            }
                        }
                    }

                    // Чтобы не дублировать начисление, можно хранить флаг payment->processed_at или проверять, что balance уже увеличен.
                    // Простая защита: если payment->meta->credited = true (если есть поле) — пропускаем. Тут для простоты проверим статус был не success.
                    if ($toAdd > 0) {
                        $driver->balance = (float)$driver->balance + $toAdd;
                        $driver->save();
                        Log::info('iPay callback: credited driver balance', ['driver_id' => $driver->id, 'amount' => $toAdd]);
                    } else {
                        Log::warning('iPay callback: success received but amount to credit is zero', ['payment_id' => $payment->id]);
                    }
                } else {
                    Log::warning('iPay callback: driver not found for payment', ['payment_id' => $payment->id, 'driver_id' => $payment->driver_id]);
                }
            }
        }); // DB transaction

        return response('OK', 200);
    }

    // NOT XML — возможно form-data от старого интеграционного варианта
    $data = $request->all();
    if (empty($data)) {
        Log::warning('iPay callback: empty payload', ['content_snippet' => substr($raw, 0, 500)]);
        return response('Empty', 400);
    }

    // Пример проверки подписи для form-варианта (подстрой под вашу интеграцию)
    $merchantKey = $this->getSignKey(); // для form-подписей может использоваться sign_key
    $expectedSignature = hash('sha256',
        ($data['MERCHANT_ID'] ?? '') .
        ($data['ORDER_ID'] ?? '') .
        ($data['AMOUNT'] ?? '') .
        ($data['CURRENCY'] ?? '') .
        ($data['DESCRIPTION'] ?? '') .
        ($data['STATUS'] ?? '') .
        $merchantKey
    );

    if (isset($data['SIGNATURE']) && !hash_equals($expectedSignature, $data['SIGNATURE'])) {
        Log::warning('iPay callback: signature mismatch (form)', [
            'expected' => substr($expectedSignature, 0, 16),
            'received' => substr($data['SIGNATURE'] ?? '', 0, 16),
            'payload' => $data,
        ]);
        return response('Invalid signature', 400);
    }

    $orderId = $data['ORDER_ID'] ?? ($data['ORDERID'] ?? null);
    $payment = DriverPayment::where('order_id', $orderId)->first();
    if (! $payment) {
        Log::warning('iPay callback: payment not found (form)', ['order_id' => $orderId]);
        return response('Not found', 200);
    }

    // idempotent update similar to XML branch
    DB::transaction(function () use ($payment, $data) {
        if ($payment->status === 'success') return;

        $status = strtoupper($data['STATUS'] ?? '');
        $mapped = $status === 'SUCCESS' ? 'success' : ($status === 'FAIL' ? 'failed' : strtolower($status));

        $payment->status = $mapped;
        $payment->response_data = json_encode($data);
        $payment->save();

        if ($mapped === 'success') {
            $driver = Driver::find($payment->driver_id);
            if ($driver) {
                $amountRaw = $data['AMOUNT'] ?? $payment->amount;
                $toAdd = 0;
                if (is_numeric($amountRaw)) {
                    $amtInt = (int)$amountRaw;
                    // Если сумма явно в копейках (больше 1000), то /100
                    $toAdd = $amtInt > 1000 ? ($amtInt / 100.0) : (float)$amtInt;
                }
                if ($toAdd > 0) {
                    $driver->balance += $toAdd;
                    $driver->save();
                    Log::info('iPay callback (form): credited driver', ['driver_id' => $driver->id, 'amount' => $toAdd]);
                }
            }
        }
    });

    return response('OK', 200);
}

    protected function looksLikeXml(string $s): bool
    {
        return strpos(trim($s), '<') === 0;
    }
}
