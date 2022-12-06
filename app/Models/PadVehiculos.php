<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\ClaseVehiculos;
use App\Models\Marcas;
use App\Models\TipoVehiculos;
use App\Models\ColorVehiculos;

class PadVehiculos extends Model
{
    use HasFactory;
    protected $table = 'cms_padvehiculos';
    protected $primaryKey = 'id';
    public $timestamps = false;


    public static function validateBeforeSave($request) {

        $validateData = Validator::make($request, [
            'marca_id' => 'required|numeric',
            'clase_id' => 'required|numeric',
            'clase' => 'required|string',
            'tipovehiculo_id' => 'required|numeric',
            'anio' => 'required|numeric',
            'colorvehiculo_id' => 'required|numeric',
            'color' => 'required|string',
            'placas' => 'required|string',
            'noserie' => 'nullable|string',
            'alias' => 'required|string'
        ]);

        if ($validateData->fails()) {
            return $validateData->errors()->all();
        } else {
            // revisamos marca

            $claseVHVal = ClaseVehiculos::where('marca_id', '=', $request['marca_id'])
            ->where('id', '=', $request['clase_id'])->first();

            if (!$claseVHVal) {
                return ['La clase de vehiculo indicada no pertenece a la marca enviada'];
            }

            return true;
        }
    }

    public static function validateBeforeGet($request) {
        $validateData = Validator::make($request, [
            'id' => 'required|exists:cms_padvehiculos,id',
        ]);

        if ($validateData->fails()) {
            return $validateData->errors()->all();
        } else {
            return true;
        }
    }

    public function marca() {
        return $this->hasOne(Marcas::class, 'id', 'marca_id');
    }

    public function tipo() {
        return $this->hasOne(TipoVehiculos::class, 'id', 'tipovehiculo_id');
    }

    public function clase() {
        return $this->hasOne(ClaseVehiculos::class, 'id', 'clase_id');
    }

    public function color() {
        return $this->hasOne(ColorVehiculos::class, 'id', 'colorvehiculo_id');
    }

    public function solicitudes() {
        return $this->hasMany(PadSolicitudes::class, 'vehiculo_id', 'id');
    }

    public function preSolicitudes() {
        return $this->hasMany(PreSolicitudes::class, 'vehiculo_id', 'id');
    }
}
