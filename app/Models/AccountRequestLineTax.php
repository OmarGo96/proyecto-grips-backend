<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountRequestLineTax extends Model
{
    use HasFactory;
    protected $table = 'account_request_line_tax';
    protected $primaryKey = null;
    public $timestamps = false;
    public $incrementing = false;
}
