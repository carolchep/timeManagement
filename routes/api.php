<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => 'auth:api'], function(){
    Route::get('entries', 'API\TimeEntriesController@index');
    Route::post('entries', 'API\TimeEntriesController@store');
    Route::post('entries/{entry}', 'API\TimeEntriesController@update');
    Route::delete('entries/{entry}', 'API\TimeEntriesController@delete');
});
Route::post('/login', 'API\Auth\LoginController@login');
Route::post('oauth/token', 'API\Auth\AccessTokenController@issueToken');
Route::post('/register', 'API\Auth\RegisterController@register');