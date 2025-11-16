<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fecha extends Model
{
    protected $connection = 'oracle';
    protected $table = 'DIM_FECHA';
    protected $primaryKey = 'FECHA_KEY';
    public $timestamps = false;

    protected $fillable = [
        'FECHA_KEY',
        'FECHA',
        'ANIO',
        'MES',
        'DIA',
        'DIA_SEMANA',
        'NOMBRE_DIA',
        'TRIMESTRE',
        'ES_FIN_SEMANA'
    ];
}