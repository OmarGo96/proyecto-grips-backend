<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AppFailedJobs extends Model
{
    use HasFactory;
    protected $table = 'cms_app_failed_jobs';
    protected $primaryKey = 'id';
    public $timestamps = false;

     /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'result' => 'array'
    ];

    public static function create($jobname, $action, $result)
    {
        $failedJob = new AppFailedJobs();
        $failedJob->job = $jobname;
        $failedJob->action = $action;
        $failedJob->result = $result;
        $failedJob->browser = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] . "\n\n" : 'LOCAL SERVER';
        $failedJob->date_reg = Carbon::now();

        try {
            $failedJob->save();
        } catch (\Exception $e) {
            Log::debug(json_encode($e));
        }

    }
}
