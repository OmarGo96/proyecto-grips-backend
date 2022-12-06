<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GeneralUseHelper
{
    public static function validDateOODO() {
        return Carbon::now()->format('Y-m-d H:i:s.u');
    }

    public static function multiexplode($delimiters, $string) {
        $ready = str_replace($delimiters, $delimiters[0], $string);
        $launch = explode($delimiters[0], $ready);

        return  $launch;
    }

    /**
     * @return int|boolean
     */
    public static function getIrModelId($model) {
        $res = DB::table('ir_model')->where('model', 'like', '%'.$model.'%')->first();
        if ($res->id) {
            return $res->id;
        } else {
            return false;
        }
    }
}
