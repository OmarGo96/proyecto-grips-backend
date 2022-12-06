<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RutaOperador extends Model
{
    use HasFactory;
    protected $table = 'cms_ruta_operador';
    protected $primaryKey = 'id';
    public $timestamps = false;

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'distance' => 'array',
        'duration' => 'array',
        'duration_in_traffic' => 'array',
        'start_location' => 'array',
        'end_address' => 'array',
        'end_location' => 'array',
        'full_directions' => 'array'
    ];
}
