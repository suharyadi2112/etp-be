<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PassportAuthController;
use App\Http\Controllers\Api\ManageRoles\ManageRoles;
use App\Http\Controllers\Api\ManageSemester\ManageSemester;
use App\Http\Controllers\Api\ManageRoles\ManagePermission;

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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('login', [PassportAuthController::class, 'login']);
Route::post('register', [PassportAuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function () {
    //logout
    Route::post('logout', [PassportAuthController::class, 'logout']);

    //manage roles
    Route::get('get_roles', [ManageRoles::class, 'GetRoles']);
    Route::post('update_roles', [ManageRoles::class, 'UpdateRoles']);
    Route::post('store_roles', [ManageRoles::class, 'StoreRoles']);
    Route::post('del_roles/{id_roles}', [ManageRoles::class, 'DelRoles']);
    Route::get('get_permission/{id_roles}', [ManagePermission::class, 'GetPermission']);
    //manage permission
    Route::post('update_permission', [ManagePermission::class, 'UpdatePermission']);

    //manage semester
    Route::post('store_semester', [ManageSemester::class, 'StoreSemester']);
    Route::get('get_semester', [ManageSemester::class, 'GetSemester']);

});