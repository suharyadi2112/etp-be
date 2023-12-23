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
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', false); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
    }

    public function StoreRoles(Request $request){

        DB::beginTransaction();

        $validator = Validator::make($request->all(), [
            'nameroles' => 'required|unique:roles,name'
        ]);

        if ($validator->fails()) {
            GLog::AddLog('fails input roles', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => ""], 400);
        }

        try {
            $cekInsert = Role::create(['name' => strtolower($request->nameroles), 'guard_name' => 'api']);

            if ($this->useCache) { //hapus cache data lama
                Redis::del('get_all_roles');
            }
            
            GLog::AddLog('success input roles', $request->nameroles, ""); 

            DB::commit();// commit data

            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => ""], 200);
        } catch (\Exception $e) {

            DB::rollBack();// roolback data

            GLog::AddLog('fails input roles to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> "Server Error","data" => $e->getMessage()], 500);
        }

    }

    public function GetRoles(){
        
          try {

            $data = false;
            if ($this->useCache) { //cache
                $data = json_decode(Redis::get('get_all_roles'),false);
            }
                
            if (!$data || !$this->useCache) {
                $data = Role::all();
                if ($this->useCache) {
                    Redis::setex('get_all_roles', $this->useExp, $data);
                }
            }
            

            GLog::AddLog('success get all roles', "Data successfully retrieved", "info");
            return DataTables::of($data)->addIndexColumn()->make(true)->getData();
           
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> "Server Error","data" => $e->getMessage()], 500);
        }

    }

    public function DelRoles(Role $role, $id_roles){
        DB::beginTransaction(); 
        try {

            $userCount = Role::find($id_roles)->users->count();//cek pengguna dengan roles

            if ($this->useCache) { //hapus cache data lama
                Redis::del('get_all_roles');
            }
       
            if ($userCount > 0) {
               GLog::AddLog('Role has been assigned', "This role '".$id_roles."' has been used by the user.", "warning"); 
               return response()->json(["status"=> "fail","message"=> "This role has been assigned.","data" => null], 400);
            }

            $role = Role::where('id', $id_roles)->first();
            if ($role) {
                $role->delete();
                DB::commit();
                GLog::AddLog('Roles deleted', "This role '".$id_roles."' has been deleted.", "info"); 
                return response()->json(["status"=> "success","message"=> "Success del datas roles", "data" => null], 200);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            GLog::AddLog('Fails to processed', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }

    }

    public function UpdateRoles(Role $role, Request $request){

        DB::beginTransaction(); 

        $validator = Validator::make($request->all(), [
            'nameroles' => 'required|unique:roles,name',
            'id_roles' => 'required'
        ]);

        if ($validator->fails()) {
            GLog::AddLog('fails update roles', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => null], 400);
        }

        try {
            if ($this->useCache) {
                Redis::del('get_all_roles');
            }
       
            $role = Role::where('id', $request->id_roles)->update(['name' => $request->nameroles]);
            DB::commit();
            
            GLog::AddLog('Roles updated', "This role '".$request->id_roles."' has been update.", "info"); 
            return response()->json(["status"=> "success","message"=> "Success update data roles", "data" => null], 200);
            

        } catch (\Exception $e) {
            DB::rollBack();
            GLog::AddLog('Fails to processed', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }

    }

}
