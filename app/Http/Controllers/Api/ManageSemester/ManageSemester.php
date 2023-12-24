<?php

namespace App\Http\Controllers\Api\ManageSemester;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use App\Helpers\Helper as GLog;
//spatie
use Spatie\Permission\Models\Permission;
//model
use App\Models\User;
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

    public function GetSemester(){

        // pemanasan tokenCan sanctum
        // $user      = Auth::user();
        // return response()->json($user->tokenCan('addroles'));

        try {
            $getSemester = false;
            if ($this->useCache) { //cache
                $getSemester = json_decode(Redis::get('get_all_semester'),false);
            }

            if (!$getSemester || !$this->useCache) {
                $getSemester = Semester::all();
                if ($this->useCache) {
                    Redis::setex('get_all_semester', $this->useExp, $getSemester);
                }
            }

            GLog::AddLog('Success retrieved data', 'Data successfully retrieved', "info"); 
            return response()->json(["status"=> "success","message"=> "Data successfully retrieved", "data" => $getSemester], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved data', $e->getMessage(), "error"); 
            return response()->json(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }

    }

    public function StoreSemester(Request $request){

        DB::beginTransaction();

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
                Redis::del('get_all_semester');
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
                    Redis::del('get_all_semester');
                }
            });

            return response()->json(['status' => 'success', 'message' => 'Semester updated successfully', 'data' => null]);
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
            'semester_name' => 'required|string|max:255',
            'academic_year' => 'required|string|max:20',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'active_status' => 'required|in:Active,Non-Active',
            'description' => 'nullable|string',
        ]);
        $validator->after(function ($validator) use ($request, $action) {
            if ($request->input('active_status') === 'Active') {
                $query = Semester::where('active_status', 'Active');
                
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


}
