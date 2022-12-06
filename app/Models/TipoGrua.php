<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoGrua extends Model
{
    use HasFactory;
    protected $table = 'cms_tipogrua';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
