<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoVehiculos extends Model
{
    use HasFactory;
    protected $table = 'cms_tipovehiculo';
    protected $primaryKey = 'id';
    public $timestamps = false;
    
}
