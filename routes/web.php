<?php

use Illuminate\Support\Facades\Route;
use Dcblogdev\Dropbox\Facades\Dropbox;

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


Route::group(['middleware' => ['web', 'DropboxAuthenticated']], function(){
    Route::get('dropbox', function(){
        return Dropbox::post('users/get_current_account');
    });
});

Route::get('dropbox/connect', function(){
    return Dropbox::connect();
});

Route::get('dropbox/disconnect', function(){
    return Dropbox::disconnect('app/dropbox');
});