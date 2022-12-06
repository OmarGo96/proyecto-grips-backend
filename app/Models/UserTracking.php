<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTracking extends Model
{
    use HasFactory;
    protected $table = 'cms_user_tracking';
    public $timestamps = false;
    protected $primaryKey = 'foreign_id';
    public $incrementing = false;

    public function user_devices() {
        return $this->hasMany(AppUserDevices::class, 'foreign_id', 'foreign_id');
    }
}
