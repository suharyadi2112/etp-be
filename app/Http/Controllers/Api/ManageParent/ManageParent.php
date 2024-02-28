<?php

namespace App\Http\Controllers\Api\ManageParent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper as GLog;
//model
use App\Models\OrangTua;

class ManageParent extends Controller
{
    private $useCache;
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', false); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
    }

    public function GetOrangTua(Request $request){
        
        $perPage = $request->input('per_page', 5);
        $search = $request->input('search');
        $page = $request->input('page', 1);

        try {
            $cacheKey = 'search_orangtua:' . md5($search . $perPage . $page);
            $getParent = false;

            if ($this->useCache) {
                $getParent = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getParent || !$this->useCache) {

                $query = OrangTua::query()->with(['siswa' => function ($query) {
                    $query->select('a_siswa.id', 'a_siswa.nama');
                }]);
                if ($search) {
                    $query->whereHas('siswa', function ($query) use ($search) {
                        $query->where('nama', 'like', "%$search%");
                    });
                }
                $query->orderBy('created_at', 'desc');
                $getParent = $query->paginate($perPage);

                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getParent));
                } 
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getParent], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function StoreOrangtua(Request $request){

        DB::beginTransaction();
        $validator = $this->validateOrtu($request, 'insert');  
        
        if ($validator->fails()) {
            GLog::AddLog('fails input orang tua', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => null], 400);
        }
        try {
            OrangTua::create([
                'name' => strtolower($request->input('name')),
                'address' => $request->input('address'),
                'phone_number' => $request->input('phone_number'),
                'email' => $request->input('email'),
                'date_of_birth' => $request->input('date_of_birth'),
                'place_of_birth' => $request->input('place_of_birth'),
                'occupation' => $request->input('occupation'),
                'additional_notes' => $request->input('additional_notes'),
            ]);
            GLog::AddLog('success input orang tua', $request->all(), ""); 
        
            DB::commit();
            if ($this->useCache) {
                $this->deleteSearchOrtu('search_orangtua:*');
            }
            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => $request->all()], 200);

        } catch (\Exception $e) {
            
            DB::rollBack();
            GLog::AddLog('fails input orang tua to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function GetOrtuByID($id){
        
        try {
            $cacheKey = 'search_orangtua:' . md5($id);
            $getOrtu = false;
            if ($this->useCache) {
                $getOrtu = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getOrtu || !$this->useCache) {
                // $getOrtu = OrangTua::with('siswa')->find($id);

                $getOrtu = OrangTua::query()->with(['siswa' => function ($query) {
                    $query->select('a_siswa.id', 'a_siswa.nama');
                }])->find($id);

                if (!$getOrtu) {
                    throw new \Exception('Orang tua not found');
                }

                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getOrtu));
                } 
            }
            
            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getOrtu], 200);
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function DelOrtu($id){
        try {
            $ortuName = null;
            DB::transaction(function () use ($id, &$ortuName) {
                $ortuData = OrangTua::find($id);

                if (!$ortuData) {
                    throw new \Exception('ortu not found');
                }

                $ortuName = $ortuData->nama;
                $ortuData->delete();//SoftDelete

                if ($this->useCache) {
                    $this->deleteSearchOrtu('search_orangtua:*');
                }

                GLog::AddLog('success delete orang tua', $ortuData->nama, ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'orang tua delete successfully', 'data' => $ortuName], 200);
    
        } catch (ValidationException $e) {
            GLog::AddLog('fails delete orang tua validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails delete orang tua', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }

    //-----------
    private function validateOrtu(Request $request, $action = 'insert')// insert is default
    {   
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:500',
            'date_of_birth' => 'required|date',
            'place_of_birth' => 'required|string|max:1000',
            'address' => 'string|max:1000|nullable',
            'phone_number' => 'required|max:20',
            'email' => 'email|max:200',
            'occupation' => 'max:500',
            'additional_notes' => 'string|max:1000',
        ]);
     
        return $validator;
    }

    //delete cache 
    protected function deleteSearchOrtu($pattern)
    {
        $keys = Redis::keys($pattern);
        foreach ($keys as $key) {
            // Remove the "laravel_database" prefix
            $newKey = str_replace('laravel_database_', '', $key);
            Redis::del($newKey);
        }
    }
}
