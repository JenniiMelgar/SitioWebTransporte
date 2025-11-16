<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Localizacion extends Model
{
    protected $connection = 'oracle';
    protected $table = 'DIM_LOCALIZACION';
    protected $primaryKey = 'LOC_KEY';
    public $timestamps = false;

    protected $fillable = [
        'LOC_KEY',
        'STREET',
        'CITY',
        'COUNTY',
        'STATE',
        'ZIPCODE',
        'COUNTRY',
        'TIMEZONE',
        'AIRPORT_CODE',
        'START_LAT',
        'START_LNG',
        'END_LAT',
        'END_LNG'
    ];

    public function accidentes()
    {
        return $this->hasMany(Accidente::class, 'LOC_KEY', 'LOC_KEY');
    }
}