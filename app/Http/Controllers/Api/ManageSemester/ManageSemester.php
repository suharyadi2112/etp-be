<?php

namespace App\Http\Controllers\Api\ManageSemester;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper as GLog;
//model
use App\Models\Semester;

class ManageSemester extends Controller
{
    private $useCache;
    private $useExp;

    public function __construct()
    {
        $this->useCache = env('USE_CACHE_REDIS', false); //setup redis
        $this->useExp = env('USE_EXPIRED', 3600); //setup redis
    }

    public function GetSemester(Request $request){

        $perPage = $request->input('per_page', 5);
        $search = $request->input('search');
        $page = $request->input('page', 1);
        
        try {

            $cacheKey = 'search_semester:' . md5($search . $perPage . $page);
            $getSemester = false;

            if ($this->useCache) {
                $getSemester = json_decode(Redis::get($cacheKey), false);
            }

            if (!$getSemester || !$this->useCache) {
                $query = Semester::query();

                if ($search) {
                    $query->search($search);// jika ada pencarian
                }
                $query->orderBy('created_at', 'desc');
                $getSemester = $query->paginate($perPage);

                if ($this->useCache) {//set ke redis
                    Redis::setex($cacheKey, $this->useExp, json_encode($getSemester));
                } 
            }
            
            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getSemester], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function GetSemesterById($id){
        
        try {
            $data = Semester::find($id);
            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $data], 200);
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function StoreSemester(Request $request){

        DB::beginTransaction();

        if($request->active_status){
            $request->merge(['active_status' => $request->active_status]);
        }else{
            return response()->json(["status"=> "fail", "message"=>  "status not found", "data" => null], 400);
        }
      
        $validator = $this->validateSemester($request, 'insert');
        if ($validator->fails()) {
            GLog::AddLog('fails input semester', $validator->errors(), "alert"); 
            return response()->json(["status"=> "fail", "message"=>  $validator->errors(),"data" => null], 400);
        }

        try {
            $Semester = Semester::create([
                'semester_name' => $request->input('semester_name'),
                'academic_year' => $request->input('academic_year'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'active_status' => $request->input('active_status'),
                'description' => $request->input('description'),
            ]);

            if ($this->useCache) {
                $this->deleteSearchSemester('search_semester:*');
            }
            
            GLog::AddLog('success input semester', $request->all(), ""); 
            DB::commit();
            return response()->json(["status"=> "success","message"=> "Data successfully stored", "data" => $request->all()], 200);

        } catch (\Exception $e) {

            DB::rollBack();// roolback data

            GLog::AddLog('fails input semester to db', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }

    }

    public function UpdateSemester($idSemester, Request $request){

        if($request->active_status){
            $request->merge(['active_status' => $request->active_status]);
        }else{
            return response()->json(["status"=> "fail", "message"=>  "status not found", "data" => null], 400);
        }

        try {
            $request->merge(['id' => $idSemester]);//merge id to request for validation
            $validator = $this->validateSemester($request, 'update');

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
           
            DB::transaction(function () use ($request, $idSemester) {
                $semester = Semester::find($idSemester);

                if (!$semester) {
                    throw new \Exception('Semester not found');
                }
                $semester->fill($request->all());
                $semester->save();

                if ($this->useCache) {
                    $this->deleteSearchSemester('search_semester:*');
                }

                GLog::AddLog('success updated semester', $request->all(), ""); 
            });

            return response()->json(['status' => 'success', 'message' => 'Semester updated successfully', 'data' => $request->all()], 200);

        } catch (ValidationException $e) {
            GLog::AddLog('fails update semester validation', $e->errors(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->errors(), 'data' => null], 400);
        } catch (\Exception $e) {
            GLog::AddLog('fails update semester', $e->getMessage(), 'alert');
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(), 'data' => null], 500);
        }

    }

    //-----------
    private function validateSemester(Request $request, $action = 'insert')// insert is default
    {

        $validator = Validator::make($request->all(), [
            'semester_name' => ['required','string','max:255',

                function ($attribute, $value, $fail) use ($request, $action) {
                    $query = Semester::withTrashed()
                    ->where('semester_name', $value)
                    ->where('academic_year', $request->academic_year);

                    if ($action === 'update') {
                        $query->where('id', '!=', $request->id);
                    }
                    
                    $existingData = $query->first();

                    if ($existingData && !$existingData->trashed()) {
                        $fail('Semester dan Tahun ajar has already been taken.');
                    }
                },
            ],
            'academic_year' => 'required|string|max:20',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'active_status' => 'in:Active,Non-Active',
            'description' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request, $action) {
            if ($request->input('active_status') === 'Active') {
                $query = Semester::withTrashed()->where('active_status', 'Active');
                
                if ($action === 'update') {
                    $query->where('id', '!=', $request->input('id'));
                }
                if ($query->exists()) {
                    $validator->errors()->add('active_status', 'Only one active semester is allowed.');
                }
            }
        });
      
        return $validator;
    }


    protected function deleteSearchSemester($pattern)
    {
        $keys = Redis::keys($pattern);
        foreach ($keys as $key) {
            // Remove the "laravel_database" prefix
            $newKey = str_replace('laravel_database_', '', $key);
            Redis::del($newKey);
        }
    }

}
