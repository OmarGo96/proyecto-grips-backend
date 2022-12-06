<?php

namespace App\Models;

use App\Casts\StringCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;

class User extends Authenticatable
{
    protected $table = 'res_partner';
    protected $primaryKey = 'id';
    public $timestamps = false;

    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'email' => StringCast::class
    ];

    //Relaciones
    public function user_devices() {
        return $this->hasMany(AppUserDevices::class, 'foreign_id', 'id');
    }


    // Método para validar valores recibidos por request
    public static function validateBeforeLogin($request) {
        $validateData = Validator::make($request, [
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validateData->fails()) {
            return $validateData->errors()->all();
        } else {
            return true;
        }
    }

    // Método para validar antes de registrar primera vez
    public static function validateBeforeRegister($request) {
        $validateData = Validator::make($request, [
            //'username' => 'required|string|unique:res_partner,username_app',
            'name' => 'required|string|max:100',
            'vat' => 'nullable|string|max:200',
            'email' => 'required|email|unique:res_partner,email',
            'mobile' => 'nullable|string',
            'password' => 'required|string',
        ]);

        if ($validateData->fails()) {
            return $validateData->errors()->all();
        } else {
            return true;
        }
    }

    // Método para validar antes de actualizar perfil
    public static function validateBeforeUpdate($request) {
        $validateData = Validator::make($request, [
            'name' => 'required|string|max:200',
            'vat' => 'nullable|string|max:200',
            'mobile' => 'nullable|string',
            'street' => 'nullable|string',
            'street2' => 'nullable|string',
            'zip' => 'nullable|string',
            'city' => 'nullable|string',
            'state_id' => 'nullable|numeric',
            'country_id' => 'nullable|numeric',
        ]);

        if ($validateData->fails()) {
            return $validateData->errors()->all();
        } else {
            return true;
        }
    }

}
