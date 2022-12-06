<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppUserDevices extends Model
{
    use HasFactory;
    protected $table = 'cms_app_user_devices';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
