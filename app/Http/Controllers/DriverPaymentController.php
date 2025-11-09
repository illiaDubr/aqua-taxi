<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DriverPayment;
use App\Models\Driver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
public function initiatePayment(Request $request)
{
    $request->validate([
        'amount' => 'required|numeric|min:0.01',
    ]);

    $driver = auth()->user();
    if (!$driver) {
        Log::warning('iPay: unauthorized access attempt');
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $amountGrn = (float) $request->input('amount');
    $amount = (int) round($amountGrn * 100);

    // Жестко врезаем тестовую ссылку
    $testUrl = 'https://sandbox-checkout.ipay.ua/test-link-for-driver';
    $fakePid = uniqid('test_');

    // Создаем запись в БД, как будто платёж прошёл
    $payment = DriverPayment::create([
        'driver_id' => $driver->id,
        'order_id'  => $fakePid,
        'amount'    => $amountGrn,
        'amount_cents' => $amount,
        'status'    => 'created',
        'response_data' => json_encode(['fake' => true, 'url' => $testUrl]),
    ]);

    Log::info('iPay TEST payment created (stubbed)', [
        'payment_id' => $payment->id,
        'url' => $testUrl,
    ]);

    return response()->json([
        'url' => $testUrl,
        'payment_id' => $payment->id,
    ]);
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
