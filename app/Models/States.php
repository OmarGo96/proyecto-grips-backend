<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class States extends Model
{
    use HasFactory;
    protected $table = 'res_country_state';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function states_country() {
        return $this->belongsTo(Countries::class, 'country_id', 'id');
    }
}
