<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PadSolicitudes extends Model
{
    use HasFactory;
    protected $table = 'cms_padsolicitudes';
    protected $primaryKey = 'id';
    public $timestamps = false;

    //#region RELACIONES
    public function grua() {
        return $this->hasOne(FleetVehicle::class, 'id', 'grua_id');
    }

    public function tipogrua() {
        return $this->hasOne(TipoGrua::class, 'id', 'tipogrua_id');
    }

    public function operador() {
        return $this->hasOne(Operadores::class, 'id', 'operador_id');
    }
    public function vehiculo() {
        return $this->hasOne(PadVehiculos::class, 'id', 'vehiculo_id');
    }
    public function pagos() {
        return $this->hasMany(PadSolicitudesPagos::class, 'request_id', 'id');
    }
    public function servicio() {
        return $this->hasOne(TipoServicio::class, 'id', 'tiposervicio_id');
    }
    public function tipopago() {
        return $this->hasOne(TipoPago::class, 'id', 'tipopago_id');
    }
    public function preguntas() {
        return $this->hasMany(RespuestasSolicitud::class, 'request_id', 'id');
    }

    public function partner()
    {
        return $this->hasOne(User::class, 'id', 'partner_id');
    }
    //#endregion

    // public static function validateBeforeSave($request) {
    //     $validateData = Validator::make($request, [
    //         'telefono' => 'required|string',
    //         'tiposervicio_id' => 'required|exists:cms_tiposervicio,id',
    //         'tipopago_id' => 'required|exists:cms_tipopago,id',
    //         'seencuentra' => 'required|string',
    //         'referencias' => 'nullable|string',
    //         'selleva' => 'required|string',
    //         'vehiculo_id' => 'required|exists:cms_padvehiculos,id'
    //     ]);

    //     if ($validateData->fails()) {
    //         return $validateData->errors()->all();
    //     } else {
    //         return true;
    //     }
    // }

    public static function validateBeforeSave($request) {

        $validateData = Validator::make($request, [
            'status' => 'required|string',
            'type' => 'required|string',
            'datosIniciales.vehiculo_id' => 'required|exists:cms_padvehiculos,id',
            'datosIniciales.tiposervicio_id' => 'required|exists:cms_tiposervicio,id',
            'datosIniciales.preguntas' => 'required'
        ]);

        if ($validateData->fails()) {
            return $validateData->errors()->all();
        }

        if (isset($request['cotizacion'])) {
            $validateCotizacion = Validator::make($request, [
                "cotizacion.subtotal" => 'required|numeric',
                "cotizacion.tax_amount" => 'required|numeric',
                "cotizacion.total" => 'required|numeric'
            ]);
            if ($validateCotizacion->fails()) {
                return $validateCotizacion->errors()->all();
            }
        }

        $type = $request['type'];

        switch($type) {
            case 'programar':
            case 'solicitar':
                $validateSolicitud = Validator::make($request, [
                    'solicitud.seencuentra' => 'required|string',
                    'solicitud.referencias' => 'nullable|string',
                    'solicitud.selleva' => 'required|string',
                    'solicitud.tipopago_id' => 'required|exists:cms_tipopago,id',
                    'solicitud.fecha_hora' => 'nullable',
                    'solicitud.telefono' => 'nullable|string',
                ]);
                if ($validateSolicitud->fails()) {
                    return $validateSolicitud->errors()->all();
                }
                break;
        }

        return true;
    }
}
