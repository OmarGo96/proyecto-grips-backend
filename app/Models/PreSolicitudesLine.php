<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreSolicitudesLine extends Model
{
    use HasFactory;
    protected $table = 'cms_pre_solicitudes_line';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
