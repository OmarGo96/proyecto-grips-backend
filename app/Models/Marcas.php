<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marcas extends Model
{
    use HasFactory;
    protected $table = 'cms_marcas';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
