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

    public function StoreSiswa(Request $request){

        DB::beginTransaction();
        if($request->status){
            $request->merge(['status' => 'Active']); //assign baru, dari from true and false
        }else{
            $request->merge(['status' => 'Non-Active']);
        }

        $validator = $this->validateSiswa($request, 'insert');  

        if ($validator->fails()) {
            GLog::AddLog('fails input siswa', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => null], 400);
        }
        
        try {
            Siswa::create([
                'id_kelas' => $request->input('id_kelas'),
                'nis' => $request->input('nis'),
                'nama' => strtolower($request->input('nama')),
                'gender' => strtolower($request->input('gender')),
                'birth_date' => $request->input('birth_date'),
                'birth_place' => $request->input('birth_place'),
                'address' => $request->input('address'),
                'phone_number' => $request->input('phone_number'),
                'status' => $request->input('status'),
            ]);
            GLog::AddLog('success input siswa', $request->all(), ""); 
        
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


    public function UpdateSiswa(Request $request, $idSiswa){

        try {
            $request->merge(['id' => $idSiswa]);
            $validator = $this->validateSiswa($request, 'update');

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
           
            DB::transaction(function () use ($request, $idSiswa) {
                $Siswa = Siswa::find($idSiswa);

                if (!$Siswa) {
                    throw new \Exception('Siswa not found');
                }
                $Siswa->fill($request->all());
                $Siswa->save();

                if ($this->useCache) {
                    $this->deleteSearchSiswa('search_siswa:*');
                }

                GLog::AddLog('success updated siswa', $request->all(), ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'siswa updated successfully', 'data' => $request->all()], 200);

        } catch (ValidationException $e) {
            GLog::AddLog('fails update siswa validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails update siswa', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }

    public function DelSiswa($id){
        
        try {
            $siswaName = null;
            DB::transaction(function () use ($id, &$siswaName) {
                $siswaData = Siswa::find($id);

                if (!$siswaData) {
                    throw new \Exception('siswa not found');
                }

                $siswaName = $siswaData->nama;
                $siswaData->delete();//SoftDelete

                if ($this->useCache) {
                    $this->deleteSearchSiswa('search_siswa:*');
                }

                GLog::AddLog('success delete siswa', $siswaData->nama, ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'siswa delete successfully', 'data' => $siswaName], 200);
    
        } catch (ValidationException $e) {
            GLog::AddLog('fails delete siswa validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails delete siswa', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }

    public function GetSiswaByID($id){

        try {
            $data = Siswa::find($id);

            if (!$data) {
                throw new \Exception('Siswa not found');
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $data], 200);
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }

    }


    //-----------
    private function validateSiswa(Request $request, $action = 'insert')// insert is default
    {   
        $validator = Validator::make($request->all(), [
            'id_kelas' => 'required|exists:a_base_kelas,id',
            'nis' => ['required', 'max:200',

                function ($attribute,$value, $fail) use ($request, $action) {
                    $query = Siswa::withTrashed()->where('nis', $value)->where('deleted_at' , null);

                    if ($action === 'update') {
                        $query->where('id', '!=', $request->id);
                    }
                    
                    $existingData = $query->count();

                    if ($existingData > 0) {
                        $fail('Nis already been taken.');
                    }
                },
            ],

            'nama' => 'required|string|max:200',
            'gender' => 'required|string|max:200',
            'birth_date' => 'required|date',
            'birth_place' => 'required|string|max:1000',
            'address' => 'string|max:1000|nullable',
            'phone_number' => 'max:20|nullable',
            'status' => 'string|max:10|in:Active,Non-Active',
        ]);
     
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
