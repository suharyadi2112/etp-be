<?php
use App\Http\Controllers\Api\ManageRoles\ManagePermission;
use App\Http\Controllers\Api\ManageRoles\ManageRoles;
use App\Http\Controllers\Api\PassportAuthController;


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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Origin, Authorization');

Route::post('login', [PassportAuthController::class, 'login']);
Route::post('register', [PassportAuthController::class, 'register']);
Route::get('/hello-world', [PassportAuthController::class, 'helloWorld']);

Route::middleware(['auth:api'])->group(function () {
    //logout
    Route::post('logout', [PassportAuthController::class, 'logout']);

    //manage roles
    Route::get('get_roles', [ManageRoles::class, 'GetRoles']);
    Route::post('store_roles', [ManageRoles::class, 'StoreRoles']);
    Route::post('del_roles/{id_roles}', [ManageRoles::class, 'DelRoles']);
    Route::get('get_permission/{id_roles}', [ManagePermission::class, 'GetPermission']);

    Route::post('update_permission', [ManagePermission::class, 'UpdatePermission']);
});