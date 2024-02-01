<?php

namespace App\Http\Controllers\Api\ManageBaseKelas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper as GLog;
//model
use App\Models\BaseKelas;

class ManageBaseKelas extends Controller
{
    private $useCache;
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', false); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
    }

    public function GetBaseKelas(Request $request){
        
        $perPage = $request->input('per_page', 5);
        $search = $request->input('search');
        $page = $request->input('page', 1);

        try {
            $cacheKey = 'search_basekelas:' . md5($search . $perPage . $page);
            $getBaseKelas = false;

            if ($this->useCache) {
                $getBaseKelas = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getBaseKelas || !$this->useCache) {
                $query = BaseKelas::query();
                if ($search) {
                    $query->search($search);// jika ada pencarian
                }
                $query->orderBy('created_at', 'desc');
                $getBaseKelas = $query->paginate($perPage);

                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getBaseKelas));
                } 
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getBaseKelas], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function StoreBaseKelas(Request $request){

        DB::beginTransaction();
        $cekDelData = BaseKelas::withTrashed()->where([ //get data soft delete
            ['nama_kelas', '=', $request->nama_kelas],
        ])->first();

        if ($cekDelData && $cekDelData['deleted_at'] == null) {
            $validator = $this->validateBaseKelas($request, 'insert');
            if ($validator->fails()) {
                GLog::AddLog('fails input base kelas', $validator->errors(), "alert"); 
                return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => null], 400);
            }
        }
        try { 

            if ($cekDelData && $cekDelData['deleted_at'] != null) {
                $cekDelData->restore();
                GLog::AddLog('success restore base kelas', $request->all(), ""); 
            }else{
                BaseKelas::create([
                    'nama_kelas' => strtolower($request->input('nama_kelas')),
                    'ruang_kelas' => strtolower($request->input('ruang_kelas')),
                    'deskripsi' => $request->input('deskripsi'),
                ]);
                GLog::AddLog('success input base kelas', $request->all(), ""); 
            }
            DB::commit();
            if ($this->useCache) {
                $this->deleteSearchBaseKelas('search_basekelas:*');
            }
            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => $request->all()], 200);

        } catch (\Exception $e) {
            
            DB::rollBack();
            GLog::AddLog('fails input base kelas to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function DelBaseKelas($idBaseKelas){
        
        try {
            $kelasName = null;
            DB::transaction(function () use ($idBaseKelas, &$kelasName) {
                $matBaseKelas = BaseKelas::find($idBaseKelas);

                if (!$matBaseKelas) {
                    throw new \Exception('Base kelas not found');
                }

                $kelasName = $matBaseKelas->base_subject_name;
                $matBaseKelas->delete();//SoftDelete

                if ($this->useCache) {
                    $this->deleteSearchBaseKelas('search_basekelas:*');
                }

                GLog::AddLog('success delete base kelas', $matBaseKelas->nama_kelas, ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'Base kelas delete successfully', 'data' => $kelasName], 200);
    
        } catch (ValidationException $e) {
            GLog::AddLog('fails delete base kelas validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails delete base kelas', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }

    public function GetBaseKelasByID($id){
        try {
            $data = BaseKelas::find($id);
            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $data], 200);
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function UpdateBaseKelas(Request $request, $id){
           
        try {

            $request->merge(['id' => $id]);//merge id to request for validation
            $validator = $this->validateBaseKelas($request, 'update');

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            
            DB::transaction(function () use ($request, $id) {

                $matBaseKelas = BaseKelas::find($id);

                if (!$matBaseKelas) {
                    throw new \Exception('Base kelas not found');
                }

                $matBaseKelas->fill(array_map('strtolower',$request->all()));
                $matBaseKelas->save();

                if ($this->useCache) {
                    $this->deleteSearchBaseKelas('search_basekelas:*');
                }
            });

            GLog::AddLog('success updated base kelas', $request->all(), ""); 
            return response()->json(['status' => 'success', 'message' => 'base kelas updated successfully', 'data' => null], 200);
        } catch (ValidationException $e) {
            GLog::AddLog('fails update base kelas validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails update base kelas', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }

    }

    //-----------
    private function validateBaseKelas(Request $request, $action = 'insert')// insert is default
    {   
        $validator = Validator::make($request->all(), [
            'nama_kelas' => 'required|string|max:200',
        ]);
        if ($action === 'insert') {
            $validator->addRules([
                'nama_kelas' => 'unique:a_base_kelas,nama_kelas',
            ]);
        } elseif ($action === 'update') {
            $validator->addRules([
                'nama_kelas' => 'unique:a_base_kelas,nama_kelas,' . $request->id,
            ]);
        }
        return $validator;
    }

    //delete cache 
    protected function deleteSearchBaseKelas($pattern)
    {
        $keys = Redis::keys($pattern);
        foreach ($keys as $key) {
            // Remove the "laravel_database" prefix
            $newKey = str_replace('laravel_database_', '', $key);
            Redis::del($newKey);
        }
    }

}
