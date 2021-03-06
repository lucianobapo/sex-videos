<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sumup/callback/{data?}', function ($data=null) {
    if (isset($_GET['code'])){
        Sumup\Sumup::setClientSecret(config('sumup.secret'));
        Sumup\Sumup::setClientId(config('sumup.client_id'));
        Sumup\Sumup::setRedirectUri(config('sumup.redirect_uri'));
        $access_token = Sumup\OAuth::getToken([
            'grant_type' => 'authorization_code',
            'code' => $_GET['code']
          ]);
    }
    
    return dd($access_token);
});
