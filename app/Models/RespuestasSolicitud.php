<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RespuestasSolicitud extends Model
{
    use HasFactory;
    protected $table = 'cms_respuestas_solicitud';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function preguntasSol() {
        return $this->belongsTo(PreguntasSolicitud::class, 'pregunta_id', 'id');
    }
}
