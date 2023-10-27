<?php

namespace App\Http\Controllers\Api\ManageRoles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use Yajra\Datatables\Datatables;
use Spatie\Permission\Models\Role;
use App\Helpers\Helper as GLog;

//spatie
use Spatie\Permission\Models\Permission;

//model
use App\Models\User;

class ManageRoles extends Controller
{

    private $useCache;
    private $filteredPermissions;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', true); //setup redis
    }

    public function StoreRoles(Request $request){

        DB::beginTransaction();

        $validator = Validator::make($request->all(), [
            'nameroles' => 'required'
        ]);

        if ($validator->fails()) {
            GLog::AddLog('fails input roles', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => ""], 500);
        }

        try {
            $cekInsert = Role::create(['name' => strtolower($request->nameroles), 'guard_name' => 'api']);
            
            GLog::AddLog('success input roles', $request->nameroles, ""); 

            DB::commit();// commit data

            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => ""], 200);
        } catch (\Exception $e) {

            DB::rollBack();// roolback data

            GLog::AddLog('fails input roles to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> "Server Error","data" => $e->getMessage()], 500);
        }

    }

    public function GetRoles(Request $request){
        
        try {

            $data = false;
            if ($this->useCache) {
                $data = json_decode(Redis::get('get_all_roles'),false);
            }
                
            if (!$data || !$this->useCache) {
                $data = Role::all();
                if ($this->useCache) {
                    Redis::setex('get_all_roles', 3600, $data);
                }
            }
            
            $resData = DataTables::of($data)->addIndexColumn()->make(true);
            $jsonData = $resData->getData();

            $permissionsData = Auth::user()->getAllPermissions()->map->only('id', 'name');
            $jsonData->permissions = $permissionsData; //letak permission di api

            GLog::AddLog('success get all roles', "", ""); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $jsonData], 200);
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> "Server Error","data" => $e->getMessage()], 500);
        }

    }

    public function DelRoles(Role $role, $id_roles){
        try {
            $item = Role::withCount('users')->find($id_roles);
       
            if ($item && $item->users_count > 0) {
               GLog::AddLog('Role has been assigned', "This role '".$id_roles."' has been used by the user.", "warning"); 
               return response()->json(["status"=> "fail","message"=> "Server Error","data" => "This role '".$id_roles."' has been used by the user."], 500);
            }
   

            $role = Role::findById($id_roles);
            if ($role) {
                $role->delete();
                GLog::AddLog('Roles deleted', "This role '".$id_roles."' has been deleted.", "info"); 
                return response()->json(["status"=> "success","message"=> "Success del datas roles", "data" => ""], 200);
            }

        } catch (\Exception $e) {
            GLog::AddLog('Fails to processed', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> "Server Error","data" => $e->getMessage()], 500);
        }

    }

}
