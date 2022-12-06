<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfirmacionesPagoPartner extends Model
{
    use HasFactory;
    protected $table = 'cms_confirmaciones_pago_partner';
    protected $primaryKey = 'id';
    public $timestamps = false;

     /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'message_solicitud' => 'array'
    ];
}
