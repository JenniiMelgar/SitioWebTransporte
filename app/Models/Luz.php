<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Luz extends Model
{
    protected $connection = 'oracle';
    protected $table = 'DIM_LUZ';
    protected $primaryKey = 'LUZ_KEY';
    public $timestamps = false;

    protected $fillable = [
        'LUZ_KEY',
        'SUNRISE_SUNSET',
        'CIVIL_TWILIGHT',
        'NAUTICAL_TWILIGHT',
        'ASTRONOMICAL_TWILIGHT'
    ];

    public function accidentes()
    {
        return $this->hasMany(Accidente::class, 'LUZ_KEY', 'LUZ_KEY');
    }
}