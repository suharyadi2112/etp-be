<?php

namespace App\Http\Controllers\Api\ManageSiswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use GuzzleHttp\Client;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use App\Helpers\Helper as GLog;
use App\Jobs\UploadToDropbox as UpDrop;
use Dcblogdev\Dropbox\Facades\Dropbox;
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
        $filePhoto = $this->base64ToImage($request->photo_profile, $request->nis);//get ori photo dari base64

        return $filePhoto;

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
            
            if($request->status){
                $request->merge(['status' => 'Active']); //assign baru, dari from true and false
            }else{
                $request->merge(['status' => 'Non-Active']);
            }

            $request->merge(['id' => $idSiswa]);
            $validator = $this->validateSiswa($request, 'update');

            $filePhoto = $this->base64ToImage($request->photo_profile, $request->nis);
            $request->merge(['photo_name_ori' => $filePhoto]); //update name ori

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

            $cacheKey = 'search_siswa:' . md5($id);
            $getSiswa = false;
            if ($this->useCache) {
                $getSiswa = json_decode(Redis::get($cacheKey), false); //cache tidak ada photo base64
                if ($getSiswa) {
                    $onlyPhoto = Siswa::find($id)->pluck('photo_profile')->first();
                    if ($onlyPhoto !== null) {
                        $getSiswa->photo_profile = $onlyPhoto;
                    } else {
                        $getSiswa->photo_profile = null;
                    }
                }
            }

            if (!$getSiswa || !$this->useCache) {
                $getSiswa = Siswa::with('basekelas')->find($id);
                if (!$getSiswa) {
                    throw new \Exception('Siswa not found');
                }
                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getSiswa));  //except photoprofile base64
                } 
                $getSiswa->makeVisible('photo_profile'); //munculkan photo profile
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getSiswa], 200);
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

    public function base64ToImage($base64String, $nis)
    {

        try {
            
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
            $file = "/siswa/profile/".$nis."/" . uniqid() .".$extension";
            Storage::disk('public')->put($file,base64_decode($image));
        
            dispatch(new UpDrop($file));//job upload ke dropbox
            
            // Storage::delete($file); //file temporary bisa di hapus setelah digunakan

            return $file;
    
        } catch (\Exception $e) {
            // Tangani pengecualian di sini
            GLog::AddLog('Error occurred while processing base64 to image: ', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }

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
