<?php

namespace App\Http\Controllers\API;

use App\Rules\ChecksCurrentPassword;
use App\Rules\PhoneExists;
use App\Transformers\UserTransformer;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserAccountController extends Controller
{
    public function update(Request $request)
    {
        $this->validate($request, [
            'phone' => [
                'required',
                new PhoneExists($request->user()->id)
            ],
            'profile_image' => 'required|file|mimes:jpg,jpeg,png',
            'name' => 'required'
        ]);

        $user = $request->user();
        $user->phone = $request->phone;
        $user->name = $request->name;
        $user->save();

        if ($request->hasFile('profile_image')) {
            $extension = $request->file('profile_image')->getClientOriginalExtension();
            $path = $request->file('profile_image')->storeAs('avatars', md5("$user->name ".now()) . ".$extension", 'public');

            $user->profile_image_url = $path;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'account details updated',
            'user' => fractal($user, new UserTransformer())
        ]);
    }

    public function changePassword(Request $request)
    {
        $this->validate($request, [
            'current_password' => [
                'required',
                new ChecksCurrentPassword($request->user()->password)
            ],
            'new_password' => 'required|confirmed',
        ]);
    }

    public function toggleOnlineStatus(Request $request)
    {
        $this->validate($request, [
            'is_online' => 'required'
        ]);

        $user = $request->user();

        $message = "you are now online";

        if ((bool) $request->is_online) {
            $user->last_seen_on = null;
            $user->save();
        } else {
            $user->last_seen_on = now(config('app.timezone'));
            $user->save();
            $message = "you are now offline";
        }

        return response()->json([
             'success' => true,
            'message' => $message
        ]);
    }

}
