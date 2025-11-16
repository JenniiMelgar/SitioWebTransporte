<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fuente extends Model
{
    protected $connection = 'oracle';
    protected $table = 'DIM_FUENTE';
    protected $primaryKey = 'FUENTE_KEY';
    public $timestamps = false;

    protected $fillable = [
        'FUENTE_KEY',
        'SOURCE'
    ];

    public function accidentes()
    {
        return $this->hasMany(Accidente::class, 'FUENTE_KEY', 'FUENTE_KEY');
    }
}