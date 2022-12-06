<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramacionLlamadas extends Model
{
    use HasFactory;
    protected $table = 'cms_programacion_llamadas';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
