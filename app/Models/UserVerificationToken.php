<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVerificationToken extends Model
{
    protected $table = 'cms_app_user_verification_tokens';
    protected $primaryKey = 'id';
    public $timestamps = false;

    use HasFactory;
}
