<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAdmin extends Model
{
    use HasFactory;
    protected $table = 'res_users';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function driver() {
        return $this->hasOne(Operadores::class, 'id', 'driver_id');
    }

}

