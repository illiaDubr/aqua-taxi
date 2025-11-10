<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\DriverPayment;

class IpayStatusCommand extends Command
{
    protected $signature = 'ipay:status {payment_id}';
    protected $description = 'Check iPay status for a given DriverPayment';

    public function handle()
    {
        $payment = DriverPayment::find($this->argument('payment_id'));
        if (! $payment) {
            $this->error('Payment not found');
            return;
        }

        $merchantId = config('ipay.merchant_id', '5521');
        $systemKey  = config('ipay.system_key', 'e5817ba6ce82a941e2be9623aabcd9e37e78f19a');
        $endpoint   = config('ipay.sandbox_endpoint', 'https://sandbox-checkout.ipay.ua/api302');

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

        $xmlClean = preg_replace('/\s+/', ' ', trim($xml));

        $response = Http::asForm()->withHeaders([
            'Accept' => 'application/xml',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post($endpoint, ['data' => $xmlClean]);

        $this->line("Status code: " . $response->status());
        $this->line("Content-Type: " . $response->header('Content-Type'));
        $this->line("Raw response:\n" . $response->body());

        $raw = $response->body();
        if (! str_starts_with(trim($raw), '<')) {
            $this->error('Response is not XML');
            return;
        }

        libxml_use_internal_errors(true);
        $bodyXml = simplexml_load_string($raw);
        if (! $bodyXml) {
            $this->error('Invalid XML response');
            return;
        }

        $status = (string) ($bodyXml->status ?? '');
        $card   = (string) ($bodyXml->card_mask ?? '');
        $amount = (string) ($bodyXml->amount ?? '');
        $desc   = (string) ($bodyXml->desc ?? '');

        $this->info("Status: {$status}");
        $this->line("Card: {$card}");
        $this->line("Amount: {$amount}");
        $this->line("Desc: {$desc}");
    }
}
