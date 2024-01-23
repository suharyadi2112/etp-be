<?php

namespace App\Http\Controllers\Api\ManageRoles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper as GLog;
//spatie
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class ManagePermission extends Controller
{
    
    private $useCache;
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', true); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
        app()[PermissionRegistrar::class]->forgetCachedPermissions();//clear cache spatie
    }


    public function GetPermission($id_roles)
    {   

        try {
            
            $permissionsWithStatus = false;
            if ($this->useCache && $id_roles) { //cache & id if null
                $permissionsWithStatus = json_decode(Redis::get('get_all_role_and_permission|'.$id_roles),false);
            }
            $roleName = Role::all(['id', 'name']);

            if (!$permissionsWithStatus || !$this->useCache) {

                $roleIdToCheck = $id_roles; 

                $permissions = Permission::all(['id', 'name', 'group']);
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

                if ($this->useCache && $id_roles) { //cache & id if null
                    Redis::setex('get_all_role_and_permission|'.$id_roles, $this->useExp, $permissionsWithStatus);
                }
            }

            GLog::AddLog('Success retrieved data', "Data successfully retrieved", "info");
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved","data" => $permissionsWithStatus, "dataRole" => $roleName], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails get roles and permission', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail", "message"=>  $e->getMessage(),"data" => null], 500);
        }
    }

    //update permission
    public function UpdatePermission(Request $request) {  

        DB::beginTransaction(); 
        // reset cache permission

        $validator = Validator::make($request->all(), [
            'roleid' => 'required'
        ]);

        if ($validator->fails()) {
            GLog::AddLog('fails body payload', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message" => $validator->errors(),"data" => null], 400);
        }else{
         
            try {
                
                if ($this->useCache) { 
                    Redis::del('get_all_role_and_permission|'.$request->roleid);
                }

                DB::table('role_has_permissions')->where('role_id', '=', $request->roleid)->delete();

                $permissionsToInsert = [];
                foreach ($request->permissions as $permissionId) {
                    $permissionsToInsert[] = [
                        'permission_id' => $permissionId['permission_id'],
                        'role_id' => $request->roleid,
                    ];
                }
                DB::table('role_has_permissions')->insert($permissionsToInsert);

                GLog::AddLog('success update permission', json_encode($permissionsToInsert), "info"); 

                DB::commit();
                return response()->json(["status"=> "success", "message" => "Updated permissions success","data" => $permissionsToInsert ], 200);
            } catch (\Exception $e) {

                DB::rollBack();

                GLog::AddLog('fails udpate permission', $e->getMessage(), "error"); 
                return response()->json(["status"=> "fail","message"=>  $e->getMessage(),"data" => null], 500);
            }
        
        } 
    }

}
