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

     public function findOrCreateUser(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'nullable|email',
            'provider_name' => 'required|in:facebook,twitter,google',
            'provider_user_id' => 'required'
        ]);

        $socialAccount = LinkedSocialAccount::where('provider_name', $request->provider_name)
        ->where('provider_id', $request->provider_user_id)->first();
        
        if($socialAccount){
            return $this->issueToken($request, 'social');
        }
        //Since we can have nullable email, we need to make sure that user email is not null ;)
        //Thx to hdahon for the fix
        $user = User::where('email', $request->email)
                    ->whereNotNull("email")
                    ->first();
        if($user){
            $this->addSocialAccountToUser($request, $user);
        } else {
            try{
                $this->createUserAccount($request);
            }catch(\Exception $e){
                return response($e->getMessage(), 422);
            }
        }
        return $this->issueToken($request, 'social');
    }

     private function addSocialAccountToUser(Request $request, User $user)
     {
        // $this->validate($request, [
        //     'provider_name' => ['required', Rule::unique('linked_social_accounts')->where(function($query) use ($user) {
        //         return $query->where('user_id', $user->id);
        //     })],
        //     'provider_user_id' => 'required'
        // ]);
        $user->socialAccounts()->create([
            'provider_name' => $request->provider_name,
            'provider_id' => $request->provider_user_id
        ]);
    }

    private function createUserAccount(Request $request){
        // DB::transaction( function () use ($request){
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'user_type' => config('constants.USER_PARTNER')
            ]);
            $this->addSocialAccountToUser($request, $user);
        // });
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
