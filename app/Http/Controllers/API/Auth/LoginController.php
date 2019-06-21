<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Traits\IssuesToken;
use App\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use \Illuminate\Support\Facades\Validator;
use Laravel\Passport\Client;
// use Illuminate\Support\Facades\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers, IssuesToken;

    private $client;

    public function __construct()
    {
        $this->client = Client::where('name', config('app.name').' Password Grant Client')->first();
    }

     protected function sendFailedLoginResponse(Request $request)
     {
        return response()->json([
            $this->username() => [trans('auth.failed')],
        ]);
     }

     protected function login(Request $request)
     {
         $rules = [
             'email' => 'required',
             'password' => 'required'
         ];

         $validator = Validator::make($request->all(), $rules, [
             'email.required' => 'We need to know your e-mail address!',
         ]);

         if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ]);
         }

        return $this->issueToken($request, 'password');
     }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return $this->loggedOut($request) ?: response()->json([
            'success' => false,
            'message' => 'cannot log you out'
        ]);
    }

    protected function loggedOut(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'you are logout'
        ]);
    }
}
