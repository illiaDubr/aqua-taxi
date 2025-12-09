namespace App\Http\Controllers;

use App\Models\Factory;
use App\Models\FactoryPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FactoryPaymentController extends Controller
{
    private function factory(Request $request)
    {
        $userRequest = $request->user();
        $userGuard   = Auth::guard('factory')->user();
        $factory = $userRequest ?: $userGuard;

        if (!$factory) {
            return response()->json(['error' => 'Потрібна авторизація виробника'], 401);
        }

        return $factory;
    }

    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $factory = $this->factory($request);
        $amount = (float)$request->amount;
        $amountCents = (int)($amount * 100);

        $salt = sha1(microtime(true));
        $sign = hash_hmac('sha512', $salt, config('ipay.sign_key'));

        $payload = <<<XML
<payment>
    <auth>
        <mch_id>{config('ipay.merchant_id')}</mch_id>
        <salt>{$salt}</salt>
        <sign>{$sign}</sign>
    </auth>
    <urls>
        <good>{url('/api/factory/payment/check')}</good>
        <bad>{url('/api/factory/payment/check')}</bad>
    </urls>
    <transactions>
        <transaction>
            <amount>{$amountCents}</amount>
            <currency>UAH</currency>
            <desc>Поповнення балансу виробника</desc>
            <info>{"factory_id":{$factory->id}}</info>
        </transaction>
    </transactions>
</payment>
XML;

        $response = Http::asForm()->post(config('ipay.endpoint'), [
            'data' => $payload
        ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'iPay error'], 400);
        }

        $xml = simplexml_load_string($response->body());
        $pid = (string)$xml->pid;
        $url = (string)$xml->url;

        $payment = FactoryPayment::create([
            'factory_id'    => $factory->id,
            'order_id'      => uniqid("fpay_"),
            'pid'           => $pid,
            'amount'        => $amount,
            'amount_cents'  => $amountCents,
            'status'        => 'pending'
        ]);

        return response()->json([
            'url' => $url,
            'payment_id' => $payment->id,
            'pid' => $pid
        ]);
    }

    public function check(Request $request)
    {
        $payment = FactoryPayment::where('status', 'pending')
            ->latest()->first();

        if (!$payment) {
            return redirect('/factoryPanel?ipay=none');
        }

        $salt = sha1(microtime(true));
        $sign = hash_hmac('sha512', $salt, config('ipay.sign_key'));

        $xml = <<<XML
<payment>
    <auth>
        <mch_id>{config('ipay.merchant_id')}</mch_id>
        <salt>{$salt}</salt>
        <sign>{$sign}</sign>
    </auth>
    <action>status</action>
    <pid>{$payment->pid}</pid>
</payment>
XML;

        $response = Http::asForm()->post(config('ipay.endpoint'), [
            'data' => $xml
        ]);

        $body = simplexml_load_string($response->body());
        $status = (string)$body->status;

        if ($status == '5') {
            DB::transaction(function () use ($payment) {
                $factory = Factory::find($payment->factory_id);
                $factory->balance += $payment->amount;
                $factory->save();

                $payment->status = 'success';
                $payment->save();
            });

            return redirect('/factoryPanel?ipay=success');
        }

        $payment->status = 'failed';
        $payment->save();

        return redirect('/factoryPanel?ipay=fail');
    }
}
