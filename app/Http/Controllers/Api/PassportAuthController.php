<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper as GLog;
//spatie
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
//model
use App\Models\User;

class PassportAuthController extends Controller
{
    public function login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            GLog::AddLog('fails body payload', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message" => $validator->errors(),"data" => null], 400);
        }

        $data = [
            'email' => $request->email,
            'password' => $request->password
        ];

        if (auth()->attempt($data)) {
            try {
                $token = auth()->user()->createToken('EtpEducation')->accessToken;
                $userRoles = auth()->user()->getRoleNames()->toArray();

                GLog::AddLog('success create token', "berhasil membentuk token", "info"); 
                
                return response()->json(
                    [
                        "status"=> "success", 
                        "message" => "Success login",
                        "data" => [
                            "token_type" => "Bearer",
                            "access_token" => $token,
                        ],
                    ], 200)->header('X-User-Roles', implode(', ', $userRoles));

            } catch (\Exception $e) {
                GLog::AddLog('fails create token', $e->getMessage(), "error"); 
                return response()->json(["status"=> "fail","message"=> "Server Error","data" => $e->getMessage()], 500);
            }
        } else {
            GLog::AddLog('fails login', "gagal login", "warning"); 
            return response()->json(["status"=> "fails", "message" => "Unauthorised","data" => $token], 401);
        }
    }

    public function register(Request $request)
    {

        DB::beginTransaction();

        $validator = Validator::make($request->all(), [
            'name' => 'required|min:4',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'roless' => 'required',
        ]);

        if ($validator->fails()) {
            GLog::AddLog('fails body payload', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message" => $validator->errors(),"data" => null], 400);
        }

        try {

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password)
            ]);

            // reset cache permission
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            $roleToAssign = Role::findById($request->roless, 'api');
            $user->assignRole($roleToAssign);
            
            DB::commit();// commit data

            GLog::AddLog('Success store users', "Data successfully stored", "info");
            return response()->json(["status"=> "success", "message" => "Data successfully stored", "data" => $request->all()], 200);
        } catch (\Exception $e) {

            DB::rollBack();// roolback data

            GLog::AddLog('fails store users', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> "Server Error","data" => $e->getMessage()], 500);
        }
    }
}
