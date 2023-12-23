<?php // Code within app\Helpers\Helper.php
namespace App\Helpers;

use Config;
use Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\LogActivity as LogActivityModel;

use App\Jobs\LogJob as LJ;//job log

class Helper
{

  public $jobLog;

  //log
  public static function AddLog($subject, $data, $specification)
  {

    $log = [];
    $log['subject'] = $subject;
    $log['url'] = Request::fullUrl();
    $log['method'] = Request::method();
    $log['ip'] = Request::ip();
    $log['agent'] = Request::header('user-agent');
    $log['user_id'] = auth()->check() ? auth()->user()->id : 1;
    $log['data'] = $data;
    $log['created_at'] = date('Y-m-d H:i:s');
    $log['updated_at'] = date('Y-m-d H:i:s');

    if ($specification) {//false, log tidak tampil di cmd
      switch ($specification) {
        case 'error':
          Log::error($data);
          break;
        case 'alert':
          Log::alert($data);
          break;
        case 'warning':
          Log::warning($data);
          break;
        case 'info':
          Log::info($data);
          break;
        case 'debug':
          Log::debug($data);
          break;
        
        default:
          Log::emergency($data);
          break;
      }
    }

    $instance = new self();
    $instance->jobLog = env('JOBLOG', false);
    if ($instance->jobLog) { //env JOBLOG
       dispatch(new LJ($log));
    }

  }


  public static function logActivityLists()
  {
    return LogActivityModel::latest()->get();
  }

}