<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PadSolicitudesPagos extends Model
{
    use HasFactory;
    protected $table = 'cms_padsolicitudes_pagos';
    public $timestamps = false;
}
