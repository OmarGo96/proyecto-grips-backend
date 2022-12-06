<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaseVehiculos extends Model
{
    use HasFactory;
    protected $table = 'cms_clase_vehiculos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function clase_vehiculo_marcas() {
        return $this->belongsTo(Marcas::class, 'marca_id', 'id');
    }
}
