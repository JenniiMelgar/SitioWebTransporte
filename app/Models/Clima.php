<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clima extends Model
{
    protected $connection = 'oracle';
    protected $table = 'DIM_CLIMA';
    protected $primaryKey = 'CLIMA_KEY';
    public $timestamps = false;

    protected $fillable = [
        'CLIMA_KEY',
        'WEATHER_CONDITION',
        'WIND_DIRECTION'
    ];

    public function accidentes()
    {
        return $this->hasMany(Accidente::class, 'CLIMA_KEY', 'CLIMA_KEY');
    }
}