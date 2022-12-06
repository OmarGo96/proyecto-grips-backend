<?php

namespace App\Models;

use App\Enums\PreSolicitudStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppNotificationsLog extends Model
{
    use HasFactory;
    protected $table = 'cms_app_notifications_log';
    protected $primaryKey = 'id';
    public $timestamps = false;

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'encoded_notification' => 'array',
    ];

    public function preSol() {
        $notInStatus = [PreSolicitudStatus::EXPIRADA, PreSolicitudStatus::CANCELADA];
        return $this->belongsTo(PreSolicitudes::class, 'model_id', 'id')->whereNotIn('status', $notInStatus);
    }

}
