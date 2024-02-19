<?php

namespace App\Http\Controllers\Api\ManageGuru;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use App\Helpers\Helper as GLog;
//model
use App\Models\Guru;


class ManageGuru extends Controller
{
    private $useCache;
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', false); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
    }

    public function GetGuru(Request $request){

        $perPage = $request->input('per_page', 5);
        $search = $request->input('search');
        $page = $request->input('page', 1);

        try {
            $cacheKey = 'search_guru:' . md5($search . $perPage . $page);
            $getGuru = false;

            if ($this->useCache) {
                $getGuru = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getGuru || !$this->useCache) {
                $query = Guru::query();
                if ($search) {
                    $query->search($search);// jika ada pencarian
                }
                $query->orderBy('created_at', 'desc');
                $getGuru = $query->paginate($perPage);

                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getGuru));
                } 
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getGuru], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function StoreGuru(Request $request){

        DB::beginTransaction();
        if($request->status){
            $request->merge(['status' => 'Active']); //assign baru, dari from true and false
        }else{
            $request->merge(['status' => 'Non-Active']);
        }
     
        $validator = $this->validateGuru($request, 'insert');  
        $filePhoto = $this->base64ToImage($request->photo_profile, $request->nip);//get ori photo dari base64

        if ($validator->fails()) {
            GLog::AddLog('fails input guru', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => null], 400);
        }
        
        try {
            Guru::create([
                'nip' => $request->input('nip'),
                'nuptk' => $request->input('nuptk'),
                'nama' => strtolower($request->input('nama')),
                'gender' => strtolower($request->input('gender')),
                'birth_date' => $request->input('birth_date'),
                'birth_place' => $request->input('birth_place'),
                'address' => $request->input('address'),
                'phone_number' => $request->input('phone_number'),
                'facebook' => $request->input('facebook'),
                'instagram' => $request->input('instagram'),
                'linkedin' => $request->input('linkedin'),
                'photo_profile' => $request->input('photo_profile'),
                'photo_name_ori' => $filePhoto,
                'religion' => $request->input('religion'),
                'email' => $request->input('email'),
                'parent_phone_number' => $request->input('parent_phone_number'),
                'status' => $request->input('status'),
            ]);
            GLog::AddLog('success input guru', $request->all(), ""); 
        
            DB::commit();
            if ($this->useCache) {
                $this->deleteSearchGuru('search_guru:*');
            }
            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => $request->all()], 200);

        } catch (\Exception $e) {
            
            DB::rollBack();
            GLog::AddLog('fails input guru to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function UpdateGuru(Request $request, $idGuru){

        try {
            if($request->status){
                $request->merge(['status' => 'Active']); //assign baru, dari from true and false
            }else{
                $request->merge(['status' => 'Non-Active']);
            }

            $request->merge(['id' => $idGuru]);
            $validator = $this->validateGuru($request, 'update');

            $filePhoto = $this->base64ToImage($request->photo_profile, $request->nip);
            $request->merge(['photo_name_ori' => $filePhoto]); //update name ori

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
           
            DB::transaction(function () use ($request, $idGuru) {
                $Guru = Guru::find($idGuru);

                if (!$Guru) {
                    throw new \Exception('Guru not found');
                }
                $Guru->fill(array_map('strtolower',$request->all()));
                $Guru->save();

                if ($this->useCache) {
                    $this->deleteSearchGuru('search_Guru:*');
                }

                GLog::AddLog('success updated guru', $request->all(), ""); 
            });
            
            return response()->json(['status' => 'success', 'message' => 'guru updated successfully', 'data' => $request->all()], 200);

        } catch (ValidationException $e) {
            GLog::AddLog('fails update guru validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails update guru', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }

    public function DelGuru($id){
        
        try {
            $guruName = null;
            DB::transaction(function () use ($id, &$guruName) {
                $guruData = Guru::find($id);

                if (!$guruData) {
                    throw new \Exception('guru not found');
                }

                $guruName = $guruData->nama;
                $guruData->delete();//SoftDelete

                if ($this->useCache) {
                    $this->deleteSearchGuru('search_guru:*');
                }

                GLog::AddLog('success delete guru', $guruData->nama, ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'guru delete successfully', 'data' => $guruName], 200);
    
        } catch (ValidationException $e) {
            GLog::AddLog('fails delete guru validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails delete guru', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }

    public function GetGuruByID($id){
        
        try {
            $cacheKey = 'search_guru:' . md5($id);
            $getGuru = false;
            if ($this->useCache) {
                $getGuru = json_decode(Redis::get($cacheKey), false);
                if ($getGuru) {
                    $onlyPhoto = Guru::find($id)->pluck('photo_profile')->first();
                    if ($onlyPhoto !== null) {
                        $getGuru->photo_profile = $onlyPhoto;
                    } else {
                        $getGuru->photo_profile = null;
                    }
                }
            }

            if (!$getGuru || !$this->useCache) {
                $getGuru = Guru::find($id);

                if (!$getGuru) {
                    throw new \Exception('Guru not found');
                }

                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getGuru)); //except photoprofile base64
                } 
                $getGuru->makeVisible('photo_profile'); //munculkan photo profile
            }
            
            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getGuru], 200);
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    private function base64ToImage($base64String, $nip)
    {
        $image = explode('base64,',$base64String);
        $image = end($image);
        $image = str_replace(' ', '+', $image);

        $matches = [];
        preg_match('/^data:image\/(\w+);base64/', $base64String, $matches);
        if (count($matches) > 1) {
            $extension = $matches[1]; // Dapatkan ekstensi file
        } else {
            $extension = 'png';  //default
        }
        $file = "/guru/profile/".$nip."/" . uniqid() .".$extension";
        Storage::disk('public')->put($file,base64_decode($image));
        
        return $file;
    }


    //-----------
    private function validateGuru(Request $request, $action = 'insert')// insert is default
    {   
        $validator = Validator::make($request->all(), [
            'nip' => ['required', 'max:200',

                function ($attribute,$value, $fail) use ($request, $action) {
                    $query = Guru::withTrashed()->where('nip', $value)->where('deleted_at' , null);

                    if ($action === 'update') {
                        $query->where('id', '!=', $request->id);
                    }
                    
                    $existingData = $query->count();

                    if ($existingData > 0) {
                        $fail('Nip already been taken.');
                    }
                },
            ],

            'nuptk' => ['max:200',

                function ($attribute,$value, $fail) use ($request, $action) {
                    $query = Guru::withTrashed()->where('nuptk', $value)->where('deleted_at' , null);

                    if ($action === 'update') {
                        $query->where('id', '!=', $request->id);
                    }
                    
                    $existingData = $query->count();

                    if ($existingData > 0) {
                        $fail('Nuptk already been taken.');
                    }
                },
            ],

            'nama' => 'required|string|max:200',
            'religion' => 'required|string|max:200',
            'gender' => 'required|string|max:200',
            'birth_date' => 'required|date',
            'birth_place' => 'required|string|max:1000',
            'address' => 'string|max:1000|nullable',
            'phone_number' => 'max:20|nullable',
            'parent_phone_number' => 'max:20|nullable',
            'email' => 'email|max:200',
            'facebook' => 'string|max:400',
            'instagram' => 'string|max:400',
            'linkedin' => 'string|max:400',
            'religion' => 'required|string|max:200',
            'photo_profile' => ['string', 
            
                function ($attribute,$value, $fail) use ($request, $action) {
                    if (!is_string($value)) {
                        return $fail('The photo must be a string.');
                    }

                    $base64Data = explode('base64,', $value);
                    if (count($base64Data) != 2) {
                        return $fail('The photo must be a valid base64 string.');
                    }

                    $decodedData = base64_decode($base64Data[1], true);

                    if ($decodedData === false) {
                        return $fail('Failed to decode base64.');
                    }

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $decodedData);
                    finfo_close($finfo);
                    if (!in_array($mimeType, ['image/png', 'image/jpeg', 'image/jpg'])) {
                        return $fail('Photo profile harus PNG, JPEG, or JPG image.');
                    }
                    
                    // Check image size (3MB)
                    $maxSizeInBytes = 3 * 1024 * 1024; // 3MB in bytes
                    $fileSizeInBytes = strlen($decodedData);
                    if ($fileSizeInBytes > $maxSizeInBytes) {
                        return $fail('Ukuran foto profil harus kurang dari 3MB.');
                    }

                }
            
            ],
            'status' => 'string|max:10|in:Active,Non-Active',
        ]);
     
        return $validator;
    }


    //delete cache 
    protected function deleteSearchGuru($pattern)
    {
        $keys = Redis::keys($pattern);
        foreach ($keys as $key) {
            // Remove the "laravel_database" prefix
            $newKey = str_replace('laravel_database_', '', $key);
            Redis::del($newKey);
        }
    }


}
