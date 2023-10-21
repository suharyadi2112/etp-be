<?php

namespace App\Http\Controllers\Api\ManageRoles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Helpers\Helper as GLog;
//spatie
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
//model
use App\Models\User;

class ManagePermission extends Controller
{
 
    public function GetPermission($id_roles)
    {   

        if (!auth()->user()->can('update permission')) {
            return response()->json(["status"=> "fails","message"=> "Unauthorized. You do not have access.","data" => null], 401);
        }
        
        try {
            $permissions = Permission::all(['id', 'name', 'group']);
            $roleIdToCheck = $id_roles; 
            $permissionsWithStatus = $permissions->groupBy('group')->map(function ($groupPermissions, $groupName) use ($roleIdToCheck) {
                $permissions = $groupPermissions->map(function ($permission) use ($roleIdToCheck) {
                    $data = DB::table('role_has_permissions')
                        ->where('role_id', $roleIdToCheck)
                        ->where('permission_id', $permission->id)
                        ->first();

                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'status_check' => $data ? true : false,
                    ];
                });

                return [
                    'group' => $groupName,
                    'permissions' => $permissions,
                ];
            });


            GLog::AddLog('Success retrieved data', "Data successfully retrieved", "info");
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved","data" => $permissionsWithStatus], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails get roles and permission', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> "Server Error","data" => $e->getMessage()], 500);
        }
    }

    //update permission
    public function UpdatePermission(Request $request) {  

        DB::beginTransaction(); 
        // reset cache permission
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $validator = Validator::make($request->all(), [
            'roleid' => 'required'
        ]);



        if ($validator->fails()) {
            GLog::AddLog('fails body payload', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message" => $validator->errors(),"data" => null], 400);
        }else{
         
            if ($request->permission_id) {
                try {
                    
                    $deleted = DB::table('role_has_permissions')->where('role_id', '=', $request->roleid)->delete();

                    $permissionsToInsert = [];
                    foreach ($request->permission_id as $permissionId) {
                        $permissionsToInsert[] = [
                            'permission_id' => $permissionId,
                            'role_id' => $request->roleid,
                        ];
                    }
                    $resInsert = DB::table('role_has_permissions')->insert($permissionsToInsert);

                    GLog::AddLog('success update permission', json_encode($permissionsToInsert), "info"); 

                    DB::commit();
                    return response()->json(["status"=> "success", "message" => "Updated permissions success","data" => $permissionsToInsert ], 200);
                } catch (\Exception $e) {

                    DB::rollBack();

                    GLog::AddLog('fails udpate permission', $e->getMessage(), "error"); 
                    return response()->json(["status"=> "fail","message"=> "Server Error","data" => $e->getMessage()], 500);
                }
            }else{
                return response()->json(["status"=> "fail","message"=> "Permission id not found","data" => null ], 500);
            }
        } 
    }

}
