<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hora extends Model
{
    protected $connection = 'oracle';
    protected $table = 'DIM_HORA';
    protected $primaryKey = 'HORA_KEY';
    public $timestamps = false;

    protected $fillable = [
        'HORA_KEY',
        'HORA'
    ];
}