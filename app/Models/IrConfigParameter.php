<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IrConfigParameter extends Model
{
    use HasFactory;
    protected $table = 'ir_config_parameter';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
