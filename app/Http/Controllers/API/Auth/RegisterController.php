<?php

namespace App\Http\Controllers\API\Auth;

use Al\Models\PartnerAccount;
use App\Events\ApiUserRegistered;
use App\Http\Controllers\Controller;
use App\Providers\AppUserRegistered;
use App\Subscription;
use App\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Plank\Mediable\MediaUploader;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected function validator(array $data, $type)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'required|unique:users,phone' //Add Custom Phone Validator
        ];

        $messages = [
            'email.required' => 'We need to know your e-mail address!',
        ];

        return Validator::make($data, $rules, [
            'bike_reg_no.required' => 'We need to know your bike registration number',
            'email.required' => 'We need to know your e-mail address!',
        ]);
    }

    protected function create(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'type' => $data['type'],
            'phone' => $data['phone']
        ]);

        if ($user->type == 'partner') {
            PartnerAccount::create([
                'partner_id' => $user->id,
                'balance' => 0
            ]);
        }

        return $user;
    }

    public function register(Request $request, MediaUploader $mediaUploader)
    {
        $validator = $this->validator($request->all(), $request->type);

       if ($validator->fails()) {
           return response()->json([
               'success' => false,
               'errors' => $validator->errors()
           ]);
       }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'type' => $request->type,
            'password' => $request->password
        ]);

        if ($user->type == 'partner') {
            PartnerAccount::create([
                'partner_id' => $user->id,
                'balance' => 0
            ]);

           Subscription::create([
                'subscriber_id' => $user->id
            ]);
        }

//        $user = $this->create($user_data);

        event(new AppUserRegistered($user));

        return response()->json([
            'success' => true,
            'message' => "you have are signed up. Check $user->email for verification link"
        ]);
    }

    public function verifyUserEmail($token)
    {
        
    }
}
