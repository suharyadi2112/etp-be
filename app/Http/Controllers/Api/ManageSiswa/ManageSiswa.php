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
                $query = Siswa::query();
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

}
