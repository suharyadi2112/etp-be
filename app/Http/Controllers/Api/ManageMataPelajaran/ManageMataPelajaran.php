<?php

namespace App\Http\Controllers\Api\ManageMataPelajaran;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use App\Helpers\Helper as GLog;
//spatie
use Spatie\Permission\Models\Permission;
//model
use App\Models\MataPelajaran;
use App\Models\User;

class ManageMataPelajaran extends Controller
{
    private $useCache;
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', false); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
    }

    public function GetMatPelajaran(){

        try {
            $getMatPelajaran = false;
            if ($this->useCache) { //cache
                $getMatPelajaran = json_decode(Redis::get('get_all_mata_pelajaran'),false);
            }

            if (!$getMatPelajaran || !$this->useCache) {
                $getMatPelajaran = MataPelajaran::all();
                if ($this->useCache) {
                    Redis::setex('get_all_mata_pelajaran', $this->useExp, $getMatPelajaran);
                }
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getMatPelajaran], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }

    }

    public function StoreMatPelajaran(Request $request){

        $validator = $this->validateMatPelajaran($request, 'insert');

        if ($validator->fails()) {
            GLog::AddLog('fails input mata pelajaran', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => null], 400);
        }

        try {
            MataPelajaran::create([
                'subject_name' => $request->input('subject_name'),
                'subject_description' => $request->input('subject_description'),
                'education_level' => $request->input('education_level'),
                'subject_code' => $request->input('subject_code'),
            ]);

            if ($this->useCache) {
                Redis::del('get_all_mata_pelajaran');
            }
            
            GLog::AddLog('success input mata pelajaran', $request->all(), ""); 
            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => $request->all()], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails input mata pelajaran to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }

    }



    //-----
    //-----------
    private function validateMatPelajaran(Request $request, $action = 'insert')// insert is default
    {
        $validator = Validator::make($request->all(), [
            'subject_name' => 'required|string|max:100|unique:a_mata_pelajaran,subject_name',
            'subject_description' => 'nullable|string',
            'education_level' => 'nullable|string',
            'subject_code' => 'nullable|string|unique:a_mata_pelajaran,subject_code',
        ]);

        return $validator;
    }

}
