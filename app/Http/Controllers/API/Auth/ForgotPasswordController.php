<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetLink;
use App\User;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    public function __construct()
    {
        $this->middleware('guest');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $this->validateEmail($request);

        $fields = array(
            'email' => $request->email
        );

        $token = JWT::encode($fields, 'email');

        // dd(JWT::decode($token, 'email', ['HS256'])->email);

        $inserted = DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        if ($inserted) {
            $user = User::where('email', $request->email)->first();
            Mail::to($request->email)->send(new PasswordResetLink($token, $user));
            return response()->json([
                'status' => 'success',
                'message' => "a reset link has been sent to $request->email"
            ]);
        }
    }

    protected function validateEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email|exists:users']);
    }
}
