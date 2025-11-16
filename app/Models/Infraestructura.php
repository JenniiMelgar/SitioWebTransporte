<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Infraestructura extends Model
{
    protected $connection = 'oracle';
    protected $table = 'DIM_INFRAESTRUCTURA';
    protected $primaryKey = 'INFRA_KEY';
    public $timestamps = false;

    protected $fillable = [
        'INFRA_KEY',
        'AMENITY',
        'BUMP',
        'CROSSING',
        'GIVE_WAY',
        'JUNCTION',
        'NO_EXIT',
        'RAILWAY',
        'ROUNDABOUT',
        'STATION',
        'STOP',
        'TRAFFIC_CALMING',
        'TRAFFIC_SIGNAL',
        'TURNING_LOOP'
    ];

    public function accidentes()
    {
        return $this->hasMany(Accidente::class, 'INFRA_KEY', 'INFRA_KEY');
    }
}