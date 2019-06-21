<?php

namespace App\Http\Controllers\API;

use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Plank\Mediable\MediaUploader;

class UserController extends Controller
{
    public function updateDeviceToken(Request $request)
    {
        $user = $request->user();
        $user->firebase_token = $request->firebase_token;
        $user->save();

        return response()->json([
            'success' => true
        ]);
    }

    public function storePartnerDetails(Request $request, MediaUploader $mediaUploader)
    {
        $rules['national_id'] = 'required';
        $rules['driving_licence'] = 'required';
        $rules['next_of_kin_name'] = 'required';
        $rules['next_of_kin_phone'] = 'required';
        $rules['next_of_kin_email'] = 'required';
        $rules['id_photo'] = 'required|file|mimes:jpg,jpeg,png';
        $rules['passport_photo'] = 'required|file|mimes:jpg,jpeg,png';
        $rules['bike_photo'] = 'required|file|mimes:jpg,jpeg,png';
        $rules['bike_brand'] = 'required';
        $rules['bike_model'] = 'required';
        $rules['bike_reg_no'] = 'required';
        $rules['cgc_photo'] = 'required|file|mimes:jpg,jpeg,png,pdf';
        $rules['log_book'] = 'required|file|mimes:jpg,jpeg,png,pdf';

       $validator =  Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ]);
        }

        $user = $request->user();

        $user->partnerDetails()->create(request([
            'driving_licence',
            'next_of_kin_name',
            'next_of_kin_phone',
            'next_of_kin_email',
            'bike_brand',
            'bike_model',
            'bike_reg_no'
        ]));

        if ($request->file('passport_photo')) {
            $media = $mediaUploader->fromSource($request->file('passport_photo'))
                ->useHashForFilename()
                ->toDestination('public', 'user-uploads/partner-details')
                ->upload();
            $user->attachMedia($media, 'passport-photo');
        }

        if ($request->file('id_photo')) {
            $media = $mediaUploader->fromSource($request->file('id_photo'))
                ->useHashForFilename()
                ->toDestination('public', 'user-uploads/partner-details')
                ->upload();
            $user->attachMedia($media, 'id-photo');
        }

        if ($request->file('dl_photo')) {
            $media = $mediaUploader->fromSource($request->file('dl_photo'))
                ->useHashForFilename()
                ->toDestination('public', 'user-uploads/partner-details')
                ->upload();
            $user->attachMedia($media, 'dl-photo');
        }

        if ($request->file('cgc_photo')) {
            $media = $mediaUploader->fromSource($request->file('cgc_photo'))
                ->useHashForFilename()
                ->toDestination('public', 'user-uploads/partner-details')
                ->upload();
            $user->attachMedia($media, 'cgc-photo');
        }

        if ($request->file('bike_photo')) {
            $media = $mediaUploader->fromSource($request->file('bike_photo'))
                ->useHashForFilename()
                ->toDestination('public', 'user-uploads/partner-details')
                ->upload();
            $user->attachMedia($media, 'bike-photo');
        }

        if ($request->file('log_book')) {
            $media = $mediaUploader->fromSource($request->file('log_book'))
                ->useHashForFilename()
                ->toDestination('public', 'user-uploads/partner-details')
                ->upload();
            $user->attachMedia($media, 'log-book');
        }

        return response()->json([
            'status' => true,
            'message' => 'details recorded'
        ]);
    }
}
