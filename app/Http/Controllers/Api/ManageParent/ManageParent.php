<?php

namespace App\Http\Controllers\Api\ManageParent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Carbon;
use App\Helpers\Helper as GLog;
//model
use App\Models\OrangTua;
use App\Models\Siswa;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels;

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
        
        $perPage = $request->input('per_page');
        $search = $request->input('search');
        $page = $request->input('page');

        try {
            $cacheKey = 'search_orangtua:' . md5($search . $perPage . $page);
            $getParent = false;

            if ($this->useCache) {
                $getParent = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getParent || !$this->useCache) {

                $query = OrangTua::query()->with(['siswa' => function ($query) {
                    $query->select('a_siswa.id', 'a_siswa.nama');
                    $query->withPivot('hubungan');
                }]);
                if ($search) {
                    $query->search($search);
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

    public function GetListOrtu(){

        $tipeList = "tipelist";
        try {
            $cacheKey = 'search_ortu:' . md5($tipeList);
            $getOrtu = false;

            if ($this->useCache) {
                $getOrtu = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getOrtu || !$this->useCache) {
                $queryy = OrangTua::query();
                $getOrtu = $queryy->orderBy('created_at', 'desc')->select("id","name")->get(); 
                
                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getOrtu));
                } 
            }

            GLog::AddLog('Success retrieved data', 'Data list orang tua successfully retrieved', "info"); 
            return response(["status"=> "success","message"=> "Data list orang tua successfully retrieved", "data" => $getOrtu], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data list orang tua', $e->getMessage(), "error"); 
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
            $ortu =  OrangTua::create([
                'name' => strtolower($request->input('name')),
                'address' => $request->input('address'),
                'phone_number' => $request->input('phone_number'),
                'email' => $request->input('email'),
                'date_of_birth' => $request->input('date_of_birth'),
                'place_of_birth' => $request->input('place_of_birth'),
                'occupation' => $request->input('occupation'),
                'additional_notes' => $request->input('additional_notes'),
            ]);
            $hubungan = $request->input('relationship'); //hubungan

            $siswa = Siswa::find($request->input('id_siswa')); //temukan siswa
            // Tambahkan hubungan antara orang tua dan siswa melalui tabel pivot
            $ortu->siswa()->attach($siswa, ['hubungan' => $hubungan, 'created_at' => Carbon::now()]);
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

    public function UpOrtu(Request $request, $id){
        try {
         
            $request->merge(['id' => $id]);
            $validator = $this->validateOrtu($request, 'update');
            
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
           
            DB::transaction(function () use ($request, $id) {
                $orgTua = OrangTua::find($id);

                if (!$orgTua) {
                    throw new \Exception('orang tua not found');
                }
                $orgTua->fill($request->all());
                $orgTua->save();

                // pivot
                $idSiswaBaru = $request->input('id_siswa');
                // Periksa apakah siswa baru sudah terhubung
                if ($orgTua->siswa()->where('a_siswa.id', $idSiswaBaru)->exists()) {
                    // Perbarui hubungan yang ada
                    $orgTua->siswa()->updateExistingPivot($idSiswaBaru, ['hubungan' => $request->relationship,  'updated_at' => Carbon::now()]);
                } else {
                    // Hubungkan siswa baru (jika diperlukan)
                    $orgTua->siswa()->attach($idSiswaBaru, ['hubungan' => $request->relationship, 'created_at' => Carbon::now()]);
                }
                
                if ($this->useCache) {
                    $this->deleteSearchOrtu('search_orangtua:*');
                }

                GLog::AddLog('success updated orang tua', $request->all(), ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'orang tua updated successfully', 'data' => $request->all()], 200);

        } catch (ValidationException $e) {
            GLog::AddLog('fails update orang tua validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails update orang tua', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
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
            'id_siswa' => 'required|string|max:100',
            'name' => 'required|string|max:500',
            'date_of_birth' => 'required|date',
            'place_of_birth' => 'string|max:1000',
            'address' => 'required|string|max:1000|nullable',
            'phone_number' => 'required|max:20',
            'relationship' => 'required|max:50|string',
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
