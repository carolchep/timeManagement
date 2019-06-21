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
            'password' => bcrypt($data['password']),
        ]);

        return $user;
    }

    public function register(Request $request)
    {
        $validator = $this->validator($request->all(), $request->type);

       if ($validator->fails()) {
           return response()->json([
               'success' => false,
               'errors' => $validator->errors()
           ]);
       }

        $user = $this->create($request->all());

        return response()->json([
            'success' => true,
            'message' => "you have are signed up. Check $user->email for verification link"
        ]);
    }

    public function verifyUserEmail($token)
    {
        
    }
}
