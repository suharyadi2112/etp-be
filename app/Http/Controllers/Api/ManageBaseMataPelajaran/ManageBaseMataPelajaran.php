<?php

namespace App\Http\Controllers\Api\ManageBaseMataPelajaran;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper as GLog;
//model
use App\Models\BaseMataPelajaran;

class ManageBaseMataPelajaran extends Controller
{
    private $useCache;
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', false); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
    }

    public function GetBaseMataPelajaran(Request $request){
        
        $perPage = $request->input('per_page');
        $search = $request->input('search');
        $page = $request->input('page');

        try {
            $cacheKey = 'search_basematapelajaran:' . md5($search . $perPage . $page);
            $getBaseMatPelajaran = false;

            if ($this->useCache) {
                $getBaseMatPelajaran = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getBaseMatPelajaran || !$this->useCache) {
                $query = BaseMataPelajaran::query();
                if ($search) {
                    $query->search($search);// jika ada pencarian
                }
                $query->orderBy('created_at', 'desc');
                $getBaseMatPelajaran = $query->paginate($perPage);

                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getBaseMatPelajaran));
                } 
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getBaseMatPelajaran], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function StoreBaseMataPelajaran(Request $request){

        DB::beginTransaction();
        
        $validator = $this->validateBaseMatPelajaran($request, 'insert');
        if ($validator->fails()) {
            GLog::AddLog('fails input base mata pelajaran', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => null], 400);
        }
        try {
            BaseMataPelajaran::create([
                'base_subject_name' => strtolower($request->input('base_subject_name')),
            ]);
            GLog::AddLog('success input base mata pelajaran', $request->all(), ""); 
        
            DB::commit();
            if ($this->useCache) {
                $this->deleteSearchBaseMataPelajaran('search_basematapelajaran:*');
            }
            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => $request->all()], 200);

        } catch (\Exception $e) {
            
            DB::rollBack();
            GLog::AddLog('fails input base mata pelajaran to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function UpdateBaseMataPelajaran($idBaseMatPel, Request $request){
    
        try {
            $validator = $this->validateBaseMatPelajaran($request, 'update');

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
           
            DB::transaction(function () use ($request, $idBaseMatPel) {
                $baseMataPelajaran = BaseMataPelajaran::find($idBaseMatPel);

                if (!$baseMataPelajaran) {
                    throw new \Exception('Base mata pelajaran not found');
                }
                $baseMataPelajaran->fill(array_map('strtolower',$request->all()));
                $baseMataPelajaran->save();

                if ($this->useCache) {
                    $this->deleteSearchBaseMataPelajaran('search_basematapelajaran:*');
                }

                GLog::AddLog('success updated base mata pelajaran', $request->all(), ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'Base mata pelajaran updated successfully', 'data' => $request->all()], 200);

        } catch (ValidationException $e) {
            GLog::AddLog('fails update base mata pelajaran validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails update base mata pelajaran', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }

    }

    public function DelBaseMataPelajaran($idBaseMatPel){
        
        try {
            $subjectName = null;
            DB::transaction(function () use ($idBaseMatPel, &$subjectName) {
                $matBasePelajran = BaseMataPelajaran::find($idBaseMatPel);

                if (!$matBasePelajran) {
                    throw new \Exception('Base Mata pelajaran not found');
                }

                $subjectName = $matBasePelajran->base_subject_name;
                $matBasePelajran->delete();//SoftDelete

                if ($this->useCache) {
                    $this->deleteSearchBaseMataPelajaran('search_basematapelajaran:*');
                }

                GLog::AddLog('success delete base mata pelajaran', $matBasePelajran->base_subject_name, ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'Base Mata Pelajaran delete successfully', 'data' => $subjectName], 200);
    
        } catch (ValidationException $e) {
            GLog::AddLog('fails delete base mata pelajaran validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails delete base mata pelajaran', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }

    public function GetBaseMataPelajaranById($id){
        try {
            $data = BaseMataPelajaran::find($id);

            if (!$data) {
                throw new \Exception('Base Mata pelajaran not found');
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $data], 200);
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    
    //-----------
    private function validateBaseMatPelajaran(Request $request, $action = 'insert')// insert is default
    {   
        $validator = Validator::make($request->all(), [
            'base_subject_name' => ['required','string','max:200',
                function ($attribute, $value, $fail) use ($request, $action) {
                    $query = BaseMataPelajaran::withTrashed()
                    ->where('base_subject_name', $value);

                    if ($action === 'update') {
                        $query->where('id', '!=', $request->id);
                    }
                    
                    $existingData = $query->first();
        
                    if ($existingData && !$existingData->trashed()) {
                        $fail($attribute.' has already been taken.');
                    }
                },
            ],
        ]);

        return $validator;
    }

    //delete cache 
    protected function deleteSearchBaseMataPelajaran($pattern)
    {
        $keys = Redis::keys($pattern);
        foreach ($keys as $key) {
            // Remove the "laravel_database" prefix
            $newKey = str_replace('laravel_database_', '', $key);
            Redis::del($newKey);
        }
    }
}
