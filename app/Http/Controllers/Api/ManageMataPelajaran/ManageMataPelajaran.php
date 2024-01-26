<?php

namespace App\Http\Controllers\Api\ManageMataPelajaran;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper as GLog;
//model
use App\Models\MataPelajaran;

class ManageMataPelajaran extends Controller
{
    private $useCache;
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', false); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
    }
    
    public function GetMatPelajaran(Request $request){

        $perPage = $request->input('per_page', 5);
        $search = $request->input('search');
        $page = $request->input('page', 1);

        try {

            $cacheKey = 'search_matapelajaran:' . md5($search . $perPage . $page);
            $getMatPelajaran = false;

            if ($this->useCache) {
                $getMatPelajaran = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getMatPelajaran || !$this->useCache) {
                $queryy = MataPelajaran::query();
                $query = $queryy->with('basematapelajaran'); 

                if ($search) {
                    $query->search($search);// jika ada pencarian
                }
                $query->orderBy('created_at', 'desc');
                $getMatPelajaran = $query->paginate($perPage);

                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getMatPelajaran));
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
            $codeMataPelajaran = $this->generateCodeMataPelajaran();
            MataPelajaran::create([
                'subject_name' => strtolower($request->input('subject_name')),
                'subject_description' => $request->input('subject_description'),
                'education_level' => $request->input('education_level'),
                'subject_code' => $codeMataPelajaran,
            ]);

            if ($this->useCache) {
                $this->deleteSearchMataPelajaran('search_matapelajaran:*');
            }
            
            GLog::AddLog('success input mata pelajaran', $request->all(), ""); 
            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => $request->all()], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails input mata pelajaran to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }

    }

    public function UpdateMatPelajaran ($idMatPel, Request $request){
        
        try {

            $request->merge(['id' => $idMatPel]);//merge id to request for validation
            $validator = $this->validateMatPelajaran($request, 'update');

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            
            DB::transaction(function () use ($request, $idMatPel) {

                $matPelajran = MataPelajaran::with('basematapelajaran')->find($idMatPel);

                if (!$matPelajran) {
                    throw new \Exception('Mata pelajaran not found');
                }

                $matPelajran->fill($request->all());
                $matPelajran->save();

                if ($this->useCache) {
                    $this->deleteSearchMataPelajaran('search_matapelajaran:*');
                }
            });

            GLog::AddLog('success updated mata pelajaran', $request->all(), ""); 
            return response()->json(['status' => 'success', 'message' => 'Mata Pelajaran updated successfully', 'data' => null], 200);
        } catch (ValidationException $e) {
            GLog::AddLog('fails update mata pelajaran validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails update mata pelajaran', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }

    }

    public function DelMatPelajaran ($idMatPel){

        try {

            $subjectName = null;
            DB::transaction(function () use ($idMatPel, &$subjectName) {
                $matPelajran = MataPelajaran::find($idMatPel);

                if (!$matPelajran) {
                    throw new \Exception('Mata pelajaran not found');
                }

                $subjectName = $matPelajran->subject_name;
                $matPelajran->delete();//SoftDelete

                if ($this->useCache) {
                    $this->deleteSearchMataPelajaran('search_matapelajaran:*');
                }

                GLog::AddLog('success delete mata pelajaran', $matPelajran->subject_name, ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'Mata Pelajaran delete successfully', 'data' => $subjectName], 200);
    
        } catch (ValidationException $e) {
            GLog::AddLog('fails delete mata pelajaran validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails delete mata pelajaran', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }

    }

    public function GetMatPelajaranById($id){
        try {
            $data = MataPelajaran::with('basematapelajaran')->find($id);
            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $data], 200);
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    //-----------
    private function validateMatPelajaran(Request $request, $action = 'insert')// insert is default
    {   
        $validator = Validator::make($request->all(), [
            'subject_name' => 'required|string|max:100',
            'subject_description' => 'nullable|string',
            'education_level' => 'required|string',
            'subject_code' => 'string',
        ]);

        //extend validator, based on action method
        if ($action === 'insert') {
            $validator->addRules([
                'subject_name' => 'unique:a_mata_pelajaran,subject_name,NULL,id,education_level,' . $request->input('education_level'),
            ]);
        } elseif ($action === 'update') {
            $validator->addRules([
                'subject_name' => 'unique:a_mata_pelajaran,subject_name,' . $request->id . ',id,education_level,' . $request->input('education_level'),
            ]);
        }
        return $validator;
    }

    //delete cache 
    protected function deleteSearchMataPelajaran($pattern)
    {
        $keys = Redis::keys($pattern);
        foreach ($keys as $key) {
            // Remove the "laravel_database" prefix
            $newKey = str_replace('laravel_database_', '', $key);
            Redis::del($newKey);
        }
    }

    protected function generateCodeMataPelajaran() {
        $randomString = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
        return $randomString;
    } 

}
