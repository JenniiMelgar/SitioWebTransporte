<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tiempo extends Model
{
    protected $connection = 'oracle';
    protected $table = 'DIM_TIEMPO_ESTADO';
    protected $primaryKey = 'TIEMPO_KEY';
    public $timestamps = false;

    protected $fillable = [
        'TIEMPO_KEY',
        'FECHA_KEY',
        'HORA_INICIO_KEY',
        'HORA_FIN_KEY',
        'HORA_WEATHER_KEY'
    ];

    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'FECHA_KEY', 'FECHA_KEY');
    }

    public function horaInicio()
    {
        return $this->belongsTo(Hora::class, 'HORA_INICIO_KEY', 'HORA_KEY');
    }

    public function horaFin()
    {
        return $this->belongsTo(Hora::class, 'HORA_FIN_KEY', 'HORA_KEY');
    }
}