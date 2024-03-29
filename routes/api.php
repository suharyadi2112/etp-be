<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SanctumAuthController;
use App\Http\Controllers\Api\ManageRoles\ManageRoles;
use App\Http\Controllers\Api\ManageSemester\ManageSemester;
use App\Http\Controllers\Api\ManageRoles\ManagePermission;
use App\Http\Controllers\Api\ManageMataPelajaran\ManageMataPelajaran;
use App\Http\Controllers\Api\ManageBaseMataPelajaran\ManageBaseMataPelajaran;
use App\Http\Controllers\Api\ManageBaseKelas\ManageBaseKelas;
use App\Http\Controllers\Api\ManageSiswa\ManageSiswa;
use App\Http\Controllers\Api\ManageGuru\ManageGuru;

use App\Http\Controllers\Api\DropBoxTool\DropBoxTool;
use App\Http\Controllers\Api\ManageParent\ManageParent;

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

Route::post('login', [SanctumAuthController::class, 'login']);
Route::post('register', [SanctumAuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function () {
    //logout
    Route::post('logout', [SanctumAuthController::class, 'logout']);

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
    Route::get('get_semester/{id}', [ManageSemester::class, 'GetSemesterById']);
    Route::put('update_semester/{id}', [ManageSemester::class, 'UpdateSemester']);

    //manage mata_pelajaran
    Route::post('store_mata_pelajaran', [ManageMataPelajaran::class, 'StoreMatPelajaran']);
    Route::get('get_mata_pelajaran', [ManageMataPelajaran::class, 'GetMatPelajaran']);
    Route::get('get_mata_pelajaran/{id}', [ManageMataPelajaran::class, 'GetMatPelajaranById']);
    Route::put('update_mata_pelajaran/{id}', [ManageMataPelajaran::class, 'UpdateMatPelajaran']);
    Route::delete('del_mata_pelajaran/{id}', [ManageMataPelajaran::class, 'DelMatPelajaran']);

    //manage base mata_pelajaran
    Route::get('get_base_mata_pelajaran', [ManageBaseMataPelajaran::class, 'GetBaseMataPelajaran']);
    Route::post('store_base_mata_pelajaran', [ManageBaseMataPelajaran::class, 'StoreBaseMataPelajaran']);
    Route::put('update_base_mata_pelajaran/{id}', [ManageBaseMataPelajaran::class, 'UpdateBaseMataPelajaran']);
    Route::delete('del_base_mata_pelajaran/{id}', [ManageBaseMataPelajaran::class, 'DelBaseMataPelajaran']);
    Route::get('get_base_mata_pelajaran/{id}', [ManageBaseMataPelajaran::class, 'GetBaseMataPelajaranById']);

    //manage base kelas
    Route::get('get_base_kelas', [ManageBaseKelas::class, 'GetBaseKelas']);
    Route::post('store_base_kelas', [ManageBaseKelas::class, 'StoreBaseKelas']);
    Route::put('update_base_kelas/{id}', [ManageBaseKelas::class, 'UpdateBaseKelas']);
    Route::delete('del_base_kelas/{id}', [ManageBaseKelas::class, 'DelBaseKelas']);
    Route::get('get_base_kelas/{id}', [ManageBaseKelas::class, 'GetBaseKelasByID']);

    //manage siswa
    Route::get('get_siswa', [ManageSiswa::class, 'GetSiswa']);
    Route::post('store_siswa', [ManageSiswa::class, 'StoreSiswa']);
    Route::put('update_siswa/{id}', [ManageSiswa::class, 'UpdateSiswa']);
    Route::delete('del_siswa/{id}', [ManageSiswa::class, 'DelSiswa']);
    Route::get('get_siswa/{id}', [ManageSiswa::class, 'GetSiswaByID']);
    
    //manage guru
    Route::get('get_guru', [ManageGuru::class, 'GetGuru']);
    Route::post('store_guru', [ManageGuru::class, 'StoreGuru']);
    Route::put('update_guru/{id}', [ManageGuru::class, 'UpdateGuru']);
    Route::delete('del_guru/{id}', [ManageGuru::class, 'DelGuru']);
    Route::get('get_guru/{id}', [ManageGuru::class, 'GetGuruByID']);

    //manage parant(orang tua/wali)
    Route::get('get_orangtua', [ManageParent::class, 'GetOrangTua']);
    Route::post('store_orangtua', [ManageParent::class, 'StoreOrangtua']);
    Route::get('get_orangtua/{id}', [ManageParent::class, 'GetOrtuByID']);
    Route::delete('del_orangtua/{id}', [ManageParent::class, 'DelOrtu']);
    Route::put('up_orangtua/{id}', [ManageParent::class, 'UpOrtu']);
    

    
    Route::post('temp_file', [DropBoxTool::class, 'GetTemporaryLink']);
});
