<?php

namespace App\Models;

use App\Enums\PreSolicitudStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppNotifications extends Model
{
    use HasFactory;
    protected $table = 'cms_app_notifications';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function preSol() {
        $notInStatus = [PreSolicitudStatus::EXPIRADA, PreSolicitudStatus::CANCELADA];
        return $this->belongsTo(PreSolicitudes::class, 'model_id', 'id')->whereNotIn('status', $notInStatus);
    }
}
