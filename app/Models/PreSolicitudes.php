<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class PreSolicitudes extends Model
{
    use HasFactory;
    protected $table = 'cms_pre_solicitudes';
    protected $primaryKey = 'id';
    public $timestamps = false;

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'datosIniciales' => 'array',
        'cotizacion' => 'array',
        'solicitud' => 'array',
        'json_solicitud' => 'array',
        'operator_initCords' => 'array'
    ];

    public function vehiculo()
    {
        return $this->hasOne(PadVehiculos::class, 'id', 'vehiculo_id');
    }
    public function tiposervicio()
    {
        return $this->hasOne(TipoServicio::class, 'id', 'tiposervicio_id');
    }
    public function tipopago()
    {
        return $this->hasOne(TipoPago::class, 'id', 'tipopago_id');
    }
    public function partner()
    {
        return $this->hasOne(User::class, 'id', 'partner_id');
    }
    public function operator()
    {
        return $this->hasOne(Operadores::class, 'id', 'operator_id');
    }


    public static function validateBeforeSave($request) {
        $validateData = Validator::make($request, [
            'status' => 'required|string',
            'type' => 'required|string',
            'datosIniciales' => 'required',
            'datosIniciales.vehiculo_id' => 'required|numeric',
            'datosIniciales.tiposervicio_id' => 'required|numeric',
            'cotizacion' => 'nullable',
            'solicitud' => 'required',
            'telefono' => 'required|string',
            'tiempoArribo' => 'nullable|string',
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'solicitud' => 'required',
            'solicitud.fecha_hora' => 'nullable|string',
            'calcObjs' => 'required',
            'distanciaKm' => 'required'
        ]);

        if ($validateData->fails()) {
            return (object) ['ok' => false, 'errors' => $validateData->errors()->all()];
        }

        return true;
    }
}
