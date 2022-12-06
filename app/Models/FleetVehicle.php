<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FleetVehicle extends Model
{
    use HasFactory;
    protected $table = 'fleet_vehicle';
    protected $primaryKey = 'id';
    public $timestamps = false;


    /**
     * @uses Endpoints: operators, partners
     */
    public static function blockUnblockFleetVehicle($operatorId, $action) {
        $fleet = FleetVehicle::where('driver_id', $operatorId)->first();
        //return (object) ['ok' => true, 'data' => $fleet->id];
        $fleet->disponible_app = $action;
        if ($fleet->save()) {
            return (object) ['ok' => true, 'data' => $fleet->id];
        } else {
            return (object) ['ok' => false, 'errors' => ['No se puedo actualizar la disponibilidad de la grúa. ID: '.$fleet->id]];
        }
    }

}
