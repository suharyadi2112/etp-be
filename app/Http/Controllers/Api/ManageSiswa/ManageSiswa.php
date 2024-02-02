<?php

namespace App\Http\Controllers\Api\ManageSiswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper as GLog;
//model
use App\Models\Siswa;


class ManageSiswa extends Controller
{
    private $useCache;
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', false); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
    }

    public function GetSiswa(Request $request){
        
        $perPage = $request->input('per_page', 5);
        $search = $request->input('search');
        $page = $request->input('page', 1);

        try {
            $cacheKey = 'search_siswa:' . md5($search . $perPage . $page);
            $getSiswa = false;

            if ($this->useCache) {
                $getSiswa = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getSiswa || !$this->useCache) {
                $queryy = Siswa::query();
                $query = $queryy->with('basekelas'); 
                if ($search) {
                    $query->search($search);// jika ada pencarian
                }
                $query->orderBy('created_at', 'desc');
                $getSiswa = $query->paginate($perPage);

                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getSiswa));
                } 
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getSiswa], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function StoreKelas(Request $request){

        DB::beginTransaction();
        $cekDelData = Siswa::withTrashed()->where([
            ['nis', '=', $request->nis],
        ])->first();
        
        if ($cekDelData && $cekDelData['deleted_at'] == null) {
            $validator = $this->validateSiswa($request, 'insert');
            if ($validator->fails()) {
                GLog::AddLog('fails input siswa', $validator->errors(), "alert"); 
                return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => null], 400);
            }
        }

        try {
            if ($cekDelData && $cekDelData['deleted_at'] != null) {
                $cekDelData->restore();
                GLog::AddLog('success restore siswa', $request->all(), ""); 
            }else{
                Siswa::create([
                    'id_kelas' => $request->input('id_kelas'),
                    'nis' => $request->input('nis'),
                    'nama' => strtolower($request->input('nama')),
                    'gender' => $request->input('gender'),
                    'birth_date' => $request->input('birth_date'),
                    'birth_place' => $request->input('birth_place'),
                    'address' => $request->input('address'),
                    'phone_number' => $request->input('phone_number'),
                    'status' => $request->input('status'),
                ]);
                GLog::AddLog('success input siswa', $request->all(), ""); 
            }
            
            DB::commit();
            if ($this->useCache) {
                $this->deleteSearchSiswa('search_siswa:*');
            }
            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => $request->all()], 200);

        } catch (\Exception $e) {
            
            DB::rollBack();
            GLog::AddLog('fails input siswa to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }


    //-----------
    private function validateSiswa(Request $request, $action = 'insert')// insert is default
    {   
        $validator = Validator::make($request->all(), [
            'id_kelas' => 'required|exists:a_base_kelas,id',
            'nis' => 'required|string|max:200|unique:siswa,nis',
            'nama' => 'required|string|max:200',
            'gender' => 'required|string|max:200',
            'birth_date' => 'required|date',
            'birth_place' => 'required|string|max:1000',
            'address' => 'string|max:1000|nullable',
            'phone_number' => 'string|max:20|nullable',
            'status' => 'string|max:10',
        ]);
        
        //extend validator, based on action method
        if ($action === 'insert') {
            $validator->addRules([
                'base_subject_name' => 'unique:a_base_mata_pelajaran,base_subject_name',
            ]);
        } elseif ($action === 'update') {
            $validator->addRules([
                'base_subject_name' => 'unique:a_base_mata_pelajaran,base_subject_name,' . $request->id,
            ]);
        }
        return $validator;
    }

    //delete cache 
    protected function deleteSearchSiswa($pattern)
    {
        $keys = Redis::keys($pattern);
        foreach ($keys as $key) {
            // Remove the "laravel_database" prefix
            $newKey = str_replace('laravel_database_', '', $key);
            Redis::del($newKey);
        }
    }


}
