<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accidente extends Model
{
    protected $connection = 'oracle';
    protected $table = 'HECHOS_T';
    protected $primaryKey = 'ID';

    protected $fillable = [
        'ID',
        'SEVERITY',
        'DISTANCE_MI',
        'TEMPERATURE_F',
        'WIND_CHILL_F',
        'HUMIDITY',
        'PRESSURE_IN',
        'VISIBILITY_MI',
        'WIND_SPEED_MPH',
        'PRECIPITATION_IN',
        'DESCRIPTION',
        'TIEMPO_KEY',
        'LOC_KEY',
        'CLIMA_KEY',
        'INFRA_KEY',
        'LUZ_KEY',
        'FUENTE_KEY'
    ];

    public $timestamps = false;

    public function tiempo()
    {
        return $this->belongsTo(Tiempo::class, 'TIEMPO_KEY', 'TIEMPO_KEY');
    }

    public function localizacion()
    {
        return $this->belongsTo(Localizacion::class, 'LOC_KEY', 'LOC_KEY');
    }

    public function clima()
    {
        return $this->belongsTo(Clima::class, 'CLIMA_KEY', 'CLIMA_KEY');
    }

    public function infraestructura()
    {
        return $this->belongsTo(Infraestructura::class, 'INFRA_KEY', 'INFRA_KEY');
    }

    public function luz()
    {
        return $this->belongsTo(Luz::class, 'LUZ_KEY', 'LUZ_KEY');
    }

    public function fuente()
    {
        return $this->belongsTo(Fuente::class, 'FUENTE_KEY', 'FUENTE_KEY');
    }
}