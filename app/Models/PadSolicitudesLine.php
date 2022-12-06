<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PadSolicitudesLine extends Model
{
    use HasFactory;
    protected $table = 'cms_padsolicitudes_line';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
