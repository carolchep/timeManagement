<?php

namespace App\Http\Controllers\API;

use Al\Models\Payment;
use App\CommissionPayment;
use App\SubscriptionPayment;
use App\Transformers\PaymentTransformer;
use App\Transformers\SubscriptionTransformer;
use App\Transformers\TripTransformer;
use App\TripRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CommissionsController extends Controller
{
    public function initializePayment(Request $request)
    {
        $user = $request->user();
        $phone = $request->phone;
        if (substr($phone, 0, 1) === '0') {
            $phone = "254" . substr($request->phone, 1);
        }

        $data =  json_decode($this->initSTK($phone, config('mpesabi.commission_callback_url')));

        if ((bool)$account = $user->account) {
            $payment = $account->commissionPayments()->create([
                'amount' => 1,
                'reference' => $data->MerchantRequestID,
                'status' => config('constants.payment_status.payment_initiated')
            ]);
        } else {
            $account = $user->account()->create();
            $payment = $account->commissionPayments()->create([
                'amount' => 1,
                'reference' => $data->MerchantRequestID,
                'status' => config('constants.payment_status.payment_initiated')
            ]);
        }

        return response()->json([
            'success' => true,
            'payment' => $payment
        ]);
    }

    private function initSTK($phone, $callback_url)
    {
        $urlNew = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $access_token = $this->getCred();

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $urlNew);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization:Bearer $access_token",
            'Content-Type:application/json'
        ));

        $short_code = config('mpesabi.short_code');
        $time = now()->format('YmdHis');
        $passkey = config('mpesabi.passkey');

        $curl_post_data = array(
            'BusinessShortCode' => $short_code,
            'Password' => $this->getPassword($short_code, $passkey, $time),
            'Timestamp' => $time,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => 1,
            'PartyA' => $phone,
            'PartyB' => $short_code,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callback_url,
            'AccountReference' => '123',
            'TransactionDesc' => 'Nothing here'
        );

        $data_string = json_encode($curl_post_data);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

        $curl_response = curl_exec($curl);

        return $curl_response;
    }

    private function getCred()
    {
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $key = config('mpesabi.consumer_key');
        $secret = config('mpesabi.consumer_secret');
//        dd(config('mpesabi'));
        $credentials = base64_encode($key . ':' . $secret);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type:application/json',
            'Accept: application/json',
            'Authorization: Basic '.$credentials
        )); //setting a custom header
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $curl_response = curl_exec($curl);

        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($curl_response, 0, $header_size);
        $body = substr($curl_response, $header_size);
        curl_close($curl);
        return $access_token = json_decode($body)->access_token;
    }

    private function getPassword($shortCode, $passkey, $time)
    {
        return base64_encode($shortCode . $passkey . $time);
    }
}
