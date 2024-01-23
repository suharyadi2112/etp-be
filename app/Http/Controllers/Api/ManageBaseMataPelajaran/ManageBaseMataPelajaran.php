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

    public function GetBaseMataPelajaran(){
        
        $data = BaseMataPelajaran::all();
        return response()->json(["status"=> "success","message"=> null,"data" => $data], 200);
    }
}
