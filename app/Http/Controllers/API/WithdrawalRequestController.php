<?php

namespace App\Http\Controllers\API;

use Al\helpers\helper;
use App\PartnerWithdrawalRequest;
use App\Rules\CanRequestForWithdrawal;
use App\Rules\CanWithdraw;
use App\Transformers\WithdrawalRequestTransformer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WithdrawalRequestController extends Controller
{
    public function showWithdrawalsTable()
    {
        return view('admin.withdrawals.index');
    }

    public function index(Request $request)
    {
        $model = new PartnerWithdrawalRequest();
        $query = $model->newQuery();

        $withdrawal_requests = $query->get();

        return response()->json([
            'success' => true,
            'data' => fractal($withdrawal_requests, new WithdrawalRequestTransformer())
        ]);
    }

    public function processRequest(Request $request)
    {
        $user_id = $request->user()->id;
        if ((bool)$withdrawal_request = PartnerWithdrawalRequest::where('partner_id', $user_id)
            ->where('request_status', 'pending')->orderBy('created_at', 'desc')->first()) {
            return response()->json([
                'success' => false,
                'message' => 'you have a pending withdrawal request',
                'withdrawal_request' => fractal($withdrawal_request, new WithdrawalRequestTransformer())
            ]);
        }

        $validator = \Validator::make($request->all(), [
            'amount' => [
                'required',
                new CanWithdraw($user_id)
            ],
            'phone' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ]);
        }

        $withdrawal_request = PartnerWithdrawalRequest::create([
            'partner_id' => $request->user()->id,
            'amount' => $request->amount,
            'phone' => $request->phone
        ]);

        return response()->json([
            'success' => true,
            'message' => 'request made successfully',
            'withdrawal_request' => fractal($withdrawal_request, new WithdrawalRequestTransformer())
        ]);
    }

    public function setApprovalStatus(Request $request, PartnerWithdrawalRequest $withdrawal_request)
    {
//        $this->validate($request, [
//            'status' => [
//                'required',
//                'in:approved,rejected',
//                new CanWithdraw($withdrawal_request->partner_id)
//            ],
//        ]);

        $withdrawal_request->request_status = $request->status;
        $withdrawal_request->save();
        if ($withdrawal_request->request_status == 'approved') {
            $withdrawal_request->approved_on = now();
            $result = $this->initializeMoneyTransfer($withdrawal_request);
            dd($result);
            $withdrawal_request->mpesa_conversation_id = $result->ConversationID;
            $withdrawal_request->save();
        }

        $withdrawal_request->rejected_on = now();

        return response()->json([
            'success' => true,
            'data' => $withdrawal_request
        ]);
    }

    private function initializeMoneyTransfer(PartnerWithdrawalRequest $request)
    {
        $url = 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';

        $access_token = $this->getCred();
        try {
            $security_credentials = $this->getSecurityCredentials();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'failed to initialize payment'
            ]);
        }
//        dd($access_token);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Content-Type:application/json",
            "Authorization:Bearer ${access_token}"
            ));
        $security_credentials = $this->getSecurityCredentials();

        $curl_post_data = array(
            'InitiatorName' => 'webinitiator',
            'SecurityCredential' => 'A9B3G/RAHZi8sbtqM+DVqGrLjoXb5Mo9WobCaJW76dKu/j6X2JvdpIHaDuwHA5cUg7wBvzz3JexxebGLzLBlTLCjEB8Nueg4mkEaZPM1VE/iYl+5CrPPeQL1VzjYFc80D7BARU9MHsFWvp8H7q5NQP85S0YKoZgXCOC2CiCz5YZfdkKEb2d6WfUFz/U+/6b0NghfQowJ5NBzICRd33nj1rQeMxtNIzh2jQRnb81Nrvo0VDVL0ubwpIF3gVCic5O49BiYpKMyHRKUJvUaWe7mJ1JHLk0HwZerHrPbSlRN3/uVa9FA2f/sGpqeM1gvxrjasr99yDG+ND+OoqKgO409EQ==',
            'CommandID' => 'SalaryPayment',
            'Amount' => 100,
            'PartyA' => '396525',
            'PartyB' => 254796543642,
            'Remarks' => "payment to driver",
            'QueueTimeOutURL' => config('mpesabi.queue_time_out_url'),
            'ResultURL' => config('mpesabi.result_url'),
            'Occasion' => ''
        );

        $data_string = json_encode($curl_post_data);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

        $curl_response = curl_exec($curl);


        return json_decode($curl_response);
    }

    private function getCred()
    {
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $key = config('mpesabi.b2c_consumer_key');
        $secret = config('mpesabi.b2c_consumer_secret');
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

    public function getSecurityCredentials ()
    {
        // $publicKey = "PATH_TO_CERTICATE";
        $publicKey =  __DIR__ . '/cert.cer';
        if(\is_file($publicKey)){
            $pubKey = file_get_contents($publicKey);
        }else{
            throw new \Exception("Please provide a valid public key file");
        }
        //$plaintext = "Safaricom132!";
        $plaintext = 'Heart0ftech';
        openssl_public_encrypt($plaintext, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING);
        return base64_encode($encrypted);
    }

    public function handleResultCallback(Request $request)
    {
        $result = json_decode(json_encode($request->Result));
        if ($result->ResultCode == 0) {
            $conversation_id = $result->ConversationID;
            $withdrawal_request  = PartnerWithdrawalRequest::where('mpesa_conversation_id', $conversation_id)
                ->first();
            if ((bool) $withdrawal_request) {
                $partner = $withdrawal_request->partner;
                $account = $partner->account;
                $amount = $withdrawal_request->amount;
                $account->balance -= $amount;
                $transaction_ref = helper::generateRandomCode(8);

                $account->transactions()->create([
                    'amount' => $amount,
                    'type' => 'credit',
                    'transaction_reference' => $transaction_ref
                ]);

                $account->save();

                $withdrawal_request->fulfilled_on = now();
                $withdrawal_request->request_status = 'fulfilled';
                $withdrawal_request->save();
            }

         }
    }
}
