<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

trait IssuesToken
{
	public function issueToken(Request $request, $grantType, $scope = ""){
		$params = [
    		'grant_type' => $grantType,
    		'client_id' => $this->client->id,
    		'client_secret' => $this->client->secret,    		
    		'scope' => $scope
    	];

        if($grantType !== 'social'){
            $params['username'] = $request->username ?: $request->email;
        }

    	$request->request->add($params);

    	$proxy = Request::create('api/oauth/token', 'POST');

    	return Route::dispatch($proxy);
	}

}
