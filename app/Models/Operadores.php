<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operadores extends Model
{
    use HasFactory;
    protected $table = 'cms_operadores';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $hidden = [
        'password'
    ];

    //#region RELACIONES
    public function employee() {
        return $this->hasOne(Employee::class, 'id', 'empleado_id');
    }

    public function user_devices() {
        return $this->hasMany(AppUserDevices::class, 'foreign_id', 'id');
    }

    public function res_users() {
        return $this->belongsTo(UserAdmin::class, 'id', 'driver_id');
    }
    //#endregion
}
