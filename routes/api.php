<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middlewares\PermissionMiddleware;

use App\Http\Controllers\API\PassportAuthController;
use App\Http\Controllers\API\ManageRoles\ManageRoles;
use App\Http\Controllers\API\ManageRoles\ManagePermission;
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

Route::post('login', [PassportAuthController::class, 'login']);
Route::post('register', [PassportAuthController::class, 'register']);

Route::middleware('auth:api')->get('/user', function (Request $request) {
    //manage roles
    // return $request->user();
});


Route::middleware('auth:api')->group(function () {
    //manage roles
    Route::get('get_roles', [ManageRoles::class, 'GetRoles']);
    Route::post('store_roles', [ManageRoles::class, 'StoreRoles']);
    Route::post('del_roles/{id_roles}', [ManageRoles::class, 'DelRoles']);
    Route::get('get_permission/{id_roles}', [ManagePermission::class, 'GetPermission']);

    Route::post('update_permission', [ManagePermission::class, 'UpdatePermission']);
});