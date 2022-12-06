<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreguntasSolicitud extends Model
{
    use HasFactory;
    protected $table = 'cms_preguntasolicitud';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
