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

        $response = Http::asForm()->withHeaders([
            'Accept' => 'application/xml',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post($endpoint, ['data' => $xml]);

        if (! $response->successful()) {
            $this->error('Request failed: ' . $response->status());
            $this->line($response->body());
            return;
        }

        $bodyXml = simplexml_load_string($response->body());
        if (! $bodyXml) {
            $this->error('Invalid XML response');
            $this->line($response->body());
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
