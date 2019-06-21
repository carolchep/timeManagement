<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordEmail;
use App\ResetToken;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Swift_TransportException;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;


    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.passwords.reset')->with(
            ['token' => $token, 'email' => $request->email]
        );
    }

    public function reset(Request $request)
    {
        $this->validate($request, $this->rules(), $this->validationErrorMessages());

        $reset = DB::table('password_resets')->where('token', $request->token);
        if((bool)$reset->first()) {
            $user = User::where('email', $reset->first()->email)->first();
            
            $user->password = $request->password;

            $user->save();
            $reset->delete();
            $resetEmail = cookie()->make('email', $user->email);
            return redirect('/password-reset')->withCookie($resetEmail);
        } else {
            toast('invalid reset link', 'error', 'top-right');

            return back();
        }
    }

    protected function rules()
    {
        return [
            'token' => 'required',
            'password' => 'required|confirmed|min:6',
        ];
    }

    public function changePassword(Request $request)
    {   
        $this->validate($request, [
            'new_password' => 'required',
            'current_password' => 'required'
        ]);

        $user = $request->user();

        if (Hash::check($request->current_password,$user->password)) {
            $user->password = $request->new_password;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'password updated'
            ]);
        }


        return response()->json([
            'status' => 'error',
            'message' => 'you current password does not match our records'
        ]);
    }

    public function sendResetToken(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();
        $token = Str::random(16);
        ResetToken::create([
            'email' => $user->email,
            'token' => $token,
            'expiry_date' => Carbon::now()->addHours(12),
            'user_id' => $user->id
        ]);
        try {
            Mail::to($user->email)->send(new ResetPasswordEmail($user, $token));
            return response()->json([
                'status' => 'success',
                'message' => "password reset email sent to $user->email."
            ]);
        } catch (Swift_TransportException $e) {
            return response()->json([
                'status' => 'error',
                'message' => "error sending email"
            ]);
        }
    }

    public function showMessage()
    {
        return view('auth.passwords.message');
    }

    public function showMobileResetForm($token)
    {
        return view('auth.mobile.reset', get_defined_vars());
    }

    public function resetPasswordMobile(Request $request)
    {
//        dd($request->all());
        $this->validate($request, [
            'password' => 'required|confirmed',
            'reset_token' => 'required|exists:reset_tokens,token'
        ]);

        $reset = ResetToken::where('token',$request->reset_token)->first();
        $user = User::where('email', $reset->email)->first();

        $user->password = $request->password;
        $user->save();

        $reset->delete();

        if ($request->has('mobile')) {
            return response()->json([
                'status' => 'success',
                'message' => 'password reset'
            ]);
        }

        return redirect('dashboard');
    }
}
