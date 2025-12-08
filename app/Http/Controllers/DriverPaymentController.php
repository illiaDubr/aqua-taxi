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

  
    private function driver(Request $request)
    {
        $userFromRequest = $request->user(); 
        $userFromGuard   = Auth::guard('driver')->user();

       
        Log::debug('Driver auth check start', [
            'request_user_id' => $userFromRequest ? $userFromRequest->id : null,
            'request_user_model' => $userFromRequest ? get_class($userFromRequest) : null,
            'guard_user_id' => $userFromGuard ? $userFromGuard->id : null,
            'auth_default_guard' => config('auth.defaults.guard'),
            'authorization_header' => $request->header('Authorization'),
            'all_headers' => $request->headers->all(),
        ]);

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



    public function initiatePayment(Request $request)
{
    $validated = $request->validate([
        'amount' => 'required|numeric|min:0.01',
    ]);

    $driver = $this->driver($request);

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

    $callbackGood = url('/api/driver/payment/check');
    $callbackBad  = url('/ordersDrive?ipay=fail');

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
            <amount>{$amountCents}</amount>
            <currency>UAH</currency>
            <desc>Поповнення балансу водія</desc>
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

        $payment = DriverPayment::create([
            'driver_id'     => $driver->id,
            'order_id'      => uniqid('ipay_'), // внутренний ID
            'pid'           => $respPid,        // настоящий iPay pid
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
            'pid'       => $respPid            // но сохраняем и возвращаем настоящий pid
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Internal error'], 500);
    }
}




public function checkStatus(Request $request)
{
    $orderId = $request->query('order_id');

    $query = DriverPayment::query()->where('status', 'pending');
    if ($orderId) {
        $query->where('order_id', $orderId); // ищем по внутреннему ID
    } else {
        $query->orderByDesc('created_at');
    }

    $payment = $query->latest()->first();

    if (!$payment) {
        Log::warning('checkStatus: no pending payment found', ['order_id' => $orderId]);
        return redirect('/?error=no_payment');
    }

    $merchantId = config('ipay.merchant_id');
    $signKey    = config('ipay.sign_key');
    $systemKey  = config('ipay.system_key');
   
    // В XML всегда идёт настоящий pid от iPay
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
        return redirect('/ordersDrive?ipay=fail');
    }

    $body = $response->body();
    if (empty($body) || strpos(trim($body), '<') !== 0) {
        Log::error('checkStatus: invalid XML response', ['raw' => $body]);
        return redirect('/ordersDrive?ipay=fail');
    }

    libxml_use_internal_errors(true);
    $bodyXml = simplexml_load_string($body);
    if (!$bodyXml) {
        Log::error('checkStatus: XML parse failed', ['raw' => $body]);
        return redirect('/ordersDrive?ipay=fail');
    }

    $status = (string) ($bodyXml->status ?? '');

    Log::info('checkStatus: iPay returned', [
        'order_id' => $payment->order_id,
        'pid'      => $payment->pid,
        'status'   => $status
    ]);

    if ($status === '5') {
        if (!$payment->credited) {
            DB::transaction(function () use ($payment) {
                $driver = Driver::find($payment->driver_id);
                if ($driver) {
                    $toAdd = $payment->amount ?? (($payment->amount_cents ?? 0) / 100);
                    $driver->balance = (float)$driver->balance + (float)$toAdd;
                    $driver->save();

                    $payment->status   = 'success';
                    $payment->credited = true;
                    $payment->save();

                    Log::info('checkStatus: credited driver', [
                        'driver_id' => $driver->id,
                        'amount'    => $toAdd
                    ]);
                } else {
                    Log::warning('checkStatus: driver not found', [
                        'payment_id' => $payment->id
                    ]);
                }
            });
        }
        return redirect('/ordersDrive?ipay=success');
    } else {
        $payment->status = $status === '4' ? 'failed' : 'pending';
        $payment->save();
        return redirect('/ordersDrive?ipay=fail');
    }
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

     
        $pid = (string) ($xml->pid ?? '');
        $status = (string) ($xml->status ?? '');
        $action = (string) ($xml->action ?? '');
        $respSalt = (string) ($xml->salt ?? $xml->auth->salt ?? '');
        $respSign = (string) ($xml->sign ?? '');

       
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
            
        }

        // Найдём платёж по pid
        if (empty($pid)) {
            Log::warning('iPay callback: missing pid', ['xml' => substr($raw, 0, 500)]);
            return response('Missing pid', 400);
        }

        $payment = DriverPayment::where('order_id', $pid)->first();
        if (! $payment) {
            Log::warning('iPay callback: payment not found', ['pid' => $pid]);
            
            return response('Not found', 200);
        }

       
        DB::transaction(function () use ($xml, $payment, $status) {
            
            if ($payment->status === 'success') {
                Log::info('iPay callback: payment already success, skipping', ['payment_id' => $payment->id]);
                return;
            }

           
            $xmlStatus = (string) ($xml->status ?? '');
            $statusMap = [
                '1' => 'registered',
                '3' => 'authorized',
                '4' => 'failed',
                '5' => 'success',
            ];
            $mapped = $statusMap[$xmlStatus] ?? $xmlStatus;

           
            $totalReceivedCents = null;
            if (!empty($xml->transactions) && !empty($xml->transactions->transaction)) {
                $sum = 0;
                foreach ($xml->transactions->transaction as $t) {
                    
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

            
            $payment->response_data = (string) $xml->asXML();
            $payment->status = $mapped;
          
            if ($totalReceivedCents !== null) {
                $payment->amount_cents = $totalReceivedCents;
                $payment->amount = round($totalReceivedCents / 100, 2); 
            }
            $payment->save();

          
            if ($mapped === 'success') {
                $driver = Driver::find($payment->driver_id);
                if ($driver) {
                   
                    $toAdd = 0.0;
                    if (!empty($payment->amount_cents)) {
                        $toAdd = (float)($payment->amount_cents) / 100.0;
                    } elseif (!empty($payment->amount)) {
                        $toAdd = (float)$payment->amount;
                    } else {
                       
                        $firstTr = $xml->transactions->transaction[0] ?? null;
                        if ($firstTr) {
                            $amt = (string)($firstTr->amount ?? $firstTr->invoice ?? 0);
                            if (is_numeric($amt)) {
                                $toAdd = ((int)$amt) / 100.0;
                            }
                        }
                    }

                 
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
        });
        return response('OK', 200);
    }

   
    $data = $request->all();
    if (empty($data)) {
        Log::warning('iPay callback: empty payload', ['content_snippet' => substr($raw, 0, 500)]);
        return response('Empty', 400);
    }

    
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
